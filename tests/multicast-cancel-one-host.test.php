<?php
/**
 * Canceling one host removes it from its multicast session, not the session.
 *
 * THE FAILURE. A multicast session waits for every host it was created for.
 * When one of them never boots, the admin cancels that host's task -- and
 * every other host in the session was canceled with it. Task::cancel() set
 * the whole session to canceled; MulticastManager then treated any canceled
 * member as a canceled session, killed udp-sender and canceled the rest.
 * Everyone had to start over. fos#48, forums topic 16528, issue #1903.
 *
 * WHAT THIS PINS:
 *
 *   Task::cancel()          DRIVEN against an in-memory table model. Removing
 *                           one host deletes only its association row, lowers
 *                           a sized session's sessclients by one, and leaves
 *                           the session's state alone. The last host out
 *                           still cancels the session.
 *   TaskManager::cancel()   DRIVEN the same way. The decision is per session,
 *                           and a sized session loses as many expected hosts
 *                           as were canceled out of it.
 *   senderLogState()        DRIVEN on text built from udpcast's own printf
 *                           formats (participants.c, udps-negotiate.c,
 *                           udp-sender.c). Only the current sender counts,
 *                           a retried connect is one host, and a sender that
 *                           has started is never told to start.
 *   _getMCExistingTask()    DRIVEN. A join and a cancel in the same tick must
 *                           not hide the join.
 *   startIfAllConnected()   DRIVEN against a real UDP socket. A host counts
 *                           as arrived at checked-in OR in progress:
 *                           TaskQueue::checkIn() moves every multicast task
 *                           to in progress, so a checked-in-only gate never
 *                           sent the start message on a live session.
 *
 * Usage: php tests/multicast-cancel-one-host.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Items\Host;
use FOG\Service\MulticastTask;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('multicast-cancel-one-host');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A tiny model of the two session tables.
 *
 * Answers the statements the code under test issues, in the shape the real
 * query builder emits them: `col` = :p and `col` IN (:p0,:p1) joined by
 * AND, for SELECT, SELECT COUNT, DELETE and UPDATE.
 */
class SessionTables
{
    /** @var array table name => list of rows */
    public $rows = [];

    /** @var array every UPDATE as [table, set, ids] */
    public $updates = [];

    /**
     * Rows of $table matching the WHERE clause of $sql.
     *
     * @param string $table  The table.
     * @param string $sql    The statement.
     * @param array  $params The bound values.
     *
     * @return array row indexes
     */
    public function match($table, $sql, $params)
    {
        $conds = [];
        if (preg_match('/WHERE (.+?)(ORDER BY|LIMIT|$)/s', $sql, $m)) {
            preg_match_all(
                '/(?:`\w+`\.)?`(\w+)`\s*(=|IN)\s*\(?([:\w,]+)\)?/',
                $m[1],
                $parts,
                PREG_SET_ORDER
            );
            foreach ($parts as $p) {
                $vals = [];
                foreach (explode(',', $p[3]) as $name) {
                    $vals[] = self::param($params, $name);
                }
                $conds[$p[1]] = $vals;
            }
        }
        $out = [];
        foreach ($this->rows[$table] ?? [] as $i => $row) {
            foreach ($conds as $col => $vals) {
                if (!in_array($row[$col] ?? null, $vals)) {
                    continue 2;
                }
            }
            $out[] = $i;
        }
        return $out;
    }

    /**
     * A bound value, whichever way the builder keyed it.
     *
     * Finders bind 'where_0'; the single-row loader binds ':id'.
     *
     * @param array  $params The bound values.
     * @param string $name   The placeholder, with or without its colon.
     *
     * @return mixed
     */
    public static function param($params, $name)
    {
        $bare = ltrim($name, ':');
        return $params[$bare] ?? $params[':' . $bare] ?? null;
    }

    /**
     * The fake database's responder.
     *
     * @param string $sql    The statement.
     * @param array  $params The bound values.
     *
     * @return array|null
     */
    public function answer($sql, $params)
    {
        if (!preg_match(
            '/^\s*(SELECT|DELETE|UPDATE|INSERT)\b.*?`(multicastSessionsAssoc|multicastSessions|tasks)`/s',
            $sql,
            $m
        )) {
            return null;
        }
        $table = $m[2];
        if ('INSERT' === $m[1]) {
            // FOGController::save() writes an existing row as an upsert of
            // every column it holds, keyed on the primary key.
            preg_match('/\(([^)]+)\) VALUES \(([^)]+)\)/', $sql, $iv);
            $cols = array_map(
                function ($c) {
                    return trim($c, '` ');
                },
                explode(',', $iv[1])
            );
            $vals = array_map(
                function ($n) use ($params) {
                    return self::param($params, trim($n));
                },
                explode(',', $iv[2])
            );
            $row = array_combine($cols, $vals);
            foreach ($this->rows[$table] as $i => $old) {
                if ($old['msID'] == $row['msID']) {
                    $this->rows[$table][$i] = array_merge($old, $row);
                }
            }
            return [];
        }
        $hits = $this->match($table, $sql, $params);
        switch ($m[1]) {
            case 'DELETE':
                foreach ($hits as $i) {
                    unset($this->rows[$table][$i]);
                }
                return [];
            case 'UPDATE':
                preg_match('/SET (.+?) WHERE/s', $sql, $s);
                $set = [];
                preg_match_all(
                    '/`(\w+)`\s*=\s*:(\w+)/',
                    $s[1],
                    $f,
                    PREG_SET_ORDER
                );
                foreach ($f as $pair) {
                    $set[$pair[1]] = self::param($params, $pair[2]);
                }
                foreach ($hits as $i) {
                    $this->rows[$table][$i] = array_merge(
                        $this->rows[$table][$i],
                        $set
                    );
                }
                $this->updates[] = [$table, $set, $hits];
                return [];
        }
        if (preg_match('/SELECT\s+COUNT/i', $sql)) {
            return [['total' => count($hits)]];
        }
        // The single-row loader selects `table`.* with joins and reads it
        // with a plain fetch(), which in PDODB is ONE row, not a list.
        // Finders name their columns and read a list.
        if (false !== strpos($sql, '.*')) {
            return $hits ? $this->rows[$table][$hits[0]] : [];
        }
        $cols = FogFakePdo::selectedColumns($sql);
        $rows = [];
        foreach ($hits as $i) {
            $row = $this->rows[$table][$i];
            $rows[] = array_intersect_key($row, array_flip($cols));
        }
        return $rows;
    }

    /**
     * Was the session canceled by any UPDATE?
     *
     * @param int $msID The session.
     *
     * @return bool
     */
    public function sessionCanceled($msID)
    {
        foreach ($this->rows['multicastSessions'] as $row) {
            if ($row['msID'] == $msID) {
                return (int)$row['msState'] === 5;
            }
        }
        return false;
    }

    /**
     * A session's sessclients now.
     *
     * @param int $msID The session.
     *
     * @return int
     */
    public function sessClients($msID)
    {
        foreach ($this->rows['multicastSessions'] as $row) {
            if ($row['msID'] == $msID) {
                return (int)$row['msSessClients'];
            }
        }
        return -1;
    }
}

/**
 * Builds the tables: session 10 holds tasks 7, 8 and 9 and was sized for
 * three; session 20 holds only task 30.
 *
 * @return SessionTables
 */
function freshTables()
{
    $m = new SessionTables();
    $m->rows['multicastSessionsAssoc'] = [
        ['msaID' => 1, 'msID' => 10, 'tID' => 7],
        ['msaID' => 2, 'msID' => 10, 'tID' => 8],
        ['msaID' => 3, 'msID' => 10, 'tID' => 9],
        ['msaID' => 4, 'msID' => 20, 'tID' => 30],
    ];
    $base = [
        'msName' => 'lab',
        'msBasePort' => 9000,
        'msLogPath' => 'img',
        'msImage' => 1,
        'msClients' => 0,
        'msState' => 1,
        'msMaxwait' => 600,
        'msNFSGroupID' => 1,
    ];
    $m->rows['multicastSessions'] = [
        ['msID' => 10, 'msSessClients' => 3] + $base,
        ['msID' => 20, 'msSessClients' => 0] + $base,
    ];
    return $m;
}

/**
 * A multicast Task whose relationships resolve without a database.
 */
class McCancelTask extends \FOG\Items\Task
{
    public $stubHost;

    public function getHost()
    {
        return $this->stubHost;
    }

    public function isMulticast($nums = false)
    {
        return true;
    }

    public function getTaskTypeText()
    {
        return 'Deploy';
    }

    public function isImagingTask()
    {
        return true;
    }
}

/**
 * Cancels one task through Task::cancel().
 *
 * @param int $id The task id.
 *
 * @return void
 */
function cancelOne($id)
{
    $task = new McCancelTask();
    $task->stubHost = (new Host())->set('id', 40 + $id)->set('name', "h$id");
    $task
        ->set('id', $id)
        ->set('typeID', 8)
        ->set('hostID', 40 + $id)
        ->set('stateID', 2)
        ->set('createdBy', 'fog');
    // UbootTftpSync reaches for a storage node the fake cannot give it; its
    // complaint is about the harness, not about the behavior under test.
    ob_start();
    @$task->cancel();
    ob_end_clean();
}

$db->pdo->rowCount = 0;

// --- Task::cancel(): one host out of three --------------------------------
$m = freshTables();
$db->responder = [$m, 'answer'];
cancelOne(7);
$left = array_column($m->rows['multicastSessionsAssoc'], 'tID');
$t->check(
    'Task::cancel() deletes only the canceled host\'s association',
    [8, 9, 30] == $left
);
$t->check(
    'Task::cancel() leaves the session running when hosts remain',
    !$m->sessionCanceled(10)
);
$t->check(
    'Task::cancel() lowers a sized session\'s sessclients by one',
    2 === $m->sessClients(10)
);
$t->check(
    'Task::cancel() does not touch another session',
    !$m->sessionCanceled(20) && 0 === $m->sessClients(20)
);

// --- Task::cancel(): the last host out ------------------------------------
$m = freshTables();
$db->responder = [$m, 'answer'];
cancelOne(30);
$t->check(
    'Task::cancel() of the last host cancels the session',
    $m->sessionCanceled(20)
);

// --- TaskManager::cancel(): per-session decision --------------------------
$m = freshTables();
$db->responder = [$m, 'answer'];
ob_start();
@(new \FOG\Managers\TaskManager())->cancel([7, 8, 30]);
ob_end_clean();
$t->check(
    'TaskManager::cancel() keeps a session that still has a host',
    !$m->sessionCanceled(10)
);
$t->check(
    'TaskManager::cancel() cancels a session it emptied, in the same call',
    $m->sessionCanceled(20)
);
$t->check(
    'TaskManager::cancel() lowers sessclients by the hosts removed',
    1 === $m->sessClients(10)
);
$db->responder = null;

// --- senderLogState(): text from udpcast's own formats --------------------
// udp-sender.c: "Udp-sender %s\n"; udps-negotiate.c: "%sUDP sender for %s
// at ", "Starting transfer: %08x\n"; participants.c: "New connection from
// %s  (#%d) %08x\n", "Disconnecting #%d (%s)\n".
$stream0 = "Udp-sender 20250223\n"
    . "UDP sender for (stdin) at 10.0.0.1 on eth0\n"
    . "Broadcasting control to 224.0.0.1\n"
    . "New connection from 10.0.0.21  (#0) 00000009\n"
    . "New connection from 10.0.0.22  (#1) 00000009\n"
    . "Starting transfer: 00000009\n"
    . "Transfer complete.\x07\n"
    . "Disconnecting #0 (10.0.0.21)\n"
    . "Disconnecting #1 (10.0.0.22)\n";
$stream1Waiting = "Udp-sender 20250223\n"
    . "UDP sender for (stdin) at 10.0.0.1 on eth0\n"
    . "New connection from 10.0.0.21  (#0) 00000009\n"
    . "New connection from 10.0.0.21  (#0) 00000009\n"
    . "New connection from 10.0.0.22  (#1) 00000009\n";

$s = MulticastTask::senderLogState('');
$t->check(
    'an empty log reports nothing connected and not started',
    false === $s['started'] && 0 === $s['connected']
);
$s = MulticastTask::senderLogState($stream0 . $stream1Waiting);
$t->check(
    'only the current sender counts: the earlier stream started, this one has not',
    false === $s['started']
);
$t->check(
    'a retried connect from one address is one host',
    2 === $s['connected']
);
$s = MulticastTask::senderLogState($stream0);
$t->check(
    'a sender that started reports started',
    true === $s['started']
);
$s = MulticastTask::senderLogState(
    $stream1Waiting . "Disconnecting #1 (10.0.0.22)\n"
);
$t->check(
    'a host that disconnected is no longer counted',
    1 === $s['connected']
);

// --- _getMCExistingTask(): a join and a cancel in the same tick -----------
/**
 * Holds an id list the way MulticastTask does, without a session.
 */
class IdHolder
{
    public $ids;

    public $id;

    public function __construct($id, $ids)
    {
        $this->id = $id;
        $this->ids = $ids;
    }

    public function getID()
    {
        return $this->id;
    }

    public function getTaskIDs()
    {
        return $this->ids;
    }

    public function setTaskIDs($ids = [])
    {
        $this->ids = $ids;
    }
}
// Known had 7, 8, 9. Task 9 was canceled (row gone) and task 11 joined in
// the same tick, so the fresh list is the same length as the known one.
$known = new IdHolder(10, [7, 8, 9]);
$fresh = new IdHolder(10, [7, 8, 11]);
$found = FogTestHarness::callStatic(
    'FOG\\Service\\MulticastManager',
    '_getMCExistingTask',
    [[$known], $fresh]
);
$ids = $found->getTaskIDs();
sort($ids);
$t->check(
    'a join in the same tick as a cancel is still seen',
    in_array(11, $ids)
);
$t->check(
    'the canceled id stays so the manager can see it was canceled',
    in_array(9, $ids)
);

// --- _lostEveryHost(): a named session starts with no hosts ---------------
// Issue #1922: the empty list a named session starts with read as "every
// host canceled", so the session was canceled before any host could join.
$lost = function ($taskIDs, $liveIDs) {
    return FogTestHarness::callStatic(
        'FOG\\Service\\MulticastManager',
        '_lostEveryHost',
        [$taskIDs, $liveIDs]
    );
};
$t->check(
    'a named session nobody has joined yet keeps running',
    false === $lost([], [])
);
$t->check(
    'a session whose every host was canceled ends',
    true === $lost([7, 8], [])
);
$t->check(
    'a session with one host left keeps running',
    false === $lost([7, 8], [8])
);

// --- startIfAllConnected(): which task states mean "arrived" -------------
/**
 * A sender whose udpcast log is a file the test writes.
 */
class GoTask extends MulticastTask
{
    public $logPath;

    public function getUDPCastLogFile()
    {
        return $this->logPath;
    }
}

/**
 * Runs startIfAllConnected() for a session launched for three hosts, after
 * one was canceled: tasks 7 and 8 remain, both connected to udp-sender.
 *
 * @param int $state The stateID both remaining tasks are in.
 *
 * @return array [returned, bytes the sender's port received]
 */
function goWith($state)
{
    global $db;
    $m = freshTables();
    $m->rows['tasks'] = [
        ['taskID' => 7, 'taskStateID' => $state],
        ['taskID' => 8, 'taskStateID' => $state],
    ];
    $db->responder = [$m, 'answer'];
    $port = 47000 + 2 * mt_rand(0, 900);
    $recv = stream_socket_server(
        sprintf('udp://127.0.0.1:%d', $port + 1),
        $errno,
        $errstr,
        STREAM_SERVER_BIND
    );
    stream_set_blocking($recv, false);
    $log = tempnam(sys_get_temp_dir(), 'mcgo');
    file_put_contents(
        $log,
        "Udp-sender 20200328\n"
        . "UDP sender for img at 127.0.0.1 on eth0\n"
        . "New connection from 10.20.0.57  (#0) 00000009\n"
        . "New connection from 10.20.0.65  (#1) 00000009\n"
    );
    $task = new GoTask(99, 'lab', $port, 'img', 'lo', 3, 1, 50, false, [7, 8]);
    $task->logPath = $log;
    ob_start();
    $ret = $task->startIfAllConnected(2, [7, 8], '127.0.0.1');
    ob_end_clean();
    usleep(100000);
    $got = (string)@fread($recv, 64);
    fclose($recv);
    unlink($log);
    $db->responder = null;
    return [$ret, $got];
}
// Live session: TaskQueue::checkIn() has moved both tasks to in progress.
list($ret, $got) = goWith(3);
$t->check(
    'hosts in progress count as arrived, so the start message is sent',
    true === $ret
);
$t->check(
    'the start message is CMD_GO, opcode 2, in network order',
    pack('nn', 2, 0) === $got
);
list($ret, $got) = goWith(2);
$t->check(
    'hosts still at checked-in count as arrived too',
    true === $ret && pack('nn', 2, 0) === $got
);
list($ret, $got) = goWith(1);
$t->check(
    'a host still queued has not arrived, so nothing is sent',
    false === $ret && '' === $got
);

$t->finish();
