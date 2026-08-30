<?php
/**
 * The saved grid layout must actually be restorable.
 *
 * Column order, visibility, page length and sort persist per user through the
 * preference store. Four things in that path fail SILENTLY -- no error, no log,
 * the table just comes up in its default arrangement or filters the wrong
 * column -- and all four were shipped and then found in a browser:
 *
 *  - stateLoadCallback returning anything other than undefined. DataTables
 *    waits for the async callback only on undefined: `void 0 !== ret &&
 *    useIt(ret)`. Returning null means "the state is null", and the answer the
 *    function is waiting for arrives too late to be used.
 *  - stripping `time` from the saved state. _fnImplementState opens with
 *    `if (state && state.time)`, so a state with no timestamp is dropped
 *    whole. It is also what stateDuration measures against.
 *  - building the per-column search row only on the ajax response. A restored
 *    column order is applied AFTER that, so the boxes stay pinned to the
 *    pre-restore layout and each one filters a column its heading does not
 *    belong to.
 *  - saving the sort by index alone. DataTables and ColReorder disagree about
 *    which frame a saved order index is in, so the sort lands on a different
 *    column after a reload once anything has been dragged -- reproduced with
 *    stock DataTables 2.0.8 and ColReorder 2.0.3 using the library's own
 *    localStorage callbacks, so the compensation has to live here.
 *
 * ...and one that fails LOUDLY, in the wrong hands. DataTables Select writes
 * the selected row ids into every state save of its own accord and re-selects
 * them on load. Nothing here asked for it and nothing here reads it, but every
 * bulk action on a list page reads rows({selected: true}) -- so a selection
 * made on one machine on Friday comes back armed on another on Monday, out of
 * sight down a virtual scroll. It has to be stripped, and stripped by name,
 * because it is not this code that puts it there.
 *
 * Usage: php tests/datatable-state-persistence.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents($root . '/packages/web/management/js/fog/fog.common.js');

$fails = [];

// ------------------------------------------------ the async state load

if (!preg_match(
    '#stateLoadCallback: function\(settings, callback\) \{(.*?)\n    \},#s',
    $js,
    $m
)) {
    echo "FAIL: stateLoadCallback not found -- the gate cannot see its subject\n";
    exit(1);
}
// Comments in this block discuss `return null` at length, and a gate that
// passes on its own documentation is the failure this project has hit before.
$load = preg_replace('#^\s*//.*$#m', '', $m[1]);

// Every return in it must be bare. `return null` is the shape that broke it,
// but so is any other value: DataTables acts on anything but undefined.
if (preg_match_all('#\breturn\s+([^;\s][^;]*);#', $load, $rets)) {
    foreach ($rets[1] as $ret) {
        $fails[] = 'stateLoadCallback returns a value (' . trim($ret)
            . '); DataTables waits for the async callback only when it '
            . 'returns undefined';
    }
}
if (!preg_match('#fogPrefFetch\(key, function\(err, value\)#', $load)) {
    $fails[] = 'stateLoadCallback no longer reads through fogPrefFetch';
}

// ------------------------------------------------------- the timestamp

if (!preg_match(
    '#function fogStripVolatileState\(state\) \{(.*?)\n\}#s',
    $js,
    $m
)) {
    $fails[] = 'fogStripVolatileState() not found';
} else {
    if (preg_match('#delete\s+copy\.time#', $m[1])) {
        $fails[] = 'fogStripVolatileState() deletes the state timestamp; '
            . 'DataTables drops a state that has no time and the saved '
            . 'layout can never be restored';
    }
    // What it MUST still strip: a stored search would be reapplied invisibly,
    // and a stored selection would be reapplied invisibly AND acted on.
    foreach (['search', 'searchBuilder', 'select'] as $stripped) {
        if (!preg_match('#delete\s+copy\.' . $stripped . '\b#', $m[1])) {
            $fails[] = "fogStripVolatileState() no longer strips $stripped";
        }
    }
    if (!preg_match('#delete\s+copy\.columns\[i\]\.search#', $m[1])) {
        $fails[] = 'fogStripVolatileState() no longer strips per-column search';
    }
}

// --------------------------------------- the search row follows a restore

if (!preg_match(
    "#table\.on\(\s*'([^']*column-visibility[^']*)',#s",
    $js,
    $m
)) {
    $fails[] = 'the column-search rebuild binding was not found';
} else {
    foreach (['init.dt', 'draw.dt', 'column-reorder.dt', 'column-sizing.dt'] as $ev) {
        if (false === strpos($m[1], $ev)) {
            $fails[] = "the column-search row is not rebuilt on $ev; a "
                . 'restored column order is applied after the first response, '
                . 'so the boxes would stay pinned to the old layout';
        }
    }
}

// ------------------------------------------------- the sort follows its column

if (!preg_match('#data\.fogOrder = fogOrderKeys\(settings, data\.order\);#', $js)) {
    $fails[] = 'the saved state does not record which COLUMN is sorted; a '
        . 'saved order index alone lands on the wrong column after a reorder';
}
if (!preg_match("#table\.on\('init\.dt', function\(\) \{\s*fogApplyOrder\(table\);#s", $js)) {
    $fails[] = 'fogApplyOrder() is not called once the table is up, so a '
        . 'mis-restored sort is never corrected';
}
if (!preg_match('#function fogApplyOrder\(api\) \{(.*?)\n\}#s', $js, $m)) {
    $fails[] = 'fogApplyOrder() not found';
} elseif (!preg_match(
    '#JSON\.stringify\(wanted\) === JSON\.stringify\(api\.order\(\)\)#',
    $m[1]
)) {
    $fails[] = 'fogApplyOrder() no longer returns early when the sort is '
        . 'already right; on a server-side table that is a redraw, and so a '
        . 'wasted request, on every single grid load';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: the saved grid layout is restorable -- async load, timestamp, "
    . "row rebuild and sort column all pinned\n";
exit(0);
