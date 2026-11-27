<?php
/**
 * A hostless multicast join is a task with a session association.
 *
 * Forum 18086: an unregistered or pending machine that joins a named
 * multicast session from the iPXE menu gets a hostless task (type 8). The
 * check-in of a multicast task looks its session up through
 * multicastSessionsAssoc and throws "Invalid Multicast Session" when there is
 * no row, so BootToken::issue() has to write that row. The cases:
 *
 *   issue() with a session  -> one multicastSessionsAssoc INSERT (msID, tID)
 *   issue() without one     -> no association INSERT (a plain deploy)
 *   checkIn                 -> the session counts one more client, goes
 *                              In-Progress, and the task gets its log row
 *   cancel                  -> the session expected one fewer named client
 *
 * The real methods run against a fake database that reads the SQL it is sent.
 *
 * Usage: php tests/hostless-multicast-join.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\HookManager;
use FOG\Items\Host;
use FOG\Items\Image;
use FOG\Items\MulticastSession;
use FOG\Items\StorageNode;
use FOG\Items\Task;
use FOG\Items\TaskState;
use FOG\Items\TaskType;
use FOG\TaskHandling\BootToken;

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/hostless-fixture.php';

FogTestHarness::boot('hostless-multicast-join');
$db = FogTestHarness::fakeDb();
$db->pdo->countValue = 0;
$db->pdo->rowCount = 0;
$t = new FogChecks();

const MAC = 'aa:bb:cc:dd:ee:ff';

/** Resolved without a database, as tests/boot-token-issue.test.php does. */
class McType extends TaskType
{
    public function get($key = '')
    {
        return 'name' === $key ? 'Multicast' : parent::get($key);
    }
}
class McImage extends Image
{
    public function isValid()
    {
        return true;
    }

    public function get($key = '')
    {
        return 'name' === $key ? 'win11-base' : parent::get($key);
    }
}
class McNode extends StorageNode
{
    public function isValid()
    {
        return true;
    }
}
class McSession extends MulticastSession
{
    public function isValid()
    {
        return true;
    }
}

/**
 * Whether a statement writes to a table.
 *
 * @param array  $seen  [sql, params] pairs
 * @param string $table the table
 *
 * @return array the matching pairs
 */
function mcWritesTo(array $seen, $table)
{
    $out = [];
    foreach ($seen as $one) {
        if (preg_match(
            '#^\s*(INSERT INTO|UPDATE|DELETE FROM)\s+`' . $table . '`#i',
            $one[0]
        )) {
            $out[] = $one;
        }
    }
    return $out;
}

/**
 * Parses an INSERT into column => bound value, by position.
 *
 * @param array $stmt [sql, params]
 *
 * @return array
 */
function mcRow(array $stmt)
{
    if (!preg_match('#\(([^)]*)\)\s*VALUES#is', $stmt[0], $m)) {
        return [];
    }
    preg_match_all('#`([^`]+)`#', $m[1], $names);
    $values = array_values($stmt[1]);
    return count($names[1]) === count($values)
        ? array_combine($names[1], $values) : [];
}

// ----- issue() writes the association

$seen = [];
$db->responder = function ($sql, $params) use (&$seen) {
    $seen[] = [$sql, (array)$params];
    return null;
};
$session = (new McSession())->set('id', 9);
$raw = BootToken::issue(
    (new McType())->set('id', 8),
    (new McImage())->set('id', 5),
    (new McNode())->set('id', 3)->set('storagegroupID', 2),
    MAC,
    'menuuser',
    $session
);
$t->check('issue() with a session returns a token', is_string($raw) && '' !== $raw);
$assoc = mcWritesTo($seen, 'multicastSessionsAssoc');
$a = $assoc ? mcRow($assoc[0]) : [];
$t->check(
    'issue() writes one association row',
    1 === count($assoc)
);
$t->check(
    'the association names the session and the new task'
    . ' (the fake database gives every INSERT id 1)',
    9 === (int)($a['msID'] ?? 0) && 1 === (int)($a['tID'] ?? 0)
);

$seen = [];
BootToken::issue(
    (new McType())->set('id', 1),
    (new McImage())->set('id', 5),
    (new McNode())->set('id', 3)->set('storagegroupID', 2),
    MAC,
    'menuuser'
);
$t->check(
    'issue() without a session writes no association row',
    [] === mcWritesTo($seen, 'multicastSessionsAssoc')
);

// ----- checkIn and cancel of a hostless multicast task

$tasks = [
    7 => hostlessRow([
        'taskTypeID' => 8,
        'taskTokenHash' => hash('sha256', str_repeat('ab', 32)),
    ]),
];
$seen = [];
$base = hostlessResponder($tasks, $seen);
$db->responder = function ($sql, $params) use ($base) {
    $answer = $base($sql, $params);
    if (null !== $answer) {
        return $answer;
    }
    if (false !== strpos($sql, 'FROM `multicastSessionsAssoc`')) {
        // Route::getIds() on the association of task 7.
        return [['msID' => 9, 'taskID' => 7]];
    }
    if (false !== strpos($sql, 'FROM `multicastSessions`')
        && false !== strpos($sql, ':id')
    ) {
        return [
            'msID' => 9, 'msName' => 'lab', 'msBasePort' => 9000,
            'msLogPath' => '', 'msImage' => 5, 'msClients' => 0,
            'msSessClients' => 2, 'msInterface' => '',
            'msStartDateTime' => '2026-11-27 10:00:00', 'msPercent' => 0,
            'msState' => TaskState::getQueuedState(),
            'msCompleteDateTime' => null, 'msIsDD' => 0, 'msNFSGroupID' => 2,
            'msShutdown' => 0, 'msMaxwait' => 0, 'msSenderPID' => 0,
            'msSenderNode' => 0, 'msSenderStart' => null, 'msAnon5' => '',
            'msTransferStart' => null,
        ];
    }
    if (false !== strpos($sql, 'FROM `taskStates`')
        && false !== strpos($sql, ':id')
    ) {
        return ['tsID' => (int)$params[':id'], 'tsName' => 'state'];
    }
    return null;
};
FogTestHarness::setStatic('FOGBase', 'HookManager', new HookManager());
FogTestHarness::setStatic('FOGBase', 'Host', new Host());

$tq = (new \ReflectionClass('FOG\TaskHandling\TaskQueue'))
    ->newInstanceWithoutConstructor();
$Task = new Task(7);
foreach ([
    'Task' => $Task,
    'imagingTask' => true,
    'Image' => $Task->getImage(),
    // A multicast task keeps the master node the constructor picked.
    'StorageNode' => (new McNode())->set('id', 3),
] as $name => $value) {
    $p = new \ReflectionProperty('FOG\TaskHandling\TaskingElement', $name);
    $p->setAccessible(true);
    $p->setValue($tq, $value);
}
ob_start();
$threw = '';
try {
    $tq->checkIn();
} catch (\Throwable $e) {
    $threw = $e->getMessage() . ' at ' . basename($e->getFile())
        . ':' . $e->getLine();
}
$out = ob_get_clean();
$t->check("checkIn answers '##@GO' (got: $out $threw)", '##@GO' === $out);
$sessWrites = mcWritesTo($seen, 'multicastSessions');
$s = $sessWrites ? mcRow($sessWrites[count($sessWrites) - 1]) : [];
$t->check(
    'checkIn counts the machine as a client of the session',
    1 === (int)($s['msClients'] ?? -1)
);
$t->check(
    'checkIn moves the session to In-Progress',
    TaskState::getProgressState() === (int)($s['msState'] ?? -1)
);
$logs = array_filter(
    mcWritesTo($seen, 'taskLog'),
    function ($one) {
        return 0 === strpos($one[0], 'INSERT');
    }
);
$l = $logs ? mcRow(current($logs)) : [];
$t->check(
    'checkIn writes an In-Progress taskLog row carrying the MAC',
    1 === count($logs)
    && TaskState::getProgressState() === (int)($l['taskStateID'] ?? -1)
    && MAC === ($l['logMAC'] ?? null)
);

$seen = [];
$tasks[7]['taskStateID'] = TaskState::getProgressState();
$threw = '';
try {
    (new Task(7))->cancel();
} catch (\Throwable $e) {
    $threw = get_class($e) . ': ' . $e->getMessage();
}
$expectedDown = false;
foreach (mcWritesTo($seen, 'multicastSessions') as $one) {
    if (1 === (int)(mcRow($one)['msSessClients'] ?? -1)
        || in_array(1, array_map('intval', array_values($one[1])), true)
        && false !== strpos($one[0], 'msSessClients')
    ) {
        $expectedDown = true;
    }
}
$t->check(
    "cancel of the hostless join is safe (got: $threw)",
    '' === $threw
);
$t->check(
    'cancel takes one expected client off the session (2 -> 1)',
    $expectedDown
);

$t->finish();
