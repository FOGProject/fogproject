<?php
/**
 * ADR 0020 phase 4: the readers use the structured columns, and fall back.
 *
 * Phase 3 filled seven columns; phase 4 is where that becomes visible. Two
 * changes, and both have a failure mode that looks like success:
 *
 *   1. `history` gains a `summary` output column, built at RENDER from the
 *      row's type and subject so it reads in the language of whoever is
 *      looking rather than whoever triggered it. A row with no type -- every
 *      row written before phase 3 -- must fall back to the stored prose. Get
 *      the fallback wrong and the whole table reads blank for exactly the
 *      rows nobody can reconstruct, and no error is raised.
 *
 *   2. `userTracking` and `taskLog` render the host name from the stored
 *      copy when the host is gone. That is the entire reason phase 2 added
 *      the column: Route::deletemass('host') leaves these rows in place and
 *      the grid resolved the name live, so a deleted host's rows rendered a
 *      blank name forever. The live name must still WIN where the host
 *      exists, or a rename stops showing.
 *
 * Driven through the real column builder and the real formatters, against
 * synthetic rows -- the same mechanism route-column-contract.test.php uses,
 * for the same reason: the formatters are closures inside a private method
 * and calling them is the only way to see what they render.
 *
 * DB-free. The fake DB answers no rows, so `rel('host', ...)` resolves to an
 * invalid Host with an empty name -- which is precisely the deleted-host case
 * this needs, and is why the live-wins half is checked by priming the
 * relation cache instead.
 *
 * Usage: php tests/event-frame-readers.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('event-frame-readers');

$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

$admin = FOGCore::getClass('User')->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/**
 * Captures the column table for one class.
 *
 * Matched on classname because listing some classes runs nested listem()
 * calls for others; route-column-contract.test.php explains it at length.
 */
class ReaderColumnHook extends Hook
{
    /** @var array|null the table for the class under test */
    public static $captured = null;

    /** @var string|null the class being listed */
    public static $want = null;

    public $name = 'ReaderColumnHook';
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
new ReaderColumnHook();

/**
 * The formatter for one output column of one class.
 *
 * @param string $classname the lowercased class to list
 * @param string $dt        the output column name
 *
 * @return callable|null
 */
function readerFormatter($classname, $dt)
{
    ReaderColumnHook::$captured = null;
    ReaderColumnHook::$want = $classname;
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
    foreach ((array)ReaderColumnHook::$captured as $col) {
        if (isset($col['dt']) && $col['dt'] === $dt && isset($col['formatter'])) {
            return $col['formatter'];
        }
    }
    return null;
}

$t = new FogChecks();

// ---------------------------------------------------------------------
// 1. history's summary.
$summary = readerFormatter('history', 'summary');
$t->check('history has a summary column with a formatter', is_callable($summary));

if (is_callable($summary)) {
    $structured = [
        'hType' => \FOG\Items\History::TYPE_UPDATE,
        'hSubjectType' => 'host',
        'hSubjectID' => 42,
        'hSubjectLabel' => 'lab-01',
        'hText' => '[2026-01-01 00:00:00] Host ID: 42 NAME: lab-01 was updated.'
    ];
    $out = $summary(null, $structured);
    $t->check(
        "a typed row renders a built sentence (got: $out)",
        false !== strpos($out, 'Host')
        && false !== strpos($out, 'lab-01')
        && false !== strpos($out, '42')
        && false === strpos($out, '[2026-01-01')
    );

    // Every type must produce something distinguishable. Two types rendering
    // the same sentence would make the column that exists to tell them apart
    // useless, and nothing else would notice.
    $seen = [];
    foreach (
        [
            \FOG\Items\History::TYPE_UPDATE,
            \FOG\Items\History::TYPE_UPDATE_FAILED,
            \FOG\Items\History::TYPE_DELETE,
            \FOG\Items\History::TYPE_DELETE_FAILED
        ] as $type
    ) {
        $row = $structured;
        $row['hType'] = $type;
        $seen[] = $summary(null, $row);
    }
    $t->check(
        'each history type renders its own sentence',
        count($seen) === count(array_unique($seen))
    );

    // An object with no name drops the name clause rather than printing an
    // empty pair of quotes. Association rows and log rows have no name.
    $noName = $structured;
    $noName['hSubjectLabel'] = '';
    $out = $summary(null, $noName);
    $t->check(
        "a subject with no name still renders (got: $out)",
        '' !== $out && false === strpos($out, '""')
    );

    // The fallbacks. Each is a row the frame cannot answer for, and each
    // must come back as the stored prose rather than as an empty cell.
    $prose = '[2015-06-01 09:00:00] Host ID: 7 NAME: old has been updated.';
    $cases = [
        'a legacy row with no type' => ['hType' => ''],
        'a TYPE_LOG row, which has no subject' => [
            'hType' => \FOG\Items\History::TYPE_LOG,
            'hSubjectType' => '',
            'hSubjectID' => null
        ],
        'a type nothing recognises' => ['hType' => 'somepluginscode'],
        'a typed row with no subject id' => ['hSubjectID' => null]
    ];
    foreach ($cases as $what => $overrides) {
        $row = array_merge($structured, ['hText' => $prose], $overrides);
        $t->check(
            "$what falls back to the stored prose",
            $prose === $summary(null, $row)
        );
    }
}

// ---------------------------------------------------------------------
// 2. The host name, live where the host exists and stored where it does not.
$cases = [
    'usertracking' => ['id' => 'utHostID', 'name' => 'utHostName'],
    'tasklog' => ['id' => 'logHostID', 'name' => 'logHostName']
];
foreach ($cases as $classname => $cols) {
    $link = readerFormatter($classname, 'hostLink');
    $t->check("$classname has a hostLink formatter", is_callable($link));
    if (!is_callable($link)) {
        continue;
    }

    // Deleted host: nothing to look up, so the stored copy answers.
    FogTestHarness::setStatic('Route', 'relCache', []);
    $row = [$cols['id'] => 41, $cols['name'] => 'gone-host'];
    $out = $link(41, $row);
    $t->check(
        "$classname renders the stored name for a deleted host (got: $out)",
        false !== strpos($out, 'gone-host')
    );

    // Live host: the current name wins, so a rename shows immediately.
    FogTestHarness::setStatic(
        'Route',
        'relCache',
        ['host:41' => FOGCore::getClass('Host')->set('id', 41)->set('name', 'renamed')]
    );
    $out = $link(41, $row);
    $t->check(
        "$classname prefers the live name over the stored copy (got: $out)",
        false !== strpos($out, 'renamed')
        && false === strpos($out, 'gone-host')
    );
    FogTestHarness::setStatic('Route', 'relCache', []);
}

// ---------------------------------------------------------------------
// 3. The two summaries ADR 0023 item 5 added. These are the activity
//    viewer's only content column for their source, so an empty one is a
//    page that renders and says nothing.
$utSummary = readerFormatter('usertracking', 'summary');
$t->check('usertracking has a summary formatter', is_callable($utSummary));
if (is_callable($utSummary)) {
    FogTestHarness::setStatic('Route', 'relCache', []);
    $out = $utSummary(null, [
        'utAction' => \FOG\Items\UserTracking::ACTION_LOGIN,
        'utUserName' => 'jsmith',
        'utHostID' => 41,
        'utHostName' => 'lab-01'
    ]);
    $t->check(
        "a login names the person and the host (got: $out)",
        false !== strpos($out, 'jsmith') && false !== strpos($out, 'lab-01')
    );
    // A service start has no person. The clause has to drop rather than
    // render an empty one.
    $out = $utSummary(null, [
        'utAction' => \FOG\Items\UserTracking::ACTION_SERVICE_START,
        'utUserName' => '',
        'utHostID' => 41,
        'utHostName' => 'lab-01'
    ]);
    $t->check(
        "a row with no person still says something (got: $out)",
        '' !== $out && false !== strpos($out, 'lab-01')
    );
    // The bare worst case: no person, and a host deleted before phase 3
    // filled the stored copy. The action alone is all there is.
    $out = $utSummary(null, ['utAction' => '', 'utUserName' => '', 'utHostID' => 0]);
    $t->check(
        "a row with nothing but a code is not blank (got: $out)",
        '' !== $out
    );
}

$tlSummary = readerFormatter('tasklog', 'summary');
$t->check('tasklog has a summary formatter', is_callable($tlSummary));
if (is_callable($tlSummary)) {
    FogTestHarness::setStatic('Route', 'relCache', []);
    // An error row is already a sentence somebody wrote; it is used whole.
    $out = $tlSummary(null, [
        'logText' => 'partclone exited 1',
        'logTaskTypeName' => 'Deploy',
        'logHostID' => 41,
        'logHostName' => 'lab-01'
    ]);
    $t->check(
        "a row with text uses it verbatim (got: $out)",
        'partclone exited 1' === $out
    );
    // A state row has no text and has to be assembled.
    $out = $tlSummary(null, [
        'logText' => '',
        'logTaskTypeName' => 'Deploy',
        'logImageName' => 'win11',
        'logHostID' => 41,
        'logHostName' => 'lab-01',
        'taskStateID' => 3
    ]);
    $t->check(
        "a state row names the task, image and host (got: $out)",
        false !== strpos($out, 'Deploy')
        && false !== strpos($out, 'win11')
        && false !== strpos($out, 'lab-01')
    );
    // Pre-341 rows have no type name. Something must still render.
    $out = $tlSummary(null, ['logText' => '', 'taskStateID' => 3]);
    $t->check(
        "a row with no task type is not blank (got: $out)",
        '' !== $out
    );
}

// userTracking's plain name column takes the same route.
$name = readerFormatter('usertracking', 'hostname');
$t->check('usertracking has a hostname formatter', is_callable($name));
if (is_callable($name)) {
    FogTestHarness::setStatic('Route', 'relCache', []);
    $out = $name(41, ['utHostID' => 41, 'utHostName' => 'gone-host']);
    $t->check(
        "usertracking's hostname falls back to the stored copy (got: $out)",
        'gone-host' === $out
    );
}

$t->finish();
