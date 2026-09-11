<?php
/**
 * Route::cancel() must not report work it did not do.
 *
 * Every arm of this method had a path that returned normally -- and so 200 --
 * having changed nothing, or that died partway with an error saying nothing
 * about the actual situation:
 *
 *  - a task in a finished state fell out of the state test and answered
 *    "cancelled" with its state untouched;
 *  - Host::loadTask() sets its field to `new Task(null)` when nothing is
 *    running, and that IS `instanceof Task`, so the guard was true for every
 *    host and cancel() went on to save a Task with no typeID. save() throws,
 *    and cancel() has no catch -- so one idle member of a group aborted the
 *    whole loop and every host after it kept its task running;
 *  - scheduledtask carries isActive rather than stateID, so it failed a test
 *    it could never pass: that endpoint has never cancelled anything.
 *
 * Ported from working-1.6. Narrower here by fact, not by choice: 1.5 has no
 * filedeletequeue among its tasking classes and no OpenAPI document, so the
 * two assertions covering those have nothing to bind to.
 *
 * Usage: php tests/cancel-route-reports-truthfully.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$route = file_get_contents($web . '/lib/router/route.class.php');

$fails = array();

// Isolate cancel() so a match elsewhere in this class cannot stand in for the
// thing being tested.
if (!preg_match(
    '#public static function cancel\(\$class, \$id\).*?\n    \}\n#s',
    $route,
    $m
)) {
    echo "FAIL: Route::cancel() not found -- the gate cannot see its subject\n";
    exit(1);
}
$cancel = $m[0];

// ------------------------------------------------- the requested resource

// Bounded to the helper's own body: an open-ended match finds json_encode
// somewhere else in the file and passes a mutation that removed it.
if (!preg_match(
    '#private static function _notCancellable\(\$msg\)\s*\{(.*?)\n    \}#s',
    $route,
    $helper
)) {
    $fails[] = 'Route::_notCancellable() does not exist, so the 409s are'
        . ' hand-rolled at each call site';
} elseif (false === strpos($helper[1], 'HTTP_CONFLICT')
    || false === strpos($helper[1], 'json_encode')
) {
    $fails[] = '_notCancellable() does not answer 409 with a JSON body, so a'
        . ' refused cancel is indistinguishable from the router\'s older'
        . ' bare-string errors';
}

// A named task in a finished state. Without the negated test the method
// falls through the if and returns -- 200, nothing done.
if (!preg_match(
    '#if \(!in_array\(\$class->get\(\'stateID\'\), \$states\)\).*?_notCancellable#s',
    $cancel
)) {
    $fails[] = 'a named task outside the active states does not answer 409,'
        . ' so cancelling a Complete or Cancelled task reports success';
}

// ------------------------------------------------------- the empty Task

// Both arms, because they fail identically and were fixed together. The
// group arm is the worse of the two: it aborts a loop it is halfway through.
if (substr_count($cancel, '$Task->isValid()') < 2) {
    $fails[] = 'the host and group arms do not both test the loaded task for'
        . ' validity -- instanceof alone is true for the empty Task that'
        . ' Host::loadTask() leaves behind, so an idle host tries to save it';
}
// Bounded to the group arm. Left open-ended, the match ran on and found the
// host arm's refusal, so dropping the group's own was invisible.
if (!preg_match('#case \'group\':(.*?)\n                break;#s', $cancel, $grp)) {
    $fails[] = 'the group arm is not where this gate can see it';
} elseif (false === strpos($grp[1], '$cancelled++')
    || !preg_match(
        '#if \(\$cancelled\s*<\s*1\)\s*\{\s*self::_notCancellable#s',
        $grp[1]
    )
) {
    // The CONDITION, not just the presence of the call. Pinning only the
    // call left `if (false) { self::_notCancellable(...) }` passing -- the
    // refusal was still in the source and could no longer happen.
    $fails[] = 'the group arm does not count what it cancelled and refuse on'
        . ' zero, so a group with nothing running still reports success';
}

// ------------------------------------------------------- scheduledtask

// It has no stateID at all, so it can only ever be handled by an arm of its
// own.
if (!preg_match('#case \'scheduledtask\':.*?\$class->cancel\(\)#s', $cancel)) {
    $fails[] = 'scheduledtask has no arm of its own, so it falls into the'
        . ' state test it cannot pass and the endpoint cancels nothing';
}

// ---------------------------------------------------- the bulk arm is not

// The search-driven arm is a filter, and matching nothing is a legitimate
// result for one. If this starts refusing, the 409 has spread from "you
// named a thing I did not touch" to "your filter was empty".
if (!preg_match(
    '#\$find\[\'stateID\'\] = \$states;(.*?)\n                \} else \{#s',
    $cancel,
    $bulk
)) {
    $fails[] = 'the search-driven bulk arm is not where this gate can see it,'
        . ' so nothing checks that it still answers 200 on an empty filter';
} elseif (false !== strpos($bulk[1], '_notCancellable')) {
    $fails[] = 'the search-driven bulk arm now refuses an empty filter result;'
        . ' matching no rows is a legitimate outcome for a filter';
}

if ($fails) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "ok: the cancel route refuses what it cannot cancel\n";
exit(0);
