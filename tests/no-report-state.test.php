<?php
/**
 * The No Report task state is found by NAME, never by a fixed id.
 *
 * Forum 18086: a hostless token task that FOS never reports on ends in the
 * state "No Report". taskStates.tsID is AUTO_INCREMENT and an administrator
 * can add states, so a hard-coded id can already belong to one of theirs:
 * with INSERT IGNORE the seed is skipped silently and the reaper would write
 * tasks into the administrator's state. So:
 *
 *   the seed    inserts by name with no id (tsName is UNIQUE), so it lands
 *               beside a custom state that holds the id the old seed used.
 *   the lookup  TaskState::getNoReportState() reads the id by name, and
 *               returns 0 (matches no task) when the row is missing.
 *   the set     getTerminalStates() holds the four finished states, and
 *               leaves out a No Report that did not resolve.
 *
 * The database half uses TEMPORARY tables and the REAL step 473 statements.
 * It skips without FOG_TEST_DSN.
 *
 * Usage: php tests/no-report-state.test.php
 * Exit status 0 = pass or skip, 1 = fail.
 */

use FOG\Items\TaskState;

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/fog-schema-collector.php';

FogTestHarness::boot('no-report-state');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$reset = new \ReflectionProperty(TaskState::class, '_noReportId');
$reset->setAccessible(true);

// -------------------------------------------------------------- the lookup

$asked = [];
$db->responder = function ($sql) use (&$asked) {
    if (false === strpos($sql, 'taskStates')) {
        return null;
    }
    $asked[] = $sql;
    // load() reads one row, keyed by column.
    return [
        'tsID' => '12', 'tsName' => 'No Report', 'tsDescription' => '',
        'tsOrder' => '7', 'tsIcon' => ''
    ];
};
$reset->setValue(null, null);
$t->check(
    'the id comes from the row named No Report, not a constant',
    12 === TaskState::getNoReportState()
);
$t->check(
    'and the lookup filtered on the name',
    1 === count($asked) && false !== strpos($asked[0], 'tsName')
);
TaskState::getNoReportState();
$t->check('the id is cached for the request', 1 === count($asked));
$t->check(
    'the terminal set holds the four finished states',
    [4, 5, 6, 12] === TaskState::getTerminalStates()
);
$t->check(
    'and the base class mirror agrees',
    TaskState::getTerminalStates() === \FOG\Base\FOGBase::getTerminalStates()
);

$db->responder = function ($sql) {
    return false === strpos($sql, 'taskStates') ? null : [];
};
$reset->setValue(null, null);
$t->check(
    'no row (step not run) gives 0, which matches no task',
    0 === TaskState::getNoReportState()
);
$t->check(
    'and 0 is left out of the terminal set',
    [4, 5, 6] === TaskState::getTerminalStates()
);
$t->check(
    'so a state 0 row is not "finished"',
    false === \FOG\Audit\ActivityWindow::isTerminal(0)
);

// A daemon that started before the schema step must pick the row up once it
// exists: a 0 answer is not cached, so no reset is made between the two.
$db->responder = function ($sql) {
    return false === strpos($sql, 'taskStates') ? null : [
        'tsID' => '12', 'tsName' => 'No Report', 'tsDescription' => '',
        'tsOrder' => '7', 'tsIcon' => ''
    ];
};
$t->check(
    'a 0 answer is not cached: the row is found once the step has run',
    12 === TaskState::getNoReportState()
);

// ---------------------------------------------------------------- the seed

$src = (string)file_get_contents($web . '/commons/schema.php');
$t->check(
    'no schema step seeds No Report with a fixed id',
    0 === preg_match("/\\(\\s*7\\s*,\\s*'No Report'/", $src)
);

$dsn = getenv('FOG_TEST_DSN');
if (false === $dsn || '' === $dsn) {
    echo "SKIP  no FOG_TEST_DSN set; seed semantics not executed\n";
    $t->finish();
}
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$pdo = new \PDO(
    $dsn,
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$manifest = require $web . '/commons/schema-expected.php';
$create = preg_replace(
    '/^CREATE TABLE IF NOT EXISTS /i',
    'CREATE TEMPORARY TABLE ',
    (string)($manifest['tables']['taskStates']['create'] ?? '')
);
if (0 !== stripos($create, 'CREATE TEMPORARY TABLE')) {
    fwrite(STDERR, "FAIL: refusing a non-TEMPORARY CREATE for taskStates.\n");
    exit(1);
}
$pdo->exec($create);

// An administrator's state already holds id 7 (what the old seed used).
$pdo->exec(
    "INSERT INTO `taskStates` (`tsID`,`tsName`,`tsDescription`,`tsOrder`)"
    . " VALUES (7,'Custom','mine',7)"
);

$steps = fogCollectSchemaSteps($web . '/commons/schema.php', 'x', 473);
$seed = array_values(array_filter(
    (array)($steps[472] ?? []),
    function ($sql) {
        return is_string($sql) && false !== strpos($sql, "'No Report'");
    }
));
$t->check('step 473 has the No Report seed', 1 === count($seed));
$pdo->exec($seed[0]);
$rows = $pdo->query(
    'SELECT `tsID`,`tsName` FROM `taskStates` ORDER BY `tsID`'
)->fetchAll(\PDO::FETCH_KEY_PAIR);
$noReportId = array_search('No Report', $rows, true);
$t->check(
    'a seed beside a custom state 7 still adds a separate No Report row',
    false !== $noReportId && 7 !== (int)$noReportId && 'Custom' === $rows[7]
);
$pdo->exec($seed[0]);
$t->check(
    'a re-run adds nothing',
    2 === (int)$pdo->query('SELECT COUNT(*) FROM `taskStates`')->fetchColumn()
);

// The lookup, handed the row the seed really produced.
$row = $pdo->query(
    "SELECT * FROM `taskStates` WHERE `tsName` = 'No Report'"
)->fetch(\PDO::FETCH_ASSOC);
$db->responder = function ($sql) use ($row) {
    return false === strpos($sql, 'taskStates') ? null : $row;
};
$reset->setValue(null, null);
$t->check(
    'getNoReportState() returns the id the seed was given, not 7',
    (int)$noReportId === TaskState::getNoReportState()
);

$t->finish();
