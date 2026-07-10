<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Model_Docs — the zero-config, scan-based documentation engine.
 *
 * There is NO manifest. The content directory IS the configuration:
 *
 *   content/<locale>/<collection>/            ← a subfolder is a COLLECTION (the top-level dropdown)
 *     _index.md                               ← the collection's label (translatable) + order + landing
 *     why-tiger.md, getting-started.md, …     ← flat files; hierarchy lives in each file's metadata
 *
 * Each file self-describes in a leading HTML comment (invisible when rendered, trivially scannable):
 *
 *   <!-- tiger:doc
 *   parent: getting-started      # id of the parent node → arbitrary-depth tree (omit = top level)
 *   order:  20                   # float among siblings; 5.22 slots between 5 and 6; first | last
 *   title:  Your first module    # optional; falls back to the first # H1, then the humanized id
 *   header: true                 # optional; a label-only node (no page), just groups children
 *   -->
 *
 * A file with no block still shows up (ungrouped, last) — metadata is pure refinement. The nav tree
 * is built by linking `parent` → children recursively, so it nests as deep as you like. Collection
 * labels + section labels are files, so they translate per-locale (with en fallback).
 *
 * Doc bodies still resolve dual-source (a DB `page` of type `doc` overrides a file — the live
 * per-tenant override tier), and everything renders through Tiger_Cms_Renderer.
 *
 * @api
 */
class Docs_Model_Docs
{
    /** page.type for DB-backed docs (distinct from CMS pages so they never answer at root). */
    const TYPE_DOC = 'doc';

    /** The default collection — served prefix-less at /docs (others at /docs/<collection>). */
    const DEFAULT_COLLECTION = 'guide';

    /** ext => Tiger_Cms_Renderer format. The set of file types a doc may ship as. */
    protected static $_formats = [
        'md'    => Tiger_Model_Page::FORMAT_MARKDOWN,
        'html'  => Tiger_Model_Page::FORMAT_HTML,
        'htm'   => Tiger_Model_Page::FORMAT_HTML,
        'phtml' => Tiger_Model_Page::FORMAT_PHTML,
    ];

    /** Absolute path to the module's content/ directory. */
    protected $_contentDir;

    /** @var Docs_Model_Index the per-server build cache in front of the scan. */
    protected $_indexObj;

    public function __construct()
    {
        $dir = dirname(__DIR__) . '/content';
        $this->_contentDir = realpath($dir) ?: $dir;
    }

    /** The build cache (per-server, fingerprint-invalidated) that fronts the content scan. */
    protected function _index()
    {
        if (!$this->_indexObj) {
            $this->_indexObj = new Docs_Model_Index($this->_contentDir, function ($locale) {
                return $this->_build($locale);
            });
        }
        return $this->_indexObj;
    }

    /** Force a rebuild of the cached index (admin "Rebuild index" / deploy warm). */
    public function rebuildIndex($locale = 'en')
    {
        return $this->_index()->rebuild($locale);
    }

    // =====================================================================================
    //  Collections (the top-level dropdown)
    // =====================================================================================

    /**
     * Every collection for the locale (en fallback), sorted by order then title. Each:
     *   ['slug' => 'guide', 'title' => 'Guide', 'order' => 10.0]
     * A subfolder of content/<locale>/ is a collection; its `_index.*` supplies the (translatable)
     * label + order. No `_index` → the humanized folder name, sorted last.
     */
    public function collections($locale = 'en')
    {
        return $this->_index()->get($locale)['collections'] ?? [];
    }

    /** Scan content/<locale>/ subfolders → collections (used by the index builder). */
    protected function _scanCollections($locale)
    {
        $root = $this->_localeRoot($locale);
        $out  = [];
        foreach ((glob($root . '/*', GLOB_ONLYDIR) ?: []) as $dir) {
            $slug = basename($dir);
            if ($this->_safeSegment($slug) === '') {
                continue;
            }
            $meta = $this->_indexMeta($slug, $locale);
            $out[] = [
                'slug'  => $slug,
                'title' => ($meta['title'] ?? '') !== '' ? $meta['title'] : $this->_humanize($slug),
                'order' => $this->_order($meta['order'] ?? null),
            ];
        }
        usort($out, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcasecmp($a['title'], $b['title']));
        return $out;
    }

    /** The set of collection slugs (for the controller to split a URL's leading segment). */
    public function collectionSlugs($locale = 'en')
    {
        return array_map(static fn($c) => $c['slug'], $this->collections($locale));
    }

    // =====================================================================================
    //  Tree (the recursive sidebar nav for one collection)
    // =====================================================================================

    /**
     * The nested nav tree for a collection — arbitrary depth. Top-level list of nodes; each:
     *   ['id','title','header'=>bool,'url'=>string,'children'=>[ …same… ]]
     * Built by linking each file's `parent` to its children. A `header` node has no url (label
     * only). `$base` is the docs public prefix (e.g. /docs); guide links are prefix-less, other
     * collections are namespaced under /<base>/<collection>/.
     */
    public function tree($locale = 'en', $collection = self::DEFAULT_COLLECTION, $base = '/docs')
    {
        $collection = $this->_safeSegment($collection) ?: self::DEFAULT_COLLECTION;
        $nodes  = $this->_index()->get($locale)['trees'][$collection] ?? [];
        $prefix = rtrim($base, '/') . ($collection === self::DEFAULT_COLLECTION ? '' : '/' . $collection);
        return $this->_urlize($nodes, $prefix);
    }

    /** Recursively attach a `url` to each cached node (header → '', page → prefix/id). */
    protected function _urlize(array $nodes, $prefix)
    {
        $out = [];
        foreach ($nodes as $n) {
            $n['url']      = !empty($n['header']) ? '' : $prefix . '/' . $n['id'];
            $n['children'] = empty($n['children']) ? [] : $this->_urlize($n['children'], $prefix);
            $out[] = $n;
        }
        return $out;
    }

    /** Build the nested node tree (no urls) from a flat scan by linking parent → children (any depth). */
    protected function _nest(array $flat)
    {
        $childrenOf = [];
        $roots      = [];
        foreach ($flat as $id => $n) {
            $p = $n['parent'];
            if ($p !== '' && $p !== $id && isset($flat[$p])) {
                $childrenOf[$p][] = $id;
            } else {
                $roots[] = $id;
            }
        }
        $build = function (array $ids) use (&$build, $flat, $childrenOf) {
            $out = [];
            foreach ($ids as $id) {
                $n = $flat[$id];
                $out[] = [
                    'id'       => $id,
                    'title'    => $n['title'],
                    'header'   => $n['header'],
                    'order'    => $n['order'],
                    'children' => isset($childrenOf[$id]) ? $build($childrenOf[$id]) : [],
                ];
            }
            usort($out, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcasecmp($a['title'], $b['title']));
            return $out;   // cycles (A→B→A) never reach roots → silently excluded, no recursion loop
        };
        return $build($roots);
    }

    /**
     * The full content scan for a locale — the payload the index caches:
     *   ['collections' => [...], 'trees' => [collection => nested nodes], 'search' => [entries]]
     * Called only on a cache miss (build/rebuild); each search entry carries pre-extracted plain
     * text so query time never touches the disk.
     */
    protected function _build($locale)
    {
        $collections = $this->_scanCollections($locale);
        $trees       = [];
        $search      = [];
        foreach ($collections as $col) {
            $flat = $this->_scanNodes($locale, $col['slug']);
            $trees[$col['slug']] = $this->_nest($flat);
            foreach ($flat as $id => $n) {
                if ($n['header']) {
                    continue;
                }
                $file = $this->_findFile($col['slug'], $id, $locale);
                if (!$file) {
                    continue;
                }
                $search[] = [
                    'collection' => $col['slug'],
                    'slug'       => $id,
                    'title'      => $n['title'],
                    'section'    => $col['title'],
                    'text'       => $this->_plain((string) file_get_contents($file['path']), $file['format']),
                ];
            }
        }
        return ['collections' => $collections, 'trees' => $trees, 'search' => $search];
    }

    // =====================================================================================
    //  Resolve one doc (landing = the collection's _index)
    // =====================================================================================

    /**
     * Resolve a doc → normalized array or null (→ 404):
     *   ['slug','collection','title','html','headings','format','source' => 'db'|'file']
     * An empty slug resolves the collection's `_index` (the landing). DB docs (type=doc, keyed
     * `<collection>/<slug>`) override files — the per-tenant live-override tier.
     */
    public function resolve($slug, $locale = 'en', $orgId = '', $collection = self::DEFAULT_COLLECTION)
    {
        $collection = $this->_safeSegment($collection) ?: self::DEFAULT_COLLECTION;

        // Landing: the collection's _index (reserved; not URL-addressable — leading "_" is rejected).
        if ((string) $slug === '') {
            $file = $this->_findFile($collection, '_index', $locale);
            return $file ? $this->_renderFile($file, '', $collection) : null;
        }

        $slug = $this->_normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $page = $this->_dbDoc($collection . '/' . $slug, $locale, $orgId);
        if ($page) {
            return $this->_renderPage($page, $slug, $collection);
        }

        $file = $this->_findFile($collection, $slug, $locale);
        return $file ? $this->_renderFile($file, $slug, $collection) : null;
    }

    // =====================================================================================
    //  Search (across every collection)
    // =====================================================================================

    /**
     * Full-text-ish search across every collection's file docs, in the caller's locale (en fallback
     * per file). Returns ranked hits, best first:
     *   [ ['slug','collection','title','section' => collection label,'snippet','score'], … ]
     * Header nodes (label-only) are skipped. DB docs aren't indexed here yet.
     */
    public function search($q, $locale = 'en', $limit = 8)
    {
        $q = trim(preg_replace('/\s+/', ' ', (string) $q));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $full  = mb_strtolower($q);
        $words = array_values(array_filter(explode(' ', $full), static fn($w) => $w !== ''));

        // Score over the cached search entries — each carries pre-extracted plain text, so a query
        // never touches the disk (fast + distributed-friendly).
        $hits = [];
        foreach (($this->_index()->get($locale)['search'] ?? []) as $e) {
            $title = (string) $e['title'];
            $text  = (string) $e['text'];
            $hay   = mb_strtolower($title . "\n" . $text);

            $score = 0;
            if (mb_strpos($hay, $full) !== false) {
                $score += 50;
            } elseif (!$this->_allWords($hay, $words)) {
                continue;
            }
            if (mb_strpos(mb_strtolower($title), $full) !== false) {
                $score += 100;
            }
            foreach ($words as $w) {
                $score += min(substr_count($hay, $w), 5);
            }

            $hits[] = [
                'slug'       => (string) $e['slug'],
                'collection' => (string) $e['collection'],
                'title'      => $title,
                'section'    => (string) $e['section'],
                'snippet'    => $this->_snippet($text, $words, $full),
                'score'      => $score,
            ];
        }
        usort($hits, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, max(1, (int) $limit));
    }

    // =====================================================================================
    //  Scanning + metadata
    // =====================================================================================

    /** Scan a collection's files → [id => ['id','title','header','order','parent']]. */
    protected function _scanNodes($locale, $collection)
    {
        $collection = $this->_safeSegment($collection);
        $flat = [];
        $dir  = $this->_collectionDir($locale, $collection);
        if ($dir) {
            foreach ((glob($dir . '/*.{md,markdown,html,htm,phtml}', GLOB_BRACE) ?: []) as $path) {
                $id = preg_replace('/\.[^.]+$/', '', basename($path));
                if ($id === '_index' || $this->_safeSegment($id) === '') {
                    continue;   // _index is the collection landing, not a nav node
                }
                $meta = $this->_meta($path);
                $flat[$id] = [
                    'id'     => $id,
                    'title'  => ($meta['title'] ?? '') !== '' ? $meta['title'] : $this->_humanize($id),
                    'header' => $this->_bool($meta['header'] ?? ''),
                    'order'  => $this->_order($meta['order'] ?? null),
                    'parent' => $this->_safeSegment($meta['parent'] ?? ''),
                ];
            }
        }
        return $flat;
    }

    /** Parse a file's leading `<!-- tiger:doc … -->` block into a key=>value map (reads the head only). */
    protected function _meta($path)
    {
        $head = (string) @file_get_contents($path, false, null, 0, 2048);
        return $this->_metaFromBody($head);
    }

    /** Parse the tiger:doc block out of a body/head string. */
    protected function _metaFromBody($body)
    {
        if (!preg_match('/<!--\s*tiger:doc\b(.*?)-->/is', (string) $body, $m)) {
            return [];
        }
        $meta = [];
        foreach (preg_split('/\r?\n/', trim($m[1])) as $line) {
            if (preg_match('/^\s*([a-z_]+)\s*:\s*(.*?)\s*$/i', $line, $mm)) {
                $meta[strtolower($mm[1])] = trim($mm[2]);
            }
        }
        return $meta;
    }

    /** The `_index.*` metadata for a collection (locale then en). */
    protected function _indexMeta($collection, $locale)
    {
        $file = $this->_findFile($collection, '_index', $locale);
        return $file ? $this->_meta($file['path']) : [];
    }

    /** Remove the leading tiger:doc comment block so it doesn't render / get indexed. */
    protected function _stripMeta($body)
    {
        return preg_replace('/^\s*<!--\s*tiger:doc\b.*?-->\s*/is', '', (string) $body, 1);
    }

    // =====================================================================================
    //  Rendering
    // =====================================================================================

    /** Render a file doc → the normalized array. $slug '' means the landing (_index). */
    protected function _renderFile(array $file, $slug, $collection)
    {
        $raw  = (string) file_get_contents($file['path']);
        $meta = $this->_metaFromBody($raw);
        $body = $this->_stripMeta($raw);
        $html = (new Tiger_Cms_Renderer())->renderBody($body, $file['format']);
        return [
            'slug'       => $slug,
            'collection' => $collection,
            'title'      => ($meta['title'] ?? '') !== '' ? $meta['title'] : $this->_fileTitle($body, $file['format'], $slug ?: $collection),
            'html'       => $html,
            'headings'   => $this->_pageNav($html),
            'format'     => $file['format'],
            'source'     => 'file',
        ];
    }

    /** Render a DB doc row → the normalized array. */
    protected function _renderPage($page, $slug, $collection)
    {
        $html = (new Tiger_Cms_Renderer())->render($page);
        return [
            'slug'       => $slug,
            'collection' => $collection,
            'title'      => (string) $page->title,
            'html'       => $html,
            'headings'   => $this->_pageNav($html),
            'format'     => (string) $page->format,
            'source'     => 'db',
        ];
    }

    /**
     * Give the body's headings (h2/h3) stable ids and return the "on this page" outline:
     *   [ ['level' => 2|3, 'text' => '…', 'id' => '…'], … ]
     */
    protected function _pageNav(&$html)
    {
        $out  = [];
        $used = [];
        $html = preg_replace_callback('/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/is', function ($m) use (&$out, &$used) {
            $level = (int) $m[1];
            $attrs = isset($m[2]) ? $m[2] : '';
            $text  = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES, 'UTF-8'));
            if ($text === '') {
                return $m[0];
            }
            $id = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-') ?: 'section';
            $base = $id; $i = 2;
            while (isset($used[$id])) { $id = $base . '-' . $i++; }
            $used[$id] = true;
            $out[] = ['level' => $level, 'text' => $text, 'id' => $id];

            if (stripos($attrs, 'id=') === false) {
                $attrs = ' id="' . $id . '"' . $attrs;
            }
            return '<h' . $level . $attrs . '>' . $m[3] . '</h' . $level . '>';
        }, $html);
        return $out;
    }

    // =====================================================================================
    //  Files, paths, small helpers
    // =====================================================================================

    /** Resolve a DB doc row, tolerating a missing page table (fresh install → files only). */
    protected function _dbDoc($slug, $locale, $orgId)
    {
        try {
            return (new Tiger_Model_Page())->resolveBySlug($slug, $locale, $orgId, self::TYPE_DOC);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Find a file for a collection + name: try the requested locale then `en`, each extension.
     * `$name` is either a normalized slug or the reserved '_index'. Returns ['path','format'] or
     * null; every candidate is confirmed to sit INSIDE the content dir (traversal defense).
     */
    protected function _findFile($collection, $name, $locale)
    {
        $collection = $this->_safeSegment($collection);
        if ($collection === '' || ($name !== '_index' && $this->_safeSegment($name) === '')) {
            return null;
        }
        $locales = array_values(array_unique([$this->_safeSegment($locale) ?: 'en', 'en']));
        foreach ($locales as $loc) {
            foreach (self::$_formats as $ext => $format) {
                $path = $this->_contentDir . '/' . $loc . '/' . $collection . '/' . $name . '.' . $ext;
                $real = realpath($path);
                if ($real && strpos($real, $this->_contentDir . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
                    return ['path' => $real, 'format' => $format];
                }
            }
        }
        return null;
    }

    /** content/<locale> if it exists, else content/en (locale-level fallback for collection scans). */
    protected function _localeRoot($locale)
    {
        $loc = $this->_safeSegment($locale) ?: 'en';
        $dir = $this->_contentDir . '/' . $loc;
        return is_dir($dir) ? $dir : $this->_contentDir . '/en';
    }

    /** content/<locale>/<collection> if it exists, else content/en/<collection>, else null. */
    protected function _collectionDir($locale, $collection)
    {
        $collection = $this->_safeSegment($collection);
        if ($collection === '') {
            return null;
        }
        foreach (array_unique([$this->_safeSegment($locale) ?: 'en', 'en']) as $loc) {
            $dir = $this->_contentDir . '/' . $loc . '/' . $collection;
            if (is_dir($dir)) {
                return $dir;
            }
        }
        return null;
    }

    /** 'first' → -INF, 'last' → +INF, numeric → float, else a large default (unordered sort last). */
    protected function _order($v)
    {
        $v = strtolower(trim((string) $v));
        if ($v === 'first') { return -1000000.0; }
        if ($v === 'last' || $v === '') { return 1000000.0; }
        return is_numeric($v) ? (float) $v : 1000000.0;
    }

    /** Truthy metadata flag. */
    protected function _bool($v)
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }

    /** "getting-started" → "Getting started". */
    protected function _humanize($slug)
    {
        return ucfirst(str_replace(['-', '_'], ' ', trim((string) $slug)));
    }

    /** Normalize a single URL slug segment to a safe token, or '' (→ 404). */
    protected function _normalizeSlug($slug)
    {
        $slug = strtolower(trim((string) $slug, "/ \t\n\r\0"));
        return $slug === '' ? '' : ($this->_safeSegment($slug) === '' ? '' : $slug);
    }

    /** A single path segment sanitized to a safe token, or '' if it isn't one. */
    protected function _safeSegment($seg)
    {
        $seg = (string) $seg;
        if ($seg === '' || $seg === '.' || $seg === '..') {
            return '';
        }
        return preg_match('/^[a-z0-9][a-z0-9._-]*$/', $seg) ? $seg : '';
    }

    /** A human title for a file doc: the first markdown H1, else the humanized slug. */
    protected function _fileTitle($body, $format, $slug)
    {
        if ($format === Tiger_Model_Page::FORMAT_MARKDOWN
            && preg_match('/^\s*#\s+(.+?)\s*$/m', (string) $body, $m)) {
            return trim($m[1]);
        }
        return $this->_humanize($slug);
    }

    /** True only when every word appears somewhere in the haystack (AND match). */
    protected function _allWords($hay, array $words)
    {
        if (!$words) {
            return false;
        }
        foreach ($words as $w) {
            if (mb_strpos($hay, $w) === false) {
                return false;
            }
        }
        return true;
    }

    /** A ~160-char excerpt centered on the first phrase/word hit, ellipsized at the cut ends. */
    protected function _snippet($text, array $words, $full)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        $low  = mb_strtolower($text);
        $pos  = mb_strpos($low, $full);
        if ($pos === false) {
            $pos = null;
            foreach ($words as $w) {
                $p = mb_strpos($low, $w);
                if ($p !== false && ($pos === null || $p < $pos)) {
                    $pos = $p;
                }
            }
            $pos = $pos ?? 0;
        }
        $start = max(0, $pos - 60);
        $snip  = mb_substr($text, $start, 160);
        if ($start > 0) {
            $snip = '…' . ltrim($snip);
        }
        if ($start + 160 < mb_strlen($text)) {
            $snip = rtrim($snip) . '…';
        }
        return $snip;
    }

    /** Strip markup (markdown syntax + any HTML incl. comments) down to plain, searchable text. */
    protected function _plain($body, $format)
    {
        $s = (string) $body;
        $s = preg_replace('/<!--.*?-->/s', ' ', $s);                // HTML comments (incl. tiger:doc)
        if ($format === Tiger_Model_Page::FORMAT_MARKDOWN) {
            $s = preg_replace('/```.*?```/s', ' ', $s);
            $s = preg_replace('/`[^`]*`/', ' ', $s);
            $s = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $s);
            $s = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $s);
            $s = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $s);
            $s = preg_replace('/^\s{0,3}>\s?/m', '', $s);
            $s = preg_replace('/^\s*[-*+]\s+/m', '', $s);
            $s = preg_replace('/[*_~]+/', '', $s);
        }
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
