<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tiger_OpenApi_Generator;

/**
 * Every TigerDocs /api operation declares what it takes (`@apiRequest <Form>|datatable|none` or `@apiParam`
 * lines), so an MCP client sees typed tool arguments. TigerDocs is in core's default MCP scope set, and its five
 * tools were the only untyped ones a scoped token listed on the prod test site (2026-11-16).
 */
final class OperationsDeclareInputTest extends TestCase
{
    public function testEveryOperationDeclaresItsInput(): void
    {
        $gen     = new Tiger_OpenApi_Generator();
        $classes = $gen->discover([dirname(__DIR__, 2) . '/services']);
        $this->assertNotEmpty($classes, 'the module services were discovered');

        $untyped = $gen->untyped($classes);
        $this->assertSame([], $untyped, "these /api operations declare no input — add `@apiRequest <Form>|datatable|none`"
            . " or `@apiParam <type> <name> [required] <description>` lines:\n  " . implode("\n  ", $untyped));
    }
}
