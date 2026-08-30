<?php
/**
 * A date that predates this install's move to UTC must be MARKED, and marking
 * it must not change the value.
 *
 * The boundary (#1496) fixed what a date means from now on. It cannot fix what
 * the old ones mean -- five different clocks wrote them and no sweep can tell
 * which -- so the one thing left to do is say so on screen. That is only
 * useful if the marker is additive: the value is real, the reader usually
 * knows what it means, and sorting and filtering within the pre-boundary era
 * are mutually consistent.
 *
 * The failure this exists to prevent is the marker riding the VALUE. FOG has
 * shipped that bug twice -- GH-1245 and GH-1446 -- because a grid cell's data
 * is escaped into the cell, is what sorting and filtering compare, and is what
 * the CSV/Excel buttons export. Markup put there prints as tags and lands in
 * the download. The info card is the same shape: renderInfoCard()'s own
 * docblock says a page wanting markup there must "grow an explicit opt-in
 * rather than smuggling it through a value".
 *
 * So the two display sites work differently on purpose, and both are pinned:
 *
 *  - grids get a SIBLING key and the browser decorates the cell's DOM;
 *  - the info card gets a plain-text marker that survives htmlspecialchars,
 *    plus one line of prose at the foot of the card explaining it.
 *
 * Usage: php tests/unadjusted-dates-are-marked-not-converted.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$fails = [];

$mgr = file_get_contents($root . '/src/Base/FOGManagerController.php');
$render = file_get_contents($root . '/src/Base/FOGPageRender.php');
$epoch = file_get_contents($root . '/src/Base/StorageEpoch.php');
$js = file_get_contents($root . '/management/js/fog/fog.common.js');

// -- the grid half ---------------------------------------------------------

// ORDER IS THE BUG. isPreBoundary() classifies the STORED value, and the next
// statement replaces it with a display one. Flag second and every row is
// classified on the wrong string -- silently, and in the direction that says
// "already UTC" for values that are not. Anchored as one block so the two
// cannot drift apart.
if (!preg_match(
    '#if \(\\\\FOG\\\\Base\\\\StorageEpoch::isPreBoundary\(\s*'
    . '\(string\)\$row\[\$key\],\s*\$isDatetime\s*\)\) \{\s*'
    . '\$row\[\$key \. self::UNADJUSTED_SUFFIX\] = true;\s*\}\s*'
    . '\$row\[\$key\] = self::toDisplayStored\(#s',
    $mgr
)) {
    $fails[] = 'displayDates() does not flag the row from the STORED value '
        . 'immediately before rewriting it, so the classification is made '
        . 'against the display value instead';
}

// A sibling, never the value. Any concatenation of the marker onto a row's
// value is the bug this whole test is about.
if (preg_match('#\$row\[\$key\][^\n]*\.=#', $mgr)
    || preg_match('#\$row\[\$key\] = [^\n]*MARKER#', $mgr)
) {
    $fails[] = 'displayDates() decorates the grid value itself, which is '
        . 'escaped into the cell, compared by sorting and filtering, and '
        . 'exported to CSV';
}

// The note is display-only for the same reason the conversion is.
if (!preg_match(
    '#function unadjustedNote\(\)\s*\{\s*if \(class_exists\(.{0,60}Route.{0,30}'
    . '\&\& \\\\FOG\\\\Router\\\\Route::\$apiRequest\s*\) \{\s*return \'\';#s',
    $mgr
)) {
    $fails[] = 'unadjustedNote() is not gated on the request being a non-API '
        . 'one, so a REST consumer is told about a display conversion that '
        . 'never happened to the values it was sent';
}

// -- the browser half ------------------------------------------------------

// The glyph goes into the DOM, and nowhere else. A render() would put it back
// into the orthogonal data that sorting, filtering and export all read.
if (!preg_match("#\\\$\(cell\)\.append\(#", $js)) {
    $fails[] = 'the browser does not append the marker to the cell node, so '
        . 'it is not being kept out of the cell data';
}
if (preg_match('#__unadjusted[^\n]*\n[^\n]*return data \+#', $js)) {
    $fails[] = 'the marker is concatenated onto a cell value in JS, which '
        . 'puts it into the CSV export and the sort comparator';
}

// A redraw must not stack a second glyph -- Scroller and Responsive redraw
// rows constantly, so this runs many times over the same cell.
if (!preg_match("#find\('\.fog-unadjusted'\)\.length#", $js)) {
    $fails[] = 'fogMarkUnadjusted() has no guard against decorating a cell '
        . 'twice, so a redrawn row accumulates markers';
}

// The note lives on settings, not on an Api object: a DataTables Api is
// constructed per call, so a property set on one is gone by the next.
if (!preg_match('#settings\.fogUnadjustedNote = json\._unadjustednote#', $js)) {
    $fails[] = 'the payload note is not stashed on the DataTables settings '
        . 'object, so rowCallback cannot read it back';
}

// ...and rowCallback has to reach those settings through the callback's own
// `this`, which DataTables sets to the table's jQuery instance.
//
// NOT through the row. rowCallback runs while the row is still DETACHED --
// DataTables builds every <tr>, fires the callback for each, and attaches the
// tbody afterward -- so $(tr).closest('table') matches nothing and
// .DataTable() hands back an Api with no settings behind it. Reading
// settings()[0] off that throws, and the throw happens inside the draw: the
// grid renders one row, the header/body split is never sized, and the console
// carries the only sign of it. Every grid in FOG, on the first ajax draw.
//
// Comments are stripped before looking, so this cannot pass -- or fail -- on
// the prose above the code it is checking.
$rowCb = '';
if (preg_match('#rowCallback: function\(tr, data\) \{.*?\n    \},\n#s', $js, $m)) {
    $rowCb = preg_replace('#^\s*//.*$#m', '', $m[0]);
}
if ($rowCb === '') {
    $fails[] = 'the grid rowCallback that marks unadjusted dates is gone, or '
        . 'no longer takes (tr, data) -- nothing marks a pre-boundary date on '
        . 'a list page';
} else {
    if (strpos($rowCb, 'this.api()') === false) {
        $fails[] = 'rowCallback does not resolve the table from its own '
            . '`this`, which is the only handle on the settings object that '
            . 'is valid while the row is still detached';
    }
    if (strpos($rowCb, "closest('table')") !== false) {
        $fails[] = 'rowCallback resolves the table by walking up from the '
            . 'row, which is detached at that point: the lookup finds nothing '
            . 'and the resulting settings()[0] read throws, aborting the draw';
    }
}

// -- the detail-page half --------------------------------------------------

if (!preg_match(
    '#StorageEpoch::isPreBoundary\(\$value, \$isDatetime\)\) \{\s*'
    . 'self::\$_unadjustedSeen = true;\s*'
    . '\$out \.= \\\\FOG\\\\Base\\\\StorageEpoch::MARKER;#s',
    $render
)) {
    $fails[] = 'dateOrNever() does not mark a pre-boundary value and record '
        . 'that it did, so either the date carries no marker or the card '
        . 'never explains one';
}

// Once per page, and only when there is something to explain.
if (!preg_match(
    '#if \(self::\$_unadjustedSeen\) \{\s*echo \'<div class="small#s',
    $render
)) {
    $fails[] = 'the info card prints its explanation unconditionally, which '
        . 'is a standing disclaimer on every edit page in FOG';
}

// The marker has to survive htmlspecialchars, because both sites escape it.
if (!preg_match("#const MARKER = '[^<>&\"']*';#", $epoch)) {
    $fails[] = 'StorageEpoch::MARKER contains markup or a character that '
        . 'htmlspecialchars would escape, so it cannot ride a value through '
        . 'either display site';
}

// And the sentence must be a real msgid, not one assembled at runtime.
if (!preg_match("#_\('recorded before this server moved to UTC; written in %s'\)#", $epoch)) {
    $fails[] = 'the explanation is not a literal gettext msgid, so it never '
        . 'reaches the .pot and can never be translated';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: pre-boundary dates are marked additively -- value, sort and "
    . "export untouched\n";
exit(0);
