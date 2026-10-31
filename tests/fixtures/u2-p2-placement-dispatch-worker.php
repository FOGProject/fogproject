<?php
/** Process barrier for one-shot placement dispatch CAS; never sends LDAP. */

$mode = $argv[1] ?? '';
$barrierDir = $argv[2] ?? '';
$workerKey = $argv[3] ?? '';
$input = isset($argv[4]) ? json_decode($argv[4], true) : null;
$tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
$validEnvelope = is_array($input) && ['database', 'ticket'] === array_keys($input)
    && is_array($input['ticket'] ?? null)
    && ['guid', 'sid', 'generation', 'hash', 'payload'] === array_keys($input['ticket'])
    && is_string($input['database'] ?? null)
    && is_string($input['ticket']['guid'] ?? null)
    && preg_match('/^[a-f0-9]{32}$/D', $input['ticket']['guid'])
    && is_string($input['ticket']['sid'] ?? null)
    && is_int($input['ticket']['generation'] ?? null) && $input['ticket']['generation'] > 0
    && is_string($input['ticket']['hash'] ?? null)
    && preg_match('/^[a-f0-9]{64}$/D', $input['ticket']['hash'])
    && is_array($input['ticket']['payload'] ?? null)
    && is_array($input['ticket']['payload']['target'] ?? null)
    && is_string($input['ticket']['payload']['target']['guid'] ?? null)
    && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $input['ticket']['payload']['target']['guid'])
    && is_string($input['ticket']['payload']['target']['sid'] ?? null)
    && $input['ticket']['payload']['target']['sid'] === ($input['ticket']['sid'] ?? null)
    && strtolower(str_replace('-', '', $input['ticket']['payload']['target']['guid']))
        === ($input['ticket']['guid'] ?? null);
if (!in_array($mode, ['race', 'stale'], true) || '' === $workerKey
    || false === $resolvedDir || 0 !== strpos($resolvedDir, $tempRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-placement-dispatch-')
    || !$validEnvelope) {
    fwrite(STDERR, "invalid placement dispatch barrier arguments\n");
    exit(2);
}
$ticketGuid = hex2bin($input['ticket']['guid']);
if (false === $ticketGuid || 16 !== strlen($ticketGuid)) {
    fwrite(STDERR, "invalid binary ticket GUID encoding\n");
    exit(2);
}
// Decode only the DB ticket key. The nested payload target GUID remains its
// canonical textual UUID and is passed through unchanged.
$input['ticket']['guid'] = $ticketGuid;

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-placement-dispatch-worker');
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || '' === $dsn || 0 !== strpos($dsn, 'mysql:')) {
        throw new RuntimeException('placement dispatch worker requires explicit MySQL scratch opt-in');
    }
    $pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== $input['database']
        || ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_'))) {
        throw new RuntimeException('worker database differs from validated scratch database');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new RuntimeException('DATABASE_NAME differs from validated scratch database');
    }
    $link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $readyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'ready-' . $workerKey . '.json';
    $readyTemp = $readyPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $published = file_put_contents($readyTemp, json_encode([
        'pid' => getmypid(), 'connection_id' => $connectionID,
        'worker' => $workerKey, 'mode' => $mode,
        'target_guid' => $input['ticket']['payload']['target']['guid'] ?? null,
        'target_sid' => $input['ticket']['payload']['target']['sid'] ?? null,
        'ticket_generation' => (int)$input['ticket']['generation'],
        'ticket_hash' => $input['ticket']['hash'],
        'ready_before_dispatch' => true
    ], JSON_THROW_ON_ERROR), LOCK_EX);
    if (false === $published || !rename($readyTemp, $readyPath)) {
        @unlink($readyTemp);
        throw new RuntimeException('could not publish complete dispatch readiness record');
    }

    $go = $resolvedDir . DIRECTORY_SEPARATOR . ('race' === $mode ? 'go-race' : 'go-stale');
    $deadline = microtime(true) + 25.0;
    while (!is_file($go)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('parent did not release dispatch barrier');
        }
        usleep(10000);
    }

    $ldapTokenObject = new stdClass();
    $dispatchToken = null;
    $dispatchReason = '';
    try {
        $dispatchToken = FOG\Agent\DirectoryPlacementIntent::dispatch($input['ticket'], $ldapTokenObject);
    } catch (Throwable $e) {
        $dispatchReason = $e->getMessage();
    }
    $tokenIssued = is_object($dispatchToken);
    $moveDeniedReason = '';
    if (!$tokenIssued) {
        try {
            // This is intentionally a denied token-consumption probe. No LDAP
            // method is invoked and no real directory connection exists.
            FOG\Agent\DirectoryPlacementIntent::consumePermit(null, $ldapTokenObject, '', '');
        } catch (Throwable $e) {
            $moveDeniedReason = $e->getMessage();
        }
    }
    $q = $pdo->prepare('SELECT dpiStatus FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=?');
    $q->execute([$input['ticket']['guid'], $input['ticket']['sid'], $input['ticket']['generation']]);
    $durableStatus = $q->fetchColumn();
    echo json_encode([
        'pid' => getmypid(), 'connection_id' => $connectionID,
        'token_issued' => $tokenIssued,
        'ldap_token_object_created' => true,
        'dispatch_reason' => $dispatchReason,
        'move_attempted' => false,
        'move_denied_reason' => $moveDeniedReason,
        'durable_status' => $durableStatus ?: null
    ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
