<?php
/**
 * Isolated real-PDO session/fault checks for DirectoryNameFence.
 * No LDAP/native work is performed. Do not run against a non-scratch DB.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 fence session: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('fence session checks require explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('fence session checks require a fog_mj1-prefixed scratch DB');
}

$t = new FogChecks();
$fixtures = [];
$newName = static function () {
    return 'P2FS' . strtoupper(bin2hex(random_bytes(6)));
};
$createHost = static function () use ($pdo, $newName, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    } while ((int)$q->fetchColumn() !== 0);
    $name = $newName();
    $insert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?, ?, \'0\', \'u2p2.invalid\', \'\', ?)'
    );
    $insert->execute([$hostID, $name, hash('sha256', random_bytes(32))]);
    $fixtures[$hostID] = $name;
    return ['id' => $hostID, 'name' => $name];
};
$readName = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
    return $q->fetchColumn();
};
$authoritySnapshot = static function () use ($pdo) {
    $rows = [];
    foreach (['hostDirectory' => 'hdID', 'tasks' => 'taskID', 'directoryPlacementIntent' => 'dpiGUID'] as $table => $pk) {
        $rows[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY `' . $pk . '`')->fetchAll(\PDO::FETCH_ASSOC);
    }
    return hash('sha256', serialize($rows));
};
$runWorker = static function ($mode, array $args = []) {
    $worker = __DIR__ . '/fixtures/u2-p2-fence-session-worker.php';
    $command = array_merge([PHP_BINARY, '-d', 'display_errors=stderr', $worker, $mode], array_map('strval', $args));
    $pipes = [];
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        return ['exit' => -1, 'result' => null, 'stdout' => '', 'stderr' => 'proc_open failed'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $result = json_decode(trim((string)$stdout), true);
    return ['exit' => $exit, 'result' => is_array($result) ? $result : null,
        'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
};
$workerData = static function (array $run) {
    return 0 === $run['exit'] && is_array($run['result']) ? $run['result'] : [];
};
$checkWorker = static function ($label, $condition, array $run, array $context = []) use ($t) {
    if (!$condition) {
        $diagnostic = [
            'kind' => 'u2-p2-fence-session-case-failure',
            'case' => $label,
            'exit' => $run['exit'] ?? null,
            'result' => $run['result'] ?? null,
            'stdout' => $run['stdout'] ?? '',
            'stderr' => $run['stderr'] ?? ''
        ];
        fwrite(STDERR, json_encode(
            array_merge($diagnostic, $context),
            JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        ) . "\n");
    }
    return $t->check($label, $condition);
};
$workerProbe = static function () use ($runWorker, $workerData) {
    $run = $runWorker('probe');
    $data = $workerData($run);
    return [
        'ok' => 0 === $run['exit'] && true === ($data['callback_entered'] ?? false),
        'run' => $run,
        'data' => $data
    ];
};

try {
    // B17 connection identity: during a valid A-owned name fence, change only
    // PDODB's static link to real PDO B. The wrapper's attempted protected
    // UPDATE must be refused before PDOStatement::execute on either link.
    $swapHost = $createHost();
    $swapReplacement = $newName();
    $swap = $runWorker('pdo-swap', [$swapHost['id'], $swapReplacement]);
    $swapData = $workerData($swap);
    $checkWorker(
        'PDO link swap during A-owned fence refuses protected UPDATE before execute and poisons process',
        0 === $swap['exit']
            && (int)($swapData['connection_a'] ?? 0) > 0
            && (int)($swapData['connection_b'] ?? 0) > 0
            && (int)$swapData['connection_a'] !== (int)$swapData['connection_b']
            && true === ($swapData['callback_entered'] ?? false)
            && 'directory_fence_connection_changed' === ($swapData['reason'] ?? null)
            && 'directory_fence_poisoned' === ($swapData['second_reason'] ?? null)
            && 0 === (int)($swapData['update_executes'] ?? -1)
            && $swapHost['name'] === ($swapData['old_name'] ?? null)
            && $swapHost['name'] === ($swapData['new_name'] ?? null)
            && is_string($swapData['before_row_hash'] ?? null)
            && hash_equals($swapData['before_row_hash'], $swapData['after_row_hash'] ?? '')
            && $swapHost['name'] === $readName($swapHost['id']),
        $swap,
        ['host_id' => $swapHost['id'], 'expected_raw_name' => $swapHost['name']]
    );

    // A physical lock without the logical owner must not be mistaken for a
    // fresh scope and must remain owned until the worker process exits.
    $stale = $runWorker('stale-lock');
    $staleData = $workerData($stale);
    $checkWorker(
        'raw self-owned GET_LOCK without scope is stale, skips callback and is not auto-released',
        0 === $stale['exit']
            && true === ($staleData['acquired_present'] ?? false)
            && array_key_exists('acquired', $staleData)
            && 1 == $staleData['acquired']
            && 'directory_fence_stale_ownership' === ($staleData['reason'] ?? null)
            && false === ($staleData['callback_entered'] ?? true)
            && array_key_exists('connection_id', $staleData)
            && array_key_exists('lock_owner_after_refusal', $staleData)
            && (int)$staleData['connection_id'] > 0
            && (int)$staleData['connection_id'] === (int)$staleData['lock_owner_after_refusal']
            && 1 === (int)($staleData['get_lock_executes'] ?? 0),
        $stale
    );
    $staleProbe = $workerProbe();
    $checkWorker('process exit releases orphaned raw lock for a new process', $staleProbe['ok'], $staleProbe['run']);

    $autocommit = $runWorker('autocommit-off');
    $autocommitData = $workerData($autocommit);
    $checkWorker(
        'autocommit off refuses before callback, GET_LOCK, or protected SQL',
        0 === $autocommit['exit']
            && 0 === (int)($autocommitData['autocommit'] ?? 1)
            && 'directory_fence_lock_order' === ($autocommitData['reason'] ?? null)
            && false === ($autocommitData['callback_entered'] ?? true)
            && 0 === (int)($autocommitData['get_lock_executes'] ?? -1)
            && 0 === (int)($autocommitData['update_executes'] ?? -1),
        $autocommit
    );

    // The statement fault wrapper changes only the fetched GET_LOCK result;
    // the real prepared statement is executed first and acquires the lock.
    foreach (['get-lock-null', 'get-lock-exception'] as $acquireMode) {
    $acquireBefore = $authoritySnapshot();
    $getNull = $runWorker($acquireMode);
    $getNullData = $workerData($getNull);
    $checkWorker(
        $acquireMode . ' after real execute fails closed and next attempt reports stale ownership without retry',
        0 === $getNull['exit']
            && array_key_exists('actual_get_lock', $getNullData)
            && 1 == $getNullData['actual_get_lock']
            && true === ($getNullData['fault_injected'] ?? false)
            && ('get-lock-null' === $acquireMode ? 'directory_fence_unavailable' : 'fixture_get_lock_reply_exception') === ($getNullData['first_reason'] ?? null)
            && 'directory_fence_stale_ownership' === ($getNullData['second_reason'] ?? null)
            && false === ($getNullData['callback_entered'] ?? true)
            && false === ($getNullData['second_callback_entered'] ?? true)
            && array_key_exists('connection_id', $getNullData)
            && array_key_exists('lock_owner_after_refusal', $getNullData)
            && (int)$getNullData['connection_id'] > 0
            && (int)$getNullData['connection_id'] === (int)$getNullData['lock_owner_after_refusal']
            && 1 === (int)($getNullData['get_lock_executes'] ?? 0)
            && 0 === (int)($getNullData['update_executes'] ?? -1)
            && $acquireBefore === $authoritySnapshot(),
        $getNull
    );
    $nullProbe = $workerProbe();
    $checkWorker('GET_LOCK NULL worker exit releases its physical lock for a new process', $nullProbe['ok'], $nullProbe['run']);
    }

    foreach (['release-zero', 'release-null', 'release-exception'] as $releaseMode) {
        $host = $createHost();
        $replacement = $newName();
        $releaseBefore = $authoritySnapshot();
        $run = $runWorker($releaseMode, [$host['id'], $replacement]);
        $data = $workerData($run);
        $checkWorker(
            $releaseMode . ' after actual release poisons same process but preserves committed callback row',
            0 === $run['exit']
                && true === ($data['callback_entered'] ?? false)
                && true === ($data['callback_query_succeeded'] ?? false)
                && ('release-exception' === $releaseMode ? 'fixture_release_reply_exception' : 'directory_fence_release_unknown') === ($data['first_reason'] ?? null)
                && $replacement === ($data['stored_name'] ?? null)
                && 1 == ($data['actual_release'] ?? null)
                && true === ($data['fault_injected'] ?? false)
                && array_key_exists('lock_owner_after_release', $data)
                && null === $data['lock_owner_after_release']
                && false === ($data['second_callback_entered'] ?? true)
                && 'directory_fence_poisoned' === ($data['second_reason'] ?? null)
                && 1 === (int)($data['get_lock_executes'] ?? 0)
                && 1 === (int)($data['release_lock_executes'] ?? 0)
                && 1 === (int)($data['update_executes'] ?? 0)
                && $replacement === $readName($host['id'])
                && $releaseBefore === $authoritySnapshot(),
            $run,
            ['host_id' => $host['id'], 'expected_committed_name' => $replacement,
                'observed_name' => $readName($host['id'])]
        );
        $probe = $workerProbe();
        $checkWorker($releaseMode . ' released lock is available to a fresh worker', $probe['ok'], $probe['run']);
    }
} finally {
    foreach ($fixtures as $hostID => $name) {
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    }
}

if ($t->failures) {
    $t->finish();
}
echo "PASS U2-P2 fence session cases\n";
$t->finish();
