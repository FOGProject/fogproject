<?php
/**
 * First executable slice of U2-04 B-barriers: the real DB name fence only.
 * No schema reset, DDL, product database, AD, or native API is used here.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 barriers: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-P2 barrier test requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-fence-main');

$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD'),
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('database name must use the fog_mj1 scratch prefix');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Inject the already guarded PDO into the real wrapper. The normal PDODB
// query path (including DirectoryNameFence::beforeSQL) remains in use.
$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$t = new FogChecks();
function u2p2ReadJson($path)
{
    $raw = @file_get_contents($path);
    $decoded = false === $raw ? null : json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

$key = 'fog.mj1.name.v1:' . substr(hash('sha256', $database), 0, 40);
$lockOwner = static function () use ($pdo, $key) {
    $q = $pdo->prepare('SELECT IS_USED_LOCK(?)');
    $q->execute([$key]);
    $value = $q->fetchColumn();
    return false === $value || null === $value ? null : (int)$value;
};

// Nested scopes in one process share the same captured PDO and lock owner.
$nested = FOG\Agent\DirectoryNameFence::run(function (PDO $outer) {
    $outerID = (int)$outer->query('SELECT CONNECTION_ID()')->fetchColumn();
    $innerID = FOG\Agent\DirectoryNameFence::run(function (PDO $inner) {
        return (int)$inner->query('SELECT CONNECTION_ID()')->fetchColumn();
    });
    return [$outerID, $innerID];
});
$t->check(
    'same-process reentry reuses one PDO session',
    is_array($nested) && count($nested) === 2 && $nested[0] === $nested[1]
);
$t->check('same-process scope releases lock on outer close', null === $lockOwner());

// A caller-owned transaction must be refused before the lock or callback.
$pdo->beginTransaction();
$outerCallbackRan = false;
$outerReason = '';
try {
    FOG\Agent\DirectoryNameFence::run(function () use (&$outerCallbackRan) {
        $outerCallbackRan = true;
    });
} catch (FOG\Agent\DirectoryFenceRefusal $e) {
    $outerReason = $e->getMessage();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
$t->check(
    'outer transaction is rejected before callback and remains caller-owned',
    !$outerCallbackRan && 'directory_fence_lock_order' === $outerReason
);

// Non-Host SQL is exercised through PDODB while a real fence is held. A
// same-value update against an impossible scratch PK is intentionally inert.
$table = $pdo->query("SHOW TABLES LIKE 'multicastSessions'")->fetchColumn();
if (false === $table) {
    throw new \RuntimeException('scratch schema is missing multicastSessions');
}
$nonHostHash = static function () use ($pdo) {
    $rows = $pdo->query(
        'SELECT msID,msMaxwait FROM multicastSessions ORDER BY msID'
    )->fetchAll(\PDO::FETCH_ASSOC);
    return hash('sha256', serialize($rows));
};
$beforeNonHost = $nonHostHash();
$nonHostResult = FOG\Agent\DirectoryNameFence::run(function () use ($db) {
    $result = $db->query(
        'UPDATE `multicastSessions` SET `msMaxwait`=`msMaxwait` WHERE `msID`=-2147483648'
    );
    return [false === $result->error, (int)$result->affectedRows()];
});
$t->check(
    'non-Host SQL remains callable inside name fence without changing rows',
    is_array($nonHostResult) && true === $nonHostResult[0]
        && 0 === $nonHostResult[1] && $beforeNonHost === $nonHostHash()
);

$worker = __DIR__ . '/fixtures/u2-p2-barrier-worker.php';
$barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'u2-p2-barrier-' . bin2hex(random_bytes(12));
if (!mkdir($barrierDir, 0700)) {
    throw new \RuntimeException('cannot create private barrier directory');
}
$resolvedBarrierDir = realpath($barrierDir);
$tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR;
if (false === $resolvedBarrierDir || 0 !== strpos($resolvedBarrierDir, $tempRoot)
    || 0 !== strpos(basename($resolvedBarrierDir), 'u2-p2-barrier-')
) {
    throw new \RuntimeException('barrier directory escaped the private temp root');
}

$startWorker = static function ($mode) use ($worker, $resolvedBarrierDir) {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $worker, $mode, $resolvedBarrierDir],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start barrier worker process');
    }
    fclose($pipes[0]);
    return [$process, $pipes];
};
$waitWorker = static function ($process, array $pipes, $seconds) {
    $deadline = microtime(true) + $seconds;
    $last = null;
    do {
        $last = proc_get_status($process);
        if (!$last['running']) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if (is_array($last) && $last['running']) {
        proc_terminate($process);
        foreach ([1, 2] as $index) {
            fclose($pipes[$index]);
        }
        proc_close($process);
        return [false, '', 'worker timed out', -1];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);
    $exitCode = is_array($last) && $last['exitcode'] >= 0
        ? $last['exitcode'] : $closed;
    return [true, trim((string)$stdout), trim((string)$stderr), $exitCode];
};
$waitForSignal = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            return true;
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            return false;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return false;
};
$readWorkerResult = static function ($output) {
    $rows = preg_split('/\r?\n/', trim((string)$output));
    $value = json_decode(end($rows), true);
    return is_array($value) ? $value : null;
};

$holder = null;
$contender = null;
try {
    // Worker A acquires the production key and signals only after entering
    // the callback. Worker B then waits on the same key in a second process.
    list($holder, $holderPipes) = $startWorker('hold');
    $holderReady = $resolvedBarrierDir . '/ready-hold.json';
    $ready = $waitForSignal($holderReady, $holder, 10.0);
    $holderInfo = $ready ? u2p2ReadJson($holderReady) : null;
    $lockSeen = $ready && is_array($holderInfo)
        && (int)$holderInfo['pid'] !== getmypid()
        && $lockOwner() === (int)$holderInfo['connection_id'];
    $t->check('independent process owns the same database-scoped key', $lockSeen);

    $started = hrtime(true);
    list($contender, $contenderPipes) = $startWorker('acquire');
    list($completed, $contenderOut, $contenderErr, $contenderExit) =
        $waitWorker($contender, $contenderPipes, 11.0);
    $contender = null;
    $elapsed = (hrtime(true) - $started) / 1000000000;
    $contenderResult = $readWorkerResult($contenderOut);
    $timeout = $completed && is_array($contenderResult)
        && false === ($contenderResult['ok'] ?? null)
        && 'directory_fence_timeout' === ($contenderResult['reason'] ?? '')
        && empty($contenderResult['callback_entered']);
    $configuredWait = (new \ReflectionClass(FOG\Agent\DirectoryNameFence::class))
        ->getConstant('WAIT_SECONDS');
    $t->check(
        'contending process gets the configured three-second lock timeout',
        3 === $configuredWait && $timeout && 2.7 <= $elapsed
            && 5.5 >= $elapsed && 1 === $contenderExit
    );

    file_put_contents($resolvedBarrierDir . '/release-hold', 'release', LOCK_EX);
    list($holderDone, $holderOut, $holderErr, $holderExit) =
        $waitWorker($holder, $holderPipes, 10.0);
    $holder = null;
    $holderResult = $readWorkerResult($holderOut);
    $t->check(
        'normal scope close releases lock and waiter can acquire afterward',
        $holderDone && 0 === $holderExit && is_array($holderResult)
            && true === ($holderResult['ok'] ?? false) && null === $lockOwner()
    );

    list($afterRelease, $afterReleasePipes) = $startWorker('acquire');
    list($acquired, $acquiredOut, $acquiredErr, $acquiredExit) =
        $waitWorker($afterRelease, $afterReleasePipes, 10.0);
    $acquiredResult = $readWorkerResult($acquiredOut);
    $t->check(
        'new independent process acquires after prior owner exits',
        $acquired && 0 === $acquiredExit && is_array($acquiredResult)
            && true === ($acquiredResult['ok'] ?? false)
    );

    // exit-held intentionally skips PHP scope cleanup; server ownership must
    // disappear only after the owning process/connection has actually ended.
    list($crashedOwner, $crashedPipes) = $startWorker('exit-held');
    $exitReady = $resolvedBarrierDir . '/ready-exit.json';
    $exitSignalled = $waitForSignal($exitReady, $crashedOwner, 10.0);
    list($exitDone, $exitOut, $exitErr, $exitCode) =
        $waitWorker($crashedOwner, $crashedPipes, 10.0);
    $crashedOwner = null;
    $exitInfo = $exitSignalled ? u2p2ReadJson($exitReady) : null;
    $exitReleased = $exitDone && 0 === $exitCode && is_array($exitInfo)
        && null === $lockOwner();
    $t->check('process exit closes its lock-owning DB session', $exitReleased);
    if ($exitReleased) {
        list($afterExit, $afterExitPipes) = $startWorker('acquire');
        list($reacquired, $reacquiredOut, $reacquiredErr, $reacquiredExit) =
            $waitWorker($afterExit, $afterExitPipes, 10.0);
        $reacquiredResult = $readWorkerResult($reacquiredOut);
        $t->check(
            'separate process acquires after prior owner process exit',
            $reacquired && 0 === $reacquiredExit && is_array($reacquiredResult)
                && true === ($reacquiredResult['ok'] ?? false)
        );
    } else {
        $t->check('separate process acquires after prior owner process exit', false);
    }
} finally {
    if (is_resource($holder)) {
        @file_put_contents($resolvedBarrierDir . '/release-hold', 'cleanup', LOCK_EX);
        @proc_terminate($holder);
    }
    if (is_resource($contender)) {
        @proc_terminate($contender);
    }
    foreach (glob($resolvedBarrierDir . '/*') ?: [] as $path) {
        if (is_file($path) && 0 === strpos(realpath($path), $resolvedBarrierDir . DIRECTORY_SEPARATOR)) {
            @unlink($path);
        }
    }
    if (is_dir($resolvedBarrierDir)
        && 0 === strpos($resolvedBarrierDir, $tempRoot)
        && 0 === strpos(basename($resolvedBarrierDir), 'u2-p2-barrier-')
    ) {
        @rmdir($resolvedBarrierDir);
    }
}

echo "SCOPE: short-lock boundaries only; commit/release faults, transaction crash, "
    . "and placement arbitration run in their separate u2-p2 test programs.\n";

$t->finish();
