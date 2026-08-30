<?php
/**
 * A management grid must offer the column-visibility control it saves.
 *
 * Which columns are showing persists per user -- it rides in the same saved
 * state as column order, page length and sort, and it survives a browser
 * with no localStorage at all, so it is genuinely the preference store and
 * not the browser. What was missing was any way to SET it: the colvis button
 * was defined on the export toolbar and the report toolbar and left out of
 * registerTable()'s own defaults, which is the toolbar every host, image,
 * user, snapin and group list uses.
 *
 * A saved preference with no control to change it is worse than no feature:
 * the state is restored on every load, so a layout that ever went wrong --
 * through an older release, a shared account, a hand-edited preference --
 * could not be put right from the UI.
 *
 * All three toolbars are pinned, because the defect was one of them being
 * different from the other two.
 *
 * Usage: php tests/every-grid-toolbar-can-change-columns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);

$fails = [];

// Every toolbar is a `buttons` array holding columnSearchButton; the colvis
// entry has to be in the SAME array, so the two are anchored together rather
// than counted separately across the file.
if (!preg_match_all(
    "#columnSearchButton,\s*(?://[^\n]*\n\s*)*\{\s*extend: 'colvis'#",
    $js,
    $m
)) {
    $fails[] = 'no toolbar offers a colvis button next to the column search';
} elseif (count($m[0]) < 3) {
    $fails[] = sprintf(
        'only %d of the three toolbars (export, report, registerTable) '
        . 'offers a colvis button; a grid that saves which columns are '
        . 'showing but cannot change them restores a layout nobody can fix',
        count($m[0])
    );
}

// And the saving half, which is what makes the button worth having. If
// visibility stopped being part of the saved state the button would still
// work and the choice would evaporate on the next page load.
if (false === strpos($js, 'stateSave: true')) {
    $fails[] = 'stateSave is off, so a column choice would not survive a '
        . 'page load';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: all three grid toolbars can change column visibility\n";
exit(0);
