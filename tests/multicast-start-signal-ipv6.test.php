<?php
/**
 * The start message reaches an IPv6 udp-sender, and an IPv4 one still works.
 *
 * startIfAllConnected() sends CMD_GO to the sender's port + 1, from the
 * session's own portbase. The URL was 'udp://<ip>:<port>' and the source was
 * bound to 0.0.0.0, so an IPv6 sender address made a malformed URL and an
 * IPv4 source could not reach an IPv6 listener anyway. Now the address is
 * bracketed (urlHost) and an IPv6 sender gets an IPv6 wildcard source.
 *
 * Drives the real method against a real UDP socket on the loopback of each
 * family, the way multicast-cancel-one-host.test.php does. Skips the IPv6
 * half when the box has no IPv6 loopback.
 *
 * Usage: php tests/multicast-start-signal-ipv6.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Service\MulticastTask;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('multicast-start-signal-ipv6');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * A sender whose udpcast log is a file the test writes.
 */
class SignalTask extends MulticastTask
{
    public $logPath;

    /** @var string The daemon log: a failed signal is logged here. */
    public static $log = '';

    public function getUDPCastLogFile()
    {
        return $this->logPath;
    }
}

/**
 * Runs startIfAllConnected() for a session launched for three hosts, after
 * one was canceled: tasks 7 and 8 remain, both connected and in progress.
 *
 * @param string $ip The sender address.
 *
 * @return array|null [returned, bytes the sender's port received], or null
 *                    when the listener could not be opened
 */
function signalTo($ip)
{
    global $db;
    // Only the arrived-hosts query reaches the database: both tasks are in
    // progress.
    $db->responder = function ($sql, $params) {
        if (!preg_match('/^\s*SELECT\b.*?`tasks`/s', $sql)) {
            return null;
        }
        $rows = [
            ['taskID' => 7, 'taskStateID' => 3],
            ['taskID' => 8, 'taskStateID' => 3],
        ];
        $cols = FogFakePdo::selectedColumns($sql);
        return array_map(function ($r) use ($cols) {
            return array_intersect_key($r, array_flip($cols));
        }, $rows);
    };
    $port = 47000 + 2 * mt_rand(0, 900);
    $host = false !== strpos($ip, ':') ? "[$ip]" : $ip;
    $recv = @stream_socket_server(
        sprintf('udp://%s:%d', $host, $port + 1),
        $errno,
        $errstr,
        STREAM_SERVER_BIND
    );
    if (!$recv) {
        $db->responder = null;
        return null;
    }
    stream_set_blocking($recv, false);
    $log = tempnam(sys_get_temp_dir(), 'mcgo6');
    file_put_contents(
        $log,
        "Udp-sender 20250223\n"
        . "UDP sender for img at $ip on eth0\n"
        . "New connection from 10.20.0.57  (#0) 00000009\n"
        . "New connection from 10.20.0.65  (#1) 00000009\n"
    );
    $task = new SignalTask(99, 'lab', $port, 'img', 'lo', 3, 1, 50, false, [7, 8]);
    $task->logPath = $log;
    ob_start();
    $ret = $task->startIfAllConnected(2, [7, 8], $ip);
    ob_end_clean();
    usleep(100000);
    $got = (string)@fread($recv, 64);
    fclose($recv);
    unlink($log);
    $db->responder = null;
    return [$ret, $got];
}

SignalTask::$log = tempnam(sys_get_temp_dir(), 'mcdlog');
register_shutdown_function(function () {
    @unlink(SignalTask::$log);
});

$v4 = signalTo('127.0.0.1');
$t->check('an IPv4 listener could be opened', null !== $v4);
if (null !== $v4) {
    $t->check(
        'IPv4 sender: the start message is sent',
        true === $v4[0]
    );
    $t->check(
        'IPv4 sender: it is CMD_GO, opcode 2, in network order',
        pack('nn', 2, 0) === $v4[1]
    );
}

$v6 = signalTo('::1');
if (null === $v6) {
    echo "SKIP ipv6: no IPv6 loopback on this box\n";
} else {
    $t->check(
        'IPv6 sender: the start message is sent',
        true === $v6[0]
    );
    $t->check(
        'IPv6 sender: it is CMD_GO, opcode 2, in network order',
        pack('nn', 2, 0) === $v6[1]
    );
}

$t->finish();
