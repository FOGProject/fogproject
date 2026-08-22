<?php
/**
 * A grid that sorts by host NAME must have the host joined into its query.
 *
 * The group page's three history tabs -- Login, Task and Snapin -- group
 * their rows by host, and DataTables' RowGroup only groups correctly when
 * the grouped column is also the primary sort. So the sort key decides the
 * order the hosts appear in, and sorting on a raw host id lists them in the
 * order they were created rather than alphabetically.
 *
 * The fix has two halves that live in different files and must agree:
 *
 *   1. The model (TaskLog, UserTracking, SnapinTask) declares a join to
 *      `hosts` in $sqlQueryStr, $sqlFilterStr and $sqlTotalStr. The default
 *      FOGController templates carry no join at all, which is why this is a
 *      per-model override and not something the grid can do for itself.
 *   2. Route::_hostNameOrder() gives that class's `hostLink` column an
 *      `order` key naming `hosts`.`hostName`.
 *
 * Half 2 without half 1 is an ORDER BY on a table that is not in the query:
 * "Unknown column 'hosts.hostName' in 'order clause'", which surfaces as a
 * grid that loads and then reports an error, on a page nothing else touches.
 * Half 1 without half 2 is a wasted join and an id-ordered tab -- silent.
 *
 * So this drives the real column builder for every routed class and checks
 * the two halves against each other, in both directions:
 *
 *   - every class whose hostLink carries a host-name order declares all
 *     three join strings;
 *   - and no class that does NOT declare them carries such an order, which
 *     is the half that catches the generic `case 'hostID':` block -- it
 *     serves every class with a host id, so an order added there
 *     unconditionally would break Task, NodeFailure, SnapinJob and the rest.
 *
 * The three tabs are also asserted by name, so removing the order from one
 * of them fails here rather than quietly reverting the tab to id order.
 *
 * Usage: php tests/grid-host-name-order.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('host-name-order');

$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

// Unrestricted, so the column table is captured whole rather than the subset
// one permission set happens to reach. Same reasoning as the column contract.
$admin = FOGCore::getClass('User')->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/**
 * Captures the column table for one class at the point plugins receive it.
 *
 * Matched on classname because listing some classes runs nested listem()
 * calls for others -- see route-column-contract.test.php, which explains it
 * at length.
 */
class HostOrderHook extends Hook
{
    /** @var array|null the table for the class under test */
    public static $captured = null;

    /** @var string|null the class being listed */
    public static $want = null;

    public $name = 'HostOrderHook';
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
new HostOrderHook();

/**
 * Builds one class's column table.
 *
 * @param string $classname the lowercased class to list
 *
 * @return array|null the columns, or null if none were built
 */
function hostOrderColumns($classname)
{
    HostOrderHook::$captured = null;
    HostOrderHook::$want = $classname;
    Route::$data = [];
    // Rendering a synthetic row is noise here and the table is already
    // captured before the first row is formatted. Suppressed narrowly, for
    // the same reasons route-column-contract.test.php sets out.
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
        // Intentionally ignored; see above.
    }
    if (false !== $prevLog) {
        ini_set('error_log', $prevLog);
    }
    error_reporting($prevLevel);
    ob_end_clean();
    Route::$data = [];
    return HostOrderHook::$captured;
}

/**
 * Reads a model's three list-query templates.
 *
 * They are protected, and read off the CLASS rather than an instance so a
 * class that cannot be constructed without a database still answers.
 *
 * @param string $classname the lowercased class
 *
 * @return array|null the three strings, or null if the class is unknown
 */
function hostOrderQueryStrings($classname)
{
    $obj = FOGCore::getClass($classname);
    if (!is_object($obj)) {
        return null;
    }
    $out = [];
    foreach (['sqlQueryStr', 'sqlFilterStr', 'sqlTotalStr'] as $prop) {
        $ref = new \ReflectionProperty(get_class($obj), $prop);
        $ref->setAccessible(true);
        $out[$prop] = (string)$ref->getValue($obj);
    }
    return $out;
}

$t = new FogChecks();

// The three tabs the change exists for. Named rather than derived: losing
// one has to fail, and a derived list would simply get shorter.
$expected = ['tasklog', 'usertracking', 'snapintask'];

$found = [];
$classes = (array)FogTestHarness::getStatic('Route', 'validClasses');
sort($classes);
foreach ($classes as $classname) {
    $columns = hostOrderColumns($classname);
    if (null === $columns) {
        continue;
    }
    $order = null;
    foreach ((array)$columns as $col) {
        if (!isset($col['dt']) || 'hostLink' !== $col['dt']) {
            continue;
        }
        if (isset($col['order'])) {
            $order = (string)$col['order'];
        }
        break;
    }
    if (null === $order) {
        continue;
    }
    $found[] = $classname;
    $t->check(
        "$classname sorts hostLink by the joined host name",
        false !== strpos($order, '`hosts`.`hostName`')
    );
    $strings = hostOrderQueryStrings($classname);
    $t->check(
        "$classname is a class with query templates to check",
        null !== $strings
    );
    foreach ((array)$strings as $prop => $sql) {
        $t->check(
            "$classname joins `hosts` in \$$prop",
            false !== strpos($sql, 'JOIN `hosts`')
        );
    }
}

sort($found);
$want = $expected;
sort($want);
$t->check(
    'exactly the three group history classes sort by host name ('
    . (count($found) ? implode(', ', $found) : 'none') . ')',
    $found === $want
);

$t->finish();
