<?php
/** Claim/name-save barrier workers; restricted to opted-in fog_mj1 scratch DB. */

class U2P2ClaimPauseStatement extends PDOStatement
{
    public static $barrierDir = '';
    public static $pauseClaimSelect = false;
    public static $readyFile = 'claim-row-locked.json';
    public static $resumeFile = 'resume-claim';

    private static function writeJson($name, array $value)
    {
        $path = self::$barrierDir . DIRECTORY_SEPARATOR . $name;
        $tmp = $path . '.tmp.' . getmypid();
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === file_put_contents($tmp, $json, LOCK_EX) || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('cannot atomically write claim barrier signal');
        }
    }

    public static function writeSignalForWorker(array $value)
    {
        self::writeJson(self::$readyFile, $value);
    }

    protected function __construct()
    {
    }

    public function execute($params = null): bool
    {
        $ok = parent::execute($params);
        if ($ok && self::$pauseClaimSelect
            && preg_match('/^\s*SELECT\s+hostID\s*,\s*hostName\s*,\s*hostADDomain\s*,\s*hostUseAD\s*,\s*hostAgentFingerprint\s+FROM\s+hosts\s+WHERE\s+hostID\s*=\s*\?\s+FOR\s+UPDATE\s*$/i', $this->queryString)) {
            self::$pauseClaimSelect = false;
            self::writeJson(self::$readyFile, ['pid' => getmypid(), 'selected' => true]);
            $resume = self::$barrierDir . DIRECTORY_SEPARATOR . self::$resumeFile;
            $deadline = microtime(true) + 15.0;
            while (!is_file($resume)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('parent did not release claim barrier');
                }
                usleep(10000);
            }
        }
        return $ok;
    }
}

function u2p2ClaimWorkerPdo($pauseClaim = false)
{
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:') || '' === $dsn) {
        throw new RuntimeException('claim worker requires explicit MySQL scratch opt-in');
    }
    $pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($pauseClaim) {
        U2P2ClaimPauseStatement::$pauseClaimSelect = true;
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [U2P2ClaimPauseStatement::class, []]);
    }
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new RuntimeException('claim worker database is outside scratch prefix');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
    }
    $link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);
    return [$pdo, $db];
}

$mode = $argv[1] ?? '';
$barrierDir = $argv[2] ?? '';
$input = isset($argv[3]) ? json_decode($argv[3], true) : null;
$expectedRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (!in_array($mode, ['claim-paused', 'retry-paused', 'save-name'], true)
    || false === $resolvedDir || 0 !== strpos($resolvedDir, $expectedRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-claim-') || !is_array($input)) {
    fwrite(STDERR, "invalid scratch claim worker arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-claim-worker');
    U2P2ClaimPauseStatement::$barrierDir = $resolvedDir;
    U2P2ClaimPauseStatement::$readyFile = 'claim-row-locked.json';
    U2P2ClaimPauseStatement::$resumeFile = 'claim-paused' === $mode ? 'resume-claim' : 'resume-retry';
    list($pdo, $db) = u2p2ClaimWorkerPdo(in_array($mode, ['claim-paused', 'retry-paused'], true));

    if ('save-name' === $mode) {
        if (!isset($input['host_id'], $input['new_name'])) {
            throw new RuntimeException('name saver arguments are incomplete');
        }
        $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        U2P2ClaimPauseStatement::$readyFile = 'save-started.json';
        U2P2ClaimPauseStatement::writeSignalForWorker([
            'pid' => getmypid(), 'connection_id' => $connectionID
        ]);
        $reason = '';
        $saved = false;
        try {
            $result = $db->query(
                'UPDATE `hosts` SET `hostName`=:new_name WHERE `hostID`=:host_id',
                [],
                ['new_name' => $input['new_name'], 'host_id' => (int)$input['host_id']]
            );
            $saved = false === $result->error && 1 === (int)$result->affectedRows();
            if (!$saved) {
                $reason = 'name_save_did_not_update_one_row';
            }
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }
        echo json_encode(['saved' => $saved, 'reason' => $reason]), "\n";
        exit(0);
    }

    if ('retry-paused' === $mode) {
        foreach (['host_id','name','fingerprint','domain'] as $key) {
            if (!array_key_exists($key, $input)) {
                throw new RuntimeException('Retry worker arguments are incomplete');
            }
        }
        $host = (new FOG\Items\Host())->set('id', (int)$input['host_id'])
            ->set('name', (string)$input['name'])->set('ADDomain', (string)$input['domain'])
            ->set('useAD', 1)->set('agentFingerprint', (string)$input['fingerprint']);
        try {
            FOG\Agent\DirectoryRejoin::configure($host, ['rejoin_retry' => true], 'u2-p2-b07-fixture');
            echo json_encode(['configured' => true]), "\n";
            exit(0);
        } catch (Throwable $e) {
            echo json_encode(['configured' => false, 'reason' => $e->getMessage()]), "\n";
            exit(0);
        }
    }

    foreach (['host_id','name','fingerprint','domain','body'] as $key) {
        if (!array_key_exists($key, $input)) {
            throw new RuntimeException('claim worker arguments are incomplete');
        }
    }
    $host = (new FOG\Items\Host())->set('id', (int)$input['host_id'])
        ->set('name', (string)$input['name'])->set('ADDomain', (string)$input['domain'])
        ->set('useAD', 1)->set('agentFingerprint', (string)$input['fingerprint']);
    $saved = FOG\Agent\DirectoryRejoin::settings($host);
    try {
        FOG\Agent\DirectoryOperation::claim($host, $saved, $input['body']);
        $after = FOG\Agent\DirectoryRejoin::settings($host);
        echo json_encode(['claimed' => true, 'phase' => $after['state']['phase'] ?? null]), "\n";
        exit(0);
    } catch (Throwable $e) {
        $after = FOG\Agent\DirectoryRejoin::settings($host);
        echo json_encode(['claimed' => false, 'reason' => $e->getMessage(),
            'phase' => $after['state']['phase'] ?? null]), "\n";
        exit(0);
    }
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
