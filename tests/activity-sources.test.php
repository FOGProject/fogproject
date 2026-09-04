<?php
/**
 * ADR 0023 item 5: the activity viewer's extra sources, and their gates.
 *
 * The viewer is one grid over several tables, with four output columns --
 * createdBy, createdTime, summary, ip -- and a select that chooses which
 * table to read. Two things about that arrangement fail silently, and this
 * file is both of them:
 *
 * 1. **A source whose class does not emit all four columns renders blanks.**
 *    DataTables asks for a field the payload does not carry and draws an
 *    empty cell. No error, no console message, no failed request: the page
 *    looks like it works and says nothing. `summary` is the one that matters
 *    most -- it is the "What" column, so getting it wrong empties the only
 *    column with the content in it -- and it is also the one that is built
 *    per class rather than mapped, so it is the one that can be forgotten
 *    when a fourth source is added.
 *
 * 2. **A source offered without its own permission widens who can read that
 *    table.** `getList` resolves to `activity.view` by naming convention, so
 *    the page's gate is the only gate unless a source declares another.
 *    `userTracking` is the case that matters: ADR 0023 item 1 split it out
 *    of `report` precisely so one grant would stop reading a movement log
 *    for named people, and the permission registry says of that node that
 *    "everything that reads it ... resolves here". This viewer is a reader
 *    of it. `taskLog` resolves to `task`, where Task Management's log pane
 *    already sits.
 *
 *    `history` deliberately declares none, which is what it has had since
 *    the page shipped, and requiring `report.view` for it was put to the
 *    maintainer and declined (2026-08-22). That is pinned here as a VALUE,
 *    not assumed: if it ever gains one, this test says so, because doing
 *    that takes the page away from an activity.view holder who has it
 *    today.
 *
 * DB-free. The source table and its gating are pure functions of the class
 * and the permission cache, and the column tables come from the same column
 * builder route-column-contract.test.php drives.
 *
 * Usage: php tests/activity-sources.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Base\Hook;
use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('activity-sources');

$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

$user = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $user);
}

/**
 * Sets the acting user's permissions for the checks that follow.
 *
 * @param array $perms the permission strings to hold
 *
 * @return void
 */
function grant(array $perms)
{
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => $perms]);
}

/**
 * Calls one of ActivityManagement's private statics.
 *
 * They are private because nothing outside the page should choose a source;
 * the test reads them for the same reason it exists -- the decision has no
 * other observable surface until it is already on somebody's screen.
 *
 * @param string $method the method name
 *
 * @return mixed
 */
function activity($method)
{
    $m = new \ReflectionMethod('FOG\Pages\ActivityManagement', $method);
    $m->setAccessible(true);
    return $m->invoke(null);
}

/**
 * Captures the column table for one class.
 */
class ActivitySourceHook extends Hook
{
    /** @var array|null the table for the class under test */
    public static $captured = null;

    /** @var string|null the class being listed */
    public static $want = null;

    public $name = 'ActivitySourceHook';
    public $description = 'Captures the DataTables column table';
    public $active = true;

    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register('CUSTOMIZE_DT_COLUMNS', [$this, 'grab']);
    }

    public function grab($arguments)
    {
        if (null !== self::$captured) {
            return;
        }
        if (null !== self::$want && $arguments['classname'] !== self::$want) {
            return;
        }
        self::$captured = $arguments['columns'];
    }
}
new ActivitySourceHook();

/**
 * The output column names one class produces.
 *
 * @param string $classname the lowercased class to list
 *
 * @return array
 */
function sourceColumns($classname)
{
    ActivitySourceHook::$captured = null;
    ActivitySourceHook::$want = $classname;
    Route::$data = [];
    ob_start();
    $prevLevel = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
    $prevLog = ini_set('error_log', FOG_LOG_DIR . '/render-noise.log');
    try {
        Route::asValue(
            function () use ($classname) {
                Route::listem($classname, false, true);
            }
        );
    } catch (\Throwable $e) {
        // Rendering a synthetic row is noise; the table is already captured.
    }
    if (false !== $prevLog) {
        ini_set('error_log', $prevLog);
    }
    error_reporting($prevLevel);
    ob_end_clean();
    Route::$data = [];
    $names = [];
    foreach ((array)ActivitySourceHook::$captured as $col) {
        if (isset($col['dt'])) {
            $names[] = $col['dt'];
        }
    }
    return $names;
}

$t = new FogChecks();

grant(['*']);
$all = activity('_allSources');

// The three sources, by name. Spelled out rather than counted so that
// LOSING one fails here instead of the list merely getting shorter.
$expected = [
    'history' => ['history', null],
    'usertracking' => ['usertracking', 'usertracking.view'],
    'tasklog' => ['tasklog', 'task.view']
];
$t->check(
    'the viewer offers exactly the three known sources ('
    . implode(', ', array_keys((array)$all)) . ')',
    array_keys((array)$all) === array_keys($expected)
);
foreach ($expected as $key => $meta) {
    if (!isset($all[$key])) {
        $t->check("$key is a source", false);
        continue;
    }
    $t->check(
        "$key reads the `{$meta[0]}` class",
        $all[$key][1] === $meta[0]
    );
    $t->check(
        "$key requires " . (null === $meta[1] ? 'no extra permission' : $meta[1]),
        $all[$key][2] === $meta[1]
    );
    $t->check(
        "{$meta[0]} is a routable class",
        in_array(
            $meta[0],
            (array)FogTestHarness::getStatic('Route', 'validClasses'),
            true
        )
    );
}

// Every source must emit all four of the viewer's columns. `summary` is the
// one built per class; the other three come from the field maps.
foreach ($expected as $key => $meta) {
    $names = sourceColumns($meta[0]);
    foreach (['createdBy', 'createdTime', 'summary', 'ip'] as $col) {
        $t->check(
            "{$meta[0]} emits the viewer's `$col` column",
            in_array($col, $names, true)
        );
    }
}

// The gate. A holder of the page and nothing else sees only the ungated
// source; the movement log needs its own grant.
grant(['activity.view']);
$t->check(
    'activity.view alone does not offer the movement log',
    !array_key_exists('usertracking', (array)activity('_sources'))
);
$t->check(
    'activity.view alone does not offer task activity',
    !array_key_exists('tasklog', (array)activity('_sources'))
);
$t->check(
    'activity.view alone still offers administrative actions',
    array_key_exists('history', (array)activity('_sources'))
);
$t->check(
    'and an unpermitted source in the URL is not selectable',
    'history' === activity('_requestedSource')
);

grant(['activity.view', 'usertracking.view']);
$t->check(
    'usertracking.view adds the movement log',
    array_key_exists('usertracking', (array)activity('_sources'))
);
grant(['activity.view', 'task.view']);
$t->check(
    'task.view adds task activity',
    array_key_exists('tasklog', (array)activity('_sources'))
);

// At least one source must stay ungated, and that is load-bearing rather
// than incidental: it is the only thing keeping "this user may read no
// source" unreachable. `_requestedSource()` guards it -- $keys[0] on an
// empty array is an undefined index that then becomes a class name -- but
// the guard is the safety net, not the design. Gating `history` too was
// considered and declined, so this is the check that fires if somebody
// later does it anyway: the empty path would have just gone live.
grant([]);
$bare = (array)activity('_sources');
$t->check(
    'a user holding nothing still has one source, so the empty path is dead'
    . ' code (' . implode(', ', array_keys($bare)) . ')',
    count($bare) > 0
);
$t->check(
    'and the chosen source is always one the user actually holds',
    in_array(activity('_requestedSource'), array_keys($bare), true)
);

$t->finish();
