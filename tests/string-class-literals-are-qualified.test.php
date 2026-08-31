<?php
/**
 * A class named in a STRING must be spelled the way the class is declared.
 *
 *   tests/string-class-literals-are-qualified.test.php
 *
 * PHP resolves a class name held in a string from the GLOBAL namespace,
 * always. The enclosing `namespace` is not applied and `use` is not
 * consulted -- so inside `namespace FOG\Router;`, the literal in
 * `method_exists('Authorization', 'resolveApiPermission')` names
 * `\Authorization`, not `FOG\Auth\Authorization`. Since ADR 0013 §2 retired
 * the reverse aliases, nothing declares the global spelling and the
 * predicate is simply FALSE.
 *
 * That is the whole failure mode: these functions do not throw and do not
 * warn. They answer "no such class" the same way they answer a genuine
 * absence, so the caller takes its fallback branch and reports nothing.
 * Found in the wild twice while namespacing:
 *
 *   - `OpenAPI::_permission()` returned null for every operation, so the
 *     published API document described no permissions at all;
 *   - `PluginRunner::_discoverTasks()` had the same shape recorded in a
 *     comment for `is_subclass_of($class, 'PluginTask')` -- every plugin
 *     task skipped, with "Skipping, not a PluginTask" as the only trace.
 *
 * The fix is `Foo::class` (resolved at compile time, `use`-aware) or a
 * leading-backslash FQCN. Both are accepted here.
 *
 * Token-driven, not regex: the same text inside a comment or an unrelated
 * string is not a call, and one of those already exists in the tree
 * (FOGURLRequests.php recounts this exact bug in prose).
 *
 * A literal naming a class PHP itself declares -- class_exists('PDO') -- is
 * accepted: those genuinely live in the global namespace.
 *
 * Usage: php tests/string-class-literals-are-qualified.test.php
 * Exit status 0 = pass, 1 = fail.
 */

// Zero, and it stays zero. Same discipline as tests/global-class-prefix:
// the assertion is only worth anything because the two sites above made it
// fail before they were fixed.
const EXPECT_SITES = 0;

$root = dirname(__DIR__);

/*
 * Argument positions that are a class name, per function. Position 1 for
 * is_subclass_of()/is_a() is the PARENT -- the argument whose literal cost
 * PluginRunner every one of its tasks.
 */
$funcs = [
    'method_exists' => [0],
    'property_exists' => [0],
    'class_exists' => [0],
    'interface_exists' => [0],
    'trait_exists' => [0],
    'enum_exists' => [0],
    'get_class_vars' => [0],
    'get_class_methods' => [0],
    'get_parent_class' => [0],
    'class_implements' => [0],
    'class_parents' => [0],
    'is_subclass_of' => [1],
    'is_a' => [1],
];

$files = [];
foreach (['packages/web', 'packages/service'] as $sub) {
    $dir = $root . '/' . $sub;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
            continue;
        }
        $path = $f->getPathname();
        // lib/plugins is installed artifact, not repo source (ADR 0009), and
        // vendor/ is not ours to spell.
        if (strpos($path, '/lib/plugins/') !== false
            || strpos($path, '/vendor/') !== false
        ) {
            continue;
        }
        $files[] = $path;
    }
}
sort($files);

if (!$files) {
    fwrite(STDERR, "FAIL: found no PHP to scan\n");
    exit(1);
}

$bad = [];
foreach ($files as $path) {
    $tokens = token_get_all((string)file_get_contents($path));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_STRING) {
            continue;
        }
        $name = strtolower($t[1]);
        if (!isset($funcs[$name])) {
            continue;
        }
        // A method call ($x->method_exists(...), Foo::method_exists(...)) is
        // not the built-in.
        for ($p = $i - 1; $p >= 0; $p--) {
            if (is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
                continue;
            }
            break;
        }
        if ($p >= 0 && is_array($tokens[$p])
            && in_array(
                $tokens[$p][0],
                [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW],
                true
            )
        ) {
            continue;
        }
        // Find the opening paren.
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                continue;
            }
            break;
        }
        if ($j >= $count || $tokens[$j] !== '(') {
            continue;
        }
        // Walk the arguments, tracking nesting so a nested call's commas do
        // not shift the positions.
        $depth = 0;
        $arg = 0;
        $literals = [];
        for ($k = $j; $k < $count; $k++) {
            $tk = $tokens[$k];
            if ($tk === '(' || $tk === '[' || $tk === '{') {
                $depth++;
                continue;
            }
            if ($tk === ')' || $tk === ']' || $tk === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                continue;
            }
            if ($depth === 1 && $tk === ',') {
                $arg++;
                continue;
            }
            if ($depth === 1 && is_array($tk)
                && $tk[0] === T_CONSTANT_ENCAPSED_STRING
            ) {
                // Only a lone literal counts. A concatenation is a
                // constructed name and out of this test's reach.
                $literals[$arg] = [trim($tk[1], "'\""), $tk[2]];
            }
        }
        foreach ($funcs[$name] as $pos) {
            if (!isset($literals[$pos])) {
                continue;
            }
            list($lit, $line) = $literals[$pos];
            if (strpos($lit, '\\') !== false) {
                continue;
            }
            // Declared by PHP itself: this process has loaded no FOG code, so
            // anything class_exists() knows here is an internal class, and
            // those really are global.
            if (class_exists($lit, false)
                || interface_exists($lit, false)
                || trait_exists($lit, false)
            ) {
                continue;
            }
            $bad[] = sprintf(
                '%s:%d  %s(%s)',
                substr($path, strlen($root) + 1),
                $line,
                $name,
                "'" . $lit . "'"
            );
        }
    }
}

if (count($bad) !== EXPECT_SITES) {
    fwrite(
        STDERR,
        sprintf(
            "FAIL: %d unqualified class literal(s), expected %d\n",
            count($bad),
            EXPECT_SITES
        )
    );
    foreach ($bad as $b) {
        fwrite(STDERR, "  - $b\n");
    }
    fwrite(
        STDERR,
        "Use Foo::class, or a leading-backslash FQCN in the string.\n"
    );
    exit(1);
}

fwrite(
    STDOUT,
    sprintf("PASS (%d files scanned, %d sites)\n", count($files), count($bad))
);
exit(0);
