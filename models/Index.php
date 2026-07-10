<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Index — the per-server build cache in front of the content scan.
 *
 * The scan model already works in a DISTRIBUTED fleet (each server reads its own content copy,
 * no shared state). What this adds is: don't re-scan on every request, and rebuild automatically
 * — per server — the moment that server's content changes. No database, no coordination.
 *
 * How it self-heals:
 *   1. A cheap FINGERPRINT of the content (a hash of every file's path|mtime|size — stat only, no
 *      reads) is the change signal.
 *   2. The built index (collections + recursive trees + a search index) is serialized to a PHP
 *      file in a writable, per-server cache dir (loaded back as an opcache-compiled `include`).
 *   3. On use: fingerprint matches the cached one → serve the cache (zero scan). Mismatch or no
 *      cache → rebuild via the supplied callback, rewrite the cache. That's the whole trick.
 *
 * The fingerprint walk is throttled (docs.cache.ttl) so a hot server stats the tree at most once
 * per window; `docs.cache.check = 0` turns the walk off entirely ("trust the deploy"). The cache
 * is a regenerable artifact — never committed, safe to delete.
 *
 * @internal — Docs_Model_Docs owns this; callers use the model.
 */
class Docs_Model_Index
{
    /** Absolute path to the content/ dir. */
    protected $_contentDir;

    /** callable(string $locale): array — the full scan, called only on a cache miss. */
    protected $_build;

    /** Per-request memo, per locale (an FPM worker also skips the fingerprint walk within a request). */
    protected static $_memo = [];

    public function __construct($contentDir, callable $build)
    {
        $this->_contentDir = $contentDir;
        $this->_build      = $build;
    }

    /**
     * The built index for a locale:
     *   ['fingerprint','builtAt','checkedAt','collections'=>[…],'trees'=>[col=>nodes],'search'=>[…]]
     * Served from cache when the fingerprint still matches; rebuilt + cached otherwise.
     */
    public function get($locale)
    {
        $locale = preg_match('/^[a-z]{2}$/', (string) $locale) ? (string) $locale : 'en';
        if (isset(self::$_memo[$locale])) {
            return self::$_memo[$locale];
        }

        // Caching off → build fresh every request (dev convenience).
        if (!$this->_cfgBool('cache.enabled', true)) {
            return self::$_memo[$locale] = $this->_wrap($locale, ($this->_build)($locale));
        }

        $file = $this->_cacheFile($locale);
        $idx  = $this->_load($file);
        if (is_array($idx) && $this->_stillValid($idx, $locale, $file)) {
            return self::$_memo[$locale] = $idx;
        }

        return self::$_memo[$locale] = $this->_rebuild($locale, $file);
    }

    /** Force a rebuild for a locale (admin "Rebuild index" / deploy warm), returns the fresh index. */
    public function rebuild($locale = 'en')
    {
        $locale = preg_match('/^[a-z]{2}$/', (string) $locale) ? (string) $locale : 'en';
        return self::$_memo[$locale] = $this->_rebuild($locale, $this->_cacheFile($locale));
    }

    // ---------------------------------------------------------------------------------------------

    /** Build the payload, stamp it, and (best-effort) write the cache file. */
    protected function _rebuild($locale, $file)
    {
        $idx = $this->_wrap($locale, ($this->_build)($locale));
        $this->_store($file, $idx);
        return $idx;
    }

    /** Stamp a build payload with its fingerprint + timestamps. */
    protected function _wrap($locale, array $payload)
    {
        return ['fingerprint' => $this->_fingerprint($locale), 'builtAt' => time(), 'checkedAt' => time()] + $payload;
    }

    /**
     * Is a cached index still good? True when the content fingerprint still matches (throttled by
     * ttl); "trust the deploy" mode (check=0) skips the walk entirely.
     */
    protected function _stillValid(array &$idx, $locale, $file)
    {
        if (!$this->_cfgBool('cache.check', true)) {
            return true;
        }
        $ttl = (int) $this->_cfg('cache.ttl', 10);
        if ($ttl > 0 && (time() - (int) ($idx['checkedAt'] ?? 0)) < $ttl) {
            return true;   // walked recently — don't stat the tree again yet
        }
        if (($idx['fingerprint'] ?? null) === $this->_fingerprint($locale)) {
            $idx['checkedAt'] = time();
            $this->_store($file, $idx);   // push the throttle window forward
            return true;
        }
        return false;   // content changed → caller rebuilds
    }

    /**
     * Cheap change signal: md5 of the sorted "relpath|mtime|size" of every file under
     * content/<locale> and content/en (stat only). Add/remove/edit any file → the hash changes.
     */
    protected function _fingerprint($locale)
    {
        $parts = [];
        foreach (array_unique([$this->_contentDir . '/' . $locale, $this->_contentDir . '/en']) as $root) {
            if (!is_dir($root)) {
                continue;
            }
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO)
                );
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        $parts[] = substr($f->getPathname(), strlen($this->_contentDir)) . '|' . $f->getMTime() . '|' . $f->getSize();
                    }
                }
            } catch (Throwable $e) {
                // Unreadable tree → fingerprint stays partial; a rebuild just re-derives from what's there.
            }
        }
        sort($parts);
        return md5(implode("\n", $parts));
    }

    // ---- Cache file I/O (opcache-friendly PHP, atomic write) -------------------------------------

    protected function _cacheFile($locale)
    {
        return $this->_cacheDir() . '/index-' . $locale . '.php';
    }

    protected function _load($file)
    {
        if (!is_file($file)) {
            return null;
        }
        try {
            $data = include $file;                     // returns the array; opcache-compiled
            return is_array($data) ? $data : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function _store($file, array $idx)
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;   // can't cache → we just rebuild each request; correctness is unaffected
        }
        $php = "<?php\n// TigerDocs build cache — generated, safe to delete.\nreturn " . var_export($idx, true) . ";\n";
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $php, LOCK_EX) !== false) {
            @rename($tmp, $file);                      // atomic swap
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
        }
    }

    /**
     * Writable per-server cache dir: config override → a predictable app `var/cache` (when the app
     * root is writable — persistent, inspectable, survives an fpm restart) → system temp (always
     * writable; the right fallback for read-only / containerized deploys — the cache just rebuilds).
     */
    protected function _cacheDir()
    {
        $dir = (string) $this->_cfg('cache.dir', '');
        if ($dir !== '') {
            return rtrim($dir, '/');
        }
        if (defined('APPLICATION_PATH')) {
            $base = dirname(APPLICATION_PATH);
            if (is_dir($base . '/var/cache') || is_writable($base) || is_writable($base . '/var')) {
                return $base . '/var/cache/tiger-docs';
            }
        }
        return sys_get_temp_dir() . '/tiger-docs-' . substr(md5($this->_contentDir), 0, 8);
    }

    // ---- Config (tiger.docs.* → docs.*), tolerant of a missing registry ------------------------

    protected function _cfg($path, $default)
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
                return $node;
            }
        }
        return $default;
    }

    protected function _cfgBool($path, $default)
    {
        $v = $this->_cfg($path, $default);
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }
}
