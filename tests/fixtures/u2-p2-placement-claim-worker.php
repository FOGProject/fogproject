<?php
/** Paused real placement-intent prepare worker. No LDAP/native operation. */

$barrierDir = $argv[1] ?? '';
$input = isset($argv[2]) ? json_decode($argv[2], true) : null;
$tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (false === $resolvedDir || 0 !== strpos($resolvedDir, $tempRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-placement-claim-') || !is_array($input)
    || !isset($input['database'], $input['payload']['host_id'], $input['payload']['target']['guid'])) {
    fwrite(STDERR, "invalid placement claim barrier arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-placement-claim-worker');
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || '' === $dsn || 0 !== strpos($dsn, 'mysql:')) {
        throw new \RuntimeException('placement claim worker requires explicit MySQL scratch opt-in');
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
        throw new \RuntimeException('DATABASE_NAME differs from validated scratch database');
    }
    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    $readyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'lookup-ready.json';
    $readyTemp = $readyPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $published = file_put_contents($readyTemp, json_encode([
        'pid' => getmypid(),
        'connection_id' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn(),
        'host_id' => (int)$input['payload']['host_id'],
        'target_guid' => $input['payload']['target']['guid'],
        'old_lookup_payload' => true,
        'paused_before_prepare' => true
    ], JSON_THROW_ON_ERROR), LOCK_EX);
    if (false === $published || !rename($readyTemp, $readyPath)) {
        @unlink($readyTemp);
        throw new \RuntimeException('could not publish complete placement lookup readiness');
    }

    $go = $resolvedDir . DIRECTORY_SEPARATOR . 'go-prepare';
    $deadline = microtime(true) + 20.0;
    while (!is_file($go)) {
        if (microtime(true) >= $deadline) {
            throw new \RuntimeException('parent did not release placement prepare barrier');
        }
        usleep(10000);
    }

    $prepared = false;
    $reason = '';
    $ticket = null;
    try {
        $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($input['payload']);
        $prepared = true;
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
    }
    echo json_encode([
        'prepared' => $prepared,
        'reason' => $reason,
        'generation' => $ticket['generation'] ?? null,
        'target_guid' => $ticket['payload']['target']['guid'] ?? null,
        'target_sid' => $ticket['payload']['target']['sid'] ?? null
    ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
