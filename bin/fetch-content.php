<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * bin/fetch-content.php — pull the docs content from the remote source into this server's mirror.
 *
 * The same work the `docs.content_fetch` scheduled job and the admin "Fetch content" button do,
 * as a deploy/firstboot hook: a node that has just come up should serve the current manual on its
 * first request rather than on its first cron tick.
 *
 * Usage (needs an app boot — the source URL lives in the config cascade):
 *   php application/modules/docs/bin/fetch-content.php [--app=/path/to/app-root] [--force]
 *
 * Exit status: 0 on success (including "nothing configured", which is not a failure), 1 on a
 * fetch error — so a deploy step can gate on it.
 */

$flags = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $a, $m)) { $flags[$m[1]] = $m[2] ?? true; }
}
$appRoot = is_string($flags['app'] ?? null) ? rtrim($flags['app'], '/') : dirname(__DIR__, 4);

if (!is_file($appRoot . '/vendor/autoload.php')) {
    fwrite(STDERR, "app root doesn't look right: {$appRoot} (pass --app=/path/to/app-root)\n");
    exit(1);
}

require $appRoot . '/vendor/autoload.php';
(new Tiger_Application($appRoot))->boot();

$remote = new Docs_Model_Remote();
if (!$remote->isEnabled()) {
    echo "docs.content_url is not set — no remote content source on this install.\n";
    exit(0);
}

echo 'fetching ', $remote->baseUrl(), " → ", $remote->dir(), "\n";

$res = $remote->sync(!empty($flags['force']));

if ($res['error'] !== '') {
    fwrite(STDERR, 'FAILED: ' . $res['error'] . "\n");
    exit($res['busy'] ? 0 : 1);   // another fetch holding the lock is not this run's failure
}

printf("  %d files (%s), %d updated, %d removed%s\n",
    $res['files'],
    $res['bytes'] > 1048576 ? round($res['bytes'] / 1048576, 1) . ' MB' : round($res['bytes'] / 1024) . ' KB',
    $res['changed'],
    $res['removed'],
    $res['generated'] !== '' ? ', published ' . $res['generated'] : ''
);

// Warm this server's index so the first request doesn't pay for the scan.
try {
    (new Docs_Model_Docs())->rebuildIndex(defined('LANG') ? LANG : 'en');
    echo "  index rebuilt\n";
} catch (Throwable $e) {
    fwrite(STDERR, '  index rebuild skipped: ' . $e->getMessage() . "\n");
}

exit(0);
