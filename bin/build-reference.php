<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * bin/build-reference.php — the docs reference BUILD HOOK (run at deploy / module install).
 *
 * Regenerates ALL API reference for THIS instance into `<app>/var/docs-generated/<locale>/` — a
 * gitignored, per-instance build artifact the docs engine scans as extra collections/sections.
 * Generated docs are NEVER committed (not the module repo, not the content repo); this rebuilds
 * them from source on every deploy, so they can never rot out of sync with the code.
 *
 *   - PLATFORM (`Tiger_*`)  → var/docs-generated/<locale>/reference/   (the "Reference" collection)
 *   - each app MODULE        → var/docs-generated/<locale>/<slug>/      (merges a "Reference" section
 *                                                                        into that module's own docs)
 *
 * Usage (from anywhere — no app boot; the generator is token-based):
 *   php application/modules/docs/bin/build-reference.php [--app=/path/to/app-root] [--locale=en]
 *
 * The app root is auto-derived from this file's location; `--app` overrides it. Warm the docs index
 * afterward (curl /docs, or the admin "Rebuild index") so the new pages show immediately.
 */

require __DIR__ . '/reference.php';   // defines Docs_Reference_Generator (its CLI guard won't fire)

$flags = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $a, $m)) { $flags[$m[1]] = $m[2] ?? true; }
}
$locale  = is_string($flags['locale'] ?? null) ? $flags['locale'] : 'en';
$appRoot = is_string($flags['app'] ?? null) ? rtrim($flags['app'], '/') : dirname(__DIR__, 4);
$gen     = $appRoot . '/var/docs-generated/' . $locale;

if (!is_dir($appRoot . '/application') && !is_dir($appRoot . '/vendor')) {
    fwrite(STDERR, "app root doesn't look right: {$appRoot} (pass --app=/path/to/app-root)\n");
    exit(1);
}

$genr  = new Docs_Reference_Generator();
$total = 0;

// Clean rebuild — the generated locale tree is a pure artifact; drop it wholesale first so removed
// modules/classes don't leave stale pages behind.
tiger_rrmdir($gen);
@mkdir($gen, 0775, true);

// 1) Platform reference (Tiger_*), when tiger-core is vendored in this app.
$lib = $appRoot . '/vendor/webtigers/tiger-core/library/Tiger';
if (is_dir($lib)) {
    $n = $genr->buildPlatform($lib, $gen . '/reference', ['locale' => $locale, 'title' => 'Reference']);
    printf("  %-14s → reference/  (%d classes)\n", 'platform', $n);
    $total += $n;
}

// 2) Each app module with @api classes → its own generated "Reference" section.
foreach (glob($appRoot . '/application/modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
    $slug = basename($moduleDir);
    $n = $genr->buildModule($moduleDir, ['out' => $gen . '/' . $slug, 'locale' => $locale]);
    if ($n > 0) {
        printf("  %-14s → %s/  (%d classes)\n", $slug, $slug, $n);
        $total += $n;
    } else {
        @rmdir($gen . '/' . $slug);   // no @api classes → don't leave an empty dir
    }
}

printf("done — %d reference pages → %s\n", $total, $gen);

/** Recursively remove a directory (best-effort). */
function tiger_rrmdir($dir)
{
    if (!is_dir($dir)) { return; }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $p = $dir . '/' . $f;
        is_dir($p) ? tiger_rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
