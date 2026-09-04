<?php
/**
 * The grid column table, pinned as a golden file.
 *
 * `Route::listem()` builds a per-class table of column definitions -- 628
 * entries across 52 classes -- and hands it to plugins by reference on
 * `CUSTOMIZE_DT_COLUMNS` before rendering a single row. That table IS the
 * DataTables contract: the `dt` names are what every grid, every plugin
 * column injector and every consumer of the JSON reads.
 *
 * It is also 834 of route.class.php's 1,103 `listem()` lines, which makes it
 * the part most likely to be moved -- and a `switch` arm dropped during a
 * move, or a `use ($tmpcolumns)` lost off a closure, changes a column's
 * output with nothing to say so. `docs/route-listem-plan.md` commits 2 and 3
 * both edit this table; this is what gates them.
 *
 * WHAT IS PINNED, per column, in table order:
 *
 *   class  index  db  dt  formatter-binding  primed-classes
 *
 * `formatter-binding` is `-` for a column with no formatter, otherwise the
 * names the formatter closed over -- its `use (...)` list, read back with
 * ReflectionFunction::getStaticVariables(). That is there because losing one
 * is the specific way a move goes wrong quietly: a formatter that no longer
 * closes over `$tmpcolumns` or `$classname` does not fail to compile, it
 * silently renders a different cell. Nothing else here would notice.
 *
 * The last field is the interesting one and the reason this drives the code
 * rather than reading it. A column may carry a `prime` closure whose job is
 * to warm `Route::$relCache` so the formatter's `self::rel()` calls do not
 * become a query per row (GH-707). What a primer actually primes can only be
 * learned by RUNNING it, so each one is called against a synthetic row and
 * the resulting cache keys are recorded. `relColumn()` exists to make the
 * pairing structural; this is what proves a conversion to it changed nothing.
 *
 * Two entries legitimately prime nothing (`primac_vendor`, `mac_vendor`):
 * they warm `MACAddress`'s own vendor cache, not `$relCache`. Recorded as
 * `(none)` rather than special-cased, so the day one of them starts touching
 * $relCache the fixture says so.
 *
 * WHEN THIS FAILS. Either a column changed and should not have, or it changed
 * and should have. There is no way for the test to know which, so it prints
 * the differing lines and stops. If the change is intended:
 *
 *     php tests/route-column-contract.test.php --update
 *
 * and commit the fixture diff alongside the code -- it is the readable record
 * of what the change did to the API surface, which is the point of keeping it
 * in the tree rather than computing it twice.
 *
 * Usage: php tests/route-column-contract.test.php [--update]
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Base\Hook;
use FOG\Items\User;
use FOG\Router\Route;

require_once __DIR__ . '/lib/fog-test-harness.php';

$fixture = __DIR__ . '/fixtures/route-column-contract.txt';
$update = in_array('--update', array_slice(isset($argv) ? $argv : [], 1), true);

FogTestHarness::boot('column-contract');

$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

// An unrestricted user: the table must be captured whole, not the subset one
// permission set happens to reach.
$admin = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/**
 * Captures the column table at the point plugins receive it.
 *
 * MATCHED ON CLASSNAME, and that is not defensive tidiness. Listing a storage
 * group or a storage node runs NESTED listem() calls -- the storage machinery
 * reaches tasks -- so one Route::listem('storagenode') fires this event three
 * times: once for storagenode, then twice for task. A hook that simply keeps
 * the last table it saw therefore files TASK's 34 columns under storagenode's
 * name, and the same under storagegroup's, and looks entirely plausible doing
 * it.
 *
 * First fire for the requested class wins: first because the outermost call
 * builds its table before anything nested runs, and for the requested class
 * because a nested call for a DIFFERENT class must not answer for it.
 */
class ColumnContractHook extends Hook
{
    /** @var array|null the table for the class under test */
    public static $captured = null;

    /** @var string|null the class being listed */
    public static $want = null;

    public $name = 'ColumnContractHook';
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
new ColumnContractHook();

/**
 * Runs one primer and reports which relation classes it warmed.
 *
 * @param callable    $prime the column's prime closure
 * @param string|null $dbcol the column it reads ids from
 *
 * @return string
 */
function primedBy($prime, $dbcol)
{
    FogTestHarness::setStatic('Route', 'relCache', []);
    $row = [];
    if (null !== $dbcol) {
        $row[$dbcol] = 7;
    }
    try {
        $prime([$row]);
    } catch (\Throwable $e) {
        return '!' . get_class($e);
    }
    $classes = [];
    foreach (array_keys((array)FogTestHarness::getStatic('Route', 'relCache')) as $k) {
        $classes[] = substr($k, 0, (int)strpos($k, ':'));
    }
    $classes = array_values(array_unique($classes));
    sort($classes);
    return count($classes) ? implode('+', $classes) : '(none)';
}

/**
 * The names a closure closed over, as a stable string.
 *
 * A formatter's `use (...)` list is part of what it renders, and it is the
 * part a move can drop without breaking anything visible. `f` alone would not
 * see it go.
 *
 * @param callable $fn the closure
 *
 * @return string
 */
function bindingOf($fn)
{
    if (!($fn instanceof \Closure)) {
        return 'f';
    }
    $names = array_keys((new \ReflectionFunction($fn))->getStaticVariables());
    sort($names);
    return count($names) ? 'f:' . implode(',', $names) : 'f';
}

$lines = [];
$classes = (array)FogTestHarness::getStatic('Route', 'validClasses');
sort($classes);
foreach ($classes as $classname) {
    ColumnContractHook::$captured = null;
    ColumnContractHook::$want = $classname;
    Route::$data = [];
    // Rendering is noise here, and it is noise that must not be mistaken for
    // a finding: a synthetic row is not a real one, so formatters warn about
    // joined columns the fake never selected, some throw outright (a fake
    // date is not a date), and a couple echo. The table has already been
    // captured by the time any of that runs -- CUSTOMIZE_DT_COLUMNS fires
    // before the first row is formatted -- so the whole render is discarded.
    //
    // Suppressed narrowly, around this one call, rather than by turning
    // reporting down for the file: a warning raised while BUILDING the table
    // is a real finding and must still surface.
    ob_start();
    $prevLevel = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
    // StorageGroup::_fallbackNode() reports a degraded node choice through
    // error_log() rather than self::error(), on purpose -- it must be visible
    // on a live server whether or not FOG logging is switched on. Under CLI
    // that means stderr. Pointed at a file for the duration rather than
    // suppressed, because the deliberate unconditionality is not this test's
    // to override; it just has nowhere useful to put it.
    $prevLog = ini_set('error_log', FOG_LOG_DIR . '/render-noise.log');
    try {
        // asValue() turns sendResponse()'s exit into a catchable exception
        // (ADR 0011); listem()'s own catch converts a formatter failure into
        // exactly that.
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
    // A class whose table was never captured is recorded as such rather than
    // skipped: silently contributing no lines would let a class stop building
    // a table at all with the fixture merely getting shorter.
    if (null === ColumnContractHook::$captured) {
        $lines[] = implode("\t", [$classname, 0, '-', '__NO_TABLE__', '-', '-']);
        continue;
    }
    foreach ((array)ColumnContractHook::$captured as $n => $col) {
        $db = array_key_exists('db', $col) ? $col['db'] : null;
        $lines[] = implode(
            "\t",
            [
                $classname,
                $n,
                null === $db ? '-' : $db,
                array_key_exists('dt', $col) ? $col['dt'] : '-',
                isset($col['formatter']) ? bindingOf($col['formatter']) : '-',
                isset($col['prime']) ? primedBy($col['prime'], $db) : '-'
            ]
        );
    }
}
$actual = implode("\n", $lines) . "\n";

if ($update) {
    file_put_contents($fixture, $actual);
    echo 'updated ' . $fixture . ' (' . count($lines) . " columns)\n";
    exit(0);
}

if (!is_readable($fixture)) {
    fwrite(STDERR, "FAIL: fixture missing: $fixture\n");
    fwrite(STDERR, "Run with --update to create it.\n");
    exit(1);
}
$expected = file_get_contents($fixture);

if ($expected === $actual) {
    echo 'ok  ' . count($lines) . " columns across " . count($classes) . " classes\n";
    exit(0);
}

// A whole-file dump of 628 lines is not a report. Show only what moved.
$want = explode("\n", trim($expected, "\n"));
$got = explode("\n", trim($actual, "\n"));
$wantKeyed = [];
foreach ($want as $l) {
    $parts = explode("\t", $l);
    $wantKeyed[$parts[0] . "\t" . ($parts[1] ?? '')] = $l;
}
$gotKeyed = [];
foreach ($got as $l) {
    $parts = explode("\t", $l);
    $gotKeyed[$parts[0] . "\t" . ($parts[1] ?? '')] = $l;
}
$diffs = 0;
foreach ($wantKeyed as $k => $l) {
    if (!isset($gotKeyed[$k])) {
        fwrite(STDERR, "  gone: $l\n");
        $diffs++;
    } elseif ($gotKeyed[$k] !== $l) {
        fwrite(STDERR, "  was:  $l\n");
        fwrite(STDERR, "  now:  {$gotKeyed[$k]}\n");
        $diffs++;
    }
}
foreach ($gotKeyed as $k => $l) {
    if (!isset($wantKeyed[$k])) {
        fwrite(STDERR, "  new:  $l\n");
        $diffs++;
    }
}
fwrite(
    STDERR,
    "FAIL: the grid column table changed ($diffs difference(s), "
    . count($want) . ' expected columns, ' . count($got) . " found).\n"
    . "If that was intended, re-run with --update and commit the fixture.\n"
);
exit(1);
