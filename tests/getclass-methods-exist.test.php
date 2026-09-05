<?php
/**
 * Every method called through getClass() actually exists on that class.
 *
 * `self::getClass('FooManager')->bar()` is FOG's dynamic dispatch seam, and
 * it is invisible to every tool that would normally catch a typo: the class
 * is a STRING, so no IDE resolves it, no static analyzer follows it, and PHP
 * itself says nothing until the line runs. There are over a hundred of these
 * in the tree.
 *
 * The failure that motivated this: DirectoryFacts::row() shipped calling
 * `getClass('HostDirectoryManager')->find(...)`. FOGManagerController has no
 * find() -- it is a grid controller with exists/update/distinct, not a
 * repository; reads go through Route::getIds(). The whole test suite, a
 * deploy and a review all passed, because report() is reached only when a
 * host sends a directory block and none ever had. It became a fatal the
 * first time a real poll carried one.
 *
 * Nothing here is FOG-specific beyond the seam: it is the check the language
 * would give us for free if the class name were not a string.
 *
 * Which is why the seam is closing. ADR 0043 retired the literal getClass()
 * across core and fog-plugins, so a name is spelled at the call site where
 * the language checks it for free. What is left for this test is the bundled
 * plugin artifacts under packages/web/lib/plugins on a real install, which
 * lag a plugin release behind the source -- so it finds call sites there and
 * none in packages/web/src, and finds none at all in CI, which does not
 * fetch them. Retire it once no shipped plugin build predates that sweep.
 *
 * Parsed with token_get_all rather than a regex, so an argument list that
 * contains parentheses -- getClass('Host', self::something($x)) -- is matched
 * to its real closing paren instead of the first one.
 *
 * Usage: php tests/getclass-methods-exist.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('getclass-methods-exist');

$t = new FogChecks();

$root = dirname(__DIR__) . '/packages/web';
$roots = [$root . '/src', $root . '/lib/plugins'];

/**
 * Every `getClass('Name')->method` pair in one file.
 *
 * @param string $file the PHP file
 *
 * @return array list of [class, method, line]
 */
function getClassCalls($file)
{
    $tokens = token_get_all((string)file_get_contents($file));
    $out = [];
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];
        if (!is_array($tok) || T_STRING !== $tok[0] || 'getClass' !== $tok[1]) {
            continue;
        }
        // The '(' immediately after the name.
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && T_WHITESPACE === $tokens[$j][0]) {
            $j++;
        }
        if ($j >= $n || '(' !== $tokens[$j]) {
            continue;
        }
        // The first argument has to be a literal, or we cannot know the class.
        $k = $j + 1;
        while ($k < $n && is_array($tokens[$k]) && T_WHITESPACE === $tokens[$k][0]) {
            $k++;
        }
        if ($k >= $n
            || !is_array($tokens[$k])
            || T_CONSTANT_ENCAPSED_STRING !== $tokens[$k][0]
        ) {
            continue;
        }
        $class = trim($tokens[$k][1], "'\"");
        $line = $tokens[$k][2];

        // Walk to the matching close paren, counting depth, so an argument
        // list holding its own calls does not end the match early.
        $depth = 1;
        $p = $j + 1;
        while ($p < $n && $depth > 0) {
            if ('(' === $tokens[$p]) {
                $depth++;
            } elseif (')' === $tokens[$p]) {
                $depth--;
            }
            $p++;
        }
        // Then `->` and a name, or this is not a chained call at all.
        while ($p < $n && is_array($tokens[$p]) && T_WHITESPACE === $tokens[$p][0]) {
            $p++;
        }
        if ($p >= $n
            || !is_array($tokens[$p])
            || T_OBJECT_OPERATOR !== $tokens[$p][0]
        ) {
            continue;
        }
        $p++;
        while ($p < $n && is_array($tokens[$p]) && T_WHITESPACE === $tokens[$p][0]) {
            $p++;
        }
        if ($p >= $n || !is_array($tokens[$p]) || T_STRING !== $tokens[$p][0]) {
            continue;
        }
        $out[] = [$class, $tokens[$p][1], $line];
    }
    return $out;
}

$files = [];
foreach ($roots as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && 'php' === strtolower($f->getExtension())) {
            $files[] = $f->getPathname();
        }
    }
}
sort($files);

$checked = 0;
$missing = [];
$unresolved = [];

foreach ($files as $file) {
    foreach (getClassCalls($file) as [$class, $method, $line]) {
        $rel = str_replace(dirname(__DIR__) . '/', '', $file);
        // qualify() is the same lookup getClass() itself performs, so this
        // cannot accept a name the application would reject.
        $fqcn = \FOG\Base\FOGBase::qualify($class);
        if (!class_exists($fqcn)) {
            // Recorded, not ignored: a name that resolves to nothing is not
            // proof of anything, and an audit that quietly drops what it
            // could not read is worse than no audit.
            $unresolved[] = "$rel:$line  $class";
            continue;
        }
        $checked++;
        if (!method_exists($fqcn, $method)
            && !method_exists($fqcn, '__call')
        ) {
            $missing[] = "$rel:$line  $class->$method()";
        }
    }
}

if (count($missing) > 0) {
    foreach ($missing as $m) {
        fwrite(STDERR, "  $m\n");
    }
}
$t->check(
    'every method called through getClass() exists on the class named'
        . ' -- the seam no IDE, linter or type checker can follow, because'
        . ' the class is a string. ' . $checked . ' call(s) checked, '
        . count($missing) . ' missing',
    0 === count($missing)
);

// The audit says how much of itself it could not do. Without this the check
// above passes just as cheerfully when every class stops resolving.
fwrite(
    STDERR,
    sprintf(
        "  %d call site(s) checked across %d file(s); %d class name(s) did"
        . " not resolve\n",
        $checked,
        count($files),
        count($unresolved)
    )
);
foreach ($unresolved as $u) {
    fwrite(STDERR, "    unresolved: $u\n");
}

/*
 * Anchored on FILES rather than on call sites, and that is the whole point
 * of ADR 0043: the literals this test inspects are being eliminated, so
 * counting them would turn every success into a step toward a failure. A
 * source checkout now has none in packages/web/src at all, and CI never runs
 * bin/fetch-plugins.sh, so packages/web/lib/plugins -- where the remaining
 * ones live, in bundled artifacts built before the sweep -- is absent there
 * and $checked is legitimately 0.
 *
 * What still has to hold is that the scan READ something. A bad root, a
 * broken tokenizer loop or a filter that matches nothing all show up here,
 * and all of them would otherwise let the check above pass for the wrong
 * reason.
 */
$t->check(
    'and the scan actually reached the tree -- a scan that reads nothing'
        . ' passes the check above for the wrong reason',
    count($files) > 50
);

$t->finish();
