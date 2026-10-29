<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Exercise Docs_Model_Content against a throwaway origin: publish, change, roll back, break it,
 * take the origin away — and assert what the server serves in each case.
 *
 *     php bin/content-selftest.php
 *
 * No framework and no network: it serves its own fixtures over PHP's built-in server on a loopback
 * port. The reason it exists is the failure mode. A content resolver that quietly keeps serving
 * last week's docs looks exactly like one that is working, so "it rendered" proves nothing here —
 * every case below asserts the VERSION the server lands on, including the ones where the right
 * answer is "don't move".
 *
 * Exits non-zero on the first failure.
 */

$root = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . '/tiger-docs-selftest-' . getmypid();
$port = 8700 + (getmypid() % 200);
$fail = 0;
$n    = 0;

// ---- the two globals the model touches ------------------------------------------------------
if (!class_exists('Zend_Config')) {
    #[AllowDynamicProperties]
    class Zend_Config {
        public function __construct(array $d) { foreach ($d as $k => $v) { $this->{$k} = is_array($v) ? new self($v) : $v; } }
    }
}
if (!class_exists('Zend_Registry')) {
    class Zend_Registry {
        private static $r = [];
        public static function set($k, $v) { self::$r[$k] = $v; }
        public static function get($k) { if (!isset(self::$r[$k])) { throw new Exception('missing ' . $k); } return self::$r[$k]; }
        public static function isRegistered($k) { return isset(self::$r[$k]); }
    }
}
require $root . '/models/Content.php';

// ---- helpers --------------------------------------------------------------------------------
function rrm($d) {
    if (!is_dir($d) || is_link($d)) { @unlink($d); return; }
    foreach (scandir($d) ?: [] as $e) { if ($e !== '.' && $e !== '..') { rrm($d . '/' . $e); } }
    @rmdir($d);
}
function publish($origin, $sha, $body, $breakSum = false) {
    $src = $origin . '/.src-' . $sha;
    @mkdir($src . '/en/guide', 0775, true);
    file_put_contents($src . '/en/guide/_index.md', $body);
    $zip = $origin . '/docs-' . $sha . '.zip';
    @unlink($zip);
    $za = new ZipArchive();
    $za->open($zip, ZipArchive::CREATE);
    $za->addFile($src . '/en/guide/_index.md', 'en/guide/_index.md');
    $za->close();
    rrm($src);
    $sum = $breakSum ? str_repeat('0', 64) : hash_file('sha256', $zip);
    file_put_contents($origin . '/current.json', json_encode([
        'sha' => $sha, 'artifact' => basename($zip), 'sha256' => $sum, 'bytes' => filesize($zip),
    ]));
}
function configure($url, $dir, $ttl) {
    Zend_Registry::set('Zend_Config', new Zend_Config(
        ['tiger' => ['docs' => ['content_source' => ['url' => $url, 'dir' => $dir, 'ttl' => $ttl, 'keep' => 3]]]]
    ));
}
/** A fresh resolve — the model memoizes per request, so each case gets its own process-alike. */
function resolveFresh() {
    $ref = new ReflectionClass('Docs_Model_Content');
    $p   = $ref->getProperty('_resolved');
    $p->setValue(null, null);
    return (new Docs_Model_Content())->resolve();
}
function served($dir) {
    $f = $dir === null ? null : $dir . '/en/guide/_index.md';
    return ($f && is_file($f)) ? trim(file_get_contents($f)) : null;
}

// ---- origin ----------------------------------------------------------------------------------
@mkdir($tmp . '/origin', 0775, true);
@mkdir($tmp . '/store', 0775, true);
$srv = proc_open(
    sprintf('exec php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($tmp . '/origin')),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes
);
register_shutdown_function(static function () use ($srv, $tmp) {
    if (is_resource($srv)) { proc_terminate($srv); proc_close($srv); }
    rrm($tmp);
});
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port, $e, $s, 0.1); $i++) { usleep(100000); }

$url = 'http://127.0.0.1:' . $port;
$dir = $tmp . '/store';

function check($label, $got, $want) {
    global $fail, $n;
    $n++;
    $ok = $got === $want;
    printf("  %-46s %s\n", $label, $ok ? 'ok' : "FAIL (got " . var_export($got, true) . ", want " . var_export($want, true) . ")");
    if (!$ok) { $fail++; }
}

// 1. a cold server installs the published version
publish($tmp . '/origin', 'aaaa1111', 'VERSION-ONE');
configure($url, $dir, 1);
check('cold server installs the published version', served(resolveFresh()), 'VERSION-ONE');

// 2. a new publish is picked up
publish($tmp . '/origin', 'bbbb2222', 'VERSION-TWO');
sleep(6);                                           // past the TTL floor
check('a new publish is picked up', served(resolveFresh()), 'VERSION-TWO');

// 3. rolling the pointer back rolls the server back, from disk
publish($tmp . '/origin', 'aaaa1111', 'VERSION-ONE');
sleep(6);
check('repointing rolls the server back', served(resolveFresh()), 'VERSION-ONE');

// 4. a pointer whose checksum does not match is refused, and the old version keeps serving
publish($tmp . '/origin', 'cccc3333', 'TAMPERED', true);
sleep(6);
check('a bad checksum is refused', served(resolveFresh()), 'VERSION-ONE');
check('  and nothing was left behind', count(glob($dir . '/versions/*cccc3333*')), 0);

// 5. the origin going away does not take the docs with it
publish($tmp . '/origin', 'aaaa1111', 'VERSION-ONE');
sleep(6);
resolveFresh();
proc_terminate($srv);
sleep(1);
check('origin down: keeps serving what it has', served(resolveFresh()), 'VERSION-ONE');

// 6. a server with nothing and no origin falls back rather than failing
rrm($dir . '/versions');
@unlink($dir . '/current');
@unlink($dir . '/state.json');
check('origin down and nothing local: falls back', resolveFresh(), null);

printf("\n%d checks, %d failed\n", $n, $fail);
exit($fail === 0 ? 0 : 1);
