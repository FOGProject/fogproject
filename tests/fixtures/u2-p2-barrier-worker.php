<?php
/**
 * Child process for deterministic U2-04 name-fence barriers.
 * It only connects to an explicitly opted-in fog_mj1 scratch database.
 */

function u2p2ScratchPdo()
{
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
        throw new RuntimeException('scratch database opt-in and mysql DSN are required');
    }
    if ('' === $dsn) {
        throw new RuntimeException('MJ1_DB_DSN is required');
    }

    $pdo = new PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new RuntimeException('database name must use the fog_mj1 scratch prefix');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
    }
    return $pdo;
}

function u2p2InstallPdo(PDO $pdo)
{
    $property = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $property->setAccessible(true);
    $property->setValue(null, $pdo);

    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    return $db;
}

function u2p2WriteSignal($path, array $payload = [])
{
    $payload['pid'] = getmypid();
    file_put_contents($path, json_encode($payload), LOCK_EX);
}

function u2p2ReadJson($path)
{
    $raw = @file_get_contents($path);
    $decoded = false === $raw ? null : json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

$mode = $argv[1] ?? '';
$barrierDir = $argv[2] ?? '';
$expectedDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (!in_array($mode, ['hold', 'exit-held', 'acquire'], true)
    || false === $resolvedDir
    || 0 !== strpos($resolvedDir, $expectedDir)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-barrier-')
) {
    fwrite(STDERR, "unsafe or invalid barrier worker arguments\n");
    exit(2);
}

try {
    $tests = dirname(__DIR__);
    require_once $tests . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-fence-worker');
    $pdo = u2p2ScratchPdo();
    u2p2InstallPdo($pdo);

    if ('exit-held' === $mode) {
        FOG\Agent\DirectoryNameFence::run(function (PDO $db) use ($barrierDir) {
            $identity = $db->query(
                'SELECT DATABASE() AS db, CONNECTION_ID() AS connection_id'
            )->fetch(PDO::FETCH_ASSOC);
            u2p2WriteSignal($barrierDir . '/ready-exit.json', $identity);
            // Deliberately terminate while holding GET_LOCK. PHP process exit
            // must close the owning connection; there is no test-side unlock.
            exit(0);
        });
        throw new RuntimeException('exit-held callback unexpectedly returned');
    }

    $result = FOG\Agent\DirectoryNameFence::run(
        function (PDO $db) use ($mode, $barrierDir) {
            $identity = $db->query(
                'SELECT DATABASE() AS db, CONNECTION_ID() AS connection_id'
            )->fetch(PDO::FETCH_ASSOC);
            if ('hold' === $mode) {
                u2p2WriteSignal($barrierDir . '/ready-hold.json', $identity);
                $deadline = microtime(true) + 15.0;
                while (!is_file($barrierDir . '/release-hold')) {
                    if (microtime(true) >= $deadline) {
                        throw new RuntimeException('parent did not release hold barrier');
                    }
                    usleep(10000);
                }
            }
            return $identity;
        }
    );
    echo json_encode(['ok' => true, 'identity' => $result]), "\n";
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'class' => get_class($e),
        'reason' => $e->getMessage()
    ]), "\n";
    exit(1);
}
