<?php
/**
 * A Single Snapin task must be recorded as a Single Snapin task.
 *
 * THE FAILURE. Host::createImagePackage()'s SINGLE_SNAPIN arm exists to
 * CONVERT a task that is already running one snapin when a second, different
 * one is asked for: a host has one `tasks` row and many queued snapins, so
 * the row stops being about a single snapin and is relabeled All Snapins.
 *
 * It ran for a host with no live task as well. $Task is then an empty object,
 * nothing is queued, so the snapinTasks lookup comes back empty, in_array()
 * is false, and the arm sets three fields and saves -- INSERTING a task typed
 * All Snapins (12) and named "Multiple Snapin -- orig Single" for a first,
 * ordinary, single-snapin request. That task is valid, so the
 * `if (!$Task->isValid())` further down skips _createTasking() and the type
 * is never corrected. Only the snapin the admin picked is ever queued, so the
 * task runs correctly and reads wrong everywhere it is read: Active Tasks,
 * the task list's Task Type column, and the audit trail.
 *
 * The same request made for several hosts at once goes through
 * Group::createImagePackage(), which batch-inserts $TaskType->id verbatim and
 * is therefore right -- so one host tasked from its own page and forty tasked
 * from the host list produced two different task types for the same action.
 * Reported in forum topic 18238.
 *
 * 1.5 guarded the whole block on $Task->isValid()
 * (packages/web/lib/fog/host.class.php:1359 on dev-branch); e959f9d70 lost
 * the guard in 2018 while flattening the surrounding branches.
 *
 * WHAT THIS PINS is the guard and the two things it must not break: the
 * conversion itself, and the fact that the arm is a conversion rather than a
 * creation. Proved against a live database by
 * background_scripts/probe_single_snapin_tasktype.php, which fails on an
 * unpatched tree.
 *
 * Usage: php tests/single-snapin-task-keeps-its-type.test.php
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

$host = (string) file_get_contents($root . '/packages/web/src/Items/Host.php');

$arm = '';
if (preg_match(
    '/case TaskType::SINGLE_SNAPIN:(.*?)\n\s+case TaskType::ALL_SNAPINS:/s',
    $host,
    $m
)) {
    $arm = $m[1];
}
check(
    'the SINGLE_SNAPIN arm was found',
    '' !== $arm,
    $failures,
    $checks
);

/*
 * The guard has to come BEFORE the save, not merely exist somewhere in the
 * arm -- a check after the write would be no check at all.
 */
$guardAt = strpos($arm, 'if (!$Task->isValid())');
$saveAt = strpos($arm, '$Task->save()');
check(
    'it bails out when the host has no task to convert',
    false !== $guardAt,
    $failures,
    $checks
);
check(
    'and does so before anything is written',
    false !== $guardAt && false !== $saveAt && $guardAt < $saveAt,
    $failures,
    $checks
);

/*
 * A conversion, not a creation. Setting hostID is what a new row needs and an
 * existing one already has, so its presence is the signature of the arm
 * believing it may insert.
 */
check(
    'it does not set hostID -- it is updating, not creating',
    false === strpos($arm, "->set('hostID'"),
    $failures,
    $checks
);

/*
 * The behavior the arm exists for still has to happen: a second, different
 * snapin on a host that already has one queued relabels the task.
 */
check(
    'the conversion to All Snapins is still there',
    false !== strpos($arm, "->set('typeID', TaskType::ALL_SNAPINS)"),
    $failures,
    $checks
);
check(
    'and still only when the requested snapin is not already queued',
    false !== strpos($arm, 'if (!in_array($deploySnapins, $curSnapins))'),
    $failures,
    $checks
);

/*
 * The corrected path: with the arm standing down, the ordinary creation
 * below has to be the thing that writes the task, and it takes the type it
 * was asked for.
 */
check(
    '_createTasking is still what writes a task the arm did not convert',
    false !== strpos($host, '$Task = $this->_createTasking(')
    && false !== strpos($host, "\$TaskType->id,"),
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
