<?php
/**
 * A task must never end up pointing at nothing, and deleting an image must
 * not strand the tasks that were going to use it.
 *
 * THE FAILURE. A `tasks` row whose taskHostID / taskImageID / taskTypeID
 * match no row is permanent and unreadable at the same time. taskStateID
 * still resolves, so every "is this task live" test in the tree -- all of
 * which ask for getQueuedStates() plus getProgressState() -- counts it as
 * active. The Active Tasks list renders each cell from buildQuery()'s LEFT
 * OUTER JOINs and guards it with isset(), which is false for the NULL a
 * non-matching join returns, so the row draws as "() -" for host and image
 * with no type icon and no MAC. It can never complete: TaskingElement cannot
 * build the Image or the Host, so the machine is turned away at check-in.
 * Nothing reaps it. Reported as "null tasks" in forum topics 18228 and 18230.
 *
 * TWO WAYS IN, both closed here:
 *
 *   1. Written that way. FOGController::save() refuses a required *ID that is
 *      not an integer >= 1; FOGManagerController::insertBatch() enforced
 *      nothing, so the same model was validated one row at a time and not a
 *      hundred at a time. A 0 is legal to the server under any sql_mode.
 *   2. Stranded afterwards. Deleting an image reset hosts.imageID and removed
 *      the imageassociation rows and said nothing about `tasks`.
 *
 * WHAT THIS PINS is the mechanism, not a call site -- a test that walked
 * today's group-tasking branches would go green the moment someone added a
 * branch it did not know about, which is exactly how this arrived.
 *
 * DB-free: this reads the source, like insertbatch-required-columns. The
 * behavior is proved against the live database by
 * background_scripts/prove_batch_fk_guard.php and
 * background_scripts/prove_image_delete_cancels_tasks.php -- the second of
 * which fails on an unpatched tree, which is what makes it a gate.
 *
 * Usage: php tests/null-tasks-cannot-be-created-or-stranded.test.php
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

$man = (string) file_get_contents(
    "$root/packages/web/src/Base/FOGManagerController.php"
);
$route = (string) file_get_contents(
    "$root/packages/web/src/Router/Route.php"
);

/* ---------------------------------------------------------------- 1. guard */

$guard = '';
if (preg_match(
    '/private function _assertBatchForeignKeys\(.*?\n    \}/s',
    $man,
    $m
)) {
    $guard = $m[0];
}
check(
    'insertBatch has a required-foreign-key guard',
    '' !== $guard,
    $failures,
    $checks
);

$batch = '';
if (preg_match('/public function insertBatch\(.*?\n    \}/s', $man, $m)) {
    $batch = $m[0];
}
check(
    'insertBatch calls it',
    false !== strpos($batch, '_assertBatchForeignKeys('),
    $failures,
    $checks
);

/*
 * Order matters and is invisible at the call. The loop that builds $keys
 * rewrites $fields IN PLACE from friendly names to column names, so a guard
 * running after it would be comparing 'taskHostID' against 'hostID' and
 * matching nothing -- passing everything, silently, forever.
 */
$callPos = strpos($batch, '_assertBatchForeignKeys(');
$rewritePos = strpos($batch, '$key = $this->databaseFields[$key];');
check(
    'and calls it BEFORE $fields is rewritten to column names',
    false !== $callPos
    && false !== $rewritePos
    && $callPos < $rewritePos,
    $failures,
    $checks
);

/*
 * The rule has to be the same one save() applies, or an object validates
 * itself on one write path and not the other -- the split that produced this.
 */
check(
    'the guard demands an integer >= 1, as save() does',
    1 === preg_match(
        "/FILTER_VALIDATE_INT.*?'min_range'\s*=>\s*1/s",
        $guard
    ),
    $failures,
    $checks
);
check(
    'it reads the model\'s own required list',
    false !== strpos($guard, '$this->databaseFieldsRequired'),
    $failures,
    $checks
);

/*
 * Three exemptions, each of which turns the guard from a fix into a new
 * outage if it is missing.
 */
check(
    'it never demands the model\'s own auto-increment id',
    1 === preg_match("/'id'\s*===\s*\\\$lower/", $guard),
    $failures,
    $checks
);
check(
    'it only polices keys whose name ends in ID',
    1 === preg_match("/'id'\s*!==\s*substr\(\\\$lower,\s*-2\)/", $guard),
    $failures,
    $checks
);
check(
    'it honours the model\'s $databaseFieldsNotInt opt-out',
    false !== strpos($guard, '$this->databaseFieldsNotInt'),
    $failures,
    $checks
);

/*
 * That opt-out only reaches the manager if the constructor pulls it off the
 * model. It is the one property in the list that was not being pulled, and
 * without it a string identifier declared required would be refused here
 * while save() accepted it.
 */
check(
    'the manager pulls $databaseFieldsNotInt from its model',
    1 === preg_match(
        "/'databaseFieldsNotInt',\s*\n\s*\];/",
        $man
    )
    && 1 === preg_match(
        '/\$this->databaseFieldsNotInt\s*=\s*&\$classVars\[/',
        $man
    ),
    $failures,
    $checks
);

/*
 * Only columns the caller NAMED. Twenty-six call sites rely on being able to
 * stay silent about a column and let columnsRequiringValue() fill it;
 * demanding they name it is a different change with a different blast radius.
 */
check(
    'it only checks columns the caller actually named',
    1 === preg_match(
        '/foreach \(\(array\)\$fields as \$i => \$named\)/',
        $guard
    ),
    $failures,
    $checks
);

/* -------------------------------------------------- 2. image delete cascade */

$imageCase = '';
if (preg_match(
    "/case 'image':\s*\n\s*\\\$findWhere = \['imageID' => \\\$itemIDs\];.*?break;/s",
    $route,
    $m
)) {
    $imageCase = $m[0];
}
check(
    "deletemass's image arm was found",
    '' !== $imageCase,
    $failures,
    $checks
);
check(
    'deleting an image cancels the tasks still queued against it',
    false !== strpos($imageCase, "->cancel(")
    && false !== strpos($imageCase, "'task'"),
    $failures,
    $checks
);
check(
    'and only the ones that are still live',
    false !== strpos($imageCase, 'getQueuedStates()')
    && false !== strpos($imageCase, 'getProgressState()'),
    $failures,
    $checks
);

/*
 * The half that is easy to lose. A host delete takes its tasks with it
 * because the subject of that history is gone; an image delete must NOT,
 * because the hosts survive and their finished tasks are still their imaging
 * record. Putting 'task' into $removeItems here would silently delete it.
 */
check(
    'it does not delete task rows outright, as the host arm does',
    false === strpos($imageCase, "'task' => \$findWhere"),
    $failures,
    $checks
);

/*
 * TaskManager::cancel() rather than a bare state update: canceling a task
 * also has to reissue the host's token, unwind any multicast session behind
 * it and record the transition in the task log. A state UPDATE does none of
 * those and looks identical in the list.
 */
check(
    'it goes through TaskManager::cancel, not a bare state update',
    false !== strpos($imageCase, "getClass('TaskManager')"),
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
