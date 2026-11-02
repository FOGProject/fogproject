<?php
/**
 * A real InnoDB deadlock hits the write that stores the outcome of a sent
 * Move. The write answers 409 rejoin_deadlock_retry and rolls back, the row
 * stays MAY_HAVE_RUN so the object stays protected, and storing the same
 * outcome again succeeds. Nothing sends the Move again.
 *
 * The deadlock needs a writer that takes the intent row before the host
 * rows, the opposite of this code's order. The parent is that writer. It
 * also holds many other host rows, so InnoDB picks the lighter worker as the
 * victim.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-intent-deadlock-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory intent deadlock DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-intent-deadlock-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

use FOG\Agent\DirectoryPlacementIntent as Intent;

$fixture = intentFixtureCreate($pdo);
$ticket = Intent::prepare($fixture['payload']);
Intent::dispatch($ticket);
$before = intentFixtureRow($pdo, $fixture['target']['guid']);

$heavy = [];
for ($i = 0; $i < 50; $i++) {
    $heavy[] = 9000 + $i;
    $pdo->prepare('INSERT INTO hosts (hostID,hostName) VALUES (?,?)')->execute([9000 + $i, 'HEAVY' . $i]);
}

$pdo->beginTransaction();
try {
    // One equality write per row. InnoDB weighs a transaction by its locks
    // and its undo records, so these writes make the parent the heavier one.
    // A range or IN list on hosts would scan, and lock, every host row,
    // including the one the worker needs first.
    foreach ($heavy as $id) {
        $pdo->exec('UPDATE hosts SET hostDesc = \'deadlock weight\' WHERE hostID = ' . (int)$id);
    }
    intentFixtureAll($pdo, 'SELECT dpiGUID FROM directoryPlacementIntent WHERE dpiGUID = ? FOR UPDATE', [intentFixtureBytes($fixture['target']['guid'])]);

    $workerTicket = $ticket;
    $workerTicket['guid_hex'] = bin2hex($workerTicket['guid']);
    unset($workerTicket['guid']);
    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED', __DIR__ . '/fixtures/agent-directory-intent-finish-worker.php',
            json_encode(['ticket' => $workerTicket], JSON_UNESCAPED_SLASHES)],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start the finish worker');
    }

    // Wait until the worker is blocked on the intent row this parent holds.
    $me = (int)intentFixtureAll($pdo, 'SELECT CONNECTION_ID() AS id')[0]['id'];
    $blocked = false;
    $deadline = microtime(true) + 20.0;
    while (microtime(true) < $deadline && !$blocked) {
        $waiting = intentFixtureAll(
            $pdo,
            'SELECT ID FROM information_schema.PROCESSLIST WHERE ID <> ? AND COMMAND = \'Query\' AND INFO LIKE \'SELECT * FROM directoryPlacementIntent % FOR UPDATE\'',
            [$me]
        );
        $blocked = (bool)$waiting;
        if (!$blocked) {
            usleep(20000);
        }
    }
    $t->check('the worker is waiting on the intent row the parent holds', $blocked);

    // Close the cycle: the worker holds this host's directory row.
    $parentOutcome = 'completed';
    try {
        $pdo->prepare('UPDATE hostDirectory SET hdPlacementError = hdPlacementError WHERE hdHostID = ?')->execute([$fixture['host_id']]);
    } catch (\PDOException $e) {
        $parentOutcome = (string)($e->errorInfo[1] ?? $e->getMessage());
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
$worker = json_decode((string)$out, true);
$t->check('InnoDB chose the lighter worker as the victim and the parent went on', 'completed' === $parentOutcome);
$t->check(
    'the worker answered 409 rejoin_deadlock_retry and left no open transaction',
    is_array($worker) && 'rejoin_deadlock_retry' === $worker['reason'] && 409 === $worker['code'] && false === $worker['in_transaction']
);
// The boot notice about the session directory depends on the machine, not on the code under test.
$err = preg_replace('/^FOG: session directory [^\n]*\n?/m', '', (string)$err);
$t->check('the worker printed nothing to stderr', '' === trim((string)$err));
$t->check('the row is unchanged and still MAY_HAVE_RUN', $before === intentFixtureRow($pdo, $fixture['target']['guid']) && 'MAY_HAVE_RUN' === $before['dpiStatus']);

// The same outcome can be stored once the other writer is gone.
Intent::finish($ticket, true, 'ldap_success');
$t->check('storing the outcome again succeeds', 'SUCCEEDED' === intentFixtureStatus($pdo, $fixture['target']['guid']));

$t->finish();
