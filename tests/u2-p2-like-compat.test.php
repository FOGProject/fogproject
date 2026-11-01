<?php
/** Compare one real HostManager LIKE update against fixed P1 and current P2. */
require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-like-compat-parent');
$t = new FogChecks();

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn && '1' !== getenv('MJ1_DB_ALLOW_RESET')) {
    echo "SKIP: LIKE compatibility comparison requires explicit scratch MySQL opt-in\n";
    exit(0);
}
if ('' === $dsn || '1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('LIKE compatibility comparison requires explicit scratch MySQL opt-in');
}
$p1Root = (string)(getenv('U2_P1_SOURCE_ROOT') ?: '/p1-source');
$p1Root = realpath($p1Root);
$currentRoot = realpath(dirname(__DIR__));
if (false === $p1Root || false === $currentRoot || $p1Root === $currentRoot
    || !is_file($p1Root . '/tests/lib/fog-test-harness.php')) {
    throw new \RuntimeException('fixed P1 source root is unavailable; baseline comparison is required');
}
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('LIKE compatibility comparison requires a fog_mj1-prefixed scratch DB');
}

do {
    $targetID = random_int(1500000000, 1800000000);
    $controlID = random_int(1500000000, 1800000000);
    $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID IN (?,?)');
    $q->execute([$targetID, $controlID]);
} while ($targetID === $controlID || (int)$q->fetchColumn() !== 0);
$suffix = strtoupper(bin2hex(random_bytes(5)));
$targetName = 'U2LT' . $suffix;
$controlName = 'U2LC' . $suffix;
$pattern = 'U2LIKE' . $suffix . '%';
$initialDescription = 'U2LIKE' . $suffix . '-MATCH';
$controlDescription = 'U2LIKE' . $suffix . '-OTHER';
$updatedDescription = 'U2LIKE' . $suffix . '-UPDATED';
$worker = __DIR__ . '/fixtures/u2-p2-like-baseline-worker.php';
$fixtures = [$targetID, $controlID];

$run = static function ($root) use (
    $worker,
    $targetID,
    $controlID,
    $targetName,
    $controlName,
    $initialDescription,
    $pattern,
    $updatedDescription,
    $controlDescription
) {
    $pipes = [];
    $process = proc_open(
        [
            PHP_BINARY,
            $worker,
            $root,
            (string)$targetID,
            (string)$controlID,
            $targetName,
            $controlName,
            $initialDescription,
            $pattern,
            $updatedDescription,
            $controlDescription
        ],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname($root)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('could not start LIKE worker for ' . $root);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $report = json_decode(trim((string)$stdout), true);
    return [
        'exit' => $exit,
        'report' => is_array($report) ? $report : null,
        'stdout' => trim((string)$stdout),
        'stderr' => trim((string)$stderr)
    ];
};

$p1 = null;
$p2 = null;
try {
    $p1 = $run($p1Root);
    $p2 = $run($currentRoot);
    $p1Report = $p1['report'];
    $p2Report = $p2['report'];
    if ('' !== $p1['stderr']) {
        fwrite(STDERR, "P1 child stderr:\n" . $p1['stderr'] . "\n");
    }
    if ('' !== $p2['stderr']) {
        fwrite(STDERR, "P2 child stderr:\n" . $p2['stderr'] . "\n");
    }
    $validWorkers = 0 === $p1['exit'] && 0 === $p2['exit']
        && is_array($p1Report) && is_array($p2Report)
        && !empty($p1Report['ok']) && !empty($p2Report['ok']);
    if (!$validWorkers) {
        fwrite(STDERR, "P1 child stderr: " . $p1['stderr'] . "\nP1 child stdout: " . $p1['stdout']
            . "\nP2 child stderr: " . $p2['stderr'] . "\nP2 child stdout: " . $p2['stdout'] . "\n");
    }
    $t->check('both roots completed the same scratch-DB LIKE fixture and emitted valid JSON', $validWorkers);
    $sameSQL = $validWorkers && $p1Report['actual_sql'] === $p2Report['actual_sql'];
    $sameParams = $validWorkers && $p1Report['actual_params'] === $p2Report['actual_params'];
    $sameError = $validWorkers
        && $p1Report['error'] === $p2Report['error']
        && $p1Report['error_code'] === $p2Report['error_code']
        && $p1Report['error_info'] === $p2Report['error_info']
        && $p1Report['exception'] === $p2Report['exception'];
    $sameRows = $validWorkers
        && $p1Report['target_before'] === $p2Report['target_before']
        && $p1Report['target_after'] === $p2Report['target_after']
        && $p1Report['control_before'] === $p2Report['control_before']
        && $p1Report['control_after'] === $p2Report['control_after']
        && $p1Report['target_unchanged'] === $p2Report['target_unchanged']
        && $p1Report['control_unchanged'] === $p2Report['control_unchanged']
        && $p1Report['target_updated'] === $p2Report['target_updated'];
    echo "LIKE parity diagnostic JSON (P1 and P2 worker reports):\n"
        . json_encode(
            [
                'p1' => $p1,
                'p2' => $p2,
                'comparison' => [
                    'same_sql' => $sameSQL,
                    'same_params' => $sameParams,
                    'same_error' => $sameError,
                    'same_rows' => $sameRows
                ]
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";
    $t->check('P1 and P2 generated identical actual SQL', $sameSQL);
    $t->check('P1 and P2 bound the same actual parameters', $sameParams);
    $t->check('P1 and P2 returned identical DB error and PDO error info', $sameError);
    $t->check('P1 and P2 left the same target/control row state', $sameRows);

    $sameFailureNoChange = $sameError && $sameRows
        && false === $p1Report['result'] && false === $p2Report['result']
        && is_string($p1Report['error']) && '' !== $p1Report['error']
        && !empty($p1Report['target_unchanged']) && !empty($p1Report['control_unchanged'])
        && empty($p1Report['target_updated']);
    $sameIntendedSuccess = $sameError && $sameRows
        && true === $p1Report['result'] && true === $p2Report['result']
        && false === $p1Report['error'] && false === $p2Report['error']
        && !empty($p1Report['target_updated']) && !empty($p1Report['control_unchanged']);
    $parityClassified = $sameFailureNoChange || $sameIntendedSuccess;
    if (!$sameSQL || !$sameParams || !$sameError || !$sameRows || !$parityClassified) {
        fwrite(
            STDERR,
            "LIKE parity full worker reports:\n"
            . json_encode(
                [
                    'p1' => $p1,
                    'p2' => $p2,
                    'comparison' => [
                        'same_sql' => $sameSQL,
                        'same_params' => $sameParams,
                        'same_error' => $sameError,
                        'same_rows' => $sameRows,
                        'same_failure_no_change' => $sameFailureNoChange,
                        'same_intended_success' => $sameIntendedSuccess
                    ]
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );
    }
    if ($sameFailureNoChange) {
        echo "BASELINE COMPATIBILITY LIMITATION CONFIRMED: fixed P1 and P2 both return the same LIKE failure with unchanged rows; this is historical behavior, not a P2 LIKE acceptance PASS.\n";
    } elseif ($sameIntendedSuccess) {
        echo "BASELINE COMPARISON: fixed P1 and P2 both perform the intended LIKE update; the focused08 HY093 observation was not reproduced. This does not certify other LIKE query shapes.\n";
    } else {
        echo "BASELINE COMPARISON DIVERGENCE: P1/P2 did not match either the same-failure or same-success shape.\n";
    }
    $t->check(
        'P1/P2 parity is classified without treating LIKE feature behavior itself as acceptance',
        $parityClassified
    );
} finally {
    $delete = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID IN (?,?)');
    $delete->execute($fixtures);
    $delete = $pdo->prepare('DELETE FROM hosts WHERE hostID IN (?,?)');
    $delete->execute($fixtures);
}

$t->finish();
