<?php
/**
 * A task with a boot token and no host saves; one with neither is refused.
 *
 * A deploy to an unregistered or pending host is a Task with no host
 * (forum 18086). "No host" is SQL NULL in tasks.taskHostID, never 0: the
 * column carries the foreign key from schema step 390 and no hosts row has
 * id 0. This runs the REAL Task::save() against a fake database and reads
 * the INSERT that comes out, matching columns to bound values by position.
 *
 * It also pins the guard: hostID was a required field, and now a task needs
 * a host OR a non-empty tokenHash. REST cannot supply tokenHash (it is
 * server-owned), so REST cannot create a hostless task.
 *
 * Usage: php tests/task-hostless-model.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Items\Task;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('task-hostless-model');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Saves the task and returns the tasks INSERT as column => bound value.
 *
 * @param Task $task the task to save
 *
 * @return array<string, mixed> empty when no INSERT was issued
 */
function taskInsertRow(Task $task)
{
    global $db;
    $captured = [];
    $db->responder = function ($sql, $params) use (&$captured) {
        if (false !== stripos($sql, 'INSERT INTO `tasks`')) {
            $captured = ['sql' => $sql, 'params' => (array)$params];
        }
        return null;
    };
    $db->log = [];
    $task->save();
    $db->responder = null;
    return insertRow($captured);
}

/**
 * Parses an INSERT into column => bound value, by position.
 *
 * @param array $captured sql and params of the statement
 *
 * @return array<string, mixed> empty when it cannot be parsed
 */
function insertRow(array $captured)
{
    if (!$captured
        || !preg_match('#\(([^)]*)\)\s*VALUES#is', $captured['sql'], $m)
    ) {
        return [];
    }
    preg_match_all('#`([^`]+)`#', $m[1], $names);
    $values = array_values($captured['params']);
    if (count($names[1]) !== count($values)) {
        return [];
    }
    return array_combine($names[1], $values);
}

// A hostless task as BootToken::issue() builds it.
$hostless = (new Task())
    ->set('typeID', 1)
    ->set('stateID', 1)
    ->set('mac', 'aa:bb:cc:dd:ee:ff')
    ->set('tokenHash', str_repeat('a', 64));
$t->check('a task with no host id is hostless', $hostless->isHostless());

$row = taskInsertRow($hostless);
if (!$t->check('a hostless task with a token hash issues a tasks INSERT', [] !== $row)) {
    $t->finish();
}
// save() leaves a NULL out of the INSERT, so the column's own DEFAULT NULL
// applies. Either way the stored value is NULL and never 0, which the
// foreign key on taskHostID would refuse (error 1452).
$t->check(
    'taskHostID is NULL or left to its DEFAULT NULL, never 0',
    !array_key_exists('taskHostID', $row) || null === $row['taskHostID']
);
$t->check(
    'the MAC is written',
    'aa:bb:cc:dd:ee:ff' === ($row['taskMAC'] ?? null)
);
$t->check(
    'the token hash is written',
    str_repeat('a', 64) === ($row['taskTokenHash'] ?? null)
);

// Neither a host nor a token: refused before any INSERT.
$neither = (new Task())->set('typeID', 1)->set('stateID', 1);
$t->check('a task with neither is not valid for save', [] === taskInsertRow($neither));
$t->check('save() returns false for a task with neither', false === $neither->save());

// A host and no token: the registered-host path is unchanged.
$registered = (new Task())
    ->set('typeID', 1)
    ->set('stateID', 1)
    ->set('hostID', 42);
$t->check('a task with a host id is not hostless', !$registered->isHostless());
$row = taskInsertRow($registered);
$t->check('a task with a host saves without a token', [] !== $row);
$t->check(
    'the host id is bound',
    42 === (int)($row['taskHostID'] ?? 0)
);

// isValid(): a loaded hostless task with a token is valid, without one is not.
$loaded = (new Task())
    ->set('id', 7)
    ->set('typeID', 1)
    ->set('stateID', 1)
    ->set('tokenHash', str_repeat('b', 64));
$t->check('a hostless task with a token hash is valid', $loaded->isValid());
$t->check(
    'a hostless task without a token hash is not valid',
    !(new Task())->set('id', 7)->set('typeID', 1)->set('stateID', 1)->isValid()
);

$t->finish();
