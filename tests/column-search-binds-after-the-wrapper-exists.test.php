<?php
/**
 * The per-column search row must actually be wired to the table.
 *
 * DataTables does not build its wrapper until it has data. On a server-side
 * grid -- which is every list page in FOG -- the first response has not
 * arrived when `$(this).DataTable(opts)` returns, so `table().container()` is
 * still null at that instant and only becomes an element later.
 *
 * Delegating the search-row handlers off that null binds them to an EMPTY
 * jQuery set. `.on()` against nothing is not an error, so there is no console
 * message and no failed request: the row renders, the boxes accept typing,
 * the condition dropdowns work, and not one keystroke ever reaches
 * `column().search()`. That shipped, and reads as the feature being broken
 * rather than as a wiring bug, because every visible part of it is present.
 *
 * Two things are pinned, and one alone is not enough. Without the deferral
 * the binding happens too early; without the init hookup a deferred binding
 * that finds no container simply never happens at all.
 *
 * Usage: php tests/column-search-binds-after-the-wrapper-exists.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);

$fails = [];

// The binding lives inside a function that reads the container itself, so
// that it can be run again once there is one. Anchored on the whole head
// rather than on the name: a function that no longer re-reads the container
// is the same bug wearing the right identifier.
if (!preg_match(
    '#function fogBindColumnSearch\(\) \{\s*'
    . 'var container = table\.table\(\)\.container\(\);#s',
    $js
)) {
    $fails[] = 'the column-search binding does not re-read the container, so '
        . 'it cannot be deferred past a container that did not exist yet';
}

// Nothing may bind the namespace straight off a fresh init: that is the
// original defect, and it is a one-line edit away at all times.
if (preg_match(
    '#\$\(table\.table\(\)\.container\(\)\)\s*\.off\(\'\.fogcolsearch\'\)#s',
    $js
)) {
    $fails[] = 'the search-row handlers are delegated off '
        . 'table().container() directly, which is null until the first '
        . 'server-side response and so binds them to nothing';
}

// And it has to be re-run when the table finishes coming up, or the first
// call -- the one that finds no container -- is the only one there is.
if (!preg_match(
    "#\\.on\\('init\\.dt\\.fogcolsearch', fogBindColumnSearch\\)#",
    $js
)) {
    $fails[] = 'fogBindColumnSearch() is not re-run on init.dt, so on a '
        . 'server-side grid it runs exactly once, before there is anything '
        . 'to bind to';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: the column search row is bound once the wrapper exists\n";
exit(0);
