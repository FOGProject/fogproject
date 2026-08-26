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
 * THREE WAYS IN, all closed here:
 *
 *   1. Written that way. FOGController::save() refuses a required *ID that is
 *      not an integer >= 1; FOGManagerController::insertBatch() enforced
 *      nothing, so the same model was validated one row at a time and not a
 *      hundred at a time. A 0 is legal to the server under any sql_mode.
 *   2. Written that way by the group deploy branch specifically, which zipped
 *      a host list ordered by id against an image list ordered by host name.
 *   3. Stranded afterwards. Deleting an image reset hosts.imageID and removed
 *      the imageassociation rows and said nothing about `tasks`.
 *
 * And one that makes a GOOD task look like a lost one: checkout() marked the
 * task Complete before it wrote the imaging log, so a failure writing that
 * log left FOS retrying against a task that was already finished, answered
 * "No Active Task found for Host" forever.
 *
 * WHAT THIS PINS is the mechanism, not a call site -- a test that walked
 * today's group-tasking branches would go green the moment someone added a
 * branch it did not know about, which is exactly how this arrived.
 *
 * DB-free: this reads the source, like its sibling
 * insertbatch-required-columns.test.php.
 *
 * Usage: php tests/null-tasks-cannot-be-created-or-stranded.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';
$failures = array();
$checks = 0;

/**
 * Source with comments removed, so a commented-out line can neither satisfy
 * a check nor fail one.
 *
 * @param string $file the file to read
 *
 * @return string
 */
function ntStrip($file)
{
    $clean = '';
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

/**
 * Records a check.
 *
 * @param bool   $ok      whether it passed
 * @param string $message what failed, stated as the defect
 *
 * @return void
 */
function ntCheck($ok, $message)
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
    }
}

$man = ntStrip($web . '/lib/fog/fogmanagercontroller.class.php');
$image = ntStrip($web . '/lib/fog/image.class.php');
$group = ntStrip($web . '/lib/fog/group.class.php');
$queue = ntStrip($web . '/lib/reg-task/taskqueue.class.php');
$element = ntStrip($web . '/lib/reg-task/taskingelement.class.php');

// ---------------------------------------------------------------
// 1. insertBatch() applies the rule save() applies.
// ---------------------------------------------------------------
preg_match(
    '#private function _assertBatchForeignKeys\(.*?\n    \}#s',
    $man,
    $m
);
$guard = isset($m[0]) ? $m[0] : '';
ntCheck(
    $guard !== '',
    'insertBatch() has no required-foreign-key guard, so a 0 in a required '
    . '*ID column is written without complaint'
);

preg_match('#public function insertBatch\(.*?\n    \}#s', $man, $m);
$batch = isset($m[0]) ? $m[0] : '';
ntCheck($batch !== '', 'insertBatch() could not be isolated');
ntCheck(
    strpos($batch, '_assertBatchForeignKeys(') !== false,
    'insertBatch() no longer calls the guard'
);

/*
 * Order matters and is invisible at the call. The loop that builds $keys
 * rewrites $fields IN PLACE from friendly names to column names, so a guard
 * running after it would be comparing 'taskHostID' against 'hostID' and
 * matching nothing -- passing everything, silently, forever.
 */
$callPos = strpos($batch, '_assertBatchForeignKeys(');
$rewritePos = strpos($batch, '$key = $this->databaseFields[$key];');
ntCheck(
    $callPos !== false && $rewritePos !== false && $callPos < $rewritePos,
    'the guard runs after $fields has been rewritten to column names, so it '
    . 'matches nothing and passes everything'
);

/*
 * The rule has to be the same one save() and isValid() apply, or an object
 * validates itself on one write path and not the other -- the split that
 * produced this.
 */
ntCheck(
    preg_match("#FILTER_VALIDATE_INT.*?'min_range'\s*=>\s*1#s", $guard) === 1,
    'the guard no longer demands an integer >= 1, which is the rule save() '
    . 'and isValid() apply to the same field'
);
ntCheck(
    strpos($guard, '$this->databaseFieldsRequired') !== false,
    'the guard no longer reads the model\'s own required list'
);

/*
 * Three exemptions, each of which turns the guard from a fix into a new
 * outage if it goes missing.
 */
ntCheck(
    preg_match("#'id'\s*===\s*\\\$lower#", $guard) === 1,
    'the guard demands the model\'s own auto-increment id, which no batch '
    . 'caller supplies -- every insertBatch would throw'
);
ntCheck(
    preg_match("#'id'\s*!==\s*substr\(\\\$lower,\s*-2\)#", $guard) === 1,
    'the guard no longer restricts itself to keys ending in ID, so a '
    . 'required string column is refused for not being an integer'
);
ntCheck(
    strpos($guard, '$this->databaseFieldsNotInt') !== false,
    'the guard no longer honours the model\'s $databaseFieldsNotInt '
    . 'opt-out, so a string identifier declared required is refused here '
    . 'while save() accepts it'
);

/*
 * That opt-out only reaches the manager if the constructor pulls it off the
 * model. It is the one property in the list that was not being pulled.
 */
ntCheck(
    preg_match("#'databaseFieldsNotInt',#", $man) === 1
    && preg_match(
        '#\$this->databaseFieldsNotInt\s*=\s*&\$classVars\[#',
        $man
    ) === 1,
    'FOGManagerController no longer pulls $databaseFieldsNotInt from its '
    . 'model, so the opt-out above is always empty'
);

/*
 * Only columns the caller NAMED. Every batch call site relies on being able
 * to stay silent about a column and let columnsRequiringValue() fill it;
 * demanding they name it is a different change with a different blast radius.
 */
ntCheck(
    preg_match('#foreach \(\(array\)\$fields as \$i => \$named\)#', $guard)
    === 1,
    'the guard no longer restricts itself to the columns the caller named, '
    . 'so a column the batch is silent about is now an error'
);

// ---------------------------------------------------------------
// 2. Deleting an image cancels the tasks queued against it.
// ---------------------------------------------------------------
preg_match('#public function destroy\(.*?\n    \}#s', $image, $m);
$destroy = isset($m[0]) ? $m[0] : '';
ntCheck($destroy !== '', 'Image::destroy() could not be isolated');
ntCheck(
    strpos($destroy, "getClass('TaskManager')") !== false
    && strpos($destroy, '->cancel(') !== false,
    'deleting an image no longer cancels the tasks still queued against it, '
    . 'so each one is left pointing at an image row that does not exist'
);
ntCheck(
    strpos($destroy, 'getQueuedStates()') !== false
    && strpos($destroy, 'getProgressState()') !== false,
    'the image delete no longer restricts itself to live tasks'
);
/*
 * The half that is easy to lose. A host delete takes its tasks with it
 * because the subject of that history is gone; an image delete must NOT,
 * because the hosts survive and their finished tasks are still their imaging
 * record.
 */
ntCheck(
    strpos($destroy, "getClass('TaskManager')->destroy") === false
    && strpos($destroy, "getClass('TaskManager')\n            ->destroy")
    === false,
    'the image delete removes task rows outright rather than cancelling '
    . 'them, which throws away the imaging history of hosts that survive'
);
/*
 * And it has to find them by the image being deleted, not by anything wider.
 */
ntCheck(
    preg_match(
        "#getSubObjectIDs\(\s*'Task',.*?'imageID' => \\\$this->get\('id'\)#s",
        $destroy
    ) === 1,
    'the image delete no longer scopes the cancellation to the tasks '
    . 'pointing at THIS image'
);

// ---------------------------------------------------------------
// 3. Group deploy gives each host ITS OWN image.
// ---------------------------------------------------------------
preg_match(
    '#public function createImagePackage\(.*?\n    \}#s',
    $group,
    $m
);
$package = isset($m[0]) ? $m[0] : '';
ntCheck($package !== '', 'createImagePackage() could not be isolated');
ntCheck(
    strpos($package, '$imageMap[$Host->get(\'id\')]') !== false,
    'the group deploy no longer maps each host id to its own image, so the '
    . 'image list is zipped positionally against the host list again'
);
ntCheck(
    preg_match('#\$imageIDs\[\$i\]#', $package) === 0,
    'the group deploy indexes a flat image list by host position. The two '
    . 'lists are ordered differently and a host with no image shortens one '
    . 'of them, so the tail of the group is tasked with image 0'
);

// ---------------------------------------------------------------
// 4. The imaging log is written BEFORE the task is marked Complete.
// ---------------------------------------------------------------
preg_match('#public function checkout\(\).*?\n    \}#s', $queue, $m);
$checkout = isset($m[0]) ? $m[0] : '';
ntCheck($checkout !== '', 'TaskQueue::checkout() could not be isolated');
$logPos = strpos($checkout, '$this->imageLog(false)');
$savePos = strpos($checkout, '$this->Task->save()');
ntCheck(
    $logPos !== false && $savePos !== false && $logPos < $savePos,
    'checkout() marks the task Complete before writing the imaging log. A '
    . 'failure writing the log then throws with the task already finished, '
    . 'so FOS retries forever against a task that no longer exists and the '
    . 'host is told "No Active Task found for Host" after imaging perfectly'
);

/*
 * And the no-open-log branch has to be able to succeed. ImagingLog declares
 * hostID, start and image required, so setting only `finish` on a row that
 * had to be created could ONLY ever fail.
 */
preg_match('#protected function imageLog\(.*?\n    \}#s', $element, $m);
$imageLog = isset($m[0]) ? $m[0] : '';
ntCheck($imageLog !== '', 'imageLog() could not be isolated');
ntCheck(
    strpos($imageLog, '->isValid()') !== false
    && strpos($imageLog, "->set('hostID'") !== false
    && strpos($imageLog, "->set('start'") !== false
    && strpos($imageLog, "->set('image'") !== false,
    'imageLog()\'s checkout branch no longer completes a row it had to '
    . 'create. ImagingLog requires hostID, start and image, so setting only '
    . 'finish is a guaranteed "Failed to update imaging log"'
);

ntCheck(
    strlen($man) > 10000
    && strlen($image) > 5000
    && strlen($group) > 10000
    && strlen($queue) > 5000
    && strlen($element) > 5000,
    'the scan did not reach the sources and would pass vacuously'
);

if (count($failures) > 0) {
    echo 'FAIL null-tasks-cannot-be-created-or-stranded ('
        . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS null-tasks-cannot-be-created-or-stranded ($checks checks)\n";
exit(0);
