<?php
/**
 * The ClamAV virus scan is gone, and its removal step is ordered correctly.
 *
 * GH-328. The feature had already stopped working on this branch: FOS
 * `bin/fog.av` posts its findings to `service/av.php`, 1.6 never carried
 * that endpoint across from 1.5, and there is no model, manager, report or
 * page that reads the `virus` table. What was left was two task types
 * offering a scan that could not report anything, plus a `clamav=` kernel
 * argument feeding a boot image that had nowhere to send its results.
 *
 * TWO THINGS ARE PINNED, and the second is the one that can silently break
 * an upgrade:
 *
 *   1. Nothing offers the scan any more. Task lists are built from the
 *      `taskTypes` table itself (FOGPageRender::taskTypeAccordion() reads it
 *      through Route::getList('tasktype')), so the rows ARE the feature --
 *      re-seeding one puts the entry back on every page and in the API with
 *      no other code change.
 *
 *   2. Step 406 deletes the referencing rows BEFORE the task types.
 *      `tasks`.`taskTypeID` and `scheduledTasks`.`stTaskTypeID` both
 *      reference `taskTypes`.`ttID` ON DELETE RESTRICT (ADR 0031, groups 6
 *      and 5), and 1451 is not in Schema::runSteps()'s skippable list. Get
 *      the order wrong and the update aborts on ?node=schema for every
 *      server that has ever queued a scan -- while passing on a clean test
 *      database, which is exactly the shape of failure that reaches users.
 *
 * The historical steps that CREATED the table and seeded the rows stay put:
 * commons/schema.php is an append-only replay log, and rewriting an old step
 * changes what a 1.5 database replays into.
 *
 * Usage: php tests/virus-scan-is-removed.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('virus-scan-is-removed');

$t = new FogChecks();
$root = dirname(__DIR__);
$web = $root . '/packages/web';

$schema = (string)file_get_contents($web . '/commons/schema.php');
$manifest = include $web . '/commons/schema-expected.php';
$constraints = include $web . '/commons/schema-constraints.php';

/*
 * 1. The step exists and is the last one. That FOG_SCHEMA matches the
 * step count is already pinned by tests/schema-gate.test.php.
 */
$t->check(
    'the removal is schema step 406',
    false !== strpos($schema, "\n// 406\n")
    && false === strpos($schema, "\n// 407\n")
);
$step = substr($schema, (int)strpos($schema, "\n// 406\n"));

/*
 * 2. The delete order. Both children come before the parent, or an upgrade
 * on a server that has ever run a scan aborts at 1451.
 */
$posTypes = strpos($step, "DELETE FROM `taskTypes`");

// The two child deletes are built from one loop over table => column, so
// what is asserted is that both columns are named and that the taskTypes
// delete is emitted after the loop that names them.
$posLoop = strpos($step, "'scheduledTasks' => 'stTaskTypeID'");
$t->check(
    'the scheduledTasks child is deleted by its own type column',
    false !== $posLoop
    && false !== strpos($step, "'tasks' => 'taskTypeID'")
);
$t->check(
    'the task types are deleted after the rows that reference them',
    false !== $posTypes
    && false !== $posLoop
    && $posTypes > $posLoop
);
$t->check(
    'the parent delete names all three ids',
    false !== strpos(
        $step,
        "DELETE FROM `taskTypes` WHERE `ttID` IN (9, 21, 22)"
    )
);
$t->check(
    'the virus table is dropped in the same step',
    false !== strpos($step, "Schema::dropTable('virus')")
);

// Every RESTRICT parent of taskTypes must be one of the tables the step
// clears. A new one added later without a matching delete would abort the
// upgrade, and nothing else in the tree would notice.
$restricts = [];
foreach ($constraints as $rel) {
    if (!is_array($rel) || ($rel['parent'] ?? '') !== 'taskTypes') {
        continue;
    }
    if (empty($rel['enabled']) || ($rel['action'] ?? '') !== 'RESTRICT') {
        continue;
    }
    $restricts[] = $rel['child'];
}
$t->check(
    'every enabled RESTRICT child of taskTypes is cleared by the step',
    count($restricts) > 0
    && count(array_filter(
        $restricts,
        function ($child) use ($step) {
            return false !== strpos($step, "'" . $child . "' => '");
        }
    )) === count($restricts)
);

/*
 * 3. Nothing is left offering the scan.
 */
$t->check(
    'the constraint map no longer describes the virus table',
    0 === count(array_filter(
        $constraints,
        function ($rel) {
            return is_array($rel)
                && ('virus' === ($rel['child'] ?? '')
                    || 'virus' === ($rel['parent'] ?? ''));
        }
    ))
);
$t->check(
    'the manifest no longer expects a virus table',
    !isset($manifest['tables']['virus'])
);
$t->check(
    'the manifest declares it retired, with a reason',
    0 < count(array_filter(
        (array)($manifest['retired'] ?? []),
        function ($row) {
            return 'virus' === ($row['table'] ?? '')
                && '' !== trim((string)($row['reason'] ?? ''));
        }
    ))
);

// The boot menu is where the scan reached the boot image at all.
$boot = (string)file_get_contents($web . '/src/Boot/BootMenuBase.php');
$t->check(
    'no clamav kernel argument is emitted',
    false === stripos($boot, 'clamav')
);
$t->check(
    'the boot menu no longer special-cases task types 21 and 22',
    false === strpos($boot, '[21, 22]')
);

/*
 * 4. The replay log is intact. Rewriting the steps that created the table
 * and seeded the rows would change what a 1.5 database replays into.
 */
$t->check(
    'the historical CREATE TABLE `virus` step is still present',
    false !== strpos($schema, "'CREATE TABLE `virus` (")
);
$t->check(
    'the historical Virus Scan seed rows are still present',
    false !== strpos($schema, "(21, 'Virus Scan'")
    && false !== strpos($schema, "(22, 'Virus Scan - Quarantine'")
);

$t->finish();
