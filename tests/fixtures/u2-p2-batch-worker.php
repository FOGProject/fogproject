<?php
/** Separate real PDODB writer used while the parent holds the name fence. */
$barrierDir = $argv[1] ?? '';
$hostID = isset($argv[2]) ? (int)$argv[2] : 0;
$tag = $argv[3] ?? '';
$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolved = realpath($barrierDir);
if (false === $resolved || 0 !== strpos($resolved, $tempRoot)
    || 0 !== strpos(basename($resolved), 'u2-p2-batch-')
    || $hostID < 1 || '' === $tag) {
    fwrite(STDERR, "invalid U2-P2 batch barrier input\n");
    exit(2);
}

$report = ['ok' => false, 'fence_held' => false, 'updated' => false];
try {
    if (!is_file($resolved . DIRECTORY_SEPARATOR . 'parent-paused')) {
        throw new \RuntimeException('parent statement did not reach its pause point');
    }
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
        throw new \RuntimeException('explicit scratch MySQL opt-in is required');
    }
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-batch-worker');
    $pdo = new \PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new \RuntimeException('database must use the fog_mj1 scratch prefix');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
    }
    $namespace = (string)$pdo->query(
        'SELECT CASE WHEN @@lower_case_table_names=0 THEN DATABASE() ELSE LOWER(DATABASE()) END'
    )->fetchColumn();
    $key = 'fog.mj1.name.v1:' . substr(hash('sha256', $namespace), 0, 40);
    $lockQuery = $pdo->prepare('SELECT IS_USED_LOCK(?)');
    $lockQuery->execute([$key]);
    $report['fence_held'] = (int)$lockQuery->fetchColumn() > 0;
    if (!$report['fence_held']) {
        throw new \RuntimeException('parent did not hold the global name fence during the child write');
    }

    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    $result = (new FOG\Managers\HostManager())->update(
        ['id' => $hostID],
        'AND',
        ['description' => $tag]
    );
    $verify = $pdo->prepare('SELECT hostDesc FROM hosts WHERE hostID=?');
    $verify->execute([$hostID]);
    $report['updated'] = true === $result && $tag === $verify->fetchColumn();
    if (!$report['updated']) {
        throw new \RuntimeException('child noncritical description update did not persist');
    }
    $report['ok'] = true;
} catch (\Throwable $e) {
    $report['error'] = get_class($e) . ': ' . $e->getMessage();
}

$temporary = $resolved . DIRECTORY_SEPARATOR . 'child-result.json.tmp';
$resultPath = $resolved . DIRECTORY_SEPARATOR . 'child-result.json';
if (false === file_put_contents($temporary, json_encode($report), LOCK_EX)
    || !rename($temporary, $resultPath)) {
    @unlink($temporary);
    fwrite(STDERR, "could not atomically publish U2-P2 batch worker report\n");
    exit(2);
}
exit($report['ok'] ? 0 : 1);
