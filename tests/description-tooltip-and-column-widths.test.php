<?php
/**
 * Description is a row tooltip, and a hand-set column width is remembered.
 *
 * Two grid rules that have to hold on EVERY list page, which is why both live
 * in registerTable() rather than being decided per page -- and why a gate is
 * worth having: the moment one page starts doing its own thing, the other
 * thirty quietly disagree with it and nothing says so.
 *
 * DESCRIPTION AS A TOOLTIP. A description is prose about the whole record,
 * routinely longer than every other cell put together, so as a column it
 * either dictates the table's widths or is clipped to an ellipsis that says
 * nothing. It is hidden and shown on hover instead. Three things have to be
 * true together or the rule only half-applies:
 *
 *  - the column is hidden and marked noVis, so it is not a column and cannot
 *    be turned back into one from the toolbar;
 *  - every Column Visibility button skips noVis columns -- there are three
 *    copies of that button (list, export, report toolbars) and a rule that
 *    holds on two of them is worse than one that holds on none, because the
 *    third looks like a bug rather than a decision;
 *  - the saved state's visibility for that column is overridden on load.
 *    Column visibility persists per user and the STATE WINS over the column
 *    definition, so without this every user who has already loaded one of
 *    these grids keeps the column they have and sees no change at all. The
 *    feature would look like it had never shipped, on exactly the machines
 *    most likely to be checked.
 *
 * ...and the tooltip itself has to be set from the shared rowCallback, since a
 * page that supplied its own would silently lose it.
 *
 * COLUMN WIDTHS. Dragging a column border used to survive only until the page
 * was reloaded. The widths now ride the table's DataTables state -- the same
 * object already carrying column order, visibility, page length and sort --
 * so stateSaveCallback writes them to localStorage and to the preference
 * store, and stateLoadCallback puts them back before the first sizing pass.
 * Three things have to be true:
 *
 *  - a width is stored against the column's NAME, not its position. An index
 *    shifts the moment a column is added or removed, and every remembered
 *    width then lands on the wrong column -- the same defect the saved SORT
 *    already compensates for by storing a name (fogOrderKeys).
 *  - the store is written into the state on save and read out of it on load.
 *  - a save is triggered by the two user GESTURES that change a width, and
 *    the resizer is handed the API that makes that possible. Called without
 *    one, makeColumnsResizable() still resizes but forgets on reload -- which
 *    is precisely the bug this fixed, reintroduced silently.
 *
 * Usage: php tests/description-tooltip-and-column-widths.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$jsDir = $root . '/packages/web/management/js/fog';
$js = file_get_contents($jsDir . '/fog.common.js');

$fails = [];

/**
 * A function body with its comments removed.
 *
 * Stripping is not cosmetic. Every rule below is DISCUSSED at length in the
 * comments around the code that implements it, so a gate reading the raw file
 * passes on the prose after the code has gone -- which is the failure this
 * project has hit before.
 */
$body = function ($signature) use ($js, &$fails) {
    $quoted = preg_quote($signature, '#');
    if (!preg_match('#' . $quoted . '(.*?)\n(\}|  \},|    \},)#s', $js, $m)) {
        $fails[] = $signature . ' not found -- the gate cannot see its subject';
        return '';
    }
    return preg_replace('#^\s*//.*$#m', '', $m[1]);
};

// --------------------------------------------- description is not a column

$desc = $body('function fogDescriptionColumns(columns) {');
if ($desc !== '') {
    // Anchored on the whole comparison, not on the word 'description': a
    // condition changed to match nothing would still contain the string.
    if (!preg_match('#col\.data\s*!==\s*\'description\'#', $desc)) {
        $fails[] = 'fogDescriptionColumns() no longer selects columns by the '
            . 'description data key';
    }
    if (!preg_match('#col\.visible\s*=\s*false#', $desc)) {
        $fails[] = 'fogDescriptionColumns() no longer hides the column';
    }
    if (!preg_match('#\'noVis\'#', $desc)) {
        $fails[] = 'fogDescriptionColumns() no longer marks the column noVis, '
            . 'so the toolbar can turn it back into a column';
    }
}

// Three copies of the Column Visibility button -- list, export and report
// toolbars. Every one of them has to skip noVis, so count the definitions and
// require the same number of exclusions.
$colvis = preg_match_all('#extend:\s*\'colvis\'#', $js);
$novis = preg_match_all('#columns:\s*\':not\(\.noVis\)\'#', $js);
if ($colvis < 1) {
    $fails[] = 'no colvis button found -- the gate cannot see its subject';
} elseif ($novis !== $colvis) {
    $fails[] = $colvis . ' Column Visibility buttons but ' . $novis
        . " carry columns: ':not(.noVis)'; every one of them must, or the "
        . 'description column comes back on whichever toolbar was missed';
}

// The tooltip itself, set from the ONE rowCallback every grid shares.
$rowcb = $body('rowCallback: function(tr, data) {');
if ($rowcb !== '' && !preg_match('#tr\.title\s*=\s*data\.description#', $rowcb)) {
    $fails[] = 'the shared rowCallback no longer sets the row title from '
        . 'data.description, so no grid shows the description at all';
}

// No page may supply its own rowCallback: fogDefaults() is a shallow merge, so
// one that did would replace the shared one and lose the tooltip on that page
// only -- the exact "same on every page except one" drift this rule exists to
// prevent.
foreach (glob($jsDir . '/*/*.js') as $pageJs) {
    $src = preg_replace('#^\s*//.*$#m', '', file_get_contents($pageJs));
    if (preg_match('#\browCallback\s*:#', $src)) {
        $fails[] = basename($pageJs) . ' defines its own rowCallback, which '
            . 'replaces the shared one and drops the description tooltip on '
            . 'that page';
    }
}

// ------------------------------------------------- widths survive a reload

$colkey = $body('function fogColKey(parts, i) {');
if ($colkey !== '') {
    if (!preg_match('#return\s+col\.data;#', $colkey)) {
        $fails[] = 'fogColKey() no longer returns the column NAME; a width '
            . 'stored against a position lands on the wrong column as soon as '
            . 'the column set changes';
    }
    if (!preg_match('#parts\.columns#', $colkey)) {
        $fails[] = 'fogColKey() no longer reads the column definitions, so it '
            . 'has no name to return';
    }
}

$save = $body('stateSaveCallback: function(settings, data) {');
if ($save !== ''
    && !preg_match(
        '#data\.fogColWidths\s*=\s*fogColWidthStore\[settings\.sTableId\]#',
        $save
    )
) {
    $fails[] = 'stateSaveCallback no longer writes the column widths into the '
        . 'saved state, so a resize is forgotten on reload';
}

$load = $body('stateLoadCallback: function(settings, callback) {');
if ($load !== '') {
    if (!preg_match(
        '#fogColWidthStore\[settings\.sTableId\]\s*=\s*state\.fogColWidths#',
        $load
    )) {
        $fails[] = 'stateLoadCallback no longer seeds the width store from the '
            . 'saved state, so the widths are stored and never read back';
    }
    // The state handed to DataTables must be the one that has had Description
    // forced hidden -- passing the raw state instead is the silent half-fix
    // where the feature ships and nobody who already used the grid sees it.
    if (!preg_match(
        '#callback\(fogHideDescriptionState\(descriptionColumns, state\)\)#',
        $load
    )) {
        $fails[] = 'stateLoadCallback hands DataTables a state that has not '
            . 'had the description column forced hidden; an existing saved '
            . 'layout keeps showing the column';
    }
}

// Both gestures that change a width must save, and the resizer must be given
// the API that lets it. Anchored on the whole call so a call left in place
// against a resizer that no longer takes an api still fails.
if (preg_match_all('#fogSaveColWidths\(api\);#', $js) !== 2) {
    $fails[] = 'expected fogSaveColWidths(api) after both resize gestures '
        . '(drag end and double-click to fit)';
}
$m = [];
$calls = preg_match_all('#\.makeColumnsResizable\(([^)]*)\)#', $js, $m);
if ($calls < 2) {
    $fails[] = 'the resizer call sites have moved -- the gate cannot see them';
}
foreach ($m[1] as $arg) {
    if (trim($arg) !== 'table') {
        $fails[] = 'makeColumnsResizable() called with "' . trim($arg)
            . '" instead of the table API; without it a resize is remembered '
            . 'for this page load only';
    }
}

// ----------------------------------------------------------------- report

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: description is a row tooltip on every grid, and a hand-set column "
    . "width rides the saved state\n";
exit(0);
