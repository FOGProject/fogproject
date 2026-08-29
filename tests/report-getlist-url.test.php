<?php
/**
 * A report's getList URL addresses getList, and does not repeat a key.
 *
 * Run History needs the page's own query string on its AJAX call -- the
 * window (start, end, sources[]) lives in the URL by design, so getList
 * cannot see it any other way. It got there by appending
 * `window.location.search` wholesale, which also carries `node`, `sub` and
 * `f`. The result was:
 *
 *   ?node=report&sub=getList&f=X&node=report&sub=file&f=X
 *
 * PHP resolves a repeated key to its LAST occurrence, so every request asked
 * for `sub=file` and got the report page back. DataTables was handed HTML at
 * HTTP 200 and showed "No data available in table" under an error toast
 * reading `HTTP 200 - <div class="card">`. The table had never loaded since
 * the report shipped.
 *
 * Nothing caught it because everything that checked this checked the SERVER.
 * ADR 0022's lab harness drove the real getList() against a lab database and
 * the row came back; tests/run-history-report.test.php pins the four names
 * that must agree and the column keys. Both are correct and neither can see
 * a URL the browser builds. The endpoint worked perfectly and nothing ever
 * reached it.
 *
 * So this asserts the shape of the URL rather than the behavior of the
 * endpoint, and it asserts the PREMISE too -- that a repeated key resolves
 * to the last one -- because that is the fact the rule rests on and it is
 * not obvious.
 *
 * Usage: php tests/report-getlist-url.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('report-getlist-url');

$t = new FogChecks();

$root = dirname(__DIR__) . '/packages/web';
$js = (string)file_get_contents(
    $root . '/management/js/fog/report/fog.report.file.js'
);

$t->check('the report JS is readable', '' !== $js);

/*
 * 1. The premise. parse_str -- and PHP's own superglobal parsing, which
 *    filter_input reads -- take the LAST occurrence of a repeated key. If
 *    that ever stopped being true the rule below would still be right, but
 *    for a different reason, and the docblock above would be wrong.
 */
$parsed = [];
parse_str('sub=getList&sub=file', $parsed);
$t->check(
    'a repeated query key resolves to its LAST value',
    'file' === ($parsed['sub'] ?? null)
);

/*
 * 2. The defect shape, named directly: no URL is built by concatenating the
 *    page's raw query string. That is the whole bug, and a future report
 *    needing the window has to go through URLSearchParams like this one.
 */
$t->check(
    'no report URL appends window.location.search as a string',
    1 !== preg_match(
        '/\+\s*(?:\'&\'\s*\+\s*)?window\.location\.search/',
        $js
    )
);

/*
 * 3. The replacement, positively: the search is PARSED, and the three keys
 *    that address the endpoint are set on it rather than appended beside
 *    whatever it already held.
 */
$t->check(
    'the search is parsed rather than pasted',
    false !== strpos($js, 'new URLSearchParams(window.location.search)')
);
foreach (['node', 'sub', 'f'] as $key) {
    $t->check(
        "`$key` is set on the parsed params, not concatenated",
        1 === preg_match(
            '/params\.set\(\s*\'' . preg_quote($key, '/') . '\'\s*,/',
            $js
        )
    );
}
$t->check(
    'and it still asks for getList',
    1 === preg_match("/params\.set\(\s*'sub'\s*,\s*'getList'\s*\)/", $js)
);

/*
 * 4. Every AJAX url in the file reaches index.php through a query string
 *    that names the sub exactly once. Checked over the file's url:
 *    expressions rather than the whole text, so an unrelated mention of
 *    `sub=` in a comment cannot satisfy or break it.
 */
$urls = [];
if (preg_match_all('/url:\s*(.+?),\n\s*type:/s', $js, $m)) {
    $urls = $m[1];
}
$t->check('every report table declares a url', count($urls) > 0);
foreach ($urls as $i => $url) {
    // A literal `sub=` inside the string is fine as long as it appears once
    // and the raw search is not glued on after it.
    $literals = substr_count($url, 'sub=');
    $t->check(
        "url #$i names its sub at most once",
        $literals <= 1
    );
    $t->check(
        "url #$i does not paste the page's query string",
        false === strpos($url, '+ window.location.search')
            && false === strpos($url, "'&' + window.location.search")
    );
}

$t->finish();
