<?php
/** Concurrent DB-only DirectoryOperation::claim barrier worker; scratch DB only. */

$barrierDir = $argv[1] ?? '';
$workerKey = $argv[2] ?? '';
$input = isset($argv[3]) ? json_decode($argv[3], true) : null;
$expectedRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (false === $resolvedDir || 0 !== strpos($resolvedDir, $expectedRoot)
    || 0 !== strpos(basename($resolvedDir), 'agent-directory-claim-race-')
    || !preg_match('/^[a-z0-9_-]{1,32}$/D', $workerKey)
    || !is_array($input)) {
    fwrite(STDERR, "invalid scratch claim race arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/directory-db-connect.php';
    FogTestHarness::boot('agent-directory-claim-race-worker');
    foreach (['database','host_id','name','domain','fingerprint','body'] as $key) {
        if (!array_key_exists($key, $input)) {
            throw new \RuntimeException('claim race worker arguments are incomplete');
        }
    }
    $pdo = directoryTestPdo();
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== $input['database']) {
        throw new \RuntimeException('claim race worker database differs from the parent\'s');
    }
    directoryTestBindPdodb($pdo);

    $host = (new FOG\Items\Host())->set('id', (int)$input['host_id'])
        ->set('name', (string)$input['name'])->set('ADDomain', (string)$input['domain'])
        ->set('useAD', 1)->set('agentFingerprint', (string)$input['fingerprint']);
    $saved = FOG\Agent\DirectoryRejoin::settings($host);
    $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $readyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'ready-' . $workerKey . '.json';
    $readyTemp = $readyPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (false === file_put_contents($readyTemp, json_encode([
        'pid' => getmypid(),
        'connection_id' => $connectionID,
        'host_id' => (int)$input['host_id'],
        'attempt_id' => $saved['state']['attempt_id'] ?? null,
        'phase_before_go' => $saved['state']['phase'] ?? null
    ], JSON_THROW_ON_ERROR), LOCK_EX) || !rename($readyTemp, $readyPath)) {
        @unlink($readyTemp);
        throw new \RuntimeException('could not publish complete claim race readiness record');
    }

    $go = $resolvedDir . DIRECTORY_SEPARATOR . 'go';
    $deadline = microtime(true) + 20.0;
    while (!is_file($go)) {
        if (microtime(true) >= $deadline) {
            throw new \RuntimeException('parent did not release claim race barrier');
        }
        usleep(10000);
    }

    $claimed = false;
    $reason = '';
    try {
        FOG\Agent\DirectoryOperation::claim($host, $saved, $input['body']);
        $claimed = true;
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
    }
    $after = FOG\Agent\DirectoryRejoin::settings($host);
    echo json_encode([
        'claimed' => $claimed,
        'reason' => $reason,
        'phase' => $after['state']['phase'] ?? null,
        'last_deploy_task_id' => $after['state']['last_deploy_task_id'] ?? null,
        'trigger_task_id' => $after['state']['trigger_task_id'] ?? null,
        'attempt_id' => $after['state']['attempt_id'] ?? null,
        'host_id' => (int)$input['host_id'],
        'connection_id' => $connectionID
    ]), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
