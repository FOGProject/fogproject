<?php
/**
 * The fast Responsive measurement must stay under the memo, and must keep the
 * three things that make it equivalent to what it replaces.
 *
 * `fogFastResizeAuto()` rebuilds Responsive's `_resizeAuto()` measurement with
 * native cloneNode instead of the DataTables API walk. Every failure mode here
 * is silent -- the grid still renders and the columns are merely wrong or the
 * page is merely slow again -- so each of these is pinned as its own check:
 *
 * - it is installed as what the memo WRAPS, not beside it. Reverting that line
 *   to `Responsive.prototype._resizeAuto` leaves both functions present and the
 *   fast one simply never runs;
 * - it hands back to the original while `childNodeStore` holds anything. An
 *   expanded row has had its hidden columns' nodes MOVED into the child row, so
 *   a plain clone of the parent measures those columns empty and reports them
 *   too narrow;
 * - it hands back when auto measurement is off, per-table or per-column, where
 *   the original deliberately writes no minWidth at all;
 * - it maps the probe cell back to a column with `column.index('fromVisible')`.
 *   `s.columns` is indexed by column and the probe by visible position, so
 *   using the loop counter directly is correct until a column is hidden and
 *   then assigns every width one column to the left.
 *
 * Usage: php tests/dt-responsive-fast-measure.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);

$fails = [];

// Isolate the function body so a guard cannot be satisfied by an identically
// worded one somewhere else in the file.
if (!preg_match(
    '#function fogFastResizeAuto\(original\)\s*\{(.*?)\n\}\n#s',
    $js,
    $m
)) {
    $fails[] = 'fogFastResizeAuto() is gone: nothing below can be checked';
    $body = '';
} else {
    $body = $m[1];
}

// The install, whole. The function name alone would still match after the
// wrapping was undone.
if (!preg_match(
    '#var original = fogFastResizeAuto\(\s*Responsive\.prototype\._resizeAuto\s*\);#',
    $js
)) {
    $fails[] = 'the memo does not wrap the fast measurement: with `var '
        . 'original = Responsive.prototype._resizeAuto` both functions are '
        . 'still here and the fast one never runs on a cache miss';
}

if ($body !== '' && !preg_match(
    '#if \(!\$\.isEmptyObject\(this\.s\.childNodeStore\)\) \{\s*'
    . 'return original\.apply\(this, arguments\);#s',
    $body
)) {
    $fails[] = 'no childNodeStore deferral: with a row expanded, the hidden '
        . 'columns\' nodes have been moved into the child row and cloning the '
        . 'parent measures them empty';
}

if ($body !== '' && !preg_match(
    '#if \(!this\.c\.auto \|\| \$\.inArray\(true, \$\.map\(columns, function\(c\) \{'
    . '\s*return c\.auto;\s*\}\)\) === -1\) \{\s*return;#s',
    $body
)) {
    $fails[] = 'no auto-off guard: the original writes no minWidth at all in '
        . 'that case, so anything written here is invented';
}

if ($body !== '' && !preg_match(
    '#columns\[dt\.column\.index\(\'fromVisible\', i\)\]\.minWidth#',
    $body
)) {
    $fails[] = 'the probe cell is not mapped back through fromVisible: '
        . 'indexing s.columns by visible position silently shifts every width '
        . 'one column left as soon as a column is hidden';
}

if ($fails) {
    fwrite(STDERR, "FAIL\n");
    foreach ($fails as $f) {
        fwrite(STDERR, '  - ' . $f . "\n");
    }
    exit(1);
}

echo "PASS: fast Responsive measurement is installed under the memo and keeps "
    . "its three equivalence guards\n";
exit(0);
