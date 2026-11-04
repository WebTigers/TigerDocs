<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use Docs_Model_Remote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TigerDocs\Tests\Support\Reflect;
use Zend_Config;
use Zend_Registry;

/**
 * Docs_Model_Remote is the one place a REMOTE party chooses what lands on every fleet node's disk and
 * is then rendered into every docs page. Its gates are the module's security surface:
 *   - the content source must be https (a tree fetched over plain http can be rewritten in flight);
 *   - a manifest path must be a plain, contained relative path (no traversal / absolute / null byte);
 *   - the extension allow-list EXCLUDES php/phtml, so a write to the bucket can never become RCE.
 * These are pure string gates — exercised directly, and the load-bearing ones are mutation-checked.
 */
final class RemoteSafetyTest extends TestCase
{
    use Reflect;

    protected function tearDown(): void
    {
        try { Zend_Registry::getInstance()->offsetUnset('Zend_Config'); } catch (\Throwable $e) { /* none set */ }
    }

    private function config(array $tree): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config($tree, true));
    }

    // ── the RCE-via-bucket guard: php/phtml are not installable content ──────────────────────────

    public function testAllowListExcludesExecutableExtensions(): void
    {
        $this->assertNotContains('php', Docs_Model_Remote::ALLOWED_EXT);
        $this->assertNotContains('phtml', Docs_Model_Remote::ALLOWED_EXT);
    }

    public function testSafePathRefusesAPhtmlFile(): void
    {
        $r = new Docs_Model_Remote();
        $this->assertSame('', $this->call($r, '_safePath', ['guide/evil.phtml']), 'a .phtml must never be installable from a network source');
        $this->assertSame('', $this->call($r, '_safePath', ['guide/evil.php']));
    }

    #[DataProvider('acceptedPaths')]
    public function testSafePathAcceptsPlainContentPaths(string $in, string $expected): void
    {
        $this->assertSame($expected, $this->call(new Docs_Model_Remote(), '_safePath', [$in]));
    }

    public static function acceptedPaths(): array
    {
        return [
            'markdown'            => ['guide/overview.md', 'guide/overview.md'],
            'nested html'        => ['guide/a/b.html', 'guide/a/b.html'],
            'image'              => ['img/logo.png', 'img/logo.png'],
            'leading underscore' => ['_index.md', '_index.md'],        // collection metadata file — must be allowed
            'nested _index'      => ['tigershop/_index.md', 'tigershop/_index.md'],
            'case preserved'     => ['Guide/Overview.MD', 'Guide/Overview.MD'], // ext matched case-insensitively, name kept
            'backslash → slash'  => ['guide\\overview.md', 'guide/overview.md'],
        ];
    }

    #[DataProvider('refusedPaths')]
    public function testSafePathRefusesUnsafeOrForeignPaths(string $in): void
    {
        $this->assertSame('', $this->call(new Docs_Model_Remote(), '_safePath', [$in]));
    }

    public static function refusedPaths(): array
    {
        return [
            'parent traversal'   => ['../etc/passwd.md'],
            'embedded traversal' => ['guide/../../secret.md'],
            'absolute'           => ['/etc/passwd.md'],
            'null byte'          => ["guide/ok.md\0.png"],
            'empty'              => [''],
            'space in segment'   => ['guide/a b.md'],
            'no extension'       => ['guide/overview'],
            'unknown extension'  => ['guide/archive.zip'],
            'json allowed-ext but dotfile seg' => ['guide/.hidden.md'], // segment starts with '.', not [A-Za-z0-9_]
        ];
    }

    public function testSafePathRefusesAnOverLongPath(): void
    {
        $long = str_repeat('a', 401) . '.md';
        $this->assertSame('', $this->call(new Docs_Model_Remote(), '_safePath', [$long]));
    }

    // ── baseUrl: https only, trailing slash trimmed, unset → '' ──────────────────────────────────

    public function testBaseUrlAcceptsHttpsAndTrimsTrailingSlash(): void
    {
        $this->config(['docs' => ['content_url' => 'https://cdn.example.com/docs/']]);
        $r = new Docs_Model_Remote();
        $this->assertSame('https://cdn.example.com/docs', $r->baseUrl());
        $this->assertTrue($r->isEnabled());
    }

    public function testBaseUrlRefusesPlainHttp(): void
    {
        $this->config(['docs' => ['content_url' => 'http://cdn.example.com/docs']]);
        $r = new Docs_Model_Remote();
        $this->assertSame('', $r->baseUrl(), 'plain http is refused — the files are rendered into every page');
        $this->assertFalse($r->isEnabled());
    }

    public function testBaseUrlIsEmptyWhenUnconfigured(): void
    {
        $this->config(['docs' => []]);
        $this->assertSame('', (new Docs_Model_Remote())->baseUrl());
        $this->assertFalse((new Docs_Model_Remote())->isEnabled());
    }

    // ── _normalize: sha256 shape, size caps, compact vs full form, ksort, drop-bad ───────────────

    public function testNormalizeKeepsValidRowsInBothFormsAndSortsThem(): void
    {
        $shaZ = str_repeat('f', 64);
        $shaA = str_repeat('a', 64);
        $out  = $this->call(new Docs_Model_Remote(), '_normalize', [[
            'z/last.md'  => ['sha256' => $shaZ, 'size' => 10],  // full form
            'a/first.md' => $shaA,                              // compact form → size 0
        ]]);

        $this->assertSame(['a/first.md', 'z/last.md'], array_keys($out), 'ksort-ed');
        $this->assertSame(['sha256' => $shaA, 'size' => 0], $out['a/first.md']);
        $this->assertSame(['sha256' => $shaZ, 'size' => 10], $out['z/last.md']);
    }

    public function testNormalizeDropsBadRows(): void
    {
        $sha = str_repeat('a', 64);
        $out = $this->call(new Docs_Model_Remote(), '_normalize', [[
            'ok.md'        => $sha,
            'badsha.md'    => 'not-a-sha',
            'oversize.md'  => ['sha256' => $sha, 'size' => Docs_Model_Remote::MAX_FILE + 1],
            'negative.md'  => ['sha256' => $sha, 'size' => -1],
            '../escape.md' => $sha,                 // refused by _safePath
            'script.php'   => $sha,                 // refused extension
        ]]);

        $this->assertSame(['ok.md'], array_keys($out), 'only the one clean row survives');
    }

    // ── _encodePath: percent-encode each segment, keep the separators ────────────────────────────

    public function testEncodePathEncodesSegmentsButKeepsSlashes(): void
    {
        $this->assertSame('a/b%20c/d.md', $this->call(new Docs_Model_Remote(), '_encodePath', ['a/b c/d.md']));
    }

    // ── version(): an honest label the page can't lie with ───────────────────────────────────────

    public function testVersionReportsManifestStamp(): void
    {
        $dir = sys_get_temp_dir() . '/tigerdocs-remote-' . uniqid('', true);
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/.manifest.json', json_encode(['generated' => '2026-10-30T18:04:11Z', 'files' => []]));
        $this->config(['docs' => ['content_url' => 'https://cdn.example.com/docs', 'content_mirror' => $dir]]);

        $this->assertSame('remote 2026-10-30T18:04:11Z', (new Docs_Model_Remote())->version());

        @unlink($dir . '/.manifest.json');
        @rmdir($dir);
    }
}
