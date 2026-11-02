<?php
/**
 * Calls DirectoryPlacementIntent::dispatch() once for a ticket the parent test
 * prepared, and prints what it threw as JSON. Scratch DB only.
 *
 * Usage: php agent-directory-intent-dispatch-worker.php '<ticket json>' <start-at-microtime>
 *
 * The worker waits for the start time, so that several workers call dispatch()
 * at the same moment.
 */
$input = isset($argv[1]) ? json_decode($argv[1], true) : null;
if (!is_array($input) || !isset($input['ticket']['guid_hex'])) {
    fwrite(STDERR, "invalid intent dispatch worker arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/directory-db-connect.php';
    FogTestHarness::boot('agent-directory-intent-dispatch-worker');
    $pdo = directoryTestPdo();
    directoryTestBindPdodb($pdo);
    $ticket = $input['ticket'];
    $ticket['guid'] = hex2bin($ticket['guid_hex']);
    unset($ticket['guid_hex']);

    $startAt = isset($argv[2]) ? (float)$argv[2] : 0.0;
    while (microtime(true) < $startAt) {
        usleep(200);
    }

    $reason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::dispatch($ticket);
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
    }
    echo json_encode(['reason' => $reason, 'in_transaction' => $pdo->inTransaction()]), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
