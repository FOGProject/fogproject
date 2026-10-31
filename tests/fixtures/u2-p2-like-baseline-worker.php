<?php
/** Run one minimal LIKE UPDATE against the requested immutable source root. */
ini_set('display_errors', 'stderr');

/** Capture the statement and bound values before later logging queries replace PDODB state. */
class U2P2LikeCaptureStatement extends PDOStatement
{
    public static $armed = false;
    public static $actual = null;
    private $captured = false;

    protected function __construct()
    {
    }

    public function bindValue($param, $value, $type = PDO::PARAM_STR): bool
    {
        if (self::$armed && !$this->captured
            && preg_match('/^\s*UPDATE\s+`?hosts`?/i', $this->queryString)) {
            self::$actual = ['sql' => $this->queryString, 'params' => []];
            $this->captured = true;
        }
        if ($this->captured) {
            self::$actual['params'][(string)$param] = $value;
        }
        return parent::bindValue($param, $value, $type);
    }
}

$appRoot = isset($argv[1]) ? realpath($argv[1]) : false;
$currentRoot = realpath(dirname(__DIR__, 2));
$mountedP1 = realpath('/p1-source');
if (false === $appRoot || ($appRoot !== $currentRoot && $appRoot !== $mountedP1)
    || !is_file($appRoot . '/tests/lib/fog-test-harness.php')) {
    fwrite(STDERR, "invalid source root for LIKE compatibility worker\n");
    exit(2);
}
$targetID = isset($argv[2]) ? (int)$argv[2] : 0;
$controlID = isset($argv[3]) ? (int)$argv[3] : 0;
$targetName = (string)($argv[4] ?? '');
$controlName = (string)($argv[5] ?? '');
$initialDescription = (string)($argv[6] ?? '');
$pattern = (string)($argv[7] ?? '');
$updatedDescription = (string)($argv[8] ?? '');
$controlDescription = (string)($argv[9] ?? '');
if ($targetID < 1 || $controlID < 1 || $targetID === $controlID
    || '' === $targetName || '' === $controlName || '' === $pattern
    || false === strpos($pattern, '%')) {
    fwrite(STDERR, "invalid LIKE compatibility fixture input\n");
    exit(2);
}
$dsn = (string)getenv('MJ1_DB_DSN');
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    fwrite(STDERR, "explicit scratch MySQL opt-in is required\n");
    exit(2);
}

require_once $appRoot . '/tests/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-like-worker-' . substr(hash('sha256', $appRoot), 0, 8));
$pdo = null;
$report = [
    'ok' => false,
    'source_root' => $appRoot,
    'actual_sql' => null,
    'actual_params' => null,
    'result' => null,
    'error' => null,
    'error_code' => null,
    'error_info' => [],
    'exception' => null,
    'target_unchanged' => false,
    'control_unchanged' => false,
    'target_updated' => false,
    'target_before' => null,
    'target_after' => null,
    'control_before' => null,
    'control_after' => null
];
$stdoutNoise = '';
ob_start();
try {
    $pdo = new PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new RuntimeException('worker requires a fog_mj1-prefixed scratch database');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new RuntimeException('DATABASE_NAME differs from selected scratch database');
    }

    // P1's legacy code path still names core Items globally in a few places.
    // Load the real classes first, then provide the same compatibility aliases
    // the normal P1 bootstrap supplied; do not substitute a test double.
    foreach ([
        'FOG\\Items\\Host' => 'Host',
        'FOG\\Items\\HostDirectory' => 'HostDirectory'
    ] as $itemClass => $legacyName) {
        if (class_exists($itemClass) && !class_exists($legacyName, false)) {
            class_alias($itemClass, $legacyName);
        }
    }

    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [U2P2LikeCaptureStatement::class, []]);

    $link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

    $insert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostDesc,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,?,\'0\',\'\',\'\',?)'
    );
    $insert->execute([$targetID, $targetName, $initialDescription, hash('sha256', 'target-' . $targetID)]);
    $insert->execute([$controlID, $controlName, $controlDescription, hash('sha256', 'control-' . $controlID)]);
    $report['target_before'] = $initialDescription;
    $report['control_before'] = $controlDescription;

    U2P2LikeCaptureStatement::$actual = null;
    U2P2LikeCaptureStatement::$armed = true;
    try {
        $result = (new FOG\Managers\HostManager())->update(
            ['description' => $pattern],
            'AND',
            ['description' => $updatedDescription]
        );
        $report['result'] = is_bool($result) ? $result : null;
    } catch (Throwable $e) {
        $report['exception'] = get_class($e) . ': ' . $e->getMessage();
    } finally {
        U2P2LikeCaptureStatement::$armed = false;
    }
    $report['actual_sql'] = U2P2LikeCaptureStatement::$actual['sql'] ?? null;
    $report['actual_params'] = U2P2LikeCaptureStatement::$actual['params'] ?? null;
    $report['pdodb_last_sql_after_call'] = $db->lastSql;
    $report['pdodb_last_params_after_call'] = $db->lastParams;
    $report['error'] = $db->error;
    $report['error_code'] = $db->errorCode;
    $report['error_info'] = $db->lastErrorInfo;
    $query = $pdo->prepare('SELECT hostName,hostDesc FROM hosts WHERE hostID=?');
    $query->execute([$targetID]);
    $target = $query->fetch(PDO::FETCH_ASSOC) ?: null;
    $query->execute([$controlID]);
    $control = $query->fetch(PDO::FETCH_ASSOC) ?: null;
    $report['target_after'] = $target['hostDesc'] ?? null;
    $report['control_after'] = $control['hostDesc'] ?? null;
    $report['target_unchanged'] = is_array($target)
        && $targetName === $target['hostName']
        && $initialDescription === $target['hostDesc'];
    $report['control_unchanged'] = is_array($control)
        && $controlName === $control['hostName']
        && $controlDescription === $control['hostDesc'];
    $report['target_updated'] = is_array($target)
        && $targetName === $target['hostName']
        && $updatedDescription === $target['hostDesc'];
    $report['ok'] = null === $report['exception']
        && null !== $report['actual_sql']
        && null !== $report['actual_params'];
} catch (Throwable $e) {
    $report['exception'] = get_class($e) . ': ' . $e->getMessage();
} finally {
    if ($pdo instanceof PDO) {
        $delete = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID IN (?,?)');
        $delete->execute([$targetID, $controlID]);
        $delete = $pdo->prepare('DELETE FROM hosts WHERE hostID IN (?,?)');
        $delete->execute([$targetID, $controlID]);
    }
    $stdoutNoise = (string)ob_get_clean();
}
if ('' !== trim($stdoutNoise)) {
    fwrite(STDERR, $stdoutNoise);
}
echo json_encode($report, JSON_UNESCAPED_SLASHES) . "\n";
exit($report['ok'] ? 0 : 1);
