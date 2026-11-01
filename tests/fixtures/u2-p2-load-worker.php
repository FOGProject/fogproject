<?php
/** Hold the production global name-fence GET_LOCK on a separate scratch PDO. */

$barrierDir = $argv[1] ?? '';
$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolved = realpath($barrierDir);
if (false === $resolved || 0 !== strpos($resolved, $tempRoot)
    || 0 !== strpos(basename($resolved), 'u2-p2-load-')) {
    fwrite(STDERR, "invalid load barrier directory\n");
    exit(2);
}

$dsn = (string)getenv('MJ1_DB_DSN');
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    fwrite(STDERR, "explicit scratch MySQL opt-in is required\n");
    exit(2);
}

$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    fwrite(STDERR, "database must use the fog_mj1 scratch prefix\n");
    exit(2);
}

$key = 'fog.mj1.name.v1:' . substr(hash('sha256', $database), 0, 40);
$held = false;
$error = null;
try {
    $acquire = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $acquire->execute([$key]);
    $held = in_array($acquire->fetchColumn(), [1, '1'], true);
    $acquire->closeCursor();
    if (!$held) {
        throw new \RuntimeException('could not acquire production name-fence lock');
    }

    $identity = $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $ready = $resolved . DIRECTORY_SEPARATOR . 'ready.json';
    $temporary = $ready . '.tmp';
    file_put_contents($temporary, json_encode([
        'ready' => true,
        'database' => $database,
        'connection_id' => (int)$identity
    ]), LOCK_EX);
    rename($temporary, $ready);

    $deadline = microtime(true) + 20.0;
    $release = $resolved . DIRECTORY_SEPARATOR . 'release';
    while (!is_file($release)) {
        if (microtime(true) >= $deadline) {
            throw new \RuntimeException('parent did not release the load barrier');
        }
        usleep(10000);
    }
} catch (\Throwable $e) {
    $error = $e->getMessage();
} finally {
    if ($held) {
        $releaseLock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $releaseLock->execute([$key]);
        $releaseLock->closeCursor();
    }
}

if (null !== $error) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

echo json_encode(['ok' => true, 'connection_id' => (int)$identity]), "\n";
