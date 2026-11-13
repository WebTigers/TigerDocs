<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use Docs_Model_Docs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TigerDocs\Tests\Support\Reflect;
use Tiger_Model_Page;

/**
 * The content engine's pure helpers — slug/path sanitizers (the traversal gate a URL slug passes
 * through), the tiger:doc front-matter parser, and the small value normalizers (visibility, order,
 * bool, humanize, title, snippet, markdown→plain). Driven by reflection on a no-constructor instance
 * (the real constructor scans content roots off disk — not wanted for pure logic).
 */
final class DocsHelpersTest extends TestCase
{
    use Reflect;

    private function docs(): object
    {
        return $this->bare(Docs_Model_Docs::class);
    }

    // ── _safeSegment / _safePath / _normalizeSlug — the traversal gate ───────────────────────────

    #[DataProvider('segments')]
    public function testSafeSegment(string $in, string $expected): void
    {
        $this->assertSame($expected, $this->call($this->docs(), '_safeSegment', [$in]));
    }

    public static function segments(): array
    {
        return [
            'plain'            => ['guide', 'guide'],
            'digits+dots'      => ['a.b_c-d', 'a.b_c-d'],
            'leading digit'    => ['1abc', '1abc'],
            'empty'            => ['', ''],
            'dot'              => ['.', ''],
            'dotdot'           => ['..', ''],
            'uppercase refused'=> ['Guide', ''],       // regex is ^[a-z0-9]
            'leading dash'     => ['-abc', ''],
            'leading dot'      => ['.hidden', ''],
            'slash inside'     => ['a/b', ''],          // a single segment cannot contain a slash
        ];
    }

    #[DataProvider('paths')]
    public function testSafePath(string $in, string $expected): void
    {
        $this->assertSame($expected, $this->call($this->docs(), '_safePath', [$in]));
    }

    public static function paths(): array
    {
        return [
            'nested'            => ['guide/overview', 'guide/overview'],
            'lowercased'        => ['Guide/Overview', 'guide/overview'],
            'trim slashes'      => ['/guide/overview/', 'guide/overview'],
            'parent refused'    => ['../etc', ''],
            'embedded parent'   => ['guide/../secret', ''],
            'double slash'      => ['a//b', ''],           // empty middle segment
            'empty'             => ['', ''],
        ];
    }

    public function testNormalizeSlugDelegatesToSafePath(): void
    {
        $d = $this->docs();
        $this->assertSame('guide/overview', $this->call($d, '_normalizeSlug', ['Guide/Overview']));
        $this->assertSame('', $this->call($d, '_normalizeSlug', ['../escape']));
    }

    // ── value normalizers ────────────────────────────────────────────────────────────────────────

    #[DataProvider('visibilities')]
    public function testVis(string $in, string $expected): void
    {
        $this->assertSame($expected, $this->call($this->docs(), '_vis', [$in]));
    }

    public static function visibilities(): array
    {
        return [
            'admin'        => ['admin', 'admin'],
            'admin cased'  => ['ADMIN', 'admin'],
            'admin padded' => ['  admin ', 'admin'],
            'public'       => ['public', 'public'],
            'empty→public' => ['', 'public'],
            'junk→public'  => ['nonsense', 'public'],
        ];
    }

    #[DataProvider('orders')]
    public function testOrder(string $in, float $expected): void
    {
        $this->assertSame($expected, $this->call($this->docs(), '_order', [$in]));
    }

    public static function orders(): array
    {
        return [
            'first'   => ['first', -1000000.0],
            'FIRST'   => ['FIRST', -1000000.0],
            'last'    => ['last', 1000000.0],
            'empty'   => ['', 1000000.0],
            'numeric' => ['5.22', 5.22],
            'zero'    => ['0', 0.0],
            'junk'    => ['soon', 1000000.0],
        ];
    }

    #[DataProvider('bools')]
    public function testBool(string $in, bool $expected): void
    {
        $this->assertSame($expected, $this->call($this->docs(), '_bool', [$in]));
    }

    public static function bools(): array
    {
        return [
            '1'    => ['1', true],
            'true' => ['true', true],
            'YES'  => ['YES', true],
            'on'   => ['on', true],
            '0'    => ['0', false],
            'no'   => ['no', false],
            'empty'=> ['', false],
        ];
    }

    public function testHumanize(): void
    {
        $d = $this->docs();
        $this->assertSame('Getting started', $this->call($d, '_humanize', ['getting-started']));
        $this->assertSame('My page', $this->call($d, '_humanize', ['my_page']));
    }

    // ── tiger:doc front-matter ───────────────────────────────────────────────────────────────────

    public function testMetaFromBodyParsesTheDocBlock(): void
    {
        $body = "<!-- tiger:doc\nparent: getting-started\norder: 20\ntitle: Your first module\nvisibility: admin\n-->\n# Heading\n";
        $meta = $this->call($this->docs(), '_metaFromBody', [$body]);
        $this->assertSame('getting-started', $meta['parent']);
        $this->assertSame('20', $meta['order']);
        $this->assertSame('Your first module', $meta['title']);
        $this->assertSame('admin', $meta['visibility']);
    }

    /**
     * TIGER-497: modules ship the one-line attribute form. Read only as `key: value` lines, its
     * visibility="admin" was dropped and admin-only help was served on the public docs site.
     */
    public function testMetaFromBodyReadsTheOneLineAttributeForm(): void
    {
        $body = "<!-- tiger:doc title=\"TigerMarketing\" area=\"modules\" visibility=\"admin\" -->\n# x";
        $meta = $this->call($this->docs(), '_metaFromBody', [$body]);

        $this->assertSame('admin', $meta['visibility'] ?? null, 'the attribute form keeps a page admin-only');
        $this->assertSame('TigerMarketing', $meta['title'] ?? null);

        $mixed = "<!-- tiger:doc\ntitle: Lines win\n-->";
        $this->assertSame('Lines win', $this->call($this->docs(), '_metaFromBody', [$mixed])['title']);
    }

    public function testMetaFromBodyEmptyWhenNoBlock(): void
    {
        $this->assertSame([], $this->call($this->docs(), '_metaFromBody', ["# Just a heading\n\nBody text."]));
    }

    public function testStripMetaRemovesTheLeadingBlock(): void
    {
        $body    = "<!-- tiger:doc\ntitle: X\n-->\n# Heading\n\nBody.";
        $stripped = $this->call($this->docs(), '_stripMeta', [$body]);
        $this->assertStringStartsWith('# Heading', $stripped);
        $this->assertStringNotContainsString('tiger:doc', $stripped);
    }

    // ── _fileTitle ───────────────────────────────────────────────────────────────────────────────

    public function testFileTitleFromMarkdownH1(): void
    {
        $title = $this->call($this->docs(), '_fileTitle', ["# Real Title\n\nbody", Tiger_Model_Page::FORMAT_MARKDOWN, 'the-slug']);
        $this->assertSame('Real Title', $title);
    }

    public function testFileTitleFallsBackToHumanizedSlug(): void
    {
        $title = $this->call($this->docs(), '_fileTitle', ['<p>no h1 here</p>', Tiger_Model_Page::FORMAT_HTML, 'getting-started']);
        $this->assertSame('Getting started', $title);
    }

    // ── search internals: _allWords / _snippet ───────────────────────────────────────────────────

    public function testAllWords(): void
    {
        $d = $this->docs();
        $this->assertTrue($this->call($d, '_allWords', ['the quick brown fox', ['quick', 'fox']]));
        $this->assertFalse($this->call($d, '_allWords', ['the quick brown fox', ['quick', 'cat']]));
        $this->assertFalse($this->call($d, '_allWords', ['anything', []]), 'no words → never "all words"');
    }

    public function testSnippetReturnsShortTextWhole(): void
    {
        $snip = $this->call($this->docs(), '_snippet', ['The quick brown fox', ['quick'], 'quick']);
        $this->assertSame('The quick brown fox', $snip);
    }

    public function testSnippetWindowsAndEllipsizesALongMatch(): void
    {
        $text = str_repeat('alpha ', 80) . 'needle ' . str_repeat('omega ', 80);
        $snip = $this->call($this->docs(), '_snippet', [$text, ['needle'], 'needle']);
        $this->assertStringContainsString('needle', $snip);
        $this->assertStringStartsWith('…', $snip, 'a match deep in the text gets a leading ellipsis');
        $this->assertStringEndsWith('…', $snip, 'and a trailing one');
    }

    // ── _plain: markdown/html → plain text for the search index ──────────────────────────────────

    public function testPlainStripsMarkdown(): void
    {
        $md  = "# Heading\n\nSome `code` and [link](http://x) and **bold**.";
        $out = $this->call($this->docs(), '_plain', [$md, Tiger_Model_Page::FORMAT_MARKDOWN]);
        $this->assertSame('Heading Some and link and bold.', $out);
    }

    public function testPlainStripsHtmlTagsAndComments(): void
    {
        $out = $this->call($this->docs(), '_plain', ['<!-- secret -->Visible <b>text</b>', Tiger_Model_Page::FORMAT_HTML]);
        $this->assertSame('Visible text', $out);
    }
}
