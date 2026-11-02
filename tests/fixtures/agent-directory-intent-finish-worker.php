<?php
/**
 * Calls DirectoryPlacementIntent::finish() once for a ticket the parent test
 * prepared and dispatched, and prints what it threw as JSON. Scratch DB only.
 *
 * Usage: php agent-directory-intent-finish-worker.php '<ticket json>'
 */
$input = isset($argv[1]) ? json_decode($argv[1], true) : null;
if (!is_array($input) || !isset($input['ticket']['guid_hex'])) {
    fwrite(STDERR, "invalid intent finish worker arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/directory-db-connect.php';
    FogTestHarness::boot('agent-directory-intent-finish-worker');
    $pdo = directoryTestPdo();
    directoryTestBindPdodb($pdo);
    $ticket = $input['ticket'];
    $ticket['guid'] = hex2bin($ticket['guid_hex']);
    unset($ticket['guid_hex']);

    $reason = '';
    $code = 0;
    try {
        FOG\Agent\DirectoryPlacementIntent::finish($ticket, true, 'ldap_success');
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
        $code = $e->getCode();
    }
    echo json_encode(['reason' => $reason, 'code' => $code, 'in_transaction' => $pdo->inTransaction()]), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
