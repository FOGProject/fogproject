<?php
/** DB-only report/name-save concurrency worker; all directory reads are stubbed. */

class U2P2ReportBarrierFakeLdap
{
    public static $record = [];

    public function connect($uri, $bindDN, $password, $caCert = '')
    {
        return true;
    }

    public function domainContext($domain)
    {
        return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example'];
    }

    public function computerRecord($base, $account)
    {
        if (!isset(self::$record['account']) || 0 !== strcasecmp(
            rtrim((string)$account, '$'), rtrim((string)self::$record['account'], '$')
        )) {
            return ['status' => 'error'];
        }
        return ['status' => 'found', 'record' => self::$record];
    }

    public function close()
    {
    }
}
class_alias(U2P2ReportBarrierFakeLdap::class, 'FOG\\Net\\FOGLdap', false);

class U2P2ReportBarrierStatement extends \PDOStatement
{
    public static $armed = false;
    public static $barrierDir = '';
    public static $connectionID = 0;
    public static $pdo;

    protected function __construct()
    {
    }

    public function execute($params = null): bool
    {
        $ok = parent::execute($params);
        if ($ok && self::$armed
            && preg_match('/^\\s*UPDATE\\s+hostDirectory\\s+SET\\s+hdRejoinConfig\\s*=\\s*\\?\\s*,\\s*hdRejoinState\\s*=\\s*\\?\\s+WHERE\\s+hdHostID\\s*=\\s*\\?\\s*$/i', $this->queryString)) {
            self::$armed = false;
            $updatedState = isset($params[1]) && is_string($params[1])
                ? json_decode($params[1], true) : null;
            $readyPath = self::$barrierDir . DIRECTORY_SEPARATOR . 'report-updated-before-commit.json';
            $readyTemp = $readyPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
            $published = file_put_contents($readyTemp, json_encode([
                'pid' => getmypid(),
                'connection_id' => self::$connectionID,
                'actual_update_executed' => true,
                'transaction_open' => self::$pdo instanceof \PDO && self::$pdo->inTransaction(),
                'phase_in_update' => is_array($updatedState) ? ($updatedState['phase'] ?? null) : null,
                'last_deploy_task_id' => is_array($updatedState) ? ($updatedState['last_deploy_task_id'] ?? null) : null,
                'target_guid' => $updatedState['target']['guid'] ?? null,
                'target_sid' => $updatedState['target']['sid'] ?? null
            ], JSON_THROW_ON_ERROR), LOCK_EX);
            if (false === $published || !rename($readyTemp, $readyPath)) {
                @unlink($readyTemp);
                throw new \RuntimeException('could not publish complete report readiness record');
            }
            $go = self::$barrierDir . DIRECTORY_SEPARATOR . 'go-report';
            $deadline = microtime(true) + 25.0;
            while (!is_file($go)) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('parent did not release report commit barrier');
                }
                usleep(10000);
            }
        }
        return $ok;
    }
}

$mode = $argv[1] ?? '';
$barrierDir = $argv[2] ?? '';
$input = isset($argv[3]) ? json_decode($argv[3], true) : null;
$expectedRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (!in_array($mode, ['report-pause', 'save-name'], true)
    || false === $resolvedDir || 0 !== strpos($resolvedDir, $expectedRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-report-barrier-') || !is_array($input)) {
    fwrite(STDERR, "invalid scratch report barrier arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-report-barrier-worker');
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:') || '' === $dsn) {
        throw new \RuntimeException('report barrier worker requires explicit MySQL scratch opt-in');
    }
    foreach (['database','host_id','name','domain','fingerprint','directory_target','old_name'] as $key) {
        if (!array_key_exists($key, $input)) {
            throw new \RuntimeException('report barrier worker arguments are incomplete');
        }
    }
    $pdoOptions = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
    if ('report-pause' === $mode) {
        $pdoOptions[\PDO::ATTR_STATEMENT_CLASS] = [U2P2ReportBarrierStatement::class, []];
    }
    $pdo = new \PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '', $pdoOptions);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== $input['database']
        || ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_'))) {
        throw new \RuntimeException('report barrier worker database differs from validated scratch database');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
    }
    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    if ('report-pause' === $mode) {
        $cache = [];
        foreach ([
            'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example',
            'FOG_DIRECTORY_BIND_DN' => 'CN=fixture',
            'FOG_DIRECTORY_BIND_PASSWORD' => 'fixture-only',
            'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example',
            'FOG_DIRECTORY_CA_CERT' => ''
        ] as $key => $value) {
            $cache[$key] = ['value' => $value];
        }
        FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
        FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
        U2P2ReportBarrierFakeLdap::$record = $input['directory_target'];
        U2P2ReportBarrierStatement::$pdo = $pdo;
        U2P2ReportBarrierStatement::$barrierDir = $resolvedDir;
        U2P2ReportBarrierStatement::$connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        U2P2ReportBarrierStatement::$armed = true;
        $host = (new FOG\Items\Host())->set('id', (int)$input['host_id'])
            ->set('name', (string)$input['name'])->set('ADDomain', (string)$input['domain'])
            ->set('useAD', 1)->set('agentFingerprint', (string)$input['fingerprint']);
        $reported = false;
        $reason = '';
        try {
            FOG\Agent\DirectoryOperation::report($host, $input['body']);
            $reported = true;
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        $after = FOG\Agent\DirectoryRejoin::settings($host);
        echo json_encode([
            'reported' => $reported,
            'reason' => $reason,
            'phase' => $after['state']['phase'] ?? null,
            'target_guid' => $after['state']['target']['guid'] ?? null,
            'target_sid' => $after['state']['target']['sid'] ?? null,
            'last_deploy_task_id' => $after['state']['last_deploy_task_id'] ?? null,
            'trigger_task_id' => $after['state']['trigger_task_id'] ?? null
        ]), "\n";
        exit(0);
    }

    if (!isset($input['competitor_id'])) {
        throw new \RuntimeException('name saver competitor ID is missing');
    }
    $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $startedPath = $resolvedDir . DIRECTORY_SEPARATOR . 'save-started.json';
    $startedTemp = $startedPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $published = file_put_contents($startedTemp, json_encode([
        'pid' => getmypid(), 'connection_id' => $connectionID
    ], JSON_THROW_ON_ERROR), LOCK_EX);
    if (false === $published || !rename($startedTemp, $startedPath)) {
        @unlink($startedTemp);
        throw new \RuntimeException('could not publish complete name-save readiness record');
    }
    $saved = false;
    $reason = '';
    try {
        $result = $db->query(
            'UPDATE `hosts` SET `hostName`=:new_name WHERE `hostID`=:host_id',
            [],
            ['new_name' => $input['old_name'], 'host_id' => (int)$input['competitor_id']]
        );
        $saved = false === $result->error && 1 === (int)$result->affectedRows();
        if (!$saved) {
            $reason = is_string($result->error) ? $result->error : 'name_save_did_not_update_one_row';
        }
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
    }
    echo json_encode(['saved' => $saved, 'reason' => $reason, 'connection_id' => $connectionID]), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
