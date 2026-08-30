<?php
/**
 * No file outside src/ names a core class by its bare short name.
 *
 * Core is PSR-4 under packages/web/src/ and, since ADR 0013 §2 was amended,
 * no longer ends each file with class_alias(__NAMESPACE__ . '\X', 'X'). PHP
 * falls back to the global namespace for functions and constants but NEVER
 * for class names, so a bare `Route::` or `extends Hook` anywhere else is a
 * class-not-found at the moment that line runs.
 *
 * WHY THIS SCANS THE WHOLE TREE RATHER THAN A LIST OF DIRECTORIES.
 * bin/import-core-classes.php carries a hand-maintained $targets list, and
 * that list is what failed: the first sweep covered lib/, commons/, service/,
 * api/, management/ and packages/service, and silently missed status/ (17
 * files) and maintenance/ (3). Those are live endpoints -- the storage-node
 * and fog-client surface: bandwidth, getfiles, gethash, hostgetkey, newtoken,
 * create_update_node -- and every one of them would have fataled on the first
 * request after the aliases went. Nothing in the suite drives them, which is
 * exactly why they were easy to forget.
 *
 * So the input here is `git ls-files`, minus src/ and vendor/. A directory
 * added tomorrow is covered without anyone remembering to add it.
 *
 * TOKENISED, NEVER REGEX. A class name in a docblock, in a string or after
 * `->` is not a class reference, and grepping for `Route` finds all three.
 *
 * What is deliberately NOT reported:
 *
 *  - a name the file imports, declares itself, or already qualifies;
 *  - a name reached through a namespace the file's own `namespace` resolves
 *    -- one of the 46 flat lib/ classes referring to another;
 *  - a class name inside a STRING. Those are real and they matter, but they
 *    resolve through FOGBase::qualify() rather than through `use`, and
 *    tests/getclass-resolves-without-aliases.test.php is where that lives.
 *
 * Usage: php tests/no-bare-core-references.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$web = $repo . '/packages/web';

/** Core under src/: lowercased short name => FQCN. PSR-4, so the path IS the name. */
$core = [];
$walk = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web . '/src')
);
foreach ($walk as $file) {
    if ($file->isFile() && 'php' === $file->getExtension()) {
        $short = $file->getBasename('.php');
        $core[strtolower($short)] = 'FOG\\'
            . basename(dirname($file->getPathname())) . '\\' . $short;
    }
}
if (count($core) < 150) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($core) . " classes found under packages/web/src;"
        . " the scan root looks wrong, so this test would pass by measuring"
        . " nothing.\n"
    );
    exit(1);
}

// `git ls-files` unfiltered, then PHP source picked out by extension OR by a
// php shebang. NOT `git ls-files "*.php"`, which is what this test shipped
// with and what let the whole of packages/service through: the ten daemon
// entry points are named for their systemd unit and carry no extension at
// all (packages/service/FOGImageSize/FOGImageSize), because that name is what
// installInitScript() writes into ExecStart. The glob excluded every one of
// them, so all ten kept a bare `FOGCore::` -- a class-not-found the moment
// the forked child reached its first loop iteration -- while this test, and
// bin/import-core-classes.php, both reported the tree clean.
$tracked = [];
exec('cd ' . escapeshellarg($repo) . ' && git ls-files 2>/dev/null', $tracked);
$files = [];
foreach ($tracked as $rel) {
    $path = $repo . '/' . $rel;
    if (!is_file($path)) {
        continue;
    }
    if ('php' === strtolower(pathinfo($rel, PATHINFO_EXTENSION))) {
        $files[] = $rel;
        continue;
    }
    if ('' !== pathinfo($rel, PATHINFO_EXTENSION)) {
        continue;
    }
    $head = (string)file_get_contents($path, false, null, 0, 64);
    if (preg_match('{^#![^\n]*\bphp\b}', $head)) {
        $files[] = $rel;
    }
}
if (count($files) < 200) {
    fwrite(
        STDERR,
        'FAIL: git ls-files returned ' . count($files) . " PHP files; expected"
        . " the whole tree. Not run from a checkout?\n"
    );
    exit(1);
}
// The ten daemon entry points are the reason the selection above is not a
// glob. Assert they are actually in the scan set, so a future change back to
// `git ls-files "*.php"` fails here instead of silently measuring nothing.
$daemons = preg_grep('{^packages/service/FOG[A-Za-z]+/FOG[A-Za-z]+$}', $files);
if (count($daemons) < 10) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($daemons) . " daemon entry points in the scan"
        . " set; expected 10. The extension-less files are being skipped"
        . " again -- that is the bug this test exists to catch.\n"
    );
    exit(1);
}

$skipTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
$hits = [];
$scanned = 0;

foreach ($files as $rel) {
    // src/ is core itself, and vendor/ is not ours to edit.
    if (0 === strpos($rel, 'packages/web/src/')
        || false !== strpos($rel, '/vendor/')
    ) {
        continue;
    }
    $path = $repo . '/' . $rel;
    if (!is_readable($path)) {
        continue;
    }
    $src = file_get_contents($path);
    $scanned++;

    $ns = preg_match('/^namespace\s+([^;]+);/m', $src, $m) ? trim($m[1]) : '';
    // Names the file already resolves: its imports and its own declarations.
    $known = [];
    if (preg_match_all('/^use\s+([^;]+);$/m', $src, $um)) {
        foreach ($um[1] as $use) {
            $use = trim($use);
            $bind = false !== stripos($use, ' as ')
                ? trim(preg_split('/\s+as\s+/i', $use)[1])
                : substr(strrchr('\\' . $use, '\\'), 1);
            $known[strtolower($bind)] = true;
        }
    }
    if (preg_match_all(
        '/^\s*(?:final\s+|abstract\s+)*(?:class|interface|trait)\s+(\w+)/mi',
        $src,
        $dm
    )) {
        foreach ($dm[1] as $name) {
            $known[strtolower($name)] = true;
        }
    }

    $tokens = token_get_all($src);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || T_STRING !== $tokens[$i][0]) {
            continue;
        }
        $name = $tokens[$i][1];
        $key = strtolower($name);
        if (!isset($core[$key]) || isset($known[$key])) {
            continue;
        }
        // Keywords that tokenise as T_STRING and are not class references.
        if (in_array($key, ['self', 'parent', 'static'], true)) {
            continue;
        }
        // The previous significant token. Skipping whitespace matters: PHP
        // puts a T_WHITESPACE between `new` and the name, so $tokens[$i - 1]
        // never sees the T_NEW and a naive scanner misses every `new Foo()`.
        $prev = $i - 1;
        while ($prev >= 0
            && is_array($tokens[$prev])
            && in_array($tokens[$prev][0], $skipTokens, true)
        ) {
            $prev--;
        }
        // Already qualified (\Foo or Bar\Foo), a method name, a property, or
        // a function declaration -- none of these is an unqualified class
        // reference.
        if (isset($tokens[$prev])
            && is_array($tokens[$prev])
            && in_array(
                $tokens[$prev][0],
                [
                    T_NS_SEPARATOR, T_OBJECT_OPERATOR, T_DOUBLE_COLON,
                    T_FUNCTION, T_CONST
                ],
                true
            )
        ) {
            continue;
        }
        $next = $i + 1;
        while ($next < $count
            && is_array($tokens[$next])
            && in_array($tokens[$next][0], $skipTokens, true)
        ) {
            $next++;
        }
        $isRef = (isset($tokens[$next])
                && is_array($tokens[$next])
                && T_DOUBLE_COLON === $tokens[$next][0])
            || (isset($tokens[$prev])
                && is_array($tokens[$prev])
                && in_array(
                    $tokens[$prev][0],
                    [T_NEW, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF],
                    true
                ));
        if ($isRef) {
            $hits[] = sprintf(
                '  %s:%d  %s  ->  use %s;',
                $rel,
                $tokens[$i][2],
                $name,
                $core[$key]
            );
        }
    }
}

$hits = array_values(array_unique($hits));
if ($hits) {
    fwrite(
        STDERR,
        'FAIL: ' . count($hits) . " bare reference(s) to a core class:\n"
        . implode("\n", $hits) . "\n\n"
        . "Core is namespaced under packages/web/src/ and is not aliased into\n"
        . "the global namespace (ADR 0013 §2). Add the import shown, or run\n"
        . "  php bin/import-core-classes.php --fix\n"
    );
    exit(1);
}

printf(
    "ok  %d file(s) scanned against %d core classes, 0 bare references\n",
    $scanned,
    count($core)
);
exit(0);
