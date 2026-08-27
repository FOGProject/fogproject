<?php
/**
 * killTasking() must answer "is it gone", and be able to say no.
 *
 * The multicast manager has three arms that log _('could not be killed')
 * (multicastmanager.class.php around the startTask/complete/cancel loops).
 * None of them could ever run: MulticastTask::killTask() called
 * killTasking(), threw the answer away and returned a hardcoded true. So a
 * udp-sender that survived proc_terminate() was reported as killed, and
 * clearSenderRef() then zeroed senderpid -- which is the exact column
 * _reconcileOrphanedSenders() reads to find orphans. The survivor kept its
 * portbase and became invisible to the only thing that would have cleaned
 * it up.
 *
 * The contract underneath was worse than missing, it was inverted:
 * killTasking() returned FALSE after a successful kill, !$isRunning ("was
 * it already dead") from the itemType branch, and fell off the end
 * returning null when the process had already exited. Simply forwarding it
 * would have printed "could not be killed" on every successful kill.
 *
 * These checks run REAL child processes -- the point is to exercise the
 * shipped method, not a copy of its shape. No root, no database and no
 * daemon: FOGService is loaded against a stub parent and driven directly.
 *
 * Usage: php tests/multicast-kill-contract.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG;

if (!function_exists('proc_open') || !function_exists('posix_kill')) {
    fwrite(STDERR, "SKIP: proc_open/posix_kill unavailable\n");
    exit(0);
}
if (!is_dir('/proc/self')) {
    fwrite(STDERR, "SKIP: no /proc on this platform\n");
    exit(0);
}
// SIGTERM and SIGKILL come from ext-pcntl, not ext-posix. The daemons need
// pcntl anyway -- service_lib.php forks -- but a bare CLI image can have
// posix_kill() and still not have the constants, and without them
// proc_terminate() is handed the string 'SIGTERM' and quietly does nothing.
if (!defined('SIGTERM') || !defined('SIGKILL')) {
    fwrite(STDERR, "SKIP: ext-pcntl signal constants unavailable\n");
    exit(0);
}
// killAll() shells out to ps to walk the process tree, and returns without
// signalling anything if it is missing.
exec('command -v ps', $psOut, $psRet);
if (0 !== $psRet) {
    fwrite(STDERR, "SKIP: no ps on PATH\n");
    exit(0);
}

/**
 * Stand-in for the real base class.
 *
 * FOGService's constructor and logging reach the database and the settings
 * table; none of the process-handling methods under test touch either, so
 * the parent is stubbed and instances are built without a constructor.
 */
abstract class FOGBase
{
}

require_once dirname(__DIR__)
    . '/packages/web/src/Service/FOGService.php';

/**
 * Concrete handle on the abstract service.
 */
class KillProbe extends FOGService
{
    public static function outall($string)
    {
    }
}

$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $label What is being asserted.
 * @param bool   $cond  Whether it held.
 *
 * @return void
 */
function check($label, $cond)
{
    global $failures, $checks;
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/**
 * Starts a shell command and returns [service, pid].
 *
 * @param string $cmd The command to run.
 *
 * @return array
 */
function spawn($cmd)
{
    $svc = (new \ReflectionClass('FOG\KillProbe'))
        ->newInstanceWithoutConstructor();
    $descriptor = [
        0 => ['pipe', 'r'],
        2 => ['file', '/dev/null', 'a']
    ];
    $svc->procRef = [0 => proc_open($cmd, $descriptor, $pipes)];
    $svc->procPipes = [0 => $pipes];
    $status = proc_get_status($svc->procRef[0]);
    $pid = (int)$status['pid'];
    settle($pid);
    return [$svc, $pid];
}

/**
 * Waits for a freshly forked child to actually become the thing we asked for.
 *
 * execve() does not make a process visible all at once, and what you see in
 * the window before it lands depends on the kernel. Between fork() and exec()
 * the child still shares the parent's memory map, so /proc/<pid>/cmdline
 * reads back THIS INTERPRETER'S argv -- and where the mm has been swapped but
 * argv is not yet recorded, it reads empty. Both were observed: empty on the
 * dev box for roughly four spawns in five, and the parent's own argv on the
 * PHP 7.4 CI image, where a first attempt at this that merely waited for
 * "non-empty" sailed straight through and failed the match.
 *
 * So the wait is for a cmdline that is non-empty AND is not ours. That is
 * independent of what the checks below then assert about its contents, which
 * is the point -- settling on the assertion would prove nothing.
 *
 * Nothing in the product looks at a pid this young: the reconciler reads one
 * persisted by a previous daemon run, and clearSenderRef() one that has been
 * sending an image for minutes.
 *
 * @param int $pid The pid to wait for.
 *
 * @return void
 */
function settle($pid)
{
    $mine = (string)@file_get_contents('/proc/self/cmdline');
    for ($i = 0; $i < 250; $i++) {
        $theirs = (string)@file_get_contents(
            sprintf('/proc/%d/cmdline', $pid)
        );
        if ('' !== $theirs && $theirs !== $mine) {
            return;
        }
        usleep(20000);
    }
}

/**
 * Is a pid present at all, whatever it is running?
 *
 * Deliberately NOT the method under test -- an independent read, so a
 * mutation to isPidAlive() cannot make its own verification agree with it.
 *
 * @param int $pid The pid.
 *
 * @return bool
 */
function pidPresent($pid)
{
    return $pid > 0 && file_exists(sprintf('/proc/%d', $pid));
}

// --- isPidAlive() ----------------------------------------------------------
//
// The primitive both the kill path and _reconcileOrphanedSenders() rest on.

// The unraced anchor: this process has been running long enough that no
// execve window applies to it.
$svc = (new \ReflectionClass('FOG\KillProbe'))->newInstanceWithoutConstructor();
check('a long-running pid reads as alive', $svc->isPidAlive(getmypid()));

list($svc, $pid) = spawn('sleep 30');
check('a settled child reads as alive', $svc->isPidAlive($pid));
check('a live pid matches its own cmdline', $svc->isPidAlive($pid, 'sleep'));
check(
    'a live pid does NOT match somebody else\'s cmdline',
    !$svc->isPidAlive($pid, 'udp-sender')
);
check('pid 0 is never alive', !$svc->isPidAlive(0));
check('a negative pid is never alive', !$svc->isPidAlive(-1));
check('a non-numeric pid is never alive', !$svc->isPidAlive('nonsense'));

// The cmdline match is what makes a RECYCLED pid answer "gone" rather than
// killing a stranger's process. Without it _reconcileOrphanedSenders() would
// SIGKILL whatever inherited the number.
check(
    'this very process is alive but is not a udp-sender',
    $svc->isPidAlive(getmypid()) && !$svc->isPidAlive(getmypid(), 'udp-sender')
);

// A pid whose cmdline is unreadable answers "gone", and that is the answer
// we want. It covers a zombie (reaped, holds no port) and a child caught
// mid-exec. Both are unidentifiable as a udp-sender, and the reconciler
// SIGKILLs whatever this says is alive -- so "cannot prove it is ours"
// must mean "leave it alone".
check(
    'pid 1 exists but is not matched as a udp-sender',
    !$svc->isPidAlive(1, 'udp-sender')
);

check('killTasking() reports a well-behaved child gone', $svc->killTasking());
check('and it really is gone', !pidPresent($pid));

// --- the case the whole fix exists for -------------------------------------
//
// A child that ignores SIGTERM. The old code called proc_close() straight
// after proc_terminate(), and proc_close() BLOCKS until the child is reaped
// -- so this shape did not merely misreport, it WEDGED the daemon inside the
// kill, forever, with the service unit still showing active. The loop keeps
// the shell alive after each sleep is signalled.
//
// It runs in a child interpreter on a wall clock. That is not ceremony: a
// regression here does not fail, it hangs, and a hang in tests/run-all.sh
// stops the suite rather than reporting anything. Bounding it turns the
// worst outcome back into a legible failure.

if (in_array('--kill-sigterm-proof', (array)$argv, true)) {
    list($svc, $pid) = spawn('trap "" TERM; while :; do sleep 1; done');
    // Announced before the kill so the parent can clean up after a timeout;
    // under a regression this process never gets to say anything again.
    fwrite(STDOUT, 'pid=' . $pid . "\n");
    $gone = $svc->killTasking();
    fwrite(
        STDOUT,
        ($gone ? 'gone' : 'alive')
        . ' ' . (pidPresent($pid) ? 'present' : 'absent') . "\n"
    );
    exit(0);
}

$descriptor = [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'a']];
$child = proc_open(
    [PHP_BINARY, __FILE__, '--kill-sigterm-proof'],
    $descriptor,
    $childPipes
);
// Non-blocking, or the wall clock below is decorative: stream_get_contents()
// on a blocking pipe waits for EOF, which under a regression means waiting
// for the very process that is never going to finish.
if (isset($childPipes[1]) && is_resource($childPipes[1])) {
    stream_set_blocking($childPipes[1], false);
}
$out = '';
$timedout = false;
$began = microtime(true);
while (is_resource($child)) {
    $out .= (string)stream_get_contents($childPipes[1]);
    $status = proc_get_status($child);
    if (!$status['running']) {
        $out .= (string)stream_get_contents($childPipes[1]);
        break;
    }
    if (microtime(true) - $began > 30) {
        $timedout = true;
        proc_terminate($child, SIGKILL);
        break;
    }
    usleep(50000);
}
$took = microtime(true) - $began;
// Whatever happened, do not leave a SIGTERM-proof loop behind.
if (preg_match('/pid=(\d+)/', $out, $m)) {
    @posix_kill((int)$m[1], SIGKILL);
}
foreach ((array)$childPipes as $pipe) {
    if (is_resource($pipe)) {
        fclose($pipe);
    }
}
if (is_resource($child)) {
    proc_close($child);
}

check('the kill of a SIGTERM-proof child returned at all', !$timedout);
check(
    'a SIGTERM-proof child is still reported gone (escalated to SIGKILL)',
    false !== strpos($out, 'gone ')
);
check(
    'and it really is gone',
    false !== strpos($out, ' absent')
);
check('the kill did not block indefinitely', $took < 30);

// --- the start-failed path -------------------------------------------------
//
// MulticastManager calls killTask() when startTask() FAILED, so there are no
// pipes and no handle. Unguarded, killTasking() read procPipes[$index] and
// warned "Undefined array key" into the daemon log on every failed start.

$warnings = [];
set_error_handler(
    function ($errno, $errstr) use (&$warnings) {
        $warnings[] = $errstr;
        return true;
    }
);
$svc = (new \ReflectionClass('FOG\KillProbe'))->newInstanceWithoutConstructor();
$svc->procRef = [];
$svc->procPipes = [];
$never = $svc->killTasking();
restore_error_handler();

check('a task that never started reports gone', $never);
check(
    'and warns about nothing',
    0 === count($warnings),
    $warnings
);

// --- an already-exited child ----------------------------------------------
//
// The branch that used to fall off the end of the function returning null.
// null is falsey, so the caller read it as "could not be killed" -- had the
// caller ever looked.

list($svc, $pid) = spawn('true');
usleep(300000);
check('an already-exited child reports gone', $svc->killTasking());

// --- the itemType branch ---------------------------------------------------
//
// Used by the image and snapin replicators. It used to bail out returning
// true whenever its pipe bookkeeping was missing -- leaving a live lftp
// running and telling the caller it was gone.

$svc = (new \ReflectionClass('FOG\KillProbe'))->newInstanceWithoutConstructor();
$descriptor = [0 => ['pipe', 'r'], 2 => ['file', '/dev/null', 'a']];
$ref = proc_open('sleep 30', $descriptor, $pipes);
$status = proc_get_status($ref);
$pid = (int)$status['pid'];
$svc->procRef = ['image' => ['disk.img' => [0 => $ref]]];
$svc->procPipes = [];

check('the itemType branch kills even with no pipes recorded', $svc->killTasking(0, 'image', 'disk.img'));
check('and that child really is gone', !pidPresent($pid));
check(
    'the handle is dropped from procRef, not left for cleanupProcList()',
    !isset($svc->procRef['image']['disk.img'][0])
);

check(
    'an unknown itemType key is a no-op that reports gone',
    $svc->killTasking(0, 'image', 'nosuchfile')
);

// The replicators call killTasking() on the itemType path and then keep
// running; cleanupProcList() walks procRef expecting a live resource with
// matching pipes beside it. Dropping one half and not the other left it
// fclose()ing pipes that were gone and proc_close()ing a closed handle, so
// the two structures are pinned as staying in step.
$warnings = [];
set_error_handler(
    function ($errno, $errstr) use (&$warnings) {
        $warnings[] = $errstr;
        return true;
    }
);
$svc->cleanupProcList();
restore_error_handler();
check(
    'cleanupProcList() is clean after an itemType kill',
    0 === count($warnings)
);

// --- and the callers -------------------------------------------------------
//
// The product wiring, so this file fails if the answer stops being used.

$taskFile = dirname(__DIR__)
    . '/packages/web/src/Service/MulticastTask.php';
$mgrFile = dirname(__DIR__)
    . '/packages/web/src/Service/MulticastManager.php';
$task = file_get_contents($taskFile);
$mgr = file_get_contents($mgrFile);

$killTask = '';
if (preg_match('/public function killTask\(\).*?\n    \}/s', $task, $m)) {
    $killTask = $m[0];
}
check(
    'killTask() has a body to read',
    '' !== $killTask
);
check(
    'killTask() returns killTasking()\'s answer, not a hardcoded true',
    false !== strpos($killTask, '$this->killTasking()')
    && false === strpos($killTask, 'return true;')
);

$clear = '';
if (preg_match('/public function clearSenderRef\(\).*?\n    \}/s', $task, $m)) {
    $clear = $m[0];
}
check(
    'clearSenderRef() refuses to zero the reference of a live sender',
    false !== strpos($clear, 'isPidAlive(')
);
check(
    'and the liveness test is pinned to udp-sender, not any pid',
    false !== strpos($clear, 'UDPSENDERPATH')
);

check(
    'the manager still acts on the kill result',
    substr_count($mgr, '->killTask()') >= 3
    && false !== strpos($mgr, 'could not be killed')
);

// --- report ----------------------------------------------------------------

if (count($failures)) {
    fwrite(STDERR, sprintf("FAIL (%d of %d)\n", count($failures), $checks));
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    exit(1);
}

printf("ok  %d checks passed\n", $checks);
exit(0);
