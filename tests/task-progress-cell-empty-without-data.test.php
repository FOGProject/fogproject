<?php
/**
 * The active-task progress cell must be blank when there is no transfer data.
 *
 * THE FAILURE. The Active Tasks grid's column 8 render (fog.task.list.js)
 * always builds "timeElapsed / timeRemaining dataCopied of dataTotal
 * (bpm/min)" plus a progress bar, whatever the task's state. A queued task,
 * a snapin task, or any other non-imaging task never populates those
 * transfer fields, so the cell renders literally "/ of (/min)" with an
 * empty bar instead of nothing. Reported via the forum, screenshotted on
 * two queued "All Snapins" tasks.
 *
 * THE FIX is a guard at the top of the render: when row.dataTotal is
 * empty/falsy there is no transfer to describe, so the cell returns ''
 * instead of building the string and the bar.
 *
 * WHAT THIS PINS is that the guard runs BEFORE the " of " string is ever
 * assembled -- so the check has to come first in the render function, not
 * be layered on after the fact.
 *
 * Usage: php tests/task-progress-cell-empty-without-data.test.php
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

$list = (string) file_get_contents(
    $root . '/packages/web/management/js/fog/task/fog.task.list.js'
);

$render = '';
if (preg_match(
    '/render: function\(data, type, row\) \{\s*'
    . '\/\/.*?\n\s*\/\/.*?\n\s*\/\/.*?\n'
    . '.*?targets: 8/s',
    $list,
    $m
)) {
    $render = $m[0];
}
check(
    'the column 8 progress-cell render was found',
    '' !== $render,
    $failures,
    $checks
);

/*
 * The guard must appear before the ' of ' string is ever built, otherwise
 * it is dead code sitting after the cell has already been assembled.
 */
$guardPos = strpos($render, 'row.dataTotal');
$ofPos = strpos($render, "' of '");
check(
    'row.dataTotal is checked',
    false !== $guardPos,
    $failures,
    $checks
);
check(
    "the ' of ' string is still built for the non-empty case",
    false !== $ofPos,
    $failures,
    $checks
);
check(
    'the dataTotal guard runs BEFORE the " of " string is assembled',
    false !== $guardPos && false !== $ofPos && $guardPos < $ofPos,
    $failures,
    $checks
);
check(
    'the guard returns an empty string, not partial markup',
    (bool) preg_match(
        "/if\s*\(\s*!row\.dataTotal\s*\)\s*\{\s*return\s*'';\s*\}/",
        $render
    ),
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
