<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use Docs_Model_Index;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Config;
use Zend_Registry;

/**
 * Docs_Model_Index is the per-server build cache that fronts the content scan. Its whole job is to
 * self-heal: serve the cached index while the content is unchanged, and rebuild the moment this
 * server's content changes — detected by a cheap stat-only fingerprint, with no DB and no
 * coordination. Driven against a temp content dir, with the fingerprint-throttle TTL set to 0 so the
 * cache re-checks every call (the headline is the invalidation, not the throttle).
 */
final class IndexCacheTest extends TestCase
{
    private string $content;
    private string $cache;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/tigerdocs-index-' . uniqid('', true);
        $this->content = $base . '/content';
        $this->cache   = $base . '/cache';
        @mkdir($this->content, 0775, true);
        @mkdir($this->cache, 0775, true);

        // cache.dir → our temp cache; cache.ttl = 0 → never skip the fingerprint walk on a throttle.
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'docs' => ['cache' => ['dir' => $this->cache, 'ttl' => '0']],
        ], true));

        // The static per-request memo is shared across instances — clear it so each test re-reads disk.
        (new ReflectionProperty(Docs_Model_Index::class, '_memo'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        try { Zend_Registry::getInstance()->offsetUnset('Zend_Config'); } catch (\Throwable $e) { /* none */ }
        (new ReflectionProperty(Docs_Model_Index::class, '_memo'))->setValue(null, []);
        $this->rrmdir(dirname($this->content));
    }

    private function index(callable $build): Docs_Model_Index
    {
        return new Docs_Model_Index($this->content, $build, fn($locale) => [$this->content]);
    }

    private function resetMemo(): void
    {
        (new ReflectionProperty(Docs_Model_Index::class, '_memo'))->setValue(null, []);
    }

    public function testServesFromCacheThenRebuildsWhenContentChanges(): void
    {
        file_put_contents($this->content . '/a.md', "# A\n\nfirst");

        $calls = 0;
        $build = function ($locale) use (&$calls) {
            $calls++;
            return ['collections' => [], 'trees' => [], 'search' => [['slug' => 'a']]];
        };

        // 1) cold → builds once, writes the cache.
        $idx = $this->index($build)->get('en');
        $this->assertSame(1, $calls, 'cold cache builds');
        $this->assertSame([['slug' => 'a']], $idx['search']);
        $this->assertArrayHasKey('fingerprint', $idx);

        // 2) unchanged content → served from the cache, the build callback is NOT called again.
        $this->resetMemo();
        $this->index($build)->get('en');
        $this->assertSame(1, $calls, 'unchanged content serves from cache (no rebuild)');

        // 3) a file changes → the fingerprint flips → the index rebuilds.
        $this->resetMemo();
        file_put_contents($this->content . '/a.md', "# A\n\nfirst and MORE content added");
        $this->index($build)->get('en');
        $this->assertSame(2, $calls, 'changed content triggers a rebuild');
    }

    public function testAddingAFileAlsoInvalidates(): void
    {
        file_put_contents($this->content . '/a.md', 'one');
        $calls = 0;
        $build = function ($locale) use (&$calls) { $calls++; return ['collections' => [], 'trees' => [], 'search' => []]; };

        $this->index($build)->get('en');
        $this->assertSame(1, $calls);

        $this->resetMemo();
        file_put_contents($this->content . '/b.md', 'two');   // a new file changes the stat fingerprint
        $this->index($build)->get('en');
        $this->assertSame(2, $calls, 'a newly added file invalidates the cache');
    }

    public function testLocaleIsClampedToTwoLetterElseEn(): void
    {
        $calls = 0;
        $build = function ($locale) use (&$calls) { $calls++; return ['collections' => [], 'trees' => [], 'search' => []]; };
        $index = $this->index($build);

        $index->get('ENGLISH');   // not /^[a-z]{2}$/ → clamped to 'en' → builds
        $index->get('en');        // same memo key 'en' → served from the per-request memo, no rebuild
        $this->assertSame(1, $calls, 'a non-2-letter locale maps to en, and both calls share one build');
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
