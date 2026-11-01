<?php
/** Paused PREPARED-ticket worker used by the U2-06 parent-delete barrier. */

$barrierDir = $argv[1] ?? '';
$workerKey = $argv[2] ?? '';
$input = isset($argv[3]) ? json_decode($argv[3], true) : null;
$tempRoot = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
$ticketKeys = ['guid', 'sid', 'generation', 'hash', 'payload'];
$valid = is_array($input)
    && is_string($input['database'] ?? null)
    && is_array($input['ticket'] ?? null)
    && $ticketKeys === array_keys($input['ticket'])
    && is_string($input['ticket']['guid'] ?? null)
    && preg_match('/^[a-f0-9]{32}$/D', $input['ticket']['guid'])
    && is_string($input['ticket']['sid'] ?? null)
    && is_int($input['ticket']['generation'] ?? null)
    && $input['ticket']['generation'] > 0
    && is_string($input['ticket']['hash'] ?? null)
    && preg_match('/^[a-f0-9]{64}$/D', $input['ticket']['hash'])
    && is_array($input['ticket']['payload'] ?? null)
    && is_array($input['ticket']['payload']['target'] ?? null)
    && ($input['ticket']['payload']['target']['sid'] ?? null) === $input['ticket']['sid']
    && strtolower(str_replace('-', '', (string)($input['ticket']['payload']['target']['guid'] ?? '')))
        === $input['ticket']['guid'];
if (!$valid || '' === $workerKey || false === $resolvedDir
    || 0 !== strpos($resolvedDir, $tempRoot)
    || 0 !== strpos(basename($resolvedDir), 'u206-writer-intent-')) {
    fwrite(STDERR, "invalid U2-06 writer barrier arguments\n");
    exit(2);
}
$input['ticket']['guid'] = hex2bin($input['ticket']['guid']);
if (false === $input['ticket']['guid'] || 16 !== strlen($input['ticket']['guid'])) {
    fwrite(STDERR, "invalid binary placement GUID\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u206-writer-intent-worker');
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || '' === $dsn || 0 !== strpos($dsn, 'mysql:')) {
        throw new \RuntimeException('worker requires explicit MySQL scratch opt-in');
    }
    $pdo = new \PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== $input['database']
        || ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_'))) {
        throw new \RuntimeException('worker database differs from validated scratch database');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new \RuntimeException('worker DATABASE_NAME mismatch');
    }
    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $ready = $resolvedDir . DIRECTORY_SEPARATOR . 'ready.json';
    $readyTmp = $ready . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $bytes = file_put_contents($readyTmp, json_encode([
        'pid' => getmypid(),
        'connection_id' => $connectionID,
        'host_id' => (int)$input['ticket']['payload']['host_id'],
        'target_guid' => $input['ticket']['payload']['target']['guid'],
        'generation' => $input['ticket']['generation'],
        'ticket_hash' => $input['ticket']['hash'],
        'paused_before_dispatch' => true
    ], JSON_THROW_ON_ERROR), LOCK_EX);
    if (false === $bytes || !rename($readyTmp, $ready)) {
        @unlink($readyTmp);
        throw new \RuntimeException('failed to publish worker readiness');
    }

    $deadline = microtime(true) + 30.0;
    $go = $resolvedDir . DIRECTORY_SEPARATOR . 'resume';
    while (!is_file($go)) {
        if (microtime(true) >= $deadline) {
            throw new \RuntimeException('parent did not resume paused worker');
        }
        usleep(10000);
    }

    $token = null;
    $dispatchReason = '';
    try {
        // Opaque identity only. This process does not construct or connect LDAP.
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($input['ticket'], new \stdClass());
    } catch (\Throwable $e) {
        $dispatchReason = $e->getMessage();
    }
    $intent = $pdo->prepare(
        'SELECT dpiGeneration,dpiStatus,dpiHash FROM directoryPlacementIntent '
        . 'WHERE dpiGUID=? AND dpiSID=? AND dpiHostID=?'
    );
    $intent->execute([
        $input['ticket']['guid'],
        $input['ticket']['sid'],
        (int)$input['ticket']['payload']['host_id']
    ]);
    $row = $intent->fetch(\PDO::FETCH_ASSOC) ?: null;
    echo json_encode([
        'dispatch_token_issued' => is_object($token),
        'dispatch_reason' => $dispatchReason,
        'ldap_transport' => 'not_connected',
        'intent' => $row
    ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
