<?php
/** Real scratch-DB batch and multi-payload writer boundary checks. */

class U2P2BatchBarrierStatement extends PDOStatement
{
    public static $workerReport;
    public static $barrierError;
    private $barrierDir;
    private $workerPath;
    private $workerHostID;
    private $predicateTag;
    private $paused = false;

    protected function __construct($barrierDir, $workerPath, $workerHostID, $predicateTag)
    {
        $this->barrierDir = $barrierDir;
        $this->workerPath = $workerPath;
        $this->workerHostID = (int)$workerHostID;
        $this->predicateTag = (string)$predicateTag;
    }

    public function execute($params = null): bool
    {
        $result = parent::execute($params);
        if (!$result || $this->paused
            || !preg_match('/^\s*UPDATE\s+`?hosts`?\s+SET\s+(?:`?hosts`?\.)?`?hostDesc`?\s*=/i', $this->queryString)) {
            return $result;
        }
        $this->paused = true;
        try {
            file_put_contents($this->barrierDir . DIRECTORY_SEPARATOR . 'parent-paused', '1', LOCK_EX);
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $this->workerPath, $this->barrierDir, (string)$this->workerHostID, $this->predicateTag],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__)
            );
            if (!is_resource($process)) {
                throw new RuntimeException('cannot start child description update process');
            }
            fclose($pipes[0]);
            $resultPath = $this->barrierDir . DIRECTORY_SEPARATOR . 'child-result.json';
            $deadline = microtime(true) + 15.0;
            while (microtime(true) < $deadline) {
                if (is_file($resultPath)) {
                    $decoded = json_decode((string)file_get_contents($resultPath), true);
                    if (is_array($decoded)
                        && array_key_exists('ok', $decoded) && is_bool($decoded['ok'])
                        && array_key_exists('fence_held', $decoded) && is_bool($decoded['fence_held'])
                        && array_key_exists('updated', $decoded) && is_bool($decoded['updated'])
                    ) {
                        self::$workerReport = $decoded;
                        break;
                    }
                }
                $status = proc_get_status($process);
                if (empty($status['running'])) {
                    break;
                }
                usleep(10000);
            }
            $exitDeadline = microtime(true) + 2.0;
            do {
                $status = proc_get_status($process);
                if (empty($status['running'])) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $exitDeadline);
            if (!empty($status['running'])) {
                proc_terminate($process);
                self::$barrierError = 'child description update timed out';
            }
            foreach ([1, 2] as $pipeIndex) {
                $output = stream_get_contents($pipes[$pipeIndex]);
                fclose($pipes[$pipeIndex]);
                if ($output && !is_array(self::$workerReport)) {
                    self::$barrierError = trim($output);
                }
            }
            proc_close($process);
            if (!is_array(self::$workerReport) || empty(self::$workerReport['ok'])) {
                self::$barrierError = self::$barrierError ?: 'child did not confirm its real PDODB update';
            }
        } catch (Throwable $e) {
            self::$barrierError = get_class($e) . ': ' . $e->getMessage();
        }
        return $result;
    }
}

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 batch: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('U2-P2 batch requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-batch');
$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('U2-P2 batch requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}
$intentTable = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
);
$intentTable->execute(['directoryPlacementIntent']);
if (1 !== (int)$intentTable->fetchColumn()) {
    throw new RuntimeException('scratch intent schema must be installed before U2-P2 batch tests');
}

$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$t = new FogChecks();
$fixtureHosts = [];
$fixtureDirectories = [];
$cleanupNames = [];
$barrierDirs = [];
$name = static function ($prefix = 'P2B') {
    return $prefix . strtoupper(bin2hex(random_bytes(5)));
};
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$insertHost = static function ($hostName, $description, $ou = 'OU=Before,DC=u2p2,DC=invalid', $forcedID = null) use (
    $pdo,
    &$fixtureHosts
) {
    $fingerprint = hash('sha256', random_bytes(32));
    if (null === $forcedID) {
        $q = $pdo->prepare(
            'INSERT INTO hosts (hostName,hostDesc,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
            . 'VALUES (?, ?, \'1\', \'u2p2.invalid\', ?, ?)'
        );
        $q->execute([$hostName, $description, $ou, $fingerprint]);
        $hostID = (int)$pdo->lastInsertId();
    } else {
        $q = $pdo->prepare(
            'INSERT INTO hosts (hostID,hostName,hostDesc,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
            . 'VALUES (?, ?, ?, \'1\', \'u2p2.invalid\', ?, ?)'
        );
        $q->execute([$forcedID, $hostName, $description, $ou, $fingerprint]);
        $hostID = (int)$forcedID;
    }
    $fixtureHosts[] = $hostID;
    return ['id' => $hostID, 'name' => $hostName, 'fingerprint' => $fingerprint];
};
$rawHost = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT * FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
};
$removeBarrierDirs = static function () use (&$barrierDirs) {
    foreach ($barrierDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
};

try {
    $manager = new FOG\Managers\HostManager();

    // B25: both payloads use the original predicate. The first moves X out
    // of it; the later ADOU payload must not spill onto X or a new match.
    $tag = 'U2B25_' . strtoupper(bin2hex(random_bytes(6)));
    $x = $insertHost($name(), $tag);
    $xBefore = $rawHost($x['id']);
    $xMovedDescription = 'U2B25_MOVED_' . strtoupper(bin2hex(random_bytes(4)));
    $xRequestedOU = 'OU=ShouldNotReachX,DC=u2p2,DC=invalid';
    $multiResult = $manager->update(
        ['description' => $tag],
        'AND',
        [
            ['description' => $xMovedDescription],
            ['ADOU' => $xRequestedOU]
        ]
    );
    $xAfter = $rawHost($x['id']);
    $t->check(
        'B25 original-predicate multipayload update does not apply later ADOU after X leaves predicate',
        true === $multiResult && is_array($xBefore) && is_array($xAfter)
            && $tag === $xBefore['hostDesc']
            && $xMovedDescription === $xAfter['hostDesc']
            && $xBefore['hostADOU'] === $xAfter['hostADOU']
            && $xRequestedOU !== $xAfter['hostADOU']
    );

    // B26: pause after the parent's first real SQL UPDATE, while its sensitive
    // multipayload update still owns the production GET_LOCK. A child process
    // uses its own real PDODB connection for a noncritical description update
    // that brings Y into the original WHERE. The later fixed-ID update must
    // not select Y, and original predicate semantics must still exclude X.
    $raceTag = 'U2B26_' . strtoupper(bin2hex(random_bytes(6)));
    $raceX = $insertHost($name(), $raceTag);
    $raceY = $insertHost($name(), 'U2B26_OTHER_' . strtoupper(bin2hex(random_bytes(4))));
    $raceXBefore = $rawHost($raceX['id']);
    $raceYBefore = $rawHost($raceY['id']);
    $raceMoved = 'U2B26_MOVED_' . strtoupper(bin2hex(random_bytes(4)));
    $raceOU = 'OU=MustNotReach,DC=u2p2,DC=invalid';
    $barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'u2-p2-batch-' . bin2hex(random_bytes(8));
    if (!mkdir($barrierDir, 0700)) {
        throw new RuntimeException('cannot create isolated batch barrier directory');
    }
    $barrierDirs[] = $barrierDir;
    U2P2BatchBarrierStatement::$workerReport = null;
    U2P2BatchBarrierStatement::$barrierError = null;
    // Statement class must be installed before this connection prepares any
    // statements. Keep the single PDODB instance and swap only its shared PDO
    // handle for the parent update window.
    $racePDO = new PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $racePDO->setAttribute(PDO::ATTR_STATEMENT_CLASS, [
        U2P2BatchBarrierStatement::class,
        [$barrierDir, __DIR__ . '/fixtures/u2-p2-batch-worker.php', $raceY['id'], $raceTag]
    ]);
    if ((string)$racePDO->query('SELECT DATABASE()')->fetchColumn() !== $database) {
        throw new RuntimeException('barrier PDO selected a different scratch database');
    }
    $link->setValue(null, $racePDO);
    $raceResult = $manager->update(
        ['description' => $raceTag],
        'AND',
        [
            ['description' => $raceMoved],
            ['ADOU' => $raceOU]
        ]
    );
    $raceXAfter = $rawHost($raceX['id']);
    $raceYAfter = $rawHost($raceY['id']);
    $childReport = U2P2BatchBarrierStatement::$workerReport;
    $t->check(
        'B26 child real PDODB facts update runs while parent owns global name fence',
        null === U2P2BatchBarrierStatement::$barrierError
            && is_array($childReport) && !empty($childReport['ok']) && !empty($childReport['fence_held'])
            && !empty($childReport['updated'])
    );
    $t->check(
        'B26 fixed original host tuple set excludes Y and original predicate excludes moved X',
        true === $raceResult && is_array($raceXBefore) && is_array($raceYBefore)
            && is_array($raceXAfter) && is_array($raceYAfter)
            && $raceMoved === $raceXAfter['hostDesc']
            && $raceXBefore['hostADOU'] === $raceXAfter['hostADOU']
            && $raceTag === $raceYAfter['hostDesc']
            && $raceYBefore['hostADOU'] === $raceYAfter['hostADOU']
            && $raceOU !== $raceYAfter['hostADOU']
    );

    // Ordinary positive batch control: 20 distinct new rows through the real
    // HostManager insertBatch path, with cleanup names registered beforehand.
    $positivePrefix = 'P2B20' . strtoupper(bin2hex(random_bytes(4)));
    $positiveNames = [];
    $positiveValues = [];
    for ($i = 0; $i < 20; $i++) {
        $positiveNames[] = $positivePrefix . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $positiveValues[] = [$positiveNames[$i]];
    }
    $cleanupNames = array_merge($cleanupNames, $positiveNames);
    $positiveResult = $manager->insertBatch(['name'], $positiveValues);
    $positiveQ = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostName=?');
    $positiveRows = 0;
    foreach ($positiveNames as $positiveName) {
        $positiveQ->execute([$positiveName]);
        $positiveRows += (int)$positiveQ->fetchColumn();
    }
    $t->check(
        'ordinary 20-row HostManager insertBatch succeeds and stores every unique row',
        is_array($positiveResult) && 20 === (int)$positiveResult[1] && 20 === $positiveRows
    );

    // B27: 500 new id=0 tuples precede a duplicate hostID whose existing
    // valid STARTED rename would take a new name. Full batch preflight must
    // refuse before the first chunk can insert any of those 500 rows.
    do {
        $activeID = random_int(1500000000, 1800000000);
        $activeIDCheck = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $activeIDCheck->execute([$activeID]);
    } while ((int)$activeIDCheck->fetchColumn() !== 0);
    $activeName = $name('P2BA');
    $active = $insertHost($activeName, 'U2B27_ACTIVE', 'OU=Managed,DC=u2p2,DC=invalid', $activeID);
    $activeState = [
        'version' => 1,
        'protocol' => 2,
        'phase' => 'started',
        'attempt_id' => bin2hex(random_bytes(16)),
        'host_id' => $active['id'],
        'fingerprint' => $active['fingerprint'],
        'operation' => 'rename',
        'mode' => 'existing',
        'name' => $activeName,
        'expected_current' => 'OLD' . strtoupper(bin2hex(random_bytes(4))),
        'expected_pending' => $activeName,
        'target' => [
            'guid' => $guid(),
            'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
            'account' => $activeName . '$'
        ]
    ];
    $insertHD = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    );
    $insertHD->execute([
        $active['id'],
        'u2p2.invalid',
        'CN=' . $activeName . ',OU=Managed,DC=u2p2,DC=invalid',
        $activeName . '$',
        json_encode($activeState)
    ]);
    $fixtureDirectories[] = $active['id'];
    $activeBefore = $rawHost($active['id']);
    $batchPrefix = 'P2B5' . strtoupper(bin2hex(random_bytes(4)));
    $batchValues = [];
    $batchNames = [];
    for ($i = 0; $i < 500; $i++) {
        $batchNames[] = $batchPrefix . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $batchValues[] = [0, $batchNames[$i]];
    }
    $cleanupNames = array_merge($cleanupNames, $batchNames);
    $changedActiveName = $name('P2BC');
    $batchValues[] = [$active['id'], $changedActiveName];
    $batchFailure = null;
    try {
        $manager->insertBatch(['id', 'name'], $batchValues);
    } catch (Throwable $e) {
        $batchFailure = $e;
    }
    $newBatchCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM hosts WHERE hostName LIKE ' . $pdo->quote($batchPrefix . '%')
    )->fetchColumn();
    $t->check(
        'B27 protected 501st tuple rejects the full batch before any first-chunk inserts',
        $batchFailure instanceof FOG\Agent\DirectoryFenceRefusal
            && 'directory_host_operation_protected' === $batchFailure->getMessage()
            && 0 === $newBatchCount
            && $activeBefore === $rawHost($active['id'])
    );

    // B28 is the ordinary-SQL control for B27. The final tuple has no
    // protected identity; its explicit NULL violates hosts.hostName NOT NULL
    // only when the second real SQL chunk executes. The established batch
    // contract allows the first 500-row chunk to remain committed when that
    // later statement fails; this does not ask the guard to validate SQL or
    // make the whole 501-row operation atomic.
    $sqlFailurePrefix = 'P2B28' . strtoupper(bin2hex(random_bytes(4)));
    $sqlFailureNames = [];
    $sqlFailureValues = [];
    for ($i = 0; $i < 500; $i++) {
        $sqlFailureNames[] = $sqlFailurePrefix . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $sqlFailureValues[] = [$sqlFailureNames[$i]];
    }
    $cleanupNames = array_merge($cleanupNames, $sqlFailureNames);
    $sqlFailureValues[] = [null];
    $ordinaryBatchFailure = null;
    $ordinaryBatchResult = null;
    try {
        $ordinaryBatchResult = $manager->insertBatch(['name'], $sqlFailureValues);
    } catch (Throwable $e) {
        $ordinaryBatchFailure = $e;
    }
    $firstChunkQ = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostName LIKE ?');
    $firstChunkQ->execute([$sqlFailurePrefix . '%']);
    $firstChunkRows = (int)$firstChunkQ->fetchColumn();
    $ordinarySQLFailure = $ordinaryBatchFailure
        && !($ordinaryBatchFailure instanceof FOG\Agent\DirectoryFenceRefusal);
    if ($ordinaryBatchFailure instanceof FOG\Agent\DirectoryFenceRefusal && 0 === $firstChunkRows) {
        echo "COMPATIBILITY FINDING B28: DirectoryNameFence refused the ordinary invalid-SQL tuple before the first 500-row chunk; legacy partial-SQL-failure behavior was not exercised.\n";
    }
    $t->check(
        'B28 ordinary 501-row batch commits first 500 before final NOT NULL SQL failure',
        $ordinarySQLFailure && 500 === $firstChunkRows && null === $ordinaryBatchResult
    );
} finally {
    foreach ($fixtureDirectories as $hostID) {
        $delete = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $delete->execute([$hostID]);
    }
    foreach (array_values(array_unique($fixtureHosts)) as $hostID) {
        $delete = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $delete->execute([$hostID]);
    }
    $deleteByName = $pdo->prepare('DELETE FROM hosts WHERE hostName=?');
    foreach (array_values(array_unique($cleanupNames)) as $cleanupName) {
        $deleteByName->execute([$cleanupName]);
    }
    $removeBarrierDirs();
}

echo "NOTE: real scratch SQL covers B25, B26, B27, B28, and the ordinary 20-row batch control; no fake SQL rewriting is used.\n";
$t->finish();
