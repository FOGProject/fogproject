<?php
/**
 * Route::cancel() must not report work it did not do.
 *
 * Every arm of this method used to have a path that returned normally --
 * and therefore 200 -- while changing nothing, or while changing far more
 * than was asked:
 *
 *  - a task in a finished state fell out of the state test and answered
 *    "cancelled" with its state untouched (the reason this file exists: a
 *    Failed task did exactly that);
 *  - a group cancelled by no state filter at all and by a paginated listing,
 *    so whatever came back was cancelled whatever state it was in -- and the
 *    listing covers every task the member hosts have ever run;
 *  - a host with nothing running reached Task::save() on an empty Task and
 *    answered 406 "Required database field is empty: typeID";
 *  - scheduledtask carries no stateID, so it failed a test it could never
 *    pass and the endpoint never once cancelled a scheduled task;
 *  - filedeletequeue has no model cancel(), so a valid id raised an Error,
 *    which is not an \Exception and so escaped the catch as a bodyless 500.
 *
 * All five are silent in exactly the same way: the caller is told the
 * resource was dealt with. That is what this gate pins.
 *
 * Usage: php tests/cancel-route-reports-truthfully.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$route = file_get_contents($web . '/lib/router/route.class.php');
$openapi = file_get_contents($web . '/lib/fog/openapi.class.php');

$fails = [];

// Isolate cancel() so a match elsewhere in this 5000-line class cannot
// stand in for the thing being tested.
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

// The 409 helper, and that it is what the arms call. Reached through
// sendResponse() directly the body would be a bare string under a JSON
// content type, which is the older shape this deliberately does not copy.
// Bounded to the helper's own body. An unbounded `.*?` here happily found
// json_encode somewhere else in the 5000 lines below and passed a mutation
// that removed it.
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
        . ' so cancelling a Complete/Cancelled/Failed task reports success';
}

// ------------------------------------------------------------- the group

// The state filter is the whole fix. Without it the arm cancels whatever the
// listing hands it: the same host filter matches 29 rows for group 2 on the
// development server, none of them active.
if (!preg_match(
    '#case \'group\':.*?getIds\(\s*\'task\',\s*\[\s*\'hostID\'[^\]]*?\'stateID\' => \$states#s',
    $cancel
)) {
    $fails[] = 'the group arm does not filter its task ids by state, so'
        . ' cancelling a group rewrites every task its hosts ever ran';
}
// listem() is paginated, so it was also the reason only one page of the
// intended work happened. Reading ids has no page.
if (false !== strpos($cancel, 'Route::listem(')) {
    $fails[] = 'the group arm still reads tasks through listem(), whose'
        . ' response is a paginated envelope, so it cancels one page';
}
// The CONDITION, not just the presence of the refusal: pinning only the call
// leaves `if (false) { self::_notCancellable(...) }` passing, with the
// refusal still in the source and no longer able to happen.
if (!preg_match('#case \'group\':(.*?)\n                    break;#s', $cancel, $grp)) {
    $fails[] = 'the group arm is not where this gate can see it';
} elseif (!preg_match(
    '#if \(count\(\$taskIDs\)\s*<\s*1\)\s*\{\s*self::_notCancellable#s',
    $grp[1]
)) {
    $fails[] = 'a group with no active tasks does not answer 409, so'
        . ' cancelling an idle group reports success';
}

// --------------------------------------------------------------- the host

// Host::loadTask() sets the field to `new Task(null)` when nothing is
// running, and that IS instanceof Task -- so instanceof alone is always
// true and proves nothing.
if (!preg_match('#\$Task->isValid\(\)#', $cancel)) {
    $fails[] = 'the host arm does not test the loaded task for validity, so'
        . ' an idle host tries to save an empty Task';
}

// ------------------------------------------------- the two silent classes

// scheduledtask has isActive, not stateID, so it can only ever be handled
// by an arm of its own.
if (!preg_match('#case \'scheduledtask\':.*?\$class->cancel\(\)#s', $cancel)) {
    $fails[] = 'scheduledtask has no arm of its own, so it falls into the'
        . ' state test it cannot pass and the endpoint cancels nothing';
}

// filedeletequeue is the one tasking class with no model cancel(), so the
// manager is the only implementation there is.
if (!preg_match(
    '#case \'filedeletequeue\':.*?getManager\(\)->cancel\(#s',
    $cancel
)) {
    $fails[] = 'filedeletequeue does not cancel through its manager, and it'
        . ' has no model cancel() -- the call raises an uncatchable Error';
}

// ------------------------------------------------------- the bulk arm is not

// The search-driven arm is a filter, and matching nothing is a legitimate
// result for one. If this ever starts refusing, the 409 has spread from
// "you named a thing I did not touch" to "your filter was empty", which is
// a different and wrong contract.
if (!preg_match(
    '#\$find\[\'stateID\'\] = \$states;(.*?)\n                    \} else \{#s',
    $cancel,
    $bulk
)) {
    $fails[] = 'the search-driven bulk arm is not where this gate can see it,'
        . ' so nothing checks that it still answers 200 on an empty filter';
} elseif (false !== strpos($bulk[1], '_notCancellable')) {
    $fails[] = 'the search-driven bulk arm now refuses an empty filter result;'
        . ' matching no rows is a legitimate outcome for a filter, and the'
        . ' 409 is only for a caller who named a specific resource';
}

// ------------------------------------------------------------- the document

// A route that answers a status the spec does not list is a route whose
// consumers cannot handle it. Only cancel returns 409, so it belongs on
// that operation and not in the shared _errorResponses() map.
if (!preg_match(
    '#private static function _conflictResponse\(\).*?\'409\'#s',
    $openapi
)) {
    $fails[] = 'openapi.class.php has no 409 response helper';
}
// Bounded to that one path entry, for the same reason as the helper above:
// _conflictResponse() is defined later in this file, so an open-ended match
// finds its own definition and passes whatever the cancel path says.
if (!preg_match(
    "#'/\{id\}/cancel'\] = \[(.*?)\n            \];#s",
    $openapi,
    $path
)) {
    $fails[] = 'the cancel path entry is not where this gate can see it';
} elseif (false === strpos($path[1], '_conflictResponse()')) {
    $fails[] = 'the cancel operation does not document its 409, so the spec'
        . ' describes a route that always succeeds';
}
if (preg_match(
    '#private static function _errorResponses\(\).*?\'409\'#s',
    $openapi
)) {
    $fails[] = 'the 409 has leaked into the shared error map, so every'
        . ' operation now claims a status only cancel can return';
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
