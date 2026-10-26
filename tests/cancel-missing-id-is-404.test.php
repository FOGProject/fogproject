<?php
/**
 * DELETE {class}/[id]/cancel with an id that does not exist must answer 404.
 *
 * Route::cancel()'s `default:` arm used `!$class->isValid()` to mean "no id
 * was given" and ran the bulk search. An id that was given but matched no row
 * is also not valid, so DELETE /task/999999/cancel canceled every active task
 * and answered 200. snapinjob, snapintask and multicastsession share the arm
 * (GH-1949).
 *
 * The arm now answers 404 through _requireFound() when an id was named. Only
 * the id-less route (null $id) keeps the bulk search.
 *
 * WHAT THIS PINS, driven through the real Route::cancel() on FogFakeDb:
 *
 *   - a named id that does not load, as the int a caller can pass and as the
 *     string the router hands over (including "0"): 404 and no UPDATE of
 *     tasks;
 *   - the id-less call still issues its state-filtered id search and then
 *     cancels what it found;
 *   - a named id that loads and is live still cancels (the arm was not
 *     turned into a blanket refusal).
 *
 * HOW: sendResponse() exits, so each case runs in its own child process with
 * Route::$_rethrowDepth raised, the seam route-cancel-guards.test.php uses.
 *
 * Usage: php tests/cancel-missing-id-is-404.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

/**
 * Runs one case and prints its marker.
 *
 * @param string $case 'missing-int', 'missing-str', 'missing-zero', 'bulk' or 'live'
 *
 * @return void
 */
function runMissingIdChild($case)
{
    FogTestHarness::boot('cancel-missing-id-child');
    $db = FogTestHarness::fakeDb();
    FogTestHarness::setStatic('Route', '_rethrowDepth', 1);

    $db->responder = function ($sql, $params) use ($case) {
        $flat = preg_replace('/\s+/', ' ', trim($sql));
        if (false === strpos($flat, 'FROM `tasks`')) {
            return null;
        }
        // The load of one task by id. Only `live` has a row for it.
        if (false !== strpos($flat, '`taskID`=')) {
            return ('live' === $case)
                ? [
                    'taskID' => 1, 'taskName' => 'net', 'taskStateID' => 3,
                    'taskTypeID' => 1, 'taskHostID' => 1,
                ]
                : null;
        }
        // The bulk arm's id search: two active tasks.
        return [['taskID' => 11, 'id' => 11], ['taskID' => 12, 'id' => 12]];
    };

    $ids = [
        'missing-int' => 999999,
        'missing-str' => '999999',
        'missing-zero' => '0',
        'bulk' => null,
        'live' => 1,
    ];
    $logFrom = count($db->log);
    $refusal = null;
    try {
        \FOG\Router\Route::cancel('task', $ids[$case]);
    } catch (\RuntimeException $e) {
        $refusal = $e;
    }
    $flat = preg_replace(
        '/\s+/',
        ' ',
        implode(' ~ ', array_slice($db->log, $logFrom))
    );
    $flags = [];
    // The id-less search: a task lookup carrying the state filter.
    if (false !== strpos($flat, '`taskStateID` IN')) {
        $flags[] = 'search';
    }
    if (false !== strpos($flat, 'UPDATE `tasks`')) {
        $flags[] = 'taskwrite';
    }
    $shape = $flags ? implode(',', $flags) : '-';
    if (null !== $refusal) {
        printf("REFUSED %d %s\n", (int)$refusal->getCode(), $shape);
        return;
    }
    printf("PROCEEDED %s\n", $shape);
}

/**
 * Runs one case in a child process and returns its marker line.
 *
 * @param string $case the case name
 *
 * @return string
 */
function missingIdChild($case)
{
    $cmd = sprintf(
        '%s %s --case=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__FILE__),
        escapeshellarg($case)
    );
    $out = (string)shell_exec($cmd);
    foreach (preg_split('/\r?\n/', $out) as $line) {
        $line = trim($line);
        if (preg_match('/^(REFUSED|PROCEEDED)\b/', $line)) {
            return $line;
        }
    }
    return 'NO MARKER: ' . trim(preg_replace('/\s+/', ' ', $out));
}

foreach ($argv as $arg) {
    if (0 === strpos($arg, '--case=')) {
        runMissingIdChild(substr($arg, 7));
        exit(0);
    }
}

FogTestHarness::boot('cancel-missing-id');
FogTestHarness::fakeDb();
$t = new FogChecks();

foreach (['missing-int', 'missing-str', 'missing-zero'] as $case) {
    $out = missingIdChild($case);
    $t->check(
        "$case: a named id that does not exist answers 404: $out",
        0 === strpos($out, 'REFUSED 404')
    );
    $t->check(
        "$case: nothing is searched or written: $out",
        false === strpos($out, 'taskwrite') && false === strpos($out, 'search')
    );
}

$bulk = missingIdChild('bulk');
$t->check(
    'id-less: the bulk arm still runs its state-filtered search: ' . $bulk,
    0 === strpos($bulk, 'PROCEEDED') && false !== strpos($bulk, 'search')
);

$live = missingIdChild('live');
$t->check(
    'a named id that loads and is live still cancels: ' . $live,
    0 === strpos($live, 'PROCEEDED')
);

$t->finish();
