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

/**
 * The short names a file already resolves through a namespace-level import.
 *
 * Tokenised, for the same reason the reference scan below is: a regex has to
 * anchor somewhere, and the one this replaced anchored `use` at the start of
 * a line. That is correct for the unbraced `namespace X;` form every core
 * file uses, and wrong for a file declaring MORE THAN ONE namespace -- PHP
 * requires braces there, so every import inside one is indented, and the gate
 * reported properly imported classes as bare references (GH-1726).
 *
 * Simply allowing leading whitespace would have traded that false positive
 * for a false NEGATIVE, by a route worth spelling out because it is not the
 * obvious one. A trait import inside a class body is also an indented
 * `use X;` -- packages/web/src/Base/FOGPage.php:45 really does say
 * `use FOGPageRender;` bare. The gate does NOT report that line itself: a
 * reference is only counted after new/extends/implements/instanceof or
 * before `::`, and a trait import is none of those. The damage is that
 * $known suppresses EVERY occurrence of a short name in the file, so
 * binding the trait import would also silence a `new FOGPageRender()`
 * further down -- a reference the gate does detect. Verified both ways
 * against a file carrying the trait import and the `new` together: the
 * tokeniser still reports the `new`; a whitespace-tolerant regex binds
 * FOGPageRender and reports nothing.
 *
 * Only brace depth separates a trait import from a namespace import, and
 * only the tokeniser knows the depth.
 *
 * Two forms are deliberately not handled because the tree contains none of
 * either, verified by `git grep`: group imports (`use A\{B, C};`) and
 * `use function` / `use const`. Both would need adding here if one ever
 * lands; a group import would currently bind nothing and so read as a bare
 * reference, which fails in the safe direction.
 *
 * @param array $tokens token_get_all() output for the file
 *
 * @return array short names, as written
 */
function fogImportedNames(array $tokens)
{
    $names = [];
    $depth = 0;
    $classBody = [];
    $expectBody = false;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $tok = $tokens[$i];
        if (!is_array($tok)) {
            if ('{' === $tok) {
                $depth++;
                if ($expectBody) {
                    $classBody[$depth] = true;
                    $expectBody = false;
                }
            } elseif ('}' === $tok) {
                unset($classBody[$depth]);
                $depth--;
            }
            continue;
        }
        if (in_array($tok[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            // The next `{` opens a class-like body, named or anonymous.
            $expectBody = true;
            continue;
        }
        if (T_USE !== $tok[0]) {
            continue;
        }
        // Inside a class-like body this is a TRAIT import. It binds nothing
        // for `new X` and must not mark X as resolved -- see the docblock.
        if (isset($classBody[$depth])) {
            continue;
        }
        // A closure's `use (...)` binds variables, not names.
        $j = $i + 1;
        while ($j < $count
            && is_array($tokens[$j])
            && in_array(
                $tokens[$j][0],
                [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                true
            )
        ) {
            $j++;
        }
        if (isset($tokens[$j]) && !is_array($tokens[$j]) && '(' === $tokens[$j]) {
            continue;
        }
        // Everything up to the terminating `;`, as written.
        $clause = '';
        for (; $j < $count; $j++) {
            if (!is_array($tokens[$j])) {
                if (';' === $tokens[$j]) {
                    break;
                }
                $clause .= $tokens[$j];
                continue;
            }
            if (in_array(
                $tokens[$j][0],
                [T_COMMENT, T_DOC_COMMENT],
                true
            )) {
                continue;
            }
            $clause .= $tokens[$j][1];
        }
        // `use A\B, C\D;` is one statement binding two names.
        foreach (explode(',', $clause) as $one) {
            $one = trim($one);
            if ('' === $one) {
                continue;
            }
            $bind = false !== stripos($one, ' as ')
                ? trim(preg_split('/\s+as\s+/i', $one)[1])
                : substr(strrchr('\\' . $one, '\\'), 1);
            if ('' !== $bind) {
                $names[] = $bind;
            }
        }
        $i = $j;
    }

    return $names;
}
/*
 * Self-check on fogImportedNames(), because nothing in the tree currently
 * exercises it. Every tracked file today puts its imports at column 0, so a
 * regression that stopped seeing indented ones -- the GH-1726 bug -- would
 * scan all 389 files and report a clean pass. The same reasoning as the
 * daemon-count assertion above: a scanner that has quietly stopped scanning
 * must fail, not succeed.
 */
$selfSrc = <<<'PROBE'
<?php
namespace Probe {
    use FOG\Items\Host;
    use FOG\Items\Image as Picture;
    class Thing
    {
        use SomeTrait;
        public function go(array $rows)
        {
            return array_map(function ($r) use ($rows) {
                return $r;
            }, $rows);
        }
    }
}
PROBE;
$selfGot = fogImportedNames(token_get_all($selfSrc));
sort($selfGot);
$selfWant = ['Host', 'Picture'];
if ($selfGot !== $selfWant) {
    fwrite(
        STDERR,
        "FAIL: fogImportedNames() self-check.\n"
        . '  expected: ' . implode(', ', $selfWant) . "\n"
        . '  got:      ' . (count($selfGot) ? implode(', ', $selfGot) : '(none)')
        . "\n\n"
        . "  Host      indented namespace import, must be bound (GH-1726).\n"
        . "  Picture   aliased import binds the ALIAS, not the class.\n"
        . "  SomeTrait trait import in a class body must NOT be bound -- it\n"
        . "            would suppress every other use of that short name.\n"
        . "  \$rows     a closure's use() binds variables, not names.\n"
    );
    exit(1);
}

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

    $tokens = token_get_all($src);

    // Names the file already resolves: its imports and its own declarations.
    $known = [];
    foreach (fogImportedNames($tokens) as $bind) {
        $known[strtolower($bind)] = true;
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
