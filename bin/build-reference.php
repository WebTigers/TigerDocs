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

if (!is_dir($appRoot . '/application') && !is_dir($appRoot . '/vendor')) {
    fwrite(STDERR, "app root doesn't look right: {$appRoot} (pass --app=/path/to/app-root)\n");
    exit(1);
}

$res = (new Docs_Reference_Generator())->buildAll($appRoot, $locale);

foreach ($res['targets'] as $name => $count) {
    printf("  %-14s → %s/  (%d classes)\n", $name, $name === 'platform' ? 'reference' : $name, $count);
}
printf("done — %d reference pages → %s\n", $res['total'], $res['dir']);
