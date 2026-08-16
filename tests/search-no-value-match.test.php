<?php
/**
 * Universal search must not match a globalSettings value.
 *
 * `Route::unisearch()` returns only an id and a name per row, so a setting's
 * value never appears in a response. It used to be matched anyway, with
 * `OR settingValue LIKE :item3`, and that is a different thing from
 * returning it: the number of results answers "does this value contain that
 * substring", which is exactly the question `maskSensitiveSetting()` exists
 * to refuse. globalSettings is where FOG keeps its credentials, so the
 * answer is worth having.
 *
 * Restoring the clause looks like a small usability improvement -- "let
 * admins find a setting by its value" -- and nothing about the code says
 * otherwise, which is why this test exists rather than a comment alone.
 * The empty `case 'setting':` in that switch carries the reasoning.
 *
 * Scoped to the query builder, not the whole file: `settingValue` is a real
 * column and legitimately appears in reads elsewhere. What must not come
 * back is matching it in a search.
 *
 * DB-free: reads the source.
 *
 * Usage: php tests/search-no-value-match.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$routeFile = dirname(__DIR__)
    . '/packages/web/lib/router/route.class.php';

if (!is_readable($routeFile)) {
    fwrite(STDERR, "FAIL: cannot read $routeFile\n");
    exit(1);
}

$src = file_get_contents($routeFile);

// The body of unisearch(), from its signature to the next method.
$start = strpos($src, 'public static function unisearch(');
if (false === $start) {
    fwrite(STDERR, "FAIL: could not locate unisearch() in route\n");
    exit(1);
}
$end = strpos($src, 'public static function search(', $start);
if (false === $end) {
    fwrite(STDERR, "FAIL: could not find the end of unisearch()\n");
    exit(1);
}
$body = substr($src, $start, $end - $start);

$failures = [];
$checks = 0;

// Comments explain the absence, so they must not count as the thing itself.
$code = preg_replace('#//[^\n]*#', '', $body);

$checks++;
if (preg_match('/settingValue/i', $code)) {
    $failures[] = 'unisearch() references settingValue. The universal '
        . 'search must match a setting by its KEY only -- matching the '
        . 'value turns the result count into an oracle for credential '
        . 'settings whose value the API deliberately masks.';
}

// The marker case is what makes the omission legible in the source. Losing
// it is not a vulnerability, but it is how the next person re-adds the
// clause without knowing there was a reason.
$checks++;
if (false === strpos($body, "case 'setting':")) {
    $failures[] = "the explicit `case 'setting':` marker in unisearch()'s "
        . 'switch is gone; it is what records that the missing value clause '
        . 'is deliberate';
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks search value-match checks\n";
exit(0);
