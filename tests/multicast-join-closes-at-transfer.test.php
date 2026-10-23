<?php
/**
 * The PXE join to a multicast session closes when udp-sender starts sending.
 *
 * THE FAILURE. Any machine that knows a session's name can join it from the
 * PXE menu. A registered host gets a task; an unregistered one does not.
 * MulticastSession::isJoinable() closed the join when the session's tasks
 * reached its size, so a session of unregistered machines never closed by
 * that rule. udp-sender began transmitting once enough receivers connected,
 * and the menu still let a late machine in. That machine received a partial
 * image. Issue #1922.
 *
 * WHAT THIS PINS:
 *
 *   isJoinable()            DRIVEN. A recorded transfer start closes the
 *                           join, inside the wait window and with no task
 *                           in the session.
 *   recordTransferStart()   DRIVEN on text built from udpcast's own printf
 *                           formats. It writes msTransferStart once the
 *                           sender's log says "Starting transfer", and only
 *                           once per sender.
 *
 * Usage: php tests/multicast-join-closes-at-transfer.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Items\MulticastSession;
use FOG\Service\MulticastTask;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('multicast-join-closes-at-transfer');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A session whose sender was spawned a minute ago, sized for two, waiting
 * up to ten minutes. No task is associated: every machine joined by name
 * without one.
 *
 * @param string $transferstart The recorded transfer start, or ''.
 *
 * @return MulticastSession
 */
function waitingSession($transferstart)
{
    return (new MulticastSession())
        ->set('id', 5)
        ->set('name', 'lab')
        ->set('senderpid', 4242)
        ->set('sessclients', 2)
        ->set('senderstart', date('Y-m-d H:i:s', time() - 60))
        ->set('maxwait', 600)
        ->set('transferstart', $transferstart);
}

// --- isJoinable() ---------------------------------------------------------
$t->check(
    'a session still waiting for receivers can be joined',
    true === waitingSession('')->isJoinable()
);
$t->check(
    'a session whose transfer has started cannot be joined',
    false === waitingSession(date('Y-m-d H:i:s'))->isJoinable()
);

// --- recordTransferStart() ------------------------------------------------
// udp-sender.c: "Udp-sender %s\n"; udps-negotiate.c: "%sUDP sender for %s
// at ", "Starting transfer: %08x\n"; participants.c: "New connection from
// %s  (#%d) %08x\n".

/**
 * A sender whose udpcast log is a file the test writes.
 */
class LogTask extends MulticastTask
{
    public $logPath;

    public function getUDPCastLogFile()
    {
        return $this->logPath;
    }
}

$waiting = "Udp-sender 20200328\n"
    . "UDP sender for img at 127.0.0.1 on eth0\n"
    . "New connection from 10.20.0.57  (#0) 00000009\n";
$log = tempnam(sys_get_temp_dir(), 'mcxfer');
file_put_contents($log, $waiting);
$task = new LogTask(5, 'lab', 9000, 'img', 'lo', 2, 1, 50, false, []);
$task->logPath = $log;

/**
 * The UPDATEs that wrote msTransferStart since the log was cleared.
 *
 * @return int
 */
function transferWrites()
{
    global $db;
    return count(
        array_filter(
            $db->log,
            function ($sql) {
                return 0 === stripos(ltrim($sql), 'UPDATE')
                    && false !== strpos($sql, 'msTransferStart');
            }
        )
    );
}

$db->log = [];
$t->check(
    'a sender still waiting records nothing',
    false === $task->recordTransferStart() && 0 === transferWrites()
);
file_put_contents(
    $log,
    $waiting
    . "New connection from 10.20.0.65  (#1) 00000009\n"
    . "Starting transfer: 00000009\n"
);
$t->check(
    'a sender that started transmitting records the transfer start',
    true === $task->recordTransferStart() && 1 === transferWrites()
);
$t->check(
    'the transfer start is recorded once per sender',
    false === $task->recordTransferStart() && 1 === transferWrites()
);
unlink($log);

$t->finish();
