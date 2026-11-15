<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * TIGER-511 convention guard: every controller that resolves a doc names its org explicitly via
 * `_docOrgId()` (the site org on the public surface, the caller's org in admin) — never a literal ''.
 *
 * A bare '' is NOT caught by behaviour alone: core's page cascade currently maps '' to the site org, so a
 * regression back to '' would pass every resolve test while silently handing the tenant choice back to
 * whatever core guesses. The integration suite (DocOrgOverrideTest) pins what `_docOrgId()` returns.
 */
final class DocOrgCallSiteTest extends TestCase
{
    public function testEveryControllerResolveCallPassesTheExplicitOrg(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/controllers/*.php') ?: [];
        $this->assertNotEmpty($files);

        $calls = 0;
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            if (!preg_match_all('/->resolve\(([^;]*)\)/', $src, $m)) { continue; }
            foreach ($m[1] as $args) {
                $calls++;
                $parts = array_map('trim', explode(',', $args));
                $this->assertSame('$this->_docOrgId()', $parts[2] ?? '',
                    basename($file) . ': resolve() must pass $this->_docOrgId() as the org, got: ' . ($parts[2] ?? '(none)'));
            }
        }
        $this->assertGreaterThanOrEqual(2, $calls, 'expected the public + admin resolve() call sites');
    }
}
