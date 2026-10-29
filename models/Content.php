<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Model_Content — resolve the PUBLISHED content version this server should serve.
 *
 * Docs content is a release, not a directory that drifts. A publisher writes ONE immutable
 * artifact plus a tiny pointer:
 *
 *     <base>/current.json          {"sha":"a1b2c3…","artifact":"docs-a1b2c3….zip","sha256":"…"}
 *     <base>/docs-a1b2c3….zip      every doc, every locale, one object
 *
 * A server reads the POINTER on a short TTL — one small GET — and only fetches the artifact when
 * the sha moves. The new version is unpacked beside the old one and swapped in by renaming a
 * symlink, so a request either sees the whole old version or the whole new one; there is no
 * moment where the tree is half-written.
 *
 * Why not sync a directory. A per-server `aws s3 sync --delete` on a timer has three failure modes
 * this design does not: an instance that launched after the last tick serves stale content from
 * birth; a puller that dies is invisible, because nothing reports what version a server holds; and
 * --delete against a live tree has a window where docs are simply missing. Here every server
 * converges on the pointer within the TTL whatever its uptime, `version()` says exactly what it is
 * serving, and rolling back is rewriting one small file.
 *
 * Fail-soft by construction: if the pointer or the artifact cannot be fetched, the server keeps
 * serving the version it already has. Stale docs beat no docs, and the fetch is retried next TTL.
 *
 * Disabled (no `docs.content_source.url`) this does nothing and the module reads its own content/
 * dir exactly as before — a single-server install needs none of it.
 *
 * Config (`tiger.docs.content_source.*`, or `docs.content_source.*`):
 *   url    the base URL holding current.json + the artifacts (required — this is the on switch)
 *   dir    where versions are unpacked      (default <app>/var/docs-content)
 *   ttl    seconds between pointer checks   (default 60)
 *   keep   versions retained for rollback   (default 3)
 *
 * @api
 */
class Docs_Model_Content
{
    const POINTER       = 'current.json';
    const CURRENT       = 'current';
    const STATE         = 'state.json';
    const DEFAULT_TTL   = 60;
    const DEFAULT_KEEP  = 3;

    /** A pointer fetch is on the request path, so it gets a short leash. */
    const POINTER_TIMEOUT  = 4;
    /** An artifact fetch is rarer and larger. */
    const ARTIFACT_TIMEOUT = 60;
    /** Refuse an artifact larger than this — a pointer is not a licence to fill the disk. */
    const MAX_BYTES        = 134217728;   // 128 MB
    /** After a failed fetch, wait this long before trying again (don't hammer a dead origin). */
    const FAIL_BACKOFF     = 30;
    /**
     * How long a server with NO content waits for whichever worker is installing. Deliberately
     * short: this is on a request, and if the artifact is broken the wait buys nothing — better to
     * fall back to the module's own content and retry next request than to hang the page.
     */
    const COLD_WAIT        = 5;

    /** @var string base URL ('' = disabled) */
    protected $_base;

    /** @var string local version store */
    protected $_dir;

    /** @var array|null memoized state */
    protected $_state;

    /** @var string|null memoized resolved dir, per request */
    protected static $_resolved;

    public function __construct()
    {
        $this->_base = rtrim((string) $this->_cfg('content_source.url', ''), '/');
        $dir = trim((string) $this->_cfg('content_source.dir', ''));
        if ($dir === '') {
            $base = defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : sys_get_temp_dir();
            $dir  = $base . '/var/docs-content';
        }
        $this->_dir = rtrim($dir, '/');
    }

    /** Is a published content source configured at all? */
    public function isEnabled()
    {
        return $this->_base !== '';
    }

    /**
     * The content directory to read docs from, bringing this server up to the published version
     * first when it is behind. Null when no source is configured (caller keeps its own default).
     *
     * @return string|null absolute path, or null
     */
    public function resolve()
    {
        if (!$this->isEnabled()) { return null; }
        if (self::$_resolved !== null) { return self::$_resolved ?: null; }

        $current = $this->_currentPath();
        $state   = $this->_readState();
        $ttl     = max(5, (int) $this->_cfg('content_source.ttl', self::DEFAULT_TTL));

        // Inside the window and we already have a version: serve it, don't ask again.
        if ($current !== null && (time() - (int) ($state['checkedAt'] ?? 0)) < $ttl) {
            return self::$_resolved = $current;
        }

        $pointer = $this->_fetchPointer();
        if ($pointer === null) {
            // Origin unreachable. Keep serving what we have and back off a little.
            $state['checkedAt'] = time() - $ttl + self::FAIL_BACKOFF;
            $this->_writeState($state);
            return self::$_resolved = ($current ?: null);
        }

        if ($current !== null && ($state['sha'] ?? null) === $pointer['sha']) {
            $state['checkedAt'] = time();
            $this->_writeState($state);
            return self::$_resolved = $current;
        }

        // When this server already has a version, a worker that loses the lock serves it and gets
        // out of the way. When it has NOTHING — a just-launched instance taking its first requests
        // — falling through would serve the module's near-empty default, so the losers wait for
        // the winner instead. Briefly slow beats briefly wrong.
        $installed = $this->_install($pointer, $current === null);
        if ($installed !== null) { return self::$_resolved = $installed; }

        // Install failed, or another worker got there first — re-read rather than reusing the
        // snapshot taken before the wait, which is exactly the case where it is now out of date.
        return self::$_resolved = ($this->_currentPath() ?: null);
    }

    /**
     * The content version this server is serving — the thing that makes "prod is stale" visible
     * instead of something you notice in a screenshot.
     *
     * @return string the published sha, or '' when not applicable
     */
    public function version()
    {
        if (!$this->isEnabled()) { return ''; }
        $state = $this->_readState();
        return (string) ($state['sha'] ?? '');
    }

    // =====================================================================================
    //  Fetch
    // =====================================================================================

    /** The pointer, validated, or null if it could not be fetched or does not make sense. */
    protected function _fetchPointer()
    {
        $body = $this->_get($this->_base . '/' . self::POINTER, self::POINTER_TIMEOUT);
        if ($body === null) { return null; }
        $p = json_decode($body, true);
        if (!is_array($p)) { return null; }

        $sha      = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($p['sha'] ?? ''));
        $artifact = (string) ($p['artifact'] ?? '');
        $sha256   = strtolower(preg_replace('/[^a-f0-9]/i', '', (string) ($p['sha256'] ?? '')));

        // The artifact name is used as a path component, so it is a NAME, never a path or a URL.
        if ($sha === '' || $artifact === '' || basename($artifact) !== $artifact) { return null; }
        if (strlen($sha256) !== 64) { return null; }   // fail closed: no checksum, no install

        return ['sha' => $sha, 'artifact' => $artifact, 'sha256' => $sha256];
    }

    /** GET a URL into memory. Null on any failure — callers decide what that means. */
    protected function _get($url, $timeout)
    {
        if (!function_exists('curl_init')) {
            $body = @file_get_contents($url);
            return $body === false ? null : $body;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_USERAGENT      => 'Tiger_Docs_Content',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Not closed explicitly: a no-op since PHP 8.0, deprecated in 8.5, which core still builds on.
        return ($body === false || $code < 200 || $code > 299) ? null : (string) $body;
    }

    /** Download a URL to a file, refusing anything over MAX_BYTES. */
    protected function _download($url, $dest)
    {
        if (!function_exists('curl_init')) { return false; }
        $fp = @fopen($dest, 'w');
        if (!$fp) { return false; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => self::ARTIFACT_TIMEOUT,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_USERAGENT      => 'Tiger_Docs_Content',
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => static function ($ch, $dlTotal, $dlNow) {
                return ($dlTotal > self::MAX_BYTES || $dlNow > self::MAX_BYTES) ? 1 : 0;
            },
        ]);
        $ok   = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);
        // Not closed explicitly: a no-op since PHP 8.0, deprecated in 8.5, which core still builds on.
        return $ok !== false && $code >= 200 && $code <= 299 && is_file($dest) && filesize($dest) > 0;
    }

    // =====================================================================================
    //  Install
    // =====================================================================================

    /**
     * Bring this server to $pointer's version and return its directory, or null if it could not be
     * done (another worker is already doing it, or the download/verify failed).
     *
     * @param  array $pointer  validated pointer
     * @param  bool  $wait     wait for the lock (cold server) instead of standing aside
     * @return string|null
     */
    protected function _install(array $pointer, $wait = false)
    {
        if (!$this->_ensureDir($this->_dir)) { return null; }

        // One worker per server does the work; the rest keep serving the old version rather than
        // queueing behind it. A docs page is not worth a thundering herd.
        $lock = @fopen($this->_dir . '/.lock', 'c');
        if (!$lock) { return null; }
        if (!$this->_acquire($lock, $wait)) { fclose($lock); return null; }

        try {
            // Another worker may have installed this exact version while we waited for the lock.
            $state = $this->_readState(true);
            $target = $this->_dir . '/versions/' . $pointer['sha'];
            if (($state['sha'] ?? null) === $pointer['sha'] && is_dir($target)) {
                return $this->_currentPath();
            }

            if (!is_dir($target) && !$this->_materialize($pointer, $target)) { return null; }
            if (!$this->_swap($target)) { return null; }

            $this->_writeState(['sha' => $pointer['sha'], 'checkedAt' => time(), 'installedAt' => time()]);
            $this->_prune($pointer['sha']);
            return $this->_currentPath();
        } catch (Throwable $e) {
            return null;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Take the install lock. Non-blocking by default — the caller has something to serve. A cold
     * server passes $wait and retries within a budget, because PHP's flock has no timeout and
     * blocking forever on a docs fetch is not a trade anyone wants.
     *
     * @param  resource $lock
     * @param  bool     $wait
     * @return bool
     */
    protected function _acquire($lock, $wait)
    {
        if (@flock($lock, LOCK_EX | LOCK_NB)) { return true; }
        if (!$wait) { return false; }
        $deadline = microtime(true) + self::COLD_WAIT;
        while (microtime(true) < $deadline) {
            usleep(100000);                                   // 100ms
            // The winner may have finished: if a version is now present, let the caller use it
            // rather than installing a second time.
            if ($this->_currentPath() !== null) { return false; }
            if (@flock($lock, LOCK_EX | LOCK_NB)) { return true; }
        }
        return false;
    }

    /** Download, verify and unpack the artifact into $target. */
    protected function _materialize(array $pointer, $target)
    {
        if (!class_exists('ZipArchive')) { return false; }
        if (!$this->_ensureDir($this->_dir . '/versions')) { return false; }

        $tmpZip = $this->_dir . '/versions/.' . $pointer['sha'] . '.' . getmypid() . '.zip';
        $tmpDir = $this->_dir . '/versions/.' . $pointer['sha'] . '.' . getmypid();

        try {
            if (!$this->_download($this->_base . '/' . $pointer['artifact'], $tmpZip)) { return false; }

            // Fail closed on the checksum. A pointer that says what to install must also say what
            // it should weigh — otherwise anything that can answer the URL can replace the docs.
            if (!hash_equals($pointer['sha256'], (string) hash_file('sha256', $tmpZip))) { return false; }

            if (!$this->_ensureDir($tmpDir)) { return false; }
            $za = new ZipArchive();
            if ($za->open($tmpZip) !== true) { return false; }
            for ($i = 0; $i < $za->numFiles; $i++) {
                $name = (string) $za->getNameIndex($i);
                // Zip-slip: no absolute paths, no traversal, no backslash games.
                if ($name === '' || $name[0] === '/' || strpos($name, '..') !== false
                    || strpos($name, '\\') !== false) {
                    $za->close();
                    return false;
                }
            }
            $ok = $za->extractTo($tmpDir);
            $za->close();
            if (!$ok) { return false; }

            // Atomic within the lock: the version dir appears complete or not at all.
            return @rename($tmpDir, $target);
        } finally {
            @unlink($tmpZip);
            if (is_dir($tmpDir)) { $this->_rrmdir($tmpDir); }
        }
    }

    /**
     * Point `current` at $target by renaming a symlink over it — atomic, so a concurrent request
     * reads one whole version or the other, never a tree mid-write.
     */
    protected function _swap($target)
    {
        $current = $this->_dir . '/' . self::CURRENT;
        $staged  = $this->_dir . '/.' . self::CURRENT . '.' . getmypid();
        @unlink($staged);
        if (function_exists('symlink') && @symlink($target, $staged) && @rename($staged, $current)) {
            return true;
        }
        @unlink($staged);
        // No symlinks on this host (some shared hosts disable them): record the path instead.
        return @file_put_contents($current . '.path', $target) !== false;
    }

    /** The resolved current version dir, or null when this server has no version yet. */
    protected function _currentPath()
    {
        $link = $this->_dir . '/' . self::CURRENT;
        $real = @realpath($link);
        if ($real && is_dir($real)) { return $real; }
        $file = $link . '.path';
        if (is_file($file)) {
            $p = @realpath(trim((string) file_get_contents($file)));
            if ($p && is_dir($p)) { return $p; }
        }
        return null;
    }

    /** Keep the newest `keep` versions (rollback is repointing, which wants them on disk). */
    protected function _prune($keepSha)
    {
        $keep = max(1, (int) $this->_cfg('content_source.keep', self::DEFAULT_KEEP));
        $dirs = glob($this->_dir . '/versions/*', GLOB_ONLYDIR) ?: [];
        if (count($dirs) <= $keep) { return; }
        usort($dirs, static function ($a, $b) { return filemtime($b) <=> filemtime($a); });
        foreach (array_slice($dirs, $keep) as $old) {
            if (basename($old) === $keepSha) { continue; }
            $this->_rrmdir($old);
        }
    }

    // =====================================================================================
    //  State + helpers
    // =====================================================================================

    protected function _readState($fresh = false)
    {
        if (!$fresh && $this->_state !== null) { return $this->_state; }
        $f = $this->_dir . '/' . self::STATE;
        $s = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        return $this->_state = (is_array($s) ? $s : []);
    }

    protected function _writeState(array $state)
    {
        $this->_state = $state;
        if (!$this->_ensureDir($this->_dir)) { return; }
        $f   = $this->_dir . '/' . self::STATE;
        $tmp = $f . '.' . getmypid();
        if (@file_put_contents($tmp, json_encode($state)) !== false) { @rename($tmp, $f); }
    }

    protected function _ensureDir($dir)
    {
        if (is_dir($dir)) { return true; }
        return @mkdir($dir, 0775, true) || is_dir($dir);
    }

    protected function _rrmdir($dir)
    {
        if (!is_dir($dir) || is_link($dir)) { @unlink($dir); return; }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') { continue; }
            $p = $dir . '/' . $e;
            is_dir($p) && !is_link($p) ? $this->_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /** Same cascade as Docs_Model_Index: `tiger.docs.<path>` then `docs.<path>`. */
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
                if ($node === null) { break; }
            }
            if ($node !== null && !$node instanceof Zend_Config) { return $node; }
        }
        return $default;
    }
}
