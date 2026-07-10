<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Model_Docs — the zero-config, multi-source documentation engine.
 *
 * There is NO manifest. A COLLECTION is just "a directory of doc files" (+ an `_index.md`), and
 * those directories come from two kinds of source, aggregated automatically:
 *
 *   1. PLATFORM content — each subfolder of the module's content/<locale>/ dir (Guide, CMS, …).
 *   2. MODULE docs — each ACTIVE module that ships a `<module>/docs/<locale>/` folder becomes its
 *      own collection (slug = module name). Self-documenting modules: drop a docs/ folder and it
 *      appears; deactivate the module and it's gone. (Toggle with `docs.modules.scan`.)
 *
 * Every file self-describes in a leading HTML comment (invisible when rendered):
 *
 *   <!-- tiger:doc
 *   parent:     getting-started   # id of the parent node → arbitrary-depth tree
 *   order:      20                # float among siblings; 5.22 slots between 5 and 6; first | last
 *   title:      Your first module # optional; else the first # H1, then the humanized id
 *   header:     true              # optional; a label-only node (no page)
 *   visibility: public            # public (default) | admin — which SURFACE it shows on
 *   -->
 *
 * VISIBILITY splits one engine into two surfaces: the public site (`/docs`, guests) shows
 * `public` docs; the in-admin help center shows `admin` docs (how to operate a module). A file's
 * visibility defaults to its collection's `_index` visibility, which defaults to `public`.
 *
 * Everything is fronted by Docs_Model_Index — a per-server, fingerprint-invalidated build cache
 * that spans all source dirs (see that class). DB docs remain the resolve-time override tier.
 *
 * @api
 */
class Docs_Model_Docs
{
    const TYPE_DOC           = 'doc';
    const DEFAULT_COLLECTION = 'guide';   // served prefix-less at /docs on the public surface
    const VIS_PUBLIC         = 'public';
    const VIS_ADMIN          = 'admin';

    /** ext => Tiger_Cms_Renderer format. */
    protected static $_formats = [
        'md'    => Tiger_Model_Page::FORMAT_MARKDOWN,
        'html'  => Tiger_Model_Page::FORMAT_HTML,
        'htm'   => Tiger_Model_Page::FORMAT_HTML,
        'phtml' => Tiger_Model_Page::FORMAT_PHTML,
    ];

    protected $_contentDir;
    protected $_indexObj;

    /** Per-request memo of collection sources, per locale. */
    protected static $_sources = [];

    public function __construct()
    {
        $dir = dirname(__DIR__) . '/content';
        $this->_contentDir = realpath($dir) ?: $dir;
    }

    // =====================================================================================
    //  Cache
    // =====================================================================================

    /** The build cache (per-server, fingerprint-invalidated) that fronts the multi-source scan. */
    protected function _index()
    {
        if (!$this->_indexObj) {
            $this->_indexObj = new Docs_Model_Index(
                $this->_contentDir,
                function ($locale) { return $this->_build($locale); },
                function ($locale) { return $this->_roots($locale); }
            );
        }
        return $this->_indexObj;
    }

    /** Force a rebuild of this server's cached index (admin "Rebuild index" / deploy warm). */
    public function rebuildIndex($locale = 'en')
    {
        return $this->_index()->rebuild($locale);
    }

    // =====================================================================================
    //  Collection sources (platform content + active-module docs)
    // =====================================================================================

    /**
     * All collection sources for a locale, keyed by slug:
     *   [slug => ['slug','dir','enDir','defaultVis','source' => 'platform'|'module']]
     * Platform subfolders first (they win a slug collision), then active-module docs/ dirs.
     */
    protected function _sources($locale)
    {
        $locale = $this->_safeSegment($locale) ?: 'en';
        if (isset(self::$_sources[$locale])) {
            return self::$_sources[$locale];
        }

        $out = [];

        // 1) Platform collections — subfolders of content/en (canonical) ∪ content/<locale>.
        $names = [];
        foreach ([$this->_contentDir . '/en', $this->_contentDir . '/' . $locale] as $root) {
            foreach ((glob($root . '/*', GLOB_ONLYDIR) ?: []) as $dir) {
                $names[basename($dir)] = true;
            }
        }
        foreach (array_keys($names) as $slug) {
            if ($this->_safeSegment($slug) === '') {
                continue;
            }
            $out[$slug] = $this->_descriptor(
                $slug,
                $this->_contentDir . '/' . $locale . '/' . $slug,
                $this->_contentDir . '/en/' . $slug,
                'platform'
            );
        }

        // 2) Module collections — each active module that ships a docs/ folder.
        if ($this->_cfgBool('modules.scan', true)) {
            foreach ($this->_moduleDocsRoots() as $slug => $docsRoot) {
                if (isset($out[$slug]) || $this->_safeSegment($slug) === '') {
                    continue;   // platform collection wins a slug collision
                }
                $out[$slug] = $this->_descriptor($slug, $docsRoot . '/' . $locale, $docsRoot . '/en', 'module');
            }
        }

        // Drop sources that resolve to no directory at all.
        $out = array_filter($out, static fn($d) => $d['dir'] !== null || $d['enDir'] !== null);

        return self::$_sources[$locale] = $out;
    }

    /** Build one source descriptor, resolving its locale + en dirs and default visibility. */
    protected function _descriptor($slug, $localeDir, $enDir, $source)
    {
        $d = [
            'slug'   => $slug,
            'dir'    => is_dir($localeDir) ? $localeDir : null,
            'enDir'  => is_dir($enDir) ? $enDir : null,
            'source' => $source,
        ];
        $meta = $this->_indexMeta($d);
        $d['defaultVis'] = $this->_vis($meta['visibility'] ?? null);
        return $d;
    }

    /** [moduleSlug => absolute docs/ path] for every ACTIVE module that ships a docs/ folder. */
    protected function _moduleDocsRoots()
    {
        $out = [];
        try {
            $inactive = array_flip((new Tiger_Model_Module())->inactiveSlugs());
        } catch (Throwable $e) {
            $inactive = [];   // no DB/table yet → treat all discovered modules as active
        }
        foreach (Tiger_Module_Discovery::all() as $slug => $m) {
            if (isset($inactive[$slug])) {
                continue;
            }
            $base = ($m['area'] === 'app' && defined('APPLICATION_PATH')) ? APPLICATION_PATH
                  : (defined('TIGER_CORE_PATH') ? TIGER_CORE_PATH : null);
            if ($base === null) {
                continue;
            }
            $docs = $base . '/modules/' . $slug . '/docs';
            if (is_dir($docs)) {
                $out[$slug] = $docs;
            }
        }
        return $out;
    }

    /** The directories to fingerprint for a locale (all source dirs — platform + module). */
    protected function _roots($locale)
    {
        $roots = [];
        foreach ($this->_sources($locale) as $d) {
            foreach ([$d['dir'], $d['enDir']] as $dir) {
                if ($dir !== null) {
                    $roots[$dir] = true;
                }
            }
        }
        return array_keys($roots);
    }

    // =====================================================================================
    //  Collections + tree (per surface / visibility)
    // =====================================================================================

    /** Collections visible on a surface, sorted: [ ['slug','title','order','vis'], … ]. */
    public function collections($locale = 'en', $visibility = self::VIS_PUBLIC)
    {
        $vis = $this->_vis($visibility);
        return array_values(array_filter(
            $this->_index()->get($locale)['collections'] ?? [],
            static fn($c) => !empty($c['vis'][$vis])
        ));
    }

    /** Collection slugs visible on a surface (for the controller's URL split). */
    public function collectionSlugs($locale = 'en', $visibility = self::VIS_PUBLIC)
    {
        return array_map(static fn($c) => $c['slug'], $this->collections($locale, $visibility));
    }

    /**
     * The nested nav tree for a collection on a surface — visibility-filtered + url-stamped.
     * $default is the prefix-less collection ('guide' public, '' admin → everything namespaced).
     */
    public function tree($locale = 'en', $collection = self::DEFAULT_COLLECTION, $base = '/docs',
                         $visibility = self::VIS_PUBLIC, $default = self::DEFAULT_COLLECTION)
    {
        $collection = $this->_safeSegment($collection);
        $nodes  = $this->_filterVis($this->_index()->get($locale)['trees'][$collection] ?? [], $this->_vis($visibility));
        $prefix = rtrim($base, '/') . (($default !== '' && $collection === $default) ? '' : '/' . $collection);
        return $this->_urlize($nodes, $prefix);
    }

    /** Keep pages of the wanted visibility; keep a header only if it has ≥1 matching descendant. */
    protected function _filterVis(array $nodes, $vis)
    {
        $out = [];
        foreach ($nodes as $n) {
            if (!empty($n['header'])) {
                $kids = $this->_filterVis($n['children'] ?? [], $vis);
                if ($kids) {
                    $n['children'] = $kids;
                    $out[] = $n;
                }
            } elseif (($n['visibility'] ?? self::VIS_PUBLIC) === $vis) {
                $n['children'] = $this->_filterVis($n['children'] ?? [], $vis);
                $out[] = $n;
            }
        }
        return $out;
    }

    /** Recursively attach a `url` to each node (header → '', page → prefix/id). */
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

    // =====================================================================================
    //  Resolve one doc (landing = the collection's _index)
    // =====================================================================================

    /**
     * Resolve a doc → normalized array or null (→ 404):
     *   ['slug','collection','title','html','headings','format','visibility','source']
     * A page whose visibility doesn't match the requesting surface returns null (a public URL can't
     * serve an admin doc, and vice-versa). Empty slug = the collection's _index (landing).
     */
    public function resolve($slug, $locale = 'en', $orgId = '', $collection = self::DEFAULT_COLLECTION,
                            $visibility = self::VIS_PUBLIC)
    {
        $collection = $this->_safeSegment($collection);
        $sources    = $this->_sources($locale);
        if ($collection === '' || !isset($sources[$collection])) {
            return null;
        }
        $desc = $sources[$collection];
        $vis  = $this->_vis($visibility);

        // Landing: the collection's _index (the controller already gated collection ∈ surface).
        if ((string) $slug === '') {
            $file = $this->_findFileIn($desc, '_index');
            return $file ? $this->_renderFile($file, '', $collection, $desc['defaultVis']) : null;
        }

        $slug = $this->_normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        // DB override (public surface only — DB docs are the public per-tenant override tier).
        if ($vis === self::VIS_PUBLIC) {
            $page = $this->_dbDoc($collection . '/' . $slug, $locale, $orgId);
            if ($page) {
                return $this->_renderPage($page, $slug, $collection);
            }
        }

        $file = $this->_findFileIn($desc, $slug);
        if (!$file) {
            return null;
        }
        $doc = $this->_renderFile($file, $slug, $collection, $desc['defaultVis']);
        return ($doc['visibility'] === $vis) ? $doc : null;   // wrong surface → 404
    }

    // =====================================================================================
    //  Search (per surface)
    // =====================================================================================

    /** Search a surface's docs. Ranked hits: [ ['slug','collection','title','section','snippet','score'], … ]. */
    public function search($q, $locale = 'en', $visibility = self::VIS_PUBLIC, $limit = 8)
    {
        $q = trim(preg_replace('/\s+/', ' ', (string) $q));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $vis   = $this->_vis($visibility);
        $full  = mb_strtolower($q);
        $words = array_values(array_filter(explode(' ', $full), static fn($w) => $w !== ''));

        $hits = [];
        foreach (($this->_index()->get($locale)['search'] ?? []) as $e) {
            if (($e['visibility'] ?? self::VIS_PUBLIC) !== $vis) {
                continue;
            }
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
    //  Build (called by the index on a cache miss)
    // =====================================================================================

    /**
     * The full multi-source scan for a locale — the payload the index caches:
     *   ['collections' => [...], 'trees' => [slug => nested nodes], 'search' => [entries]]
     * Each node/entry carries `visibility`; each collection carries the visibilities it contains.
     */
    protected function _build($locale)
    {
        $collections = [];
        $trees       = [];
        $search      = [];

        foreach ($this->_sources($locale) as $slug => $desc) {
            $flat = $this->_scanNodes($desc);
            $trees[$slug] = $this->_nest($flat);

            $meta = $this->_indexMeta($desc);
            $vis  = [$desc['defaultVis'] => true];   // a collection is at least its _index visibility
            foreach ($flat as $id => $n) {
                if ($n['header']) {
                    continue;
                }
                $vis[$n['visibility']] = true;
                $file = $this->_findFileIn($desc, $id);
                if (!$file) {
                    continue;
                }
                $search[] = [
                    'collection' => $slug,
                    'slug'       => $id,
                    'title'      => $n['title'],
                    'section'    => ($meta['title'] ?? '') !== '' ? $meta['title'] : $this->_humanize($slug),
                    'visibility' => $n['visibility'],
                    'text'       => $this->_plain((string) file_get_contents($file['path']), $file['format']),
                ];
            }

            $collections[] = [
                'slug'  => $slug,
                'title' => ($meta['title'] ?? '') !== '' ? $meta['title'] : $this->_humanize($slug),
                'order' => $this->_order($meta['order'] ?? null),
                'vis'   => $vis,
            ];
        }

        usort($collections, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcasecmp($a['title'], $b['title']));
        return ['collections' => $collections, 'trees' => $trees, 'search' => $search];
    }

    /** Scan a source's files → [id => ['id','title','header','order','parent','visibility']]. */
    protected function _scanNodes(array $desc)
    {
        $dir = $desc['dir'] ?: $desc['enDir'];
        $flat = [];
        if ($dir) {
            foreach ((glob($dir . '/*.{md,markdown,html,htm,phtml}', GLOB_BRACE) ?: []) as $path) {
                $id = preg_replace('/\.[^.]+$/', '', basename($path));
                if ($id === '_index' || $this->_safeSegment($id) === '') {
                    continue;
                }
                $meta = $this->_meta($path);
                $flat[$id] = [
                    'id'         => $id,
                    'title'      => ($meta['title'] ?? '') !== '' ? $meta['title'] : $this->_humanize($id),
                    'header'     => $this->_bool($meta['header'] ?? ''),
                    'order'      => $this->_order($meta['order'] ?? null),
                    'parent'     => $this->_safeSegment($meta['parent'] ?? ''),
                    'visibility' => $this->_vis($meta['visibility'] ?? $desc['defaultVis']),
                ];
            }
        }
        return $flat;
    }

    /** Build the nested node tree (no urls) from a flat scan — arbitrary depth. */
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
                    'id'         => $id,
                    'title'      => $n['title'],
                    'header'     => $n['header'],
                    'order'      => $n['order'],
                    'visibility' => $n['visibility'],
                    'children'   => isset($childrenOf[$id]) ? $build($childrenOf[$id]) : [],
                ];
            }
            usort($out, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcasecmp($a['title'], $b['title']));
            return $out;
        };
        return $build($roots);
    }

    // =====================================================================================
    //  Rendering
    // =====================================================================================

    /** Render a file doc → normalized array. $slug '' = the landing (_index). */
    protected function _renderFile(array $file, $slug, $collection, $defaultVis)
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
            'visibility' => $this->_vis($meta['visibility'] ?? $defaultVis),
            'source'     => 'file',
        ];
    }

    /** Render a DB doc row → normalized array (always public). */
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
            'visibility' => self::VIS_PUBLIC,
            'source'     => 'db',
        ];
    }

    /** Give h2/h3 stable ids + return the "on this page" outline. */
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
    //  Files, metadata, small helpers
    // =====================================================================================

    /** Resolve a DB doc row, tolerating a missing page table. */
    protected function _dbDoc($slug, $locale, $orgId)
    {
        try {
            return (new Tiger_Model_Page())->resolveBySlug($slug, $locale, $orgId, self::TYPE_DOC);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Find a file within a source (locale dir then en dir), each extension. '_index' allowed. */
    protected function _findFileIn(array $desc, $name)
    {
        if ($name !== '_index' && $this->_safeSegment($name) === '') {
            return null;
        }
        foreach (array_unique(array_filter([$desc['dir'], $desc['enDir']])) as $dir) {
            $real = realpath($dir);
            if (!$real) {
                continue;
            }
            foreach (self::$_formats as $ext => $format) {
                $path = realpath($dir . '/' . $name . '.' . $ext);
                if ($path && strpos($path, $real . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
                    return ['path' => $path, 'format' => $format];
                }
            }
        }
        return null;
    }

    /** The `_index.*` metadata for a source. */
    protected function _indexMeta(array $desc)
    {
        $file = $this->_findFileIn($desc, '_index');
        return $file ? $this->_meta($file['path']) : [];
    }

    /** Parse a file's leading `<!-- tiger:doc … -->` block (reads the head only). */
    protected function _meta($path)
    {
        return $this->_metaFromBody((string) @file_get_contents($path, false, null, 0, 2048));
    }

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

    protected function _stripMeta($body)
    {
        return preg_replace('/^\s*<!--\s*tiger:doc\b.*?-->\s*/is', '', (string) $body, 1);
    }

    /** Normalize a visibility value to 'public' (default) or 'admin'. */
    protected function _vis($v)
    {
        return strtolower(trim((string) $v)) === self::VIS_ADMIN ? self::VIS_ADMIN : self::VIS_PUBLIC;
    }

    protected function _order($v)
    {
        $v = strtolower(trim((string) $v));
        if ($v === 'first') { return -1000000.0; }
        if ($v === 'last' || $v === '') { return 1000000.0; }
        return is_numeric($v) ? (float) $v : 1000000.0;
    }

    protected function _bool($v)
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function _humanize($slug)
    {
        return ucfirst(str_replace(['-', '_'], ' ', trim((string) $slug)));
    }

    protected function _normalizeSlug($slug)
    {
        $slug = strtolower(trim((string) $slug, "/ \t\n\r\0"));
        return $slug === '' ? '' : ($this->_safeSegment($slug) === '' ? '' : $slug);
    }

    protected function _safeSegment($seg)
    {
        $seg = (string) $seg;
        if ($seg === '' || $seg === '.' || $seg === '..') {
            return '';
        }
        return preg_match('/^[a-z0-9][a-z0-9._-]*$/', $seg) ? $seg : '';
    }

    protected function _fileTitle($body, $format, $slug)
    {
        if ($format === Tiger_Model_Page::FORMAT_MARKDOWN
            && preg_match('/^\s*#\s+(.+?)\s*$/m', (string) $body, $m)) {
            return trim($m[1]);
        }
        return $this->_humanize($slug);
    }

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

    protected function _plain($body, $format)
    {
        $s = (string) $body;
        $s = preg_replace('/<!--.*?-->/s', ' ', $s);
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

    /** Config getter (tiger.docs.* → docs.*), tolerant of a missing registry. */
    protected function _cfgBool($path, $default)
    {
        try {
            $cfg = Zend_Registry::get('Zend_Config');
        } catch (Throwable $e) {
            return $default;
        }
        foreach (['tiger.docs.' . $path, 'docs.' . $path] as $full) {
            $node = $cfg;
            foreach (explode('.', $full) as $k) {
                $node = is_object($node) ? ($node->{$k} ?? null) : null;
                if ($node === null) {
                    break;
                }
            }
            if ($node !== null && !$node instanceof Zend_Config) {
                return in_array(strtolower((string) $node), ['1', 'true', 'yes', 'on'], true);
            }
        }
        return $default;
    }
}
