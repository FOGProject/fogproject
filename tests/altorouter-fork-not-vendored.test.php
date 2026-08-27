<?php
/**
 * AltoRouter is FOG's fork, and the router depends on what forked it.
 *
 * lib/router/altorouter.class.php carries upstream's name, authorship and
 * MIT license, and a commented-out `namespace AltoRouter;` at the top. That
 * makes it look exactly like src/Db/Mysqldump.php did before 1.6.0 --
 * a hand-copy waiting for its Packagist release. It is not. Measured
 * against every upstream tag and against master, 324 of 357 code lines
 * differ, and three separate comments in this repository said otherwise
 * until the swap was actually attempted.
 *
 * The consequence of acting on the old reading is not subtle. Route builds
 * its entire table as one chained expression on ->get() / ->post() /
 * ->map(), which exists only because this fork added a __call(). Upstream
 * has no such method, so `composer require altorouter/altorouter` and a
 * delete would turn every one of those into a fatal -- and the failure
 * lands on Route::defineRoutes(), which every API request goes through.
 * There is no partial degradation: the REST API stops, whole.
 *
 * So this pins the two halves of the dependency:
 *
 *   1. the fork still provides __call() and the fluent return that makes
 *      chaining work;
 *   2. Route still relies on it, so the pin above is not guarding a
 *      requirement that quietly went away.
 *
 * If a future FOG genuinely wants the Packagist package, this test is what
 * it has to argue with, and rewriting Route's table into separate map()
 * calls is what would let it. That is the intended conversation.
 *
 * Usage: php tests/altorouter-fork-not-vendored.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$router = $root . '/packages/web/lib/router/altorouter.class.php';
$route = $root . '/packages/web/src/Router/Route.php';

foreach ([$router, $route] as $needed) {
    if (!is_readable($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

$fails = [];

/*
 * 1. The fork's own shape.
 *
 * Read with the tokenizer rather than by substring: every name below also
 * appears in this file's prose and in the class docblock, which would
 * satisfy a grep on its own.
 */
$tokens = token_get_all(file_get_contents($router));
$methods = [];
for ($i = 0, $n = count($tokens); $i < $n; $i++) {
    if (!is_array($tokens[$i]) || T_FUNCTION !== $tokens[$i][0]) {
        continue;
    }
    $j = $i + 1;
    while ($j < $n && is_array($tokens[$j]) && T_WHITESPACE === $tokens[$j][0]) {
        $j++;
    }
    if ($j < $n && is_array($tokens[$j]) && T_STRING === $tokens[$j][0]) {
        $methods[strtolower($tokens[$j][1])] = true;
    }
}

// __call is the whole fluent verb API. Without it ->get() is a fatal.
if (!isset($methods['__call'])) {
    $fails[] = 'AltoRouter has no __call(); the fluent ->get()/->post() API '
        . 'Route is built on comes from this fork and nothing else provides '
        . 'it';
}
// map() has to hand back $this or the chain breaks at the first link, which
// __call alone would not catch.
$src = file_get_contents($router);
if (!preg_match('#function\s+map\s*\(.*?\n    \}#s', $src, $m)
    || false === strpos($m[0], 'return $this')
) {
    $fails[] = 'AltoRouter::map() no longer returns $this; the chained route '
        . 'table in Route::defineRoutes() needs every link to return the '
        . 'router';
}

/*
 * 2. Route still depends on it.
 *
 * Counted, not merely detected: a single stray ->get() would pass a
 * boolean check while the table had been rewritten, and then half of this
 * test would be guarding nothing.
 */
$verbs = preg_match_all(
    '#\)\s*->\s*(get|post|put|patch|delete|head|options)\(#',
    file_get_contents($route)
);
if ($verbs < 10) {
    $fails[] = "Route uses the fluent verb API in only $verbs place(s). If "
        . 'the route table has been rewritten onto plain map() calls, the '
        . 'fork is no longer load-bearing and the swap to '
        . 'altorouter/altorouter is worth reconsidering -- delete this test '
        . 'deliberately rather than leaving it asserting nothing';
}

if (count($fails)) {
    fwrite(STDERR, 'FAIL:' . PHP_EOL);
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: AltoRouter still provides the fluent API Route uses in "
    . "$verbs place(s)\n";
exit(0);
