<?php
/**
 * A hostless task checks in, completes, fails and is blamed without a host.
 *
 * Forum 18086: an unregistered machine is imaged under a hostless task found
 * by its boot token. FOS then calls check-in, checkout, taskerror and blame
 * like any other imaging run, and self::$Host is an EMPTY Host on every one
 * of those requests. Each host-dependent write on those paths has to be
 * skipped, and each audit row and notification has to name the machine by
 * its MAC. The cases:
 *
 *   checkIn   -> '##@GO', In-Progress, a log row, an audit row naming the MAC
 *   checkout  -> '##', Complete, NO write to `hosts`, NO email, an image
 *                stamp, and a registered host STILL gets its host update
 *   taskerror -> Failed, and a log row that carries the MAC
 *   blame     -> no nodeFailure row, the task goes back to Queued
 *   cancel    -> Task::cancel() on a hostless task does not fatal
 *
 * checkIn, checkout and cancel run in this process against a fake database
 * (tests/lib/hostless-fixture.php reads the SQL it is sent). taskerror and
 * blame end in exit, and read the token with filter_input(), so they run in a
 * php-cgi child that this file re-launches with a real POST body. Those
 * cases SKIP without php-cgi, as tests/api-bad-request-statuses.test.php does.
 *
 * Usage: php tests/hostless-checkin-checkout.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\HookManager;
use FOG\Items\Host;
use FOG\Items\Task;
use FOG\Items\TaskState;

require __DIR__ . '/lib/fog-test-harness.php';
require __DIR__ . '/lib/hostless-fixture.php';

const RAW = 'ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12';
const MAC = 'aa:bb:cc:dd:ee:ff';
const LABEL = 'aa:bb:cc:dd:ee:ff (unregistered)';

// Booted first: the classes below extend core classes, which load through the
// autoloader boot() registers.
FogTestHarness::boot('hostless-checkin-checkout');
$db = FogTestHarness::fakeDb();
$db->pdo->countValue = 0;
$db->pdo->rowCount = 0;

/**
 * Records the event names it is asked to process.
 */
class EventNameSpy extends HookManager
{
    /** @var string[] */
    private $names = [];

    public function processEvent($event, $arguments = [])
    {
        $this->names[] = $event;
        parent::processEvent($event, $arguments);
    }

    public function reset()
    {
        $this->names = [];
    }

    public function saw($event)
    {
        return in_array($event, $this->names, true);
    }

    public function names()
    {
        return $this->names;
    }
}

/**
 * Records the notifications it is given.
 */
class NotifyNameSpy
{
    /** @var array[] */
    private $calls = [];

    public function notify($event, $data = [])
    {
        $this->calls[] = ['event' => $event, 'data' => $data];
        return true;
    }

    public function reset()
    {
        $this->calls = [];
    }

    public function names()
    {
        return array_column($this->calls, 'event');
    }

    /**
     * The payloads notified under one event name.
     *
     * @param string $event the event name
     *
     * @return array[]
     */
    public function dataFor($event)
    {
        $out = [];
        foreach ($this->calls as $call) {
            if ($call['event'] === $event) {
                $out[] = $call['data'];
            }
        }
        return $out;
    }
}

/**
 * A registered host, valid without a database row.
 */
class RegisteredHost extends Host
{
    public function isValid()
    {
        return true;
    }

    public function get($key = '')
    {
        $fixed = ['id' => 12, 'name' => 'labhost'];
        return $fixed[$key] ?? parent::get($key);
    }
}

/**
 * The INSERTs into one table, as column => bound value maps.
 *
 * @param array  $seen  [sql, params] pairs
 * @param string $table the table name
 *
 * @return array
 */
function inserts(array $seen, $table)
{
    $out = [];
    foreach ($seen as $one) {
        if (0 !== strpos($one[0], 'INSERT INTO `' . $table . '`')
            || !preg_match('#\(([^)]*)\)\s*VALUES#is', $one[0], $m)
        ) {
            continue;
        }
        preg_match_all('#`([^`]+)`#', $m[1], $names);
        $values = array_values($one[1]);
        if (count($names[1]) === count($values)) {
            $out[] = array_combine($names[1], $values);
        }
    }
    return $out;
}

/**
 * Whether any statement writes to a table, by INSERT, UPDATE or DELETE.
 *
 * @param array  $seen  [sql, params] pairs
 * @param string $table the table name
 *
 * @return bool
 */
function writesTo(array $seen, $table)
{
    foreach ($seen as $one) {
        if (preg_match(
            '#^\s*(INSERT INTO|UPDATE|DELETE FROM)\s+`' . $table . '`#i',
            $one[0]
        )) {
            return true;
        }
    }
    return false;
}

/**
 * Runs one request of the given kind in a php-cgi child.
 *
 * @param string $case the case name
 * @param string $body the urlencoded POST body
 *
 * @return array decoded marker lines: kind => list of payloads
 */
function hostlessChild($case, $body)
{
    $bin = trim((string)@shell_exec('command -v php-cgi 2>/dev/null'));
    $env = [
        'REDIRECT_STATUS' => '200',
        'SCRIPT_FILENAME' => __FILE__,
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => (string)strlen($body),
        'QUERY_STRING' => 'case=' . rawurlencode($case),
        'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
    ];
    $pipes = [];
    $proc = proc_open(
        [$bin, '-q'],
        // stderr to the null device: unread, a chatty child would block.
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        null,
        $env
    );
    if (!is_resource($proc)) {
        return ['CRASH' => ['spawn failed']];
    }
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($proc);
    $found = [];
    foreach (preg_split('/\r?\n/', $out) as $line) {
        $line = trim($line);
        if (preg_match('/^INSERT (\S+) (.*)$/', $line, $m)) {
            $found['INSERT'][] = [$m[1], json_decode($m[2], true)];
        } elseif (0 === strpos($line, 'TOUCH ')) {
            $found['TOUCH'][] = $line;
        } elseif (0 === strpos($line, 'EVENT ')) {
            $found['EVENT'][] = substr($line, 6);
        } elseif ('No nodes available' === $line) {
            // The fixture has no storage node list, so the TaskingElement
            // constructor ends here. That is AFTER the event point, which
            // is what the case below needs it to have passed.
            $found['PAST_EVENT'][] = $line;
        } elseif ('##' === $line) {
            $found['ACK'][] = '##';
        } elseif (false !== strpos($line, 'Fatal error')) {
            $found['CRASH'][] = $line;
        }
    }
    if (!$found) {
        $found['CRASH'][] = 'no marker: ' . trim(str_replace("\n", ' | ', $out));
    }
    return $found;
}

/**
 * The rows a child saw for one table.
 *
 * @param array  $found hostlessChild()'s result
 * @param string $table the table
 *
 * @return array
 */
function childRows(array $found, $table)
{
    $rows = [];
    foreach ($found['INSERT'] ?? [] as $one) {
        if ($one[0] === $table) {
            $rows[] = $one[1];
        }
    }
    return $rows;
}

/**
 * The responder both modes share: tasks, task types, task states and node.
 *
 * @param array $tasks the fake tasks table
 * @param array $seen  receives every [sql, params]
 *
 * @return callable
 */
function responder(array &$tasks, array &$seen)
{
    $base = hostlessResponder($tasks, $seen);
    return function ($sql, $params) use ($base) {
        $answer = $base($sql, $params);
        if (null !== $answer) {
            return $answer;
        }
        if (false !== strpos($sql, 'FROM `nfsGroupMembers`')
            && false !== strpos($sql, ':id')
        ) {
            return [
                'ngmID' => 3, 'ngmGroupID' => 2, 'ngmMemberName' => 'node1',
                'ngmIsEnabled' => 1, 'ngmMaxClients' => 10,
                'ngmHostname' => '10.0.0.5', 'ngmFTPPath' => '/images',
                'ngmRootPath' => '/images', 'ngmSnapinPath' => '/snapins',
                'ngmIsMasterNode' => 1, 'ngmUser' => 'fog', 'ngmPass' => 'x',
            ];
        }
        if (false !== strpos($sql, 'FROM `taskStates`')
            && false !== strpos($sql, ':id')
        ) {
            return ['tsID' => (int)$params[':id'], 'tsName' => 'state'];
        }
        return null;
    };
}

// ---------------------------------------------------------------- child mode

if ('cli' !== PHP_SAPI) {
    $tasks = [
        7 => hostlessRow([
            'taskTokenHash' => hash('sha256', RAW),
            'taskStateID' => TaskState::getProgressState(),
        ]),
    ];
    $case = (string)($_GET['case'] ?? '');
    if ('blame-registered' === $case) {
        // Owned by host 12, as a task made for a registered host is.
        $tasks[7]['taskHostID'] = 12;
        $tasks[7]['taskTokenHash'] = '';
    }
    $seen = [];
    $db->responder = responder($tasks, $seen);
    $hooks = new EventNameSpy();
    $notes = new NotifyNameSpy();
    FogTestHarness::setStatic('FOGBase', 'HookManager', $hooks);
    FogTestHarness::setStatic('FOGBase', 'EventManager', $notes);
    // The script under test ends in exit, so what it wrote is printed on the
    // way out.
    register_shutdown_function(function () use (&$seen, $db, $hooks, $notes) {
        // The ack has no newline of its own.
        echo "\n";
        // Every host-scoped event the request fired, by either channel.
        foreach (array_merge($hooks->names(), $notes->names()) as $name) {
            echo 'EVENT ' . $name . "\n";
        }
        // Route::getList() reads through the raw handle, not query().
        foreach ($db->pdo->log as $sql) {
            $seen[] = [$sql, []];
        }
        // nfsFailures is the table behind NodeFailure. Any statement that
        // touches it counts: a hostless task must not even look.
        foreach ($seen as $one) {
            if (false !== strpos($one[0], '`nfsFailures`')) {
                echo 'TOUCH ' . substr($one[0], 0, 40) . "\n";
            }
        }
        foreach (['taskLog', 'tasks', 'nfsFailures'] as $table) {
            foreach (inserts($seen, $table) as $row) {
                echo 'INSERT ' . $table . ' ' . json_encode($row) . "\n";
            }
        }
    });
    // A fatal in the script under test must show up in the output, not
    // vanish into a blank 500.
    ini_set('display_errors', '1');
    // Fatals only: the fixture rows are thinner than a real database's, and
    // the missing-column notices would bury them.
    error_reporting(E_ERROR | E_PARSE);
    echo "ACK-START\n";
    if ('taskerror' === $case) {
        new FOG\TaskHandling\TaskError();
    } elseif ('tasking' === $case) {
        // TaskingElement is abstract: the base every FOS endpoint extends.
        new class extends FOG\TaskHandling\TaskingElement {
        };
    } elseif (0 === strpos($case, 'blame')) {
        // Blame's own work is in its constructor, after parent::__construct()
        // has found the task. The real TaskingElement constructor needs a
        // storage group, a node probe and a hook run, none of which are what
        // is under test, so the REAL Blame source is run on a small base that
        // finds the task the same way (the token first) and nothing else.
        // `blame-registered` is the control: a host-owned task, which must
        // still blame its node.
        eval(
            'namespace FOG\\Audit;'
            . 'class BlameBase extends \\FOG\\Base\\FOGBase {'
            . ' protected $Task;'
            . ' public function __construct() {'
            . '  parent::__construct();'
            . '  $this->Task = \\FOG\\TaskHandling\\BootToken::resolve();'
            . '  if (null === $this->Task) {'
            // Only the control may fall back to a host. A token that finds
            // nothing is a failure, not a registered host.
            . '   if (\'blame-registered\' !== ($_GET[\'case\'] ?? \'\')) {'
            . '    echo "Fatal error: no task found\\n"; exit;'
            . '   }'
            . '   self::$Host = new \\RegisteredHost();'
            . '   $this->Task = new \\FOG\\Items\\Task(7);'
            . '  }'
            . ' }'
            . '}'
        );
        $src = file_get_contents(
            dirname(__DIR__) . '/packages/web/src/Audit/Blame.php'
        );
        $under = str_replace(
            'class Blame extends TaskingElement',
            'class BlameUnderTest extends BlameBase',
            $src
        );
        if ($under === $src) {
            echo "Fatal error: Blame no longer extends TaskingElement\n";
            exit;
        }
        eval('?>' . $under);
        new FOG\Audit\BlameUnderTest();
    }
    exit;
}

// ---------------------------------------------------------------- part 1

$t = new FogChecks();

/**
 * Builds a TaskQueue around a loaded task without running its constructor,
 * which would read the token from a request.
 *
 * @param Task $Task the task
 *
 * @return object
 */
function queueFor(Task $Task)
{
    $tq = (new \ReflectionClass('FOG\TaskHandling\TaskQueue'))
        ->newInstanceWithoutConstructor();
    foreach ([
        'Task' => $Task,
        'imagingTask' => true,
        'Image' => $Task->getImage(),
    ] as $name => $value) {
        $p = new \ReflectionProperty('FOG\TaskHandling\TaskingElement', $name);
        $p->setAccessible(true);
        $p->setValue($tq, $value);
    }
    return $tq;
}

$hooks = new EventNameSpy();
$notes = new NotifyNameSpy();
FogTestHarness::setStatic('FOGBase', 'HookManager', $hooks);
FogTestHarness::setStatic('FOGBase', 'EventManager', $notes);

// ----- checkIn, hostless

$tasks = [7 => hostlessRow(['taskTokenHash' => hash('sha256', RAW)])];
$seen = [];
$db->responder = responder($tasks, $seen);
FogTestHarness::setStatic('FOGBase', 'Host', new Host());
$tq = queueFor(new Task(7));
ob_start();
$tq->checkIn();
$out = ob_get_clean();

$t->check("checkIn answers '##@GO' (got: $out)", '##@GO' === $out);
$row = (inserts($seen, 'tasks') ?: [[]])[count(inserts($seen, 'tasks')) - 1];
$t->check(
    'checkIn moves the task to In-Progress',
    TaskState::getProgressState() === (int)($row['taskStateID'] ?? -1)
);
$t->check(
    'checkIn records the check-in time',
    !empty($row['taskCheckIn'])
);
$t->check(
    'the task is still saved with no host',
    !array_key_exists('taskHostID', $row) || null === $row['taskHostID']
);
$logs = inserts($seen, 'taskLog');
$t->check(
    'checkIn writes an In-Progress taskLog row',
    1 === count($logs)
    && TaskState::getProgressState() === (int)$logs[0]['taskStateID']
);
$audit = inserts($seen, 'auditLog');
$starts = array_filter($audit, function ($r) {
    return in_array('task.start', $r, true);
});
$t->check(
    'the task.start audit row names the machine by its MAC',
    1 === count($starts) && in_array(LABEL, current($starts), true)
);
$t->check(
    'checkIn writes nothing to `hosts`',
    !writesTo($seen, 'hosts')
);
$t->check(
    'HOST_CHECKIN is not fired: there is no host to hand its listeners',
    [] === $notes->dataFor('HOST_CHECKIN')
);

// ----- checkout, hostless

$tasks = [
    7 => hostlessRow([
        'taskTokenHash' => hash('sha256', RAW),
        'taskStateID' => TaskState::getProgressState(),
        'taskCheckIn' => FogTestHarness::getStatic('FOGBase', 'DB') ? date('Y-m-d H:i:s') : '',
    ]),
];
$seen = [];
$hooks->reset();
$notes->reset();
$db->responder = responder($tasks, $seen);
FogTestHarness::setStatic('FOGBase', 'Host', new Host());
$tq = queueFor(new Task(7));
ob_start();
$tq->checkout();
$out = ob_get_clean();

$t->check("checkout answers '##' (got: $out)", '##' === $out);
$rows = inserts($seen, 'tasks');
$row = $rows ? $rows[count($rows) - 1] : [];
$t->check(
    'checkout moves the task to Complete at 100 percent',
    TaskState::getCompleteState() === (int)($row['taskStateID'] ?? -1)
    && 100 === (int)($row['taskPCT'] ?? 0)
);
$t->check(
    'checkout writes nothing to `hosts` (no key reset, no deployed stamp)',
    !writesTo($seen, 'hosts')
);
$emailRead = false;
foreach ($seen as $one) {
    if (false !== strpos($one[0], 'FOG_EMAIL_ACTION')) {
        $emailRead = true;
    }
}
$t->check('checkout does not reach the email path', !$emailRead);
$t->check(
    'checkout still stamps the image as deployed',
    1 === count(inserts($seen, 'images'))
    && !empty(inserts($seen, 'images')[0]['imageLastDeploy'])
);
$t->check(
    'HOST_TASKING_COMPLETE is not fired: there is no host',
    !$hooks->saw('HOST_TASKING_COMPLETE')
);
$complete = $notes->dataFor('HOST_IMAGE_COMPLETE');
$t->check(
    'the completion notice names the machine by its MAC',
    1 === count($complete) && LABEL === $complete[0]['HostName']
);
$done = array_filter(inserts($seen, 'auditLog'), function ($r) {
    return in_array('task.complete', $r, true);
});
$t->check(
    'the task.complete audit row names the machine by its MAC',
    1 === count($done) && in_array(LABEL, current($done), true)
);
$logs = inserts($seen, 'taskLog');
$t->check(
    'checkout writes a Complete taskLog row',
    1 === count($logs)
    && TaskState::getCompleteState() === (int)$logs[0]['taskStateID']
);

// ----- checkout, registered host: the control that makes the above bite

$tasks = [
    7 => hostlessRow([
        'taskHostID' => 12,
        'taskTokenHash' => '',
        'taskStateID' => TaskState::getProgressState(),
    ]),
];
$seen = [];
$hooks->reset();
$db->responder = responder($tasks, $seen);
FogTestHarness::setStatic('FOGBase', 'Host', new RegisteredHost());
$tq = queueFor(new Task(7));
ob_start();
$tq->checkout();
$out = ob_get_clean();
$t->check(
    'a registered host still gets its key reset and deployed stamp at checkout'
    . " (got: $out)",
    writesTo($seen, 'hosts') && '##' === $out
);
$t->check(
    'a registered host still fires HOST_TASKING_COMPLETE',
    $hooks->saw('HOST_TASKING_COMPLETE')
);

// ----- cancel, hostless

$tasks = [
    7 => hostlessRow([
        'taskTokenHash' => hash('sha256', RAW),
        'taskStateID' => TaskState::getProgressState(),
    ]),
];
$seen = [];
$db->responder = responder($tasks, $seen);
FogTestHarness::setStatic('FOGBase', 'Host', new Host());
$threw = '';
try {
    (new Task(7))->cancel();
} catch (\Throwable $e) {
    $threw = get_class($e) . ': ' . $e->getMessage();
}
$t->check("Task::cancel() on a hostless task is safe (got: $threw)", '' === $threw);
$rows = inserts($seen, 'tasks');
$row = $rows ? $rows[count($rows) - 1] : [];
$t->check(
    'cancel writes the Canceled state',
    TaskState::getCancelledState() === (int)($row['taskStateID'] ?? -1)
);

// ----- bulk cancel, hostless: TaskManager::cancel() drops the NULL host id

// One active task. The host id the SELECT answers is $cancelHost: NULL for a
// hostless task, 12 for the control below.
$cancelHost = null;
$seen = [];
$db->responder = function ($sql, $params) use (&$seen, &$cancelHost) {
    $seen[] = [$sql, (array)$params];
    if (0 === strpos($sql, 'SELECT `taskHostID` FROM `tasks`')) {
        return [['taskHostID' => $cancelHost]];
    }
    if (0 === strpos($sql, 'SELECT `taskID` FROM `tasks`')) {
        return [['taskID' => 7]];
    }
    return null;
};
FogTestHarness::setStatic('FOGBase', 'Host', new Host());
$threw = '';
try {
    (new FOG\Managers\TaskManager())->cancel([7]);
} catch (\Throwable $e) {
    $threw = get_class($e) . ': ' . $e->getMessage();
}
$t->check("TaskManager::cancel() on a hostless task is safe (got: $threw)", '' === $threw);
$t->check(
    'a bulk cancel of a hostless task writes nothing to `hosts`'
    . ' (it would reset the token of host NULL)',
    !writesTo($seen, 'hosts')
);

// The control: the same cancel for a task that has a host still resets the
// host's token and lock.
$cancelHost = 12;
$seen = [];
(new FOG\Managers\TaskManager())->cancel([7]);
$t->check(
    'a bulk cancel of a task with a host still resets that host',
    writesTo($seen, 'hosts')
);

// ---------------------------------------------------------------- part 2

if ('' === trim((string)@shell_exec('command -v php-cgi 2>/dev/null'))) {
    echo "SKIP: no php-cgi binary, so taskerror and blame were not run\n";
    $t->finish();
}

$post = http_build_query([
    'fogtoken' => RAW,
    'mac' => MAC,
    'text' => 'could not mount the image',
    'type' => 'error',
]);
$found = hostlessChild('taskerror', $post);
$crash = implode(' | ', $found['CRASH'] ?? []);
$taskRows = childRows($found, 'tasks');
$failedRow = $taskRows ? $taskRows[count($taskRows) - 1] : [];
$t->check(
    "taskerror with a token marks the hostless task Failed (got: $crash)",
    '' === $crash
    && TaskState::getFailedState() === (int)($failedRow['taskStateID'] ?? -1)
);
$t->check(
    'taskerror for a hostless task notifies HOST_IMAGE_FAIL, as checkout does'
    . ' (got: ' . implode(',', $found['EVENT'] ?? []) . ')',
    in_array('HOST_IMAGE_FAIL', $found['EVENT'] ?? [], true)
);
$logRows = childRows($found, 'taskLog');
$t->check(
    'taskerror writes a log row with the report text',
    1 === count($logRows)
    && 'could not mount the image' === ($logRows[0]['logText'] ?? null)
);
$t->check(
    'the taskerror log row carries the MAC',
    1 === count($logRows) && MAC === ($logRows[0]['logMAC'] ?? null)
);
$t->check(
    "taskerror still answers '##'",
    ['##'] === array_map('trim', $found['ACK'] ?? [])
);

// The task is found by the token and the request builds, and not one
// host-scoped event is processed on the way: HOST_NEW_SETTINGS would hand a
// listener an empty Host to override the node and group of.
$found = hostlessChild(
    'tasking',
    http_build_query(['fogtoken' => RAW, 'mac' => MAC])
);
$crash = implode(' | ', $found['CRASH'] ?? []);
$t->check(
    "a hostless request finds its task and gets past the event point"
    . " (got: $crash)",
    '' === $crash && [] !== ($found['PAST_EVENT'] ?? [])
);
$t->check(
    'a hostless request fires no HOST_NEW_SETTINGS (got: '
    . implode(',', $found['EVENT'] ?? []) . ')',
    !in_array('HOST_NEW_SETTINGS', $found['EVENT'] ?? [], true)
);

$found = hostlessChild(
    'blame',
    http_build_query(['fogtoken' => RAW, 'mac' => MAC])
);
$crash = implode(' | ', $found['CRASH'] ?? []);
$t->check(
    "blame with a token writes no nodeFailure row (got: $crash)",
    '' === $crash && [] === childRows($found, 'nfsFailures')
);
$t->check(
    'blame with a token does not even read the node failures'
    . ' (got: ' . implode(' | ', $found['TOUCH'] ?? []) . ')',
    [] === ($found['TOUCH'] ?? [])
);
$taskRows = childRows($found, 'tasks');
$requeued = $taskRows ? $taskRows[count($taskRows) - 1] : [];
$t->check(
    'blame still puts the hostless task back in the queue',
    TaskState::getQueuedState() === (int)($requeued['taskStateID'] ?? -1)
);
$t->check(
    "blame still answers '##'",
    ['##'] === array_map('trim', $found['ACK'] ?? [])
);

// The control: a task that has a host still blames its node, so the check
// above is not passing because blame never writes one at all.
$found = hostlessChild('blame-registered', http_build_query(['mac' => MAC]));
$crash = implode(' | ', $found['CRASH'] ?? []);
$t->check(
    "blame for a registered host still writes its nodeFailure row (got: $crash)",
    '' === $crash && 1 === count(childRows($found, 'nfsFailures'))
);

$t->finish();
