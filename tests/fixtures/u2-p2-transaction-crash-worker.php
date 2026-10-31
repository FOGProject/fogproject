<?php
/** Pause real DirectoryRejoin::change after its UPDATE, before COMMIT. */

class U2P2TransactionCrashStatement extends PDOStatement
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
            $readyPath = self::$barrierDir . DIRECTORY_SEPARATOR . 'updated-before-commit.json';
            $readyTemp = $readyPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
            $published = file_put_contents($readyTemp, json_encode([
                'pid' => getmypid(),
                'connection_id' => self::$connectionID,
                'actual_update_executed' => true,
                'transaction_open' => self::$pdo instanceof PDO && self::$pdo->inTransaction(),
                'callback_phase' => is_array($updatedState) ? ($updatedState['phase'] ?? null) : null,
                'callback_highwater' => is_array($updatedState) ? ($updatedState['last_deploy_task_id'] ?? null) : null
            ], JSON_THROW_ON_ERROR), LOCK_EX);
            if (false === $published || !rename($readyTemp, $readyPath)) {
                @unlink($readyTemp);
                throw new RuntimeException('could not publish complete transaction crash readiness record');
            }

            // Parent kills this process while the UPDATE remains uncommitted.
            while (true) {
                usleep(100000);
            }
        }
        return $ok;
    }
}

$barrierDir = $argv[1] ?? '';
$hostID = isset($argv[2]) ? (int)$argv[2] : 0;
$expectedRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (false === $resolvedDir || 0 !== strpos($resolvedDir, $expectedRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-transaction-crash-') || $hostID < 1) {
    fwrite(STDERR, "invalid scratch transaction crash arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-transaction-crash-worker');
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:') || '' === $dsn) {
        throw new RuntimeException('transaction crash worker requires explicit MySQL scratch opt-in');
    }
    $pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STATEMENT_CLASS => [U2P2TransactionCrashStatement::class, []]
    ]);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new RuntimeException('transaction crash worker database is outside scratch prefix');
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

    U2P2TransactionCrashStatement::$barrierDir = $resolvedDir;
    U2P2TransactionCrashStatement::$pdo = $pdo;
    U2P2TransactionCrashStatement::$connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    U2P2TransactionCrashStatement::$armed = true;
    $host = new FOG\Items\Host($hostID);
    if (!$host->isValid()) {
        throw new RuntimeException('transaction crash fixture host is unavailable');
    }
    FOG\Agent\DirectoryRejoin::change($host, static function (&$config, &$state) {
        // Fixture-only state mutation: this deliberately does not call the
        // production Task claim/authorization path and creates no Task row.
        $config['confirmed_by'] = 'u2-p2-callback-mutated-before-crash';
        $state['phase'] = 'started';
        $state['attempt_id'] = str_repeat('b', 32);
        $state['last_deploy_task_id'] = 14;
        $state['trigger_task_id'] = 14;
    });
    fwrite(STDERR, "fixture writer unexpectedly completed instead of pausing before commit\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
