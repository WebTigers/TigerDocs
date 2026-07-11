<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Reference_Generator — generate API reference docs from PHP source.
 *
 * TOKEN-BASED (no autoload, no app boot), so the AI agent — or a dev building a module — can run it
 * against ANY source tree and it writes reference pages straight into that module's docs/ folder,
 * ready to ship with the module. It reads the STANDARDIZED docblocks (AGENTS.md → "Docblocks — the
 * reference contract") plus each method signature, and emits one tiger:doc-annotated markdown page
 * per `@api` class. The normal docs scan/cache/nav/search then picks them up — reference is just docs.
 *
 * CLI:
 *   php reference.php <module-dir> [--out=DIR] [--locale=en] [--section=Reference] [--order=900]
 *   php reference.php --platform=<library-dir> --out=DIR [--locale=en]
 *
 * Generated files carry `generated: reference` in their tiger:doc block; a rebuild removes the old
 * generated set first, so it's idempotent and never clobbers hand-written pages.
 *
 * @internal — a dev/agent tool, not runtime.
 */
class Docs_Reference_Generator
{
    /** tiger:doc `generated:` value that marks (and lets us safely re-remove) our own files. */
    const MARK = 'reference';

    /** Generate a module's reference into its docs/<locale>/ folder (one "Reference" section). */
    public function buildModule($moduleDir, array $opts = [])
    {
        $locale  = $opts['locale']  ?? 'en';
        $outDir  = $opts['out']     ?? ($moduleDir . '/docs/' . $locale);
        $section = $opts['section'] ?? 'Reference';
        $order   = (string) ($opts['order'] ?? '900');

        $classes = [];
        foreach (['services', 'models', 'forms', 'library'] as $sub) {
            foreach ($this->_phpFiles($moduleDir . '/' . $sub) as $file) {
                $info = $this->_parseFile($file);
                if ($info && $this->_included($info)) {
                    $classes[$info['name']] = $info;
                }
            }
        }
        ksort($classes);

        if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
        $this->_cleanGenerated($outDir);
        if (!$classes) { return 0; }

        // The section header (generated), then a page per class, ordered alphabetically.
        $this->_write($outDir, 'reference.md', $this->_block([
            'header' => 'true', 'order' => $order, 'title' => $section, 'generated' => self::MARK,
        ]) . "# {$section}\n\nGenerated API reference for this module's `@api` classes.\n");

        $o = 10;
        foreach ($classes as $name => $info) {
            $this->_write($outDir, $this->_slug($name) . '.md', $this->_renderClass($info, 'reference', $o));
            $o += 10;
        }

        // NB: no _index is emitted. Generated output stays pure (only the section header + class
        // pages) so it can MERGE into a module's hand-written collection — the collection's label
        // comes from the module's own _index, not from generated files.
        return count($classes);
    }

    /** Generate a platform (Tiger_*) reference, sectioned by namespace prefix, into $outDir. */
    public function buildPlatform($libDir, $outDir, array $opts = [])
    {
        // Group by the 2nd underscore segment (Tiger_Model_* → "Model", single-segment → "Core").
        $groups = [];
        foreach ($this->_phpFiles($libDir) as $file) {
            $info = $this->_parseFile($file);
            if (!$info || !$this->_included($info) || strpos($info['name'], 'Tiger_') !== 0) {
                continue;
            }
            $parts = explode('_', $info['name']);
            $group = count($parts) >= 3 ? $parts[1] : 'Core';
            $groups[$group][$info['name']] = $info;
        }
        ksort($groups);

        if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
        $this->_cleanGenerated($outDir);

        // A generated _index so the `reference` collection has a landing even with no hand-written
        // one; a hand-written content/_index (higher priority) transparently wins when present.
        $this->_write($outDir, '_index.md', $this->_block([
            'title' => $opts['title'] ?? 'Reference', 'order' => (string) ($opts['order'] ?? '80'),
            'generated' => self::MARK,
        ]) . "# " . ($opts['title'] ?? 'Reference') . "\n\nAPI reference, generated from source docblocks.\n");

        $count = 0; $sectionOrder = 10;
        foreach ($groups as $group => $classes) {
            ksort($classes);
            $sid = $this->_slug($group);   // section header id
            $this->_write($outDir, $sid . '.md', $this->_block([
                'header' => 'true', 'order' => (string) $sectionOrder, 'title' => $group, 'generated' => self::MARK,
            ]) . "# {$group}\n");
            $o = 10;
            foreach ($classes as $name => $info) {
                $this->_write($outDir, $this->_slug($name) . '.md', $this->_renderClass($info, $sid, $o));
                $o += 10; $count++;
            }
            $sectionOrder += 10;
        }
        return $count;
    }

    /**
     * Rebuild ALL reference for an app instance into <appRoot>/var/docs-generated/<locale>/ — the
     * platform (Tiger_*) `reference` collection plus a Reference section for every app module. This
     * is the whole build hook; the CLI (bin/build-reference.php) and the admin button both call it.
     * Returns ['locale','dir','targets'=>[name=>count],'total'].
     */
    public function buildAll($appRoot, $locale = 'en')
    {
        $appRoot = rtrim((string) $appRoot, '/');
        $gen     = $appRoot . '/var/docs-generated/' . $locale;

        $this->_rrmdir($gen);                 // clean rebuild — pure artifact, no stale pages
        @mkdir($gen, 0775, true);

        $out = ['locale' => $locale, 'dir' => $gen, 'targets' => [], 'total' => 0];

        // Platform (Tiger_*), when tiger-core is vendored in this app.
        $lib = $appRoot . '/vendor/webtigers/tiger-core/library/Tiger';
        if (is_dir($lib)) {
            $n = $this->buildPlatform($lib, $gen . '/reference', ['locale' => $locale, 'title' => 'Reference']);
            $out['targets']['platform'] = $n;
            $out['total'] += $n;
        }

        // Each app module with @api classes → its own generated Reference section.
        foreach ((glob($appRoot . '/application/modules/*', GLOB_ONLYDIR) ?: []) as $moduleDir) {
            $slug = basename($moduleDir);
            $n    = $this->buildModule($moduleDir, ['out' => $gen . '/' . $slug, 'locale' => $locale]);
            if ($n > 0) {
                $out['targets'][$slug] = $n;
                $out['total'] += $n;
            } else {
                @rmdir($gen . '/' . $slug);
            }
        }

        return $out;
    }

    /** Recursively remove a directory (best-effort). */
    protected function _rrmdir($dir)
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    // =================================================================================== parsing

    /** Only document classes explicitly marked @api (and not @internal). */
    protected function _included(array $info)
    {
        return !empty($info['doc']['api']) && empty($info['doc']['internal']);
    }

    /** Token-parse a PHP file → the (first) class's structure, or null. */
    protected function _parseFile($path)
    {
        $tk = @token_get_all((string) file_get_contents($path));
        $n  = count($tk);
        $lastDoc = null; $docFresh = false;
        $depth = 0; $bodyDepth = null; $inClass = false; $justClass = false;
        $mods = []; $info = null;

        for ($i = 0; $i < $n; $i++) {
            $t = $tk[$i];
            if (is_array($t)) {
                $id = $t[0];
                if ($id === T_DOC_COMMENT) { $lastDoc = $t[1]; $docFresh = true; continue; }
                if ($id === T_WHITESPACE || $id === T_COMMENT) { continue; }
                if (($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT) && $info === null) {
                    $hdr = '';
                    for ($j = $i; $j < $n; $j++) { $x = $tk[$j]; $s = is_array($x) ? $x[1] : $x; if ($x === '{') { break; } $hdr .= $s; }
                    if (preg_match('/(class|interface|trait)\s+(\w+)(?:\s+extends\s+([\w\\\\]+))?(?:\s+implements\s+([\w\\\\,\s]+))?/', $hdr, $m)) {
                        $info = [
                            'name'       => $m[2],
                            'kind'       => $m[1],
                            'extends'    => $m[3] ?? null,
                            'implements' => isset($m[4]) ? array_values(array_filter(array_map('trim', explode(',', $m[4])))) : [],
                            'doc'        => $docFresh ? $this->_parseDoc($lastDoc) : [],
                            'methods'    => [],
                        ];
                        $inClass = true; $justClass = true; $bodyDepth = null;
                    }
                    $docFresh = false; $mods = [];
                    continue;
                }
                if (in_array($id, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL], true)) { $mods[] = $id; continue; }
                if ($id === T_FUNCTION) {
                    $isMethod = ($inClass && $bodyDepth !== null && $depth === $bodyDepth);
                    $vis = in_array(T_PRIVATE, $mods, true) ? 'private' : (in_array(T_PROTECTED, $mods, true) ? 'protected' : 'public');
                    if ($isMethod && $vis === 'public') {
                        $sig = ''; $pd = 0;
                        for ($j = $i; $j < $n; $j++) {
                            $x = $tk[$j]; $s = is_array($x) ? $x[1] : $x;
                            if ($x === '(') { $pd++; } elseif ($x === ')') { $pd--; }
                            if (($x === '{' && $pd === 0) || $x === ';') { break; }
                            $sig .= $s;
                        }
                        $parsed = $this->_parseSignature($sig);
                        if ($parsed && ($parsed['name'] === '__construct' || strpos($parsed['name'], '_') !== 0)) {
                            $parsed['doc'] = $docFresh ? $this->_parseDoc($lastDoc) : [];
                            $info['methods'][] = $parsed;
                        }
                    }
                    $docFresh = false; $mods = [];
                    continue;
                }
                continue;
            }
            if ($t === '{') { $depth++; if ($justClass && $bodyDepth === null) { $bodyDepth = $depth; $justClass = false; } }
            elseif ($t === '}') { $depth--; if ($bodyDepth !== null && $depth < $bodyDepth) { $inClass = false; $bodyDepth = null; } }
            elseif ($t === ';') { $docFresh = false; $mods = []; }
        }
        return $info;
    }

    /** Parse a `function …` signature text → name, params (type/name/default/variadic), return. */
    protected function _parseSignature($sig)
    {
        $sig = trim(preg_replace('/\s+/', ' ', $sig));
        if (!preg_match('/^function\s+&?\s*(\w+)\s*\((.*)\)\s*(?::\s*([?\w\\\\|]+))?\s*$/s', $sig, $m)) {
            return preg_match('/^function\s+&?\s*(\w+)/', $sig, $m2) ? ['name' => $m2[1], 'params' => [], 'return' => null] : null;
        }
        return ['name' => $m[1], 'params' => $this->_splitParams($m[2]), 'return' => isset($m[3]) ? trim($m[3]) : null];
    }

    /** Split a param-list string into [ ['type','name','default','variadic'], … ] (depth-aware). */
    protected function _splitParams($str)
    {
        $str = trim($str);
        if ($str === '') { return []; }
        $parts = []; $cur = ''; $d = 0;
        for ($i = 0, $len = strlen($str); $i < $len; $i++) {
            $c = $str[$i];
            if (strpbrk($c, '([{') !== false) { $d++; }
            elseif (strpbrk($c, ')]}') !== false) { $d--; }
            if ($c === ',' && $d === 0) { $parts[] = $cur; $cur = ''; } else { $cur .= $c; }
        }
        if (trim($cur) !== '') { $parts[] = $cur; }

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/^\s*([?\w\\\\|\[\]]+\s+)?(&\s*)?(\.\.\.)?\s*\$(\w+)\s*(?:=\s*(.+))?$/s', $p, $m)) {
                $out[] = [
                    'type'     => trim($m[1] ?? '') !== '' ? trim($m[1]) : null,
                    'variadic' => !empty($m[3]),
                    'name'     => $m[4],
                    'default'  => isset($m[5]) ? trim($m[5]) : null,
                ];
            }
        }
        return $out;
    }

    /** Parse a raw `/** … *\/` docblock into summary, description, tags. */
    protected function _parseDoc($raw)
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', (string) $raw) as $l) {
            $l = preg_replace('#^\s*/\*\*?#', '', $l);
            $l = preg_replace('#\*/\s*$#', '', $l);
            $l = preg_replace('/^\s*\*\s?/', '', $l);
            $lines[] = rtrim($l);
        }
        $d = ['summary' => '', 'desc' => '', 'params' => [], 'return' => null, 'throws' => [],
              'api' => false, 'internal' => false, 'deprecated' => null, 'since' => null, 'see' => []];
        $i = 0; $nl = count($lines);
        while ($i < $nl && trim($lines[$i]) === '') { $i++; }
        // Summary = the first paragraph (consecutive non-blank, non-tag lines joined) — a summary
        // that wraps across physical lines stays one sentence, not two split paragraphs.
        $sum = [];
        for (; $i < $nl; $i++) {
            if (trim($lines[$i]) === '' || preg_match('/^\s*@/', $lines[$i])) { break; }
            $sum[] = trim($lines[$i]);
        }
        $d['summary'] = implode(' ', $sum);
        $desc = [];
        for (; $i < $nl; $i++) { if (preg_match('/^\s*@/', $lines[$i])) { break; } $desc[] = $lines[$i]; }
        $d['desc'] = trim(implode("\n", $desc));
        for (; $i < $nl; $i++) {
            $t = trim($lines[$i]);
            if (preg_match('/^@param\s+(\S+)\s+\$(\w+)\s*(.*)$/', $t, $m))      { $d['params'][$m[2]] = ['type' => $m[1], 'desc' => trim($m[3])]; }
            elseif (preg_match('/^@param\s+\$(\w+)\s*(.*)$/', $t, $m))          { $d['params'][$m[1]] = ['type' => null, 'desc' => trim($m[2])]; }
            elseif (preg_match('/^@return\s+(\S+)\s*(.*)$/', $t, $m))           { $d['return'] = ['type' => $m[1], 'desc' => trim($m[2])]; }
            elseif (preg_match('/^@throws\s+(\S+)\s*(.*)$/', $t, $m))           { $d['throws'][] = ['class' => $m[1], 'desc' => trim($m[2])]; }
            elseif (preg_match('/^@api\b/', $t))                               { $d['api'] = true; }
            elseif (preg_match('/^@internal\b/', $t))                          { $d['internal'] = true; }
            elseif (preg_match('/^@deprecated\s*(.*)$/', $t, $m))              { $d['deprecated'] = trim($m[1]) !== '' ? trim($m[1]) : true; }
            elseif (preg_match('/^@since\s+(.*)$/', $t, $m))                   { $d['since'] = trim($m[1]); }
            elseif (preg_match('/^@see\s+(.*)$/', $t, $m))                     { $d['see'][] = trim($m[1]); }
        }
        return $d;
    }

    // ================================================================================= rendering

    protected function _renderClass(array $info, $parent, $order)
    {
        $doc = $info['doc'] ?: [];
        $md  = $this->_block(['parent' => $parent, 'order' => (string) $order, 'title' => $info['name'],
                              'visibility' => 'public', 'generated' => self::MARK]);
        $md .= "<!-- Generated by `tiger reference` from source docblocks — do not edit by hand. -->\n\n";
        $md .= "# {$info['name']}\n\n";

        $badges = ['`@api`'];
        if (!empty($doc['deprecated'])) { $badges[] = '**deprecated**' . (is_string($doc['deprecated']) ? " ({$doc['deprecated']})" : ''); }
        if (!empty($info['extends'])) { $badges[] = 'extends `' . $info['extends'] . '`'; }
        if (!empty($info['implements'])) { $badges[] = 'implements ' . implode(', ', array_map(fn($x) => "`{$x}`", $info['implements'])); }
        $md .= '> ' . implode(' · ', $badges) . "\n\n";

        if (!empty($doc['summary'])) { $md .= $doc['summary'] . "\n\n"; }
        if (!empty($doc['desc']))    { $md .= $doc['desc'] . "\n\n"; }
        if (!empty($doc['see']))     { $md .= '**See also:** ' . implode(', ', array_map(fn($x) => "`{$x}`", $doc['see'])) . "\n\n"; }

        if ($info['methods']) {
            $md .= "## Methods\n\n";
            foreach ($info['methods'] as $m) { $md .= $this->_renderMethod($m); }
        }
        return rtrim($md) . "\n";
    }

    protected function _renderMethod(array $m)
    {
        $mdoc = $m['doc'] ?: [];
        $ps = [];
        foreach ($m['params'] as $p) {
            $ps[] = ($p['type'] ? $p['type'] . ' ' : '') . ($p['variadic'] ? '...' : '') . '$' . $p['name']
                  . ($p['default'] !== null ? ' = ' . $p['default'] : '');
        }
        $sig = $m['name'] . '(' . implode(', ', $ps) . ')' . ($m['return'] ? ': ' . $m['return'] : '');
        // Heading is the method NAME only → a clean `#name` anchor + a tidy "On this page" entry;
        // the full signature goes in a code block just below (highlighted, monospace).
        $md  = "### `{$m['name']}()`\n\n";
        $md .= "```php\n{$sig}\n```\n\n";
        if (!empty($mdoc['summary'])) { $md .= $mdoc['summary'] . "\n\n"; }
        if (!empty($mdoc['desc']))    { $md .= $mdoc['desc'] . "\n\n"; }

        foreach ($m['params'] as $p) {
            $type = $p['type'] ?: ($mdoc['params'][$p['name']]['type'] ?? '');
            $desc = $mdoc['params'][$p['name']]['desc'] ?? '';
            $md  .= '- `$' . $p['name'] . '`' . ($type ? " `{$type}`" : '') . ($desc ? " — {$desc}" : '') . "\n";
        }
        if ($m['params']) { $md .= "\n"; }

        $rt = $m['return'] ?: ($mdoc['return']['type'] ?? null);
        if ($rt && $rt !== 'void') {
            $rd = $mdoc['return']['desc'] ?? '';
            $md .= "**Returns** `{$rt}`" . ($rd ? " — {$rd}" : '') . "\n\n";
        }
        foreach (($mdoc['throws'] ?? []) as $th) {
            $md .= "**Throws** `{$th['class']}`" . ($th['desc'] ? " — {$th['desc']}" : '') . "\n\n";
        }
        return $md;
    }

    // =================================================================================== writing

    protected function _block(array $kv)
    {
        $out = "<!-- tiger:doc\n";
        foreach ($kv as $k => $v) { $out .= "{$k}: {$v}\n"; }
        return $out . "-->\n\n";
    }

    protected function _write($dir, $name, $content)
    {
        file_put_contents($dir . '/' . $name, $content);
    }

    /** Remove previously generated pages (those carrying `generated: reference`) — idempotent rebuild. */
    protected function _cleanGenerated($dir)
    {
        foreach (glob($dir . '/*.md') ?: [] as $f) {
            $head = (string) file_get_contents($f, false, null, 0, 400);
            if (preg_match('/<!--\s*tiger:doc\b.*?generated:\s*' . preg_quote(self::MARK, '/') . '\b.*?-->/is', $head)) {
                @unlink($f);
            }
        }
    }

    protected function _phpFiles($dir)
    {
        if (!is_dir($dir)) { return []; }
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile() && strtolower($f->getExtension()) === 'php') { $out[] = $f->getPathname(); } }
        sort($out);
        return $out;
    }

    protected function _slug($class)
    {
        return strtolower(str_replace('_', '-', $class));
    }
}

// ------------------------------------------------------------------------------------------- CLI
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $flags = []; $pos = [];
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $a, $m)) { $flags[$m[1]] = $m[2] ?? true; } else { $pos[] = $a; }
    }
    $gen = new Docs_Reference_Generator();

    if (!empty($flags['platform'])) {
        $lib = is_string($flags['platform']) ? $flags['platform'] : ($pos[0] ?? '');
        $out = $flags['out'] ?? '';
        if (!$lib || !$out) { fwrite(STDERR, "usage: reference.php --platform=<library-dir> --out=DIR\n"); exit(1); }
        $c = $gen->buildPlatform(rtrim($lib, '/'), rtrim($out, '/'), $flags);
        echo "Generated {$c} platform reference pages → {$out}\n";
    } elseif ($pos) {
        $dir = rtrim($pos[0], '/');
        if (!is_dir($dir)) { fwrite(STDERR, "not a directory: {$dir}\n"); exit(1); }
        $c   = $gen->buildModule($dir, $flags);
        $out = $flags['out'] ?? ($dir . '/docs/' . ($flags['locale'] ?? 'en'));
        echo "Generated {$c} reference pages for " . basename($dir) . " → {$out}\n";
    } else {
        fwrite(STDERR, "usage:\n  reference.php <module-dir> [--out=DIR] [--locale=en]\n  reference.php --platform=<library-dir> --out=DIR\n");
        exit(1);
    }
}
