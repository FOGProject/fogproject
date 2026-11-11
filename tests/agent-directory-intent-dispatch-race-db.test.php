<?php
/**
 * Several processes dispatch the same ticket at the same moment, against a
 * real server. Exactly one gets the right to send the Move. The others are
 * refused with directory_placement_dispatch_changed, and none of them is left
 * holding a transaction open.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-intent-dispatch-race-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory intent dispatch race DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-intent-dispatch-race-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

use FOG\Agent\DirectoryPlacementIntent as Intent;

$workers = 4;
$fixture = intentFixtureCreate($pdo);
$ticket = Intent::prepare($fixture['payload']);
$workerTicket = $ticket;
$workerTicket['guid_hex'] = bin2hex($workerTicket['guid']);
unset($workerTicket['guid']);

$startAt = microtime(true) + 1.5;
$running = [];
for ($i = 0; $i < $workers; $i++) {
    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED', __DIR__ . '/fixtures/agent-directory-intent-dispatch-worker.php',
            json_encode(['ticket' => $workerTicket], JSON_UNESCAPED_SLASHES), sprintf('%.6F', $startAt)],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start a dispatch worker');
    }
    $running[] = [$process, $pipes];
}

$results = [];
$stderr = '';
foreach ($running as list($process, $pipes)) {
    $results[] = json_decode((string)stream_get_contents($pipes[1]), true);
    // The boot notice about the session directory depends on the machine, not on the code under test.
    $stderr .= preg_replace('/^FOG: session directory [^\n]*\n?/m', '', (string)stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}

$won = 0;
$refused = 0;
$open = false;
foreach ($results as $result) {
    if (!is_array($result)) {
        continue;
    }
    $won += '' === $result['reason'] ? 1 : 0;
    $refused += 'directory_placement_dispatch_changed' === $result['reason'] ? 1 : 0;
    $open = $open || true === $result['in_transaction'];
}
$t->check('every worker answered', $workers === count(array_filter($results, 'is_array')) && '' === trim($stderr));
$t->check(
    'exactly one worker got the right to send and the others were refused',
    1 === $won && $workers - 1 === $refused
);
$t->check('no worker left a transaction open', !$open);
$t->check('the row is MAY_HAVE_RUN at the same generation', 'MAY_HAVE_RUN' === intentFixtureStatus($pdo, $fixture['target']['guid'])
    && 1 === (int)intentFixtureRow($pdo, $fixture['target']['guid'])['dpiGeneration']);

$t->finish();
