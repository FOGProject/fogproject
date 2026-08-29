<?php
/**
 * A field the emitter strips must not be filterable or searchable.
 *
 * Route knows what to strip from a payload (sensitiveFieldMap) and it knows
 * what a request may filter and search on. Those were three independent
 * decisions written in three places, and nothing kept them in agreement:
 * every sensitive field was filterable by name on every list route, and
 * '*'/'+' expand to '%' at the request-facing entry points, so the filter
 * was a PREFIX match. Match/no-match then recovers a value the response
 * never contains, one character at a time.
 *
 * That matters because of what the columns hold. user.token is a plaintext
 * 128-character hex string and _testAuth() matches it exactly; host.sec_tok
 * is plaintext and authenticates the client protocol; the ldap plugin's
 * bindPwd is cleartext by its own docstring. (host.ADPass and productKey are
 * encrypted at rest, so the same trick reaches ciphertext -- which is why
 * the rule is "what the emitter strips" and not "what looks alarming".)
 *
 * One derived list is the fix, and this test exists to keep it derived. The
 * failure mode is not a bug in the rule, it is a fourth place growing its own
 * copy -- so what is asserted here is that the deciding functions call
 * unfilterableFields(), not that any particular field appears in it.
 *
 * DB-free: reads the source.
 *
 * Usage: php tests/sensitive-fields-unfilterable.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$routeFile = $webroot . '/src/Router/Route.php';
$mgrFile = $webroot . '/src/Base/FOGManagerController.php';

foreach ([$routeFile, $mgrFile] as $needed) {
    if (!is_readable($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

$route = file_get_contents($routeFile);
$mgr = file_get_contents($mgrFile);

$failures = [];
$checks = 0;

/**
 * Returns the source of a named method, signature to the following one.
 */
$bodyOf = function ($src, $needle) {
    $start = strpos($src, $needle);
    if (false === $start) {
        return null;
    }
    $next = preg_match(
        '/\n    (?:public|private|protected)[ a-z]* function /',
        $src,
        $m,
        PREG_OFFSET_CAPTURE,
        $start + strlen($needle)
    );
    return $next
        ? substr($src, $start, $m[0][1] - $start)
        : substr($src, $start);
};

// 1. The single source exists and reads the emitter's own map.
$checks++;
$derive = $bodyOf($route, 'public static function unfilterableFields(');
if (null === $derive) {
    $failures[] = 'Route::unfilterableFields() is gone -- the one place the '
        . 'filter rule is derived from';
} elseif (false === strpos($derive, 'sensitiveFieldMap()')) {
    $checks++;
    $failures[] = 'unfilterableFields() no longer reads sensitiveFieldMap(). '
        . 'A hand-maintained list is the bug this replaced: it drifts from '
        . 'what the emitter strips, silently, and misses anything a plugin '
        . 'declares through API_SENSITIVE_FIELDS.';
}

// 2. Both request-facing filter entry points refuse a blocked field.
//    getsearchbody() is the JSON body; handleWhereItems() is the URL
//    segment, via _assertFilterKeys().
foreach (
    [
        'public static function getsearchbody(' => 'the JSON search body',
        'private static function _assertFilterKeys(' => 'the URL filter segment'
    ] as $needle => $what
) {
    $checks++;
    $body = $bodyOf($route, $needle);
    if (null === $body) {
        $failures[] = "could not find $needle in route; has it been renamed?";
        continue;
    }
    if (false === strpos($body, '_assertNoSensitiveFilter(')) {
        $failures[] = "$what no longer calls _assertNoSensitiveFilter(), so a "
            . 'request can filter on a field the emitter strips';
    }
}

// 3. The refusal is a refusal, not a silent drop. Dropping the key answers
//    with the UNFILTERED set, which is a worse surprise than an error.
$checks++;
$assert = $bodyOf($route, 'private static function _assertNoSensitiveFilter(');
if (null === $assert) {
    $failures[] = '_assertNoSensitiveFilter() is gone';
} elseif (false === strpos($assert, 'HTTP_BAD_REQUEST')) {
    $failures[] = '_assertNoSensitiveFilter() no longer answers 400; a '
        . 'silently dropped filter returns every row instead of the one asked '
        . 'for';
}

// 4. The grid path marks those columns unsearchable, and filter() honors it.
//    Not removed from the column list: listem() is shared with the web tier
//    and product_keys.report.php needs productKey to report anything.
$checks++;
$listem = $bodyOf($route, 'public static function listem(');
if (null === $listem) {
    $failures[] = 'could not find Route::listem()';
} elseif (false === strpos($listem, "'nosearch'")) {
    $failures[] = 'listem() no longer marks sensitive columns nosearch, so '
        . 'the DataTables grid can search a field the emitter strips';
}

$checks++;
$filter = $bodyOf($mgr, 'public static function filter(');
if (null === $filter) {
    $failures[] = 'could not find FOGManagerController::filter()';
} else {
    // Once per loop: the global search and the per-column search. Counted
    // on the isset(), which appears exactly once per guard -- the guard
    // names $column['nosearch'] twice, so counting that would still pass
    // with one whole loop unguarded.
    $honored = substr_count($filter, "isset(\$column['nosearch'])");
    if ($honored < 2) {
        $failures[] = "filter() honors nosearch in $honored of its 2 "
            . 'search loops. Both build a LIKE from a client-named column, '
            . 'so a gap in either one reopens the whole thing.';
    }
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks sensitive-filter checks\n";
exit(0);
