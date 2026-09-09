<?php
/**
 * The module State control must be wide enough to read.
 *
 * THE FAILURE. ADR 0038 turned the Modules tab's checkbox into a three-state
 * <select>. Bootstrap's `.form-select` is `width: 100%`, so a cell whose only
 * content is that select has NO intrinsic width -- there is nothing for the
 * browser's auto layout to size the column to except the header text. The
 * column came out at the width of the word "State", and the chain that
 * follows makes it permanent:
 *
 *   1. fogSeedColWidths() (management/js/fog/fog.common.js) measures each
 *      header and writes the result into the <colgroup> as explicit px.
 *   2. `.fog-table-fixed` then goes on the table, and under
 *      `table-layout: fixed` the <colgroup> is authoritative -- content can
 *      no longer widen a column.
 *
 * So the seeded 53px stood, the select inside it rendered 35px, and every
 * option was clipped after its first character: "On", "Off" and "Not set" all
 * showed as "O" or "N" beside a caret. Reported in forum topic 18238, where
 * it was mistaken for the module being switched on. The grids run
 * `autoWidth: false`, so DataTables never measures the control either, and
 * the last column has no drag strip of its own -- the user could not widen it
 * without shrinking Module Name first.
 *
 * THE FIX is to give the select an intrinsic width: `w-auto` sizes it to its
 * widest option, so the auto-layout measurement fogSeedColWidths() takes is
 * big enough before it is frozen. Measured on the live 1.6 UI at 1600px, one
 * harness, the two files as the only difference
 * (background_scripts/measure_module_state_column_patched.js):
 *
 *   without w-auto   State column 53px, select 35px
 *   with w-auto      State column 88px, select 70px
 *
 * WHAT THIS PINS is the class on the control, and the two things that make it
 * load-bearing rather than cosmetic: that `.form-select` is still there (it
 * is what makes the width 100% in the first place, so a future edit dropping
 * w-auto has to be visible here), and that the seeding pass still reads the
 * rendered header width -- the moment it stops doing that, this fix is
 * addressing a mechanism that no longer exists and the reasoning above needs
 * rewriting rather than the class being carried along.
 *
 * Usage: php tests/module-state-select-is-legible.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function check($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$edit = (string) file_get_contents(
    $root . '/packages/web/management/js/fog/host/fog.host.edit.js'
);

$select = '';
if (preg_match(
    "/html = '<select class=\"([^\"]*module-state)\"'/",
    $edit,
    $m
)) {
    $select = $m[1];
}
check(
    'the module state select was found',
    '' !== $select,
    $failures,
    $checks
);
check(
    'it is sized to its content, not to 100% of a collapsed column',
    false !== strpos($select, 'w-auto'),
    $failures,
    $checks
);
/*
 * If .form-select ever goes, the select is no longer 100% wide and w-auto is
 * cargo -- but so is this whole test, and the comment above it. Fail loudly
 * rather than let the two drift apart silently.
 */
check(
    'and is still a .form-select -- which is what makes w-auto necessary',
    false !== strpos($select, 'form-select'),
    $failures,
    $checks
);

/*
 * The mechanism the fix relies on. fogSeedColWidths() takes the RENDERED
 * header width and writes it into the colgroup; that is the measurement
 * w-auto exists to make large enough.
 */
$common = (string) file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);
$seed = '';
if (preg_match(
    '/function fogSeedColWidths\(parts\) \{.*?\n\}/s',
    $common,
    $m
)) {
    $seed = $m[0];
}
check(
    'fogSeedColWidths was found',
    '' !== $seed,
    $failures,
    $checks
);
check(
    'it still seeds the colgroup from the rendered header width',
    false !== strpos($seed, 'outerWidth()')
    && false !== strpos($seed, "colgroup > col")
    && false !== strpos($seed, "this.style.width = widths[i] + 'px'"),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
