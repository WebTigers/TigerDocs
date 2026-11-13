<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use Docs_Model_Docs;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TigerDocs\Tests\Support\Reflect;
use Zend_Config;
use Zend_Registry;

/**
 * The nav-tree transforms (flat → nested, visibility filter, url stamping, url walk), the heading-id
 * slugger (stable, collision-deduped — it anchors every in-page link), and the external-links gate
 * (http(s)-only; clearing a url drops a shipped default). All pure; no DB, no filesystem.
 */
final class DocsTreeTest extends TestCase
{
    use Reflect;

    protected function tearDown(): void
    {
        try { Zend_Registry::getInstance()->offsetUnset('Zend_Config'); } catch (\Throwable $e) { /* none set */ }
    }

    private function docs(): object
    {
        return $this->bare(Docs_Model_Docs::class);
    }

    // ── _nest: flat parent/child → nested, ordered by order then title ───────────────────────────

    public function testNestBuildsAnOrderedTree(): void
    {
        $flat = [
            // deliberately inserted out of order: setup(order 2) before intro(order 1)
            'setup'         => ['id' => 'setup', 'title' => 'Setup', 'header' => false, 'order' => 2.0, 'parent' => '', 'visibility' => 'public'],
            'intro'         => ['id' => 'intro', 'title' => 'Intro', 'header' => false, 'order' => 1.0, 'parent' => '', 'visibility' => 'public'],
            'setup/install' => ['id' => 'setup/install', 'title' => 'Install', 'header' => false, 'order' => 1.0, 'parent' => 'setup', 'visibility' => 'public'],
        ];
        $tree = $this->call($this->docs(), '_nest', [$flat]);

        $this->assertSame('intro', $tree[0]['id'], 'order 1 sorts before order 2');
        $this->assertSame('setup', $tree[1]['id']);
        $this->assertSame('setup/install', $tree[1]['children'][0]['id'], 'child nested under its parent');
        $this->assertSame([], $tree[0]['children']);
    }

    // ── _urlize: header → '', page → prefix/id, recursive ────────────────────────────────────────

    public function testUrlizeStampsUrls(): void
    {
        $nodes = [
            ['id' => 'intro', 'header' => false, 'children' => []],
            ['id' => 'advanced', 'header' => true, 'children' => [
                ['id' => 'advanced/x', 'header' => false, 'children' => []],
            ]],
        ];
        $out = $this->call($this->docs(), '_urlize', [$nodes, '/docs']);

        $this->assertSame('/docs/intro', $out[0]['url']);
        $this->assertSame('', $out[1]['url'], 'a header node carries no url');
        $this->assertSame('/docs/advanced/x', $out[1]['children'][0]['url']);
    }

    // ── _filterVis: keep matching pages; keep a header only if it has a matching descendant ──────

    public function testFilterVisKeepsOnlyTheSurfacesDocs(): void
    {
        $nodes = [
            ['id' => 'pub', 'header' => false, 'visibility' => 'public', 'children' => []],
            ['id' => 'adm', 'header' => false, 'visibility' => 'admin', 'children' => []],
            ['id' => 'sec', 'header' => true, 'children' => [
                ['id' => 'sec/a', 'header' => false, 'visibility' => 'admin', 'children' => []],
            ]],
        ];
        $d = $this->docs();

        $public = array_column($this->call($d, '_filterVis', [$nodes, 'public']), 'id');
        $this->assertSame(['pub'], $public, 'admin page and the admin-only header are dropped on the public surface');

        $admin = array_column($this->call($d, '_filterVis', [$nodes, 'admin']), 'id');
        $this->assertSame(['adm', 'sec'], $admin, 'the header survives because it has an admin descendant');
    }

    // ── _walkUrls: collect page urls, skip headers (url === '') — by-reference accumulator ───────

    public function testWalkUrlsCollectsPageUrlsOnly(): void
    {
        $nodes = [
            ['url' => '/docs/a', 'children' => []],
            ['url' => '', 'children' => [['url' => '/docs/b', 'children' => []]]],
        ];
        $locs = [];
        $args = [$nodes, &$locs];
        (new ReflectionMethod($this->docs(), '_walkUrls'))->invokeArgs($this->docs(), $args);
        $this->assertSame(['/docs/a', '/docs/b'], $locs);
    }

    // ── _pageNav: stable, collision-deduped heading ids (the mutation headline) ──────────────────

    public function testPageNavDedupesCollidingHeadingIds(): void
    {
        $html    = '<h2>Setup</h2><h3>Setup</h3><h2>Setup</h2>';
        $args    = [&$html];
        $outline = (new ReflectionMethod($this->docs(), '_pageNav'))->invokeArgs($this->docs(), $args);

        $ids = array_column($outline, 'id');
        $this->assertSame(['setup', 'setup-2', 'setup-3'], $ids, 'repeated headings get -2, -3 so anchors stay unique');
        $this->assertStringContainsString('id="setup"', $html);
        $this->assertStringContainsString('id="setup-2"', $html);
        $this->assertStringContainsString('id="setup-3"', $html);
    }

    public function testPageNavKeepsAnExistingId(): void
    {
        $html    = '<h2 id="custom">Overview</h2>';
        $args    = [&$html];
        $outline = (new ReflectionMethod($this->docs(), '_pageNav'))->invokeArgs($this->docs(), $args);

        $this->assertSame('overview', $outline[0]['id'], 'the outline still slugs the text');
        $this->assertStringContainsString('id="custom"', $html, 'but an author-supplied id is not overwritten');
        $this->assertStringNotContainsString('id="overview"', $html);
    }

    // ── externalLinks: http(s) only; clearing a url drops a shipped default ──────────────────────

    public function testExternalLinksShipsTheTigerZfDefault(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['docs' => []], true));
        $links = $this->docs()->externalLinks();

        $this->assertCount(1, $links);
        $this->assertSame('tigerzf', $links[0]['key']);
        $this->assertTrue($links[0]['external']);
        $this->assertStringStartsWith('https://', $links[0]['url']);
    }

    public function testClearingAUrlDropsAShippedDefault(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['docs' => ['external' => ['tigerzf' => ['url' => '']]]], true));
        $this->assertSame([], $this->docs()->externalLinks(), 'blanking the url turns a shipped default off');
    }

    public function testExternalLinksAddsHttpsEntriesAndRefusesOthers(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['docs' => ['external' => [
            'mine' => ['title' => 'My Docs', 'url' => 'https://example.com/docs'],
            'bad'  => ['title' => 'Bad',     'url' => 'ftp://example.com/docs'],
            'noti' => ['url' => 'https://example.com/untitled'],   // no title → dropped
        ]]], true));

        $keys = array_column($this->docs()->externalLinks(), 'key');
        $this->assertContains('tigerzf', $keys);
        $this->assertContains('mine', $keys);
        $this->assertNotContains('bad', $keys, 'non-http(s) is refused');
        $this->assertNotContains('noti', $keys, 'an entry with no title is refused');
    }

    // ── _build: a collection is listed on a surface only when it has something there ────────────

    /**
     * TIGER-497: a module shipping only admin help (no _index) was listed on the PUBLIC docs site as an
     * empty section showing "No pages yet — drop a .md file…". The public surface must not list it; the
     * admin surface still does, and a collection with a public _index or a public page still shows.
     */
    public function testBuildListsACollectionOnlyOnSurfacesItHasContentFor(): void
    {
        $root = sys_get_temp_dir() . '/tdocs-' . bin2hex(random_bytes(4));
        $mk = function (string $dir, array $files) {
            mkdir($dir, 0777, true);
            foreach ($files as $name => $body) { file_put_contents("$dir/$name", $body); }
        };
        $mk("$root/shop", ['overview.md' => "<!-- tiger:doc\ntitle: Overview\nvisibility: admin\n-->\n# Shop help"]);
        $mk("$root/pay",  ['overview.md' => "<!-- tiger:doc\ntitle: Overview\nvisibility: public\n-->\n# Pay"]);
        $mk("$root/land", ['_index.md' => "<!-- tiger:doc\ntitle: Landing\n-->\n# Landing only"]);

        $docs = $this->docs();
        $prop = new \ReflectionProperty(Docs_Model_Docs::class, '_sources');
        $was  = $prop->getValue();
        $desc = fn($slug) => ['slug' => $slug, 'dirs' => ["$root/$slug"], 'defaultVis' => 'public', 'source' => 'module'];
        $prop->setValue(null, ['en' => ['shop' => $desc('shop'), 'pay' => $desc('pay'), 'land' => $desc('land')]]);

        try {
            $built = $this->call($docs, '_build', ['en']);
            $vis   = [];
            foreach ($built['collections'] as $c) { $vis[$c['slug']] = array_keys(array_filter($c['vis'])); }

            $this->assertNotContains('public', $vis['shop'], 'admin-only help is NOT listed on the public site');
            $this->assertContains('admin', $vis['shop'], 'but it is on the admin help surface');
            $this->assertContains('public', $vis['pay'], 'a collection with a public page is public');
            $this->assertContains('public', $vis['land'], 'a collection whose _index targets public is public');
        } finally {
            $prop->setValue(null, $was);
            foreach (['shop/overview.md', 'pay/overview.md', 'land/_index.md'] as $f) { @unlink("$root/$f"); }
            foreach (['shop', 'pay', 'land', ''] as $d) { @rmdir(rtrim("$root/$d", '/')); }
        }
    }
}
