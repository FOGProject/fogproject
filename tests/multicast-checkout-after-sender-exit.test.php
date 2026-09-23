<?php
/**
 * A multicast client must still be able to check out after udp-sender exits.
 *
 * udp-sender exits when the last partition is on the wire. The clients then
 * still run their post-download steps (UUID reset, NTFS flag, hostname) and
 * only after that post to Post_Stage3, which is what closes the task and its
 * imaging log. The manager's "is no longer running" arm used to call
 * MulticastSession::complete(), which marked every checked-in task Complete
 * and deleted the association rows. Every client then got "No Active Task
 * found for Host" and the imaging log was never closed (forum topic 18254).
 *
 * The contract pinned here:
 *   - that arm asks complete() to leave the received tasks for their client;
 *   - complete() in that mode does not touch those tasks or their rows;
 *   - checkout() on a session closed that way releases its row, and never
 *     saves a session that does not exist (save() would insert one).
 *
 * DB-free: this reads the source. The behavior is proved against a lab
 * database by prove_multicast_checkout_after_sender_exit_15.php, which fails
 * on an unpatched tree.
 *
 * Usage: php tests/multicast-checkout-after-sender-exit.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = array();
$checks = 0;

/**
 * Source with comments removed and whitespace collapsed.
 *
 * @param string $file the file to read
 *
 * @return string
 */
function mcStrip($file)
{
    $clean = '';
    foreach (token_get_all((string)file_get_contents($file)) as $token) {
        if (is_array($token)
            && (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0])
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return preg_replace('/\s+/', ' ', $clean);
}

/**
 * Records a check.
 *
 * @param string $what     the defect, stated as what would be wrong
 * @param bool   $ok       whether it passed
 * @param array  $failures the failures so far
 * @param int    $checks   the checks so far
 *
 * @return void
 */
function mcCheck($what, $ok, array &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$mgr = mcStrip("$root/packages/web/lib/service/multicastmanager.class.php");
$sess = mcStrip("$root/packages/web/lib/fog/multicastsession.class.php");
$queue = mcStrip("$root/packages/web/lib/reg-task/taskqueue.class.php");

/* ------------------------------------------------ 1. the sender-exit arm */

$exitPos = strpos($mgr, "_('is no longer running')");
$flagPos = strpos($mgr, '$awaitCheckout[$runningTask->getID()] = true;');
$donePos = strpos($mgr, "_('has been completed')");
mcCheck(
    'the "is no longer running" arm does not mark its session as awaiting '
    . 'checkout, so complete() closes the tasks before the clients report',
    false !== $exitPos && false !== $flagPos && false !== $donePos
    && $exitPos < $flagPos && $flagPos < $donePos,
    $failures,
    $checks
);
mcCheck(
    'the completion loop does not pass the awaiting flag to complete()',
    false !== strpos(
        $mgr,
        '$Session->complete( isset($awaitCheckout[$Task->getID()]) )'
    ),
    $failures,
    $checks
);

/* ------------------------------------------------------- 2. complete() */

$complete = '';
if (preg_match(
    '/public function complete\(\$awaitCheckout = false\) \{.*?->save\(\); \}/',
    $sess,
    $m
)) {
    $complete = $m[0];
}
mcCheck(
    'MulticastSession::complete() takes no $awaitCheckout argument',
    '' !== $complete,
    $failures,
    $checks
);
$awaitPos = strpos($complete, 'if ($awaitCheckout && count($received))');
$elsePos = strpos($complete, '} elseif (count($received))');
$closePos = strpos($complete, "'stateID' => self::getCompleteState()");
mcCheck(
    'complete() marks received tasks Complete even when asked to leave '
    . 'them for their client',
    false !== $awaitPos && false !== $elsePos && false !== $closePos
    && $awaitPos < $elsePos && $elsePos < $closePos,
    $failures,
    $checks
);
mcCheck(
    'complete() in awaiting mode does not look for the tasks still checked '
    . 'in or in progress',
    false !== strpos($complete, 'self::getCheckedInState()')
    && false !== strpos($complete, 'self::getProgressState()'),
    $failures,
    $checks
);
mcCheck(
    'complete() deletes every association row of the session, so checkout '
    . 'cannot find the session of a task it left open',
    false !== strpos($complete, "\$find['taskID'] = array_values(array_diff(\$taskIDs, \$awaiting));")
    && false === strpos($complete, "destroy(['msID' => \$this->get('id')])"),
    $failures,
    $checks
);

/* -------------------------------------------------------- 3. checkout() */

$checkout = '';
if (preg_match('/public function checkout\(\) \{.*?if \(\$this->Task->isMulticast\(\)\) \{.*?\} \}/', $queue, $m)) {
    $checkout = $m[0];
}
mcCheck(
    'checkout() does not release the association row of a session the '
    . 'manager already completed',
    false !== strpos(
        $checkout,
        "\$MulticastSession->get('stateID') == self::getCompleteState() ) { \$MCTask->destroy();"
    ),
    $failures,
    $checks
);
mcCheck(
    'checkout() can save() a session that does not exist, which inserts an '
    . 'empty multicastSessions row',
    false !== strpos($checkout, '} elseif ($MulticastSession->isValid()) {'),
    $failures,
    $checks
);

mcCheck(
    'the scan did not reach the sources and would pass vacuously',
    strlen($mgr) > 5000 && strlen($sess) > 3000 && strlen($queue) > 5000,
    $failures,
    $checks
);

if (count($failures) > 0) {
    echo 'FAIL multicast-checkout-after-sender-exit ('
        . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS multicast-checkout-after-sender-exit ($checks checks)\n";
exit(0);
