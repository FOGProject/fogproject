<?php
/**
 * The .app-main ResizeObserver pass must adjust a grid's columns only when
 * they are actually wrong -- and must decide that by MEASURING, not by
 * remembering what it was last told.
 *
 * columns.adjust() is the expensive half of every sizing pass. It fires
 * column-sizing, Responsive answers that by re-measuring the whole loaded page
 * of rows, and Scroller's "loaded page" is every row fetched so far. On a
 * normal load of the 86-host list DataTables already runs it four times of its
 * own accord -- Responsive's constructor, the scrollbar appearing on the first
 * response, Responsive settling which columns fit, and init complete -- before
 * the observer fires at all. That observer also fires for HEIGHT changes, and
 * the rows rendering IS a height change, so the fifth pass was re-doing work
 * the library had already done: ~70ms, for widths that came out identical.
 *
 * Two ways to gate it, and only one of them is correct:
 *
 *  - remember the INPUTS (container width, overflow, visible columns) at each
 *    adjust and skip when they have not moved. Measured, and it is WRONG:
 *    below the sidebar breakpoint the vertical scrollbar appears as a
 *    consequence of the adjust that sized the table, so DataTables leaves the
 *    body table 15px wider than the viewport it sits in and this pass is what
 *    converges it. The inputs are unchanged and the table is still wrong. At
 *    860px that shipped 771px of table into a 756px body -- a horizontal
 *    scrollbar on every narrow window;
 *  - ask the OUTPUT: does the body table exactly fill the scroll body, and is
 *    every header cell the width of the body cell beneath it? If both hold, an
 *    adjust cannot produce a different answer.
 *
 * So this pins the second shape and forbids the first. Every failure here is
 * silent -- the grid renders either way and is merely slow, or merely 15px out
 * -- which is why each is its own check.
 *
 * Usage: php tests/grid-adjust-runs-only-when-misaligned.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);

$fails = [];

// Isolate the two function bodies, so nothing below can be satisfied by an
// identically worded line elsewhere in a 4000-line file. Comments are stripped
// from each: a check that its own docblock can satisfy proves nothing.
$strip = function ($text) {
    return preg_replace('#^\s*//.*$#m', '', $text);
};

$aligned = '';
if (preg_match('#function fogColumnsAligned\(dt\)\s*\{(.*?)\n\}\n#s', $js, $m)) {
    $aligned = $strip($m[1]);
} else {
    $fails[] = 'fogColumnsAligned() is gone: the ResizeObserver pass has no '
        . 'way to tell a settled grid from a misaligned one, so it either '
        . 'adjusts every load or skips one that needed it';
}

$scroller = '';
if (preg_match('#function fogSizeScroller\(dt, release\)\s*\{(.*?)\n\}\n#s', $js, $m)) {
    $scroller = $strip($m[1]);
} else {
    $fails[] = 'fogSizeScroller() is gone: nothing below can be checked';
}

// -- the gate is wired in, and it is the whole gate ------------------------

// Named as one block. `fogColumnsAligned(dt)` appearing somewhere in the
// function would still match after the early return was deleted, which is
// exactly the edit that puts the fifth adjust back.
if ($scroller !== '' && !preg_match(
    '#if \(fogColumnsAligned\(dt\)\) \{\s*return;\s*\}#',
    $scroller
)) {
    $fails[] = 'fogSizeScroller() does not return early when the columns are '
        . 'already aligned, so every .app-main resize -- including the one the '
        . 'rows themselves cause on first render -- pays for a full '
        . 'columns.adjust()';
}

// ORDER IS THE BUG. The height this function writes decides whether the body
// overflows, and whether the body overflows decides whether there is a
// scrollbar to size around. Measure first and the answer is about the height
// the table had a moment ago.
if ($scroller !== '' && !preg_match(
    "#max-height.*?fogColumnsAligned\(dt\)#s",
    $scroller
)) {
    $fails[] = 'fogSizeScroller() decides whether the columns are aligned '
        . 'BEFORE it writes the new max-height, so the measurement is taken '
        . 'against a height that is about to change';
}

// -- and the gate measures, rather than remembering ------------------------

// The stored-signature gate this replaced. Reintroducing it is the 15px
// regression described above, and it looks like a cheaper check.
if (preg_match('#_fogSizeSignature#', $js)) {
    $fails[] = 'the sizing pass is gated on a remembered signature again: an '
        . 'input-based gate cannot see that the library\'s own adjust left the '
        . 'table wider than the viewport, which is what happens below the '
        . 'sidebar breakpoint';
}

// The scrollbar half: body table against the box it sits in.
if ($aligned !== '' && !preg_match(
    '#body\.getBoundingClientRect\(\)\.width - scrollBody\.clientWidth#',
    $aligned
)) {
    $fails[] = 'fogColumnsAligned() does not compare the body table against '
        . 'the scroll body\'s client width, so a table left overflowing by the '
        . 'width of a scrollbar reads as settled';
}

// The alignment half: header cell against the body cell beneath it. Totals
// agreeing does not mean the boundaries do.
if ($aligned !== '' && !preg_match(
    '#headCells\[i\]\.getBoundingClientRect\(\)\.width\s*-\s*'
    . 'bodyCells\[i\]\.getBoundingClientRect\(\)\.width#s',
    $aligned
)) {
    $fails[] = 'fogColumnsAligned() does not compare header cells to body '
        . 'cells one for one, so a header whose columns are redistributed '
        . 'differently from the rows beneath it reads as settled';
}

// A paged table has no split and therefore nothing this can prove. It must
// say NO and keep being adjusted as it was, not be quietly skipped forever.
if ($aligned !== '' && !preg_match(
    '#if \(!scrollBody \|\| !head \|\| !body\) \{\s*return false;\s*\}#',
    $aligned
)) {
    $fails[] = 'fogColumnsAligned() does not answer false for a table with no '
        . 'scroll split, so a paged grid would be skipped on the strength of a '
        . 'measurement that was never taken';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: the resize pass adjusts a grid's columns only when a measurement "
    . "says they are wrong\n";
exit(0);
