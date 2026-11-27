<?php
/**
 * The reaper handles a hostless task by what it is, and records its MAC.
 *
 * Forum 18086: an unregistered machine is imaged under a hostless task
 * (taskHostID NULL) found by a boot token. TaskManager::reapUnrunnable()
 * reaps every active task whose host row is missing, and a hostless task has
 * no host row by design. The reaper has to tell the two apart, and every log
 * row it writes has to carry the MAC, as the other task log writers do.
 *
 * The real TaskManager methods run against a fake database that reads the SQL
 * and its bound values (tests/lib/hostless-fixture.php shows the idea): the
 * answer to the reaper's SELECT is computed from the fake `tasks` rows by
 * evaluating the clauses the real SQL contains, so a clause that is missing
 * from the real query changes who gets reaped.
 *
 * Usage: php tests/hostless-reaper.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Managers\TaskManager;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('hostless-reaper');
$db = FogTestHarness::fakeDb();
$t = new FogChecks();

const MAC = 'aa:bb:cc:dd:ee:ff';

/**
 * The statements that wrote to one table, as [sql, params] pairs.
 *
 * @param array  $seen  [sql, params] pairs
 * @param string $verb  INSERT INTO, UPDATE or DELETE FROM
 * @param string $table the table
 *
 * @return array
 */
function reaperWrites(array $seen, $verb, $table)
{
    $out = [];
    foreach ($seen as $one) {
        if (0 === strpos($one[0], $verb . ' `' . $table . '`')) {
            $out[] = $one;
        }
    }
    return $out;
}

/**
 * The column names an INSERT names.
 *
 * @param string $sql the statement
 *
 * @return string[]
 */
function reaperInsertColumns($sql)
{
    if (!preg_match('#\(([^)]*)\)\s*VALUES#is', $sql, $m)) {
        return [];
    }
    preg_match_all('#`([^`]+)`#', $m[1], $names);
    return $names[1];
}

// ----- an unrunnable task with no host and no token: reaped, MAC logged

$seen = [];
$db->responder = function ($sql, $params) use (&$seen) {
    $seen[] = [$sql, (array)$params];
    if (false !== strpos($sql, 'FROM `taskStates`')
        && false !== strpos($sql, ':id')
    ) {
        return ['tsID' => (int)$params[':id'], 'tsName' => 'state'];
    }
    if (0 === strpos($sql, 'SELECT `tasks`.`taskID` AS `taskID`, '
        . '`tasks`.`taskHostID` AS `hostID`')
    ) {
        return [[
            'taskID' => 7, 'hostID' => null, 'typeID' => 1, 'imageID' => 5,
            'createdBy' => 'menuuser', 'stateID' => 1, 'hostFound' => null,
            'hostName' => '', 'typeFound' => 1, 'taskTypeName' => 'Deploy',
            'imageFound' => 5, 'mac' => MAC,
        ]];
    }
    if (0 === strpos($sql, 'SELECT `tasks`.`taskID` AS `taskID`, '
        . '`tasks`.`taskStateID` AS `stateID`')
    ) {
        return [[
            'taskID' => 7, 'stateID' => 6, 'createdBy' => 'menuuser',
            'mac' => MAC, 'typeID' => 1, 'hostID' => null, 'hostName' => '',
            'taskTypeName' => 'Deploy', 'imageName' => 'win11-base',
        ]];
    }
    return null;
};
$db->affected = 1;
$reaped = (new TaskManager())->reapUnrunnable();
$t->check(
    'a hostless task with no token is reaped as unrunnable',
    [7] === array_keys($reaped)
);
$logWrites = reaperWrites($seen, 'INSERT INTO', 'taskLog');
$withMac = 0;
$withoutMac = [];
foreach ($logWrites as $one) {
    $cols = reaperInsertColumns($one[0]);
    if (in_array('logMAC', $cols, true)) {
        $withMac++;
        if (!in_array(MAC, array_values($one[1]), true)) {
            $withoutMac[] = 'logMAC column but no bound ' . MAC;
        }
    } else {
        $withoutMac[] = implode(',', $cols);
    }
}
$t->check(
    'every taskLog write of the reaper carries logMAC and the task MAC'
    . ' (got: ' . implode(' | ', $withoutMac) . ')',
    2 === count($logWrites) && 2 === $withMac && [] === $withoutMac
);

// ----- the 24 hour rule for a hostless task that FOS never reported on

const NO_REPORT_REASON = 'FOS sent no report within 24 hours; an init older'
    . ' than this server does not report for an unregistered host';
const TOKEN = 'ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12';

/**
 * The fake `tasks` table the reaper queries run against.
 *
 * @param array $tasks rows: id, host (null = hostless), hash, state,
 *                     created (a modifier for niceDate), mac
 * @param array $seen  receives every [sql, params]
 *
 * @return callable
 */
function reaperResponder(array $tasks, array &$seen)
{
    return function ($sql, $params) use ($tasks, &$seen) {
        $params = (array)$params;
        $seen[] = [$sql, $params];
        if (false !== strpos($sql, 'FROM `taskStates`')
            && false !== strpos($sql, ':id')
        ) {
            return ['tsID' => (int)$params[':id'], 'tsName' => 'state'];
        }
        // TaskState::getNoReportState() finds its row by name; the seeded id
        // is whatever the install gave it, 7 in this fixture.
        if (false !== strpos($sql, 'FROM `taskStates`')
            && false !== strpos($sql, ':name')
        ) {
            return ['tsID' => 7, 'tsName' => 'No Report'];
        }
        // reapUnrunnable(): active tasks whose host does not resolve. A
        // hostless task never resolves, so the real query has to exempt a
        // token task by its own clause; the exemption is applied only when
        // the SQL actually contains it.
        if (0 === strpos($sql, 'SELECT `tasks`.`taskID` AS `taskID`, '
            . '`tasks`.`taskHostID` AS `hostID`')
        ) {
            $exempt = false !== strpos(
                $sql,
                "`tasks`.`taskHostID` IS NOT NULL "
                . "OR `tasks`.`taskTokenHash` = ''"
            );
            $out = [];
            foreach ($tasks as $row) {
                if (null !== $row['host']) {
                    continue;
                }
                if ($exempt && '' !== $row['hash']) {
                    continue;
                }
                $out[] = [
                    'taskID' => $row['id'], 'hostID' => null, 'typeID' => 1,
                    'imageID' => 5, 'createdBy' => 'menuuser',
                    'stateID' => $row['state'], 'hostFound' => null,
                    'hostName' => '', 'typeFound' => 1,
                    'taskTypeName' => 'Deploy', 'imageFound' => 5,
                    'mac' => $row['mac'],
                ];
            }
            return $out;
        }
        // The silent-hostless sweep: hostless, with a token, still in the
        // bound state, created before the bound cutoff.
        if (0 === strpos($sql, 'SELECT `tasks`.`taskID` AS `taskID`, '
            . '`tasks`.`taskCreateBy` AS `createdBy`')
            && isset($params[':cutoff'])
        ) {
            $out = [];
            foreach ($tasks as $row) {
                $created = FogTestHarness::callStatic(
                    'FOGBase',
                    'niceDate',
                    [$row['created']]
                )->format('Y-m-d H:i:s');
                if (null === $row['host'] && '' !== $row['hash']
                    && (int)$row['state'] === (int)$params[':state']
                    && $created < $params[':cutoff']
                ) {
                    $out[] = [
                        'taskID' => $row['id'], 'createdBy' => 'menuuser',
                        'mac' => $row['mac'],
                    ];
                }
            }
            return $out;
        }
        // The sweep's read-back after its UPDATE: which of the candidates are
        // in No Report now. A row marked 'racedIn' checked in between the
        // SELECT and the UPDATE, so the UPDATE (found on Queued) left it.
        if (0 === strpos($sql, 'SELECT `taskID` FROM `tasks` '
            . 'WHERE `taskStateID` = :noreport')
        ) {
            $asked = [];
            foreach ($params as $key => $value) {
                if (0 === strpos((string)$key, ':id')) {
                    $asked[(int)$value] = true;
                }
            }
            $out = [];
            foreach ($tasks as $row) {
                if (isset($asked[(int)$row['id']]) && empty($row['racedIn'])) {
                    $out[] = ['taskID' => $row['id']];
                }
            }
            return $out;
        }
        if (0 === strpos($sql, 'SELECT `tasks`.`taskID` AS `taskID`, '
            . '`tasks`.`taskStateID` AS `stateID`')
        ) {
            return [[
                'taskID' => 7,
                'stateID' => FogTestHarness::callStatic(
                    'FOGBase',
                    'getNoReportState'
                ),
                'createdBy' => 'menuuser',
                'mac' => MAC, 'typeID' => 1, 'hostID' => null,
                'hostName' => '', 'taskTypeName' => 'Deploy',
                'imageName' => 'win11-base',
            ]];
        }
        return null;
    };
}

// getNoReportState() caches the id it finds by name, so the first call has
// to meet a database that answers the lookup.
$seen = [];
$db->responder = reaperResponder([], $seen);
$queued = FogTestHarness::callStatic('FOGBase', 'getQueuedState');
$progress = FogTestHarness::callStatic('FOGBase', 'getProgressState');
$noReport = FogTestHarness::callStatic('FOGBase', 'getNoReportState');
$tasks = [
    // The one the sweep is for.
    7 => ['id' => 7, 'host' => null, 'hash' => TOKEN, 'state' => $queued,
          'created' => '-25 hours', 'mac' => MAC],
    // A token task still inside its 24 hours.
    8 => ['id' => 8, 'host' => null, 'hash' => TOKEN, 'state' => $queued,
          'created' => '-1 hour', 'mac' => MAC],
    // A token task FOS did report on (it left Queued).
    9 => ['id' => 9, 'host' => null, 'hash' => TOKEN, 'state' => $progress,
          'created' => '-30 hours', 'mac' => MAC],
    // A task for a registered host, old, with no token.
    10 => ['id' => 10, 'host' => 12, 'hash' => '', 'state' => $queued,
           'created' => '-30 hours', 'mac' => ''],
];

// reapUnrunnable() leaves every token task alone, whatever its age.
$seen = [];
$db->responder = reaperResponder($tasks, $seen);
$db->affected = 1;
$reaped = (new TaskManager())->reapUnrunnable();
$t->check(
    'reapUnrunnable() does not reap a hostless token task'
    . ' (got: ' . implode(',', array_keys($reaped)) . ')',
    [] === $reaped && [] === reaperWrites($seen, 'UPDATE', 'tasks')
);

// The sweep: only the 25 hour old Queued token task, and only to No Report.
$seen = [];
$db->responder = reaperResponder($tasks, $seen);
$reaped = (new TaskManager())->reapSilentHostless();
$t->check(
    'the sweep takes only the 25 hour old Queued token task'
    . ' (got: ' . implode(',', array_keys($reaped)) . ')',
    [7] === array_keys($reaped)
);
$t->check(
    'the reason is the brief text',
    NO_REPORT_REASON === ($reaped[7] ?? null)
);
$updates = reaperWrites($seen, 'UPDATE', 'tasks');
$values = $updates ? array_map('intval', array_values($updates[0][1])) : [];
$t->check(
    'the task moves to No Report, not Failed or Canceled',
    1 === count($updates) && in_array($noReport, $values, true)
    && !in_array(
        (int)FogTestHarness::callStatic('FOGBase', 'getFailedState'),
        $values,
        true
    )
);
// A task can check in between the SELECT and the UPDATE: the UPDATE must
// find on the Queued state too, or it moves a task that is now active.
$t->check(
    'the UPDATE finds on the Queued state as well as the id'
    . ' (got: ' . ($updates ? $updates[0][0] : 'no update') . ')',
    1 === count($updates)
    && false !== strpos($updates[0][0], 'taskStateID')
    && substr_count($updates[0][0], 'taskStateID') >= 2
    && in_array((int)$queued, array_map('intval', array_values($updates[0][1])), true)
);
$logWrites = reaperWrites($seen, 'INSERT INTO', 'taskLog');
$reasonRows = [];
foreach ($logWrites as $one) {
    if (in_array(NO_REPORT_REASON, array_values($one[1]), true)) {
        $reasonRows[] = $one;
    }
}
$r = $reasonRows ? reaperRow($reasonRows[0]) : [];
$t->check(
    'the reason is logged as an error row in the No Report state with the MAC'
    . ' (got: ' . json_encode($r) . ')',
    1 === count($reasonRows)
    && $noReport === (int)($r['taskStateID'] ?? -1)
    && MAC === ($r['logMAC'] ?? null)
    && 'menuuser' === ($r['createdBy'] ?? null)
);
$t->check(
    'the state transition itself goes through the state log writer'
    . ' (a `tasks` read-back and a second taskLog INSERT)',
    2 === count($logWrites)
);

// An empty sweep writes nothing.
$seen = [];
$db->responder = reaperResponder(
    [8 => $tasks[8], 9 => $tasks[9], 10 => $tasks[10]],
    $seen
);
$reaped = (new TaskManager())->reapSilentHostless();
$t->check(
    'with nothing 24 hours old and silent the sweep writes nothing',
    [] === $reaped && [] === reaperWrites($seen, 'UPDATE', 'tasks')
);

// A task that checks in between the SELECT and the UPDATE keeps its state,
// so it must get no "no report" row and no state row either.
$seen = [];
$raced = $tasks[7];
$raced['racedIn'] = true;
$db->responder = reaperResponder([7 => $raced], $seen);
$reaped = (new TaskManager())->reapSilentHostless();
$t->check(
    'a task that checked in during the sweep is not logged as No Report'
    . ' (got: ' . json_encode($reaped) . ')',
    [] === $reaped && [] === reaperWrites($seen, 'INSERT INTO', 'taskLog')
);

$t->finish();

/**
 * Parses an INSERT into column => bound value, by position.
 *
 * @param array $stmt [sql, params]
 *
 * @return array
 */
function reaperRow(array $stmt)
{
    if (!preg_match('#\(([^)]*)\)\s*VALUES#is', $stmt[0], $m)) {
        return [];
    }
    preg_match_all('#`([^`]+)`#', $m[1], $names);
    $values = array_values($stmt[1]);
    return count($names[1]) === count($values)
        ? array_combine($names[1], $values) : [];
}
