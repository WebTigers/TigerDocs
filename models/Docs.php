<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Model_Docs — the dual-source documentation resolver.
 *
 * A doc can come from EITHER of two stores, checked in this order (first hit wins):
 *
 *   1. DB — a `page` row of type `doc`, resolved by slug. This reuses the CMS content
 *      store (Tiger_Model_Page) so DB docs get org-scoping, publish gating, versioning,
 *      redirects, and the layout/shortcode pipeline for free. An org-scoped row beats a
 *      global one (resolveBySlug orders org_id DESC), so a tenant can OVERRIDE a shipped
 *      doc without touching a file.
 *   2. FILE — a static file shipped in the module's content/ dir
 *      (content/<locale>/<slug>.{md,html,phtml}, falling back to content/en). This is the
 *      DEFAULT source: Tiger ships lean, the platform's own docs live as files (the same
 *      files served at tiger.webtigers.com/docs), and an app that never touches the DB
 *      still has a working docs site.
 *
 * Both sources render through the SAME engine (Tiger_Cms_Renderer) so markdown, html, and
 * (trusted) phtml behave identically whether the body came from a row or a file. The
 * cascade is deliberately "DB wins": files are the shipped baseline, the DB is the
 * per-install/per-tenant override tier — the live-override pattern applied to content.
 *
 * @api
 */
class Docs_Model_Docs
{
    /** page.type for DB-backed docs (distinct from CMS pages so they never answer at root). */
    const TYPE_DOC = 'doc';

    /** ext => Tiger_Cms_Renderer format. The set of file types a doc may ship as. */
    protected static $_formats = [
        'md'    => Tiger_Model_Page::FORMAT_MARKDOWN,
        'html'  => Tiger_Model_Page::FORMAT_HTML,
        'htm'   => Tiger_Model_Page::FORMAT_HTML,
        'phtml' => Tiger_Model_Page::FORMAT_PHTML,
    ];

    /** Absolute path to the module's content/ directory (files + manifest). */
    protected $_contentDir;

    public function __construct()
    {
        $dir = dirname(__DIR__) . '/content';
        $this->_contentDir = realpath($dir) ?: $dir;
    }

    /**
     * Resolve a doc across both sources. Returns a normalized array or null (→ 404):
     *   ['slug', 'title', 'html', 'format', 'source' => 'db'|'file']
     *
     * @param string $slug   URL slug, possibly nested (guides/install)
     * @param string $locale two-letter language
     * @param string $orgId  current tenant ('' = global/public)
     */
    public function resolve($slug, $locale = 'en', $orgId = '')
    {
        $slug = $this->_normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        // 1) DB doc — org-scoped row wins over global; published only.
        $page = $this->_dbDoc($slug, $locale, $orgId);
        if ($page) {
            $html     = (new Tiger_Cms_Renderer())->render($page);
            $headings = $this->_pageNav($html);   // inject heading ids + build the "on this page" outline
            return [
                'slug'     => $slug,
                'title'    => (string) $page->title,
                'html'     => $html,
                'headings' => $headings,
                'format'   => (string) $page->format,
                'source'   => 'db',
            ];
        }

        // 2) Static file shipped with the module.
        $file = $this->_file($slug, $locale);
        if ($file) {
            $body     = (string) file_get_contents($file['path']);
            $html     = (new Tiger_Cms_Renderer())->renderBody($body, $file['format']);
            $headings = $this->_pageNav($html);
            return [
                'slug'     => $slug,
                'title'    => $this->_fileTitle($body, $file['format'], $slug),
                'html'     => $html,
                'headings' => $headings,
                'format'   => $file['format'],
                'source'   => 'file',
            ];
        }

        return null;
    }

    /**
     * Give the body's headings (h2/h3) stable ids and return the "on this page" outline:
     *   [ ['level' => 2|3, 'text' => '…', 'id' => '…'], … ]
     * Ids are slugified from the heading text + deduped, so the right-rail links anchor cleanly.
     * $html is modified in place (ids injected); an existing id on a heading is preserved.
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
                $attrs = ' id="' . $id . '"' . $attrs;   // keep any existing attrs; add id
            }
            return '<h' . $level . $attrs . '>' . $m[3] . '</h' . $level . '>';
        }, $html);
        return $out;
    }

    /**
     * The navigation tree for the sidebar/landing, read from content/manifest.json:
     *   [ ['title' => 'Section', 'items' => [ ['slug' => '', 'title' => ''], ... ]], ... ]
     * Missing/broken manifest → [] (the site still renders, just without a nav).
     */
    public function tree($locale = 'en')
    {
        $locale = preg_match('/^[a-z]{2}$/', (string) $locale) ? (string) $locale : 'en';
        // Per-locale nav (translated labels) wins; fall back to English, then a legacy single
        // manifest at the content root. First one that parses with sections is used.
        foreach ([
            $this->_contentDir . '/' . $locale . '/manifest.json',
            $this->_contentDir . '/en/manifest.json',
            $this->_contentDir . '/manifest.json',
        ] as $path) {
            if (is_file($path)) {
                $data = json_decode((string) file_get_contents($path), true);
                if (is_array($data) && !empty($data['sections'])) {
                    return $data['sections'];
                }
            }
        }
        return [];
    }

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
     * Find a shipped file for a slug: try the requested locale then fall back to `en`, and
     * each supported extension. Returns ['path', 'format'] or null. Every candidate is
     * confirmed to sit INSIDE the content dir (defense-in-depth against traversal).
     */
    protected function _file($slug, $locale)
    {
        $locales = array_values(array_unique([$this->_safeSegment($locale) ?: 'en', 'en']));
        foreach ($locales as $loc) {
            foreach (self::$_formats as $ext => $format) {
                $path = $this->_contentDir . '/' . $loc . '/' . $slug . '.' . $ext;
                $real = realpath($path);
                if ($real && strpos($real, $this->_contentDir . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
                    return ['path' => $real, 'format' => $format];
                }
            }
        }
        return null;
    }

    /**
     * Normalize a URL slug to a safe relative path (may be nested). Lowercased; each
     * segment must be a safe token; `.`/`..`/empty segments are rejected → '' (→ 404).
     */
    protected function _normalizeSlug($slug)
    {
        $slug = strtolower(trim((string) $slug, "/ \t\n\r\0"));
        if ($slug === '') {
            return '';
        }
        $out = [];
        foreach (explode('/', $slug) as $seg) {
            $seg = $this->_safeSegment($seg);
            if ($seg === '') {
                return '';
            }
            $out[] = $seg;
        }
        return implode('/', $out);
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

    /** A human title for a file doc: the first markdown H1, else the humanized slug tail. */
    protected function _fileTitle($body, $format, $slug)
    {
        if ($format === Tiger_Model_Page::FORMAT_MARKDOWN
            && preg_match('/^\s*#\s+(.+?)\s*$/m', $body, $m)) {
            return trim($m[1]);
        }
        $tail = substr($slug, (int) strrpos('/' . $slug, '/'));
        return ucfirst(str_replace(['-', '_'], ' ', trim($tail, '/')));
    }
}
