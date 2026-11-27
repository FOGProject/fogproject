<?php
/**
 * BootToken::issue() writes a hostless Queued task and a Queued log row.
 *
 * A deploy or multicast join from the iPXE menu by an unregistered or
 * pending host is a real Task with no host, found later by a single-use boot
 * token (forum 18086). tests/bootmenu-ipxe-output.test.php runs the menu
 * against a STUB of BootToken, so it proves the menu calls issue() and
 * prints the token, not what issue() stores. This file runs the REAL method
 * against a fake database and reads the INSERTs that come out: the bound
 * values, matched to the columns by position.
 *
 * Usage: php tests/boot-token-issue.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Items\Image;
use FOG\Items\StorageNode;
use FOG\Items\TaskState;
use FOG\Items\TaskType;
use FOG\TaskHandling\BootToken;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('boot-token-issue');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * The task type, the image and the node, resolved without a database.
 */
class IssueType extends TaskType
{
    public function get($key = '')
    {
        return 'name' === $key ? 'Deploy' : parent::get($key);
    }
}
class IssueImage extends Image
{
    // The real isValid() wants every required column of an image.
    public function isValid()
    {
        return true;
    }

    public function get($key = '')
    {
        return 'name' === $key ? 'win11-base' : parent::get($key);
    }
}
class IssueNode extends StorageNode
{
    public function isValid()
    {
        return true;
    }
}

/**
 * Parses an INSERT into column => bound value, by position.
 *
 * @param array $captured sql and params of the statement
 *
 * @return array [] when it cannot be parsed
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

/**
 * Runs the real issue() and returns the raw token and both INSERTs.
 *
 * @param string $createdBy the menu user
 *
 * @return array [token, tasks row, taskLog row]
 */
function issueOnce($createdBy)
{
    global $db;
    $task = [];
    $log = [];
    $db->responder = function ($sql, $params) use (&$task, &$log) {
        if (false !== stripos($sql, 'INSERT INTO `tasks`')) {
            $task = ['sql' => $sql, 'params' => (array)$params];
        }
        if (false !== stripos($sql, 'INSERT INTO `taskLog`')) {
            $log = ['sql' => $sql, 'params' => (array)$params];
        }
        return null;
    };
    $db->log = [];
    $node = (new IssueNode())->set('id', 3)->set('storagegroupID', 2);
    $raw = BootToken::issue(
        (new IssueType())->set('id', 1),
        (new IssueImage())->set('id', 5),
        $node,
        'AA:BB:CC:DD:EE:FF',
        $createdBy
    );
    $db->responder = null;
    return [$raw, insertRow($task), insertRow($log)];
}

list($raw, $task, $log) = issueOnce('menuuser');
if (!$t->check('issue() returns a token', is_string($raw) && '' !== $raw)) {
    $t->finish();
}
if (!$t->check('issue() writes a tasks INSERT', [] !== $task)) {
    $t->finish();
}
$t->check(
    'the raw token is 64 hex characters (32 random bytes)',
    1 === preg_match('/^[0-9a-f]{64}$/', $raw)
);
$t->check(
    'only the sha256 of the token is stored',
    hash('sha256', $raw) === ($task['taskTokenHash'] ?? null)
);
$t->check(
    'the raw token is not stored anywhere in the task row',
    !in_array($raw, array_values($task), true)
);
$t->check(
    'the task starts Queued',
    TaskState::getQueuedState() === (int)($task['taskStateID'] ?? -1)
);
$t->check(
    'the task has no host (NULL or left to DEFAULT NULL, never 0)',
    !array_key_exists('taskHostID', $task) || null === $task['taskHostID']
);
$t->check(
    'the MAC is stored lowercase',
    'aa:bb:cc:dd:ee:ff' === ($task['taskMAC'] ?? null)
);
$t->check(
    'createdBy is the menu user',
    'menuuser' === ($task['taskCreateBy'] ?? null)
);
$t->check(
    'the type is the one passed',
    1 === (int)($task['taskTypeID'] ?? 0)
);
$t->check(
    'the image is the one passed',
    5 === (int)($task['taskImageID'] ?? 0)
);
$t->check(
    'the node and its group are the ones the menu picked',
    3 === (int)($task['taskNFSMemberID'] ?? 0)
    && 2 === (int)($task['taskNFSGroupID'] ?? 0)
);
$t->check(
    'the name says unregistered and carries the MAC',
    'Unregistered - aa:bb:cc:dd:ee:ff' === ($task['taskName'] ?? null)
);

// The Queued log row recordState() writes for every new task.
$t->check('a taskLog row is written', [] !== $log);
$t->check(
    'the log row is Queued',
    TaskState::getQueuedState() === (int)($log['taskStateID'] ?? -1)
);
// The fake database reports insertId() 1 for every INSERT.
$t->check(
    'the log row names the new task',
    1 === (int)($log['taskID'] ?? 0)
);
$t->check(
    'the log row has no host name',
    '' === ($log['logHostName'] ?? '')
);
$t->check(
    'the log row carries the MAC',
    'aa:bb:cc:dd:ee:ff' === ($log['logMAC'] ?? null)
);

// createdBy is varchar(200) on tasks and varchar(30) on taskLog.
list($raw, $task, $log) = issueOnce(str_repeat('x', 250));
$t->check(
    'createdBy is cut to the 200 characters of the column',
    str_repeat('x', 200) === ($task['taskCreateBy'] ?? null)
);
$t->check(
    'the log row cuts createdBy to the 30 characters of its column',
    str_repeat('x', 30) === ($log['createdBy'] ?? null)
);

// Two issues never share a token.
list($rawA) = issueOnce('a');
list($rawB) = issueOnce('b');
$t->check('each issue returns a different token', $rawA !== $rawB);

// A failed save returns null so the menu boots without a token.
$db->responder = function ($sql, $params) {
    return null;
};
$db->error = 'forced failure';
$failed = BootToken::issue(
    (new IssueType())->set('id', 1),
    (new IssueImage())->set('id', 5),
    (new IssueNode())->set('id', 3)->set('storagegroupID', 2),
    'aa:bb:cc:dd:ee:ff',
    'menuuser'
);
$db->error = false;
$db->responder = null;
$t->check('a failed save returns null, never a token', null === $failed);

$t->finish();
