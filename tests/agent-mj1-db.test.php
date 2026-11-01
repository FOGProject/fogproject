<?php
/** Disposable DB setup plus legacy-state persistence and schema migration checks. */
if (!getenv('MJ1_DB_DSN')) { echo "SKIP MJ-1 DB: MJ1_DB_DSN not set\n"; exit(0); }
require __DIR__ . '/lib/fog-test-harness.php';
require_once __DIR__ . '/lib/fog-schema-collector.php';
FogTestHarness::boot('agent-mj1-db');
$fake = FogTestHarness::fakeDb();
function mj1Pdo(){return new \PDO(getenv('MJ1_DB_DSN'),getenv('MJ1_DB_USER')?:'root',getenv('MJ1_DB_PASSWORD'),[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>false]);}
$pdo = mj1Pdo();
$fake->pdo = $pdo;
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (getenv('MJ1_DB_ALLOW_RESET')!=='1' || !preg_match('/\Afog_mj1(?:_[A-Za-z0-9_]+)?\z/', $database)) { throw new \RuntimeException('MJ-1 DB test requires an explicitly resettable scratch database'); }
$manifest = require dirname(__DIR__).'/packages/web/commons/schema-expected.php';
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS hostDirectory');
$pdo->exec('DROP TABLE IF EXISTS directoryPlacementIntent');
$pdo->exec('DROP TABLE IF EXISTS hosts');
$pdo->exec('DROP TABLE IF EXISTS multicastSessions');
$pdo->exec($manifest['tables']['hosts']['create']);
$pdo->exec($manifest['tables']['multicastSessions']['create']);
$before = $manifest['tables']['hostDirectory']['create'];
$before = preg_replace('/`hdRejoin(?:Config|State)`\s+text\s+(?:DEFAULT NULL|NULL DEFAULT NULL),\s*/i','',$before);
$pdo->exec($before);
$steps = fogCollectSchemaSteps(dirname(__DIR__).'/packages/web/commons/schema.php', $database, 447);
$step447 = $steps[446] ?? null;
if (!is_array($step447) || array_keys($step447) !== [0, 1, 2]
    || count(array_filter($step447, 'is_string')) !== 3) { throw new \RuntimeException('official schema 447 must be three ordered DDL statements at schema index 446'); }
$sql = json_decode(shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/lib/mj1-schema-sql.php').' '.escapeshellarg($database)), true);
if (!is_array($sql) || $sql !== $step447
    || strpos($sql[0], 'ALTER TABLE `hostDirectory` ADD COLUMN `hdRejoinConfig`') !== 0
    || strpos($sql[1], 'ALTER TABLE `hostDirectory` ADD COLUMN `hdRejoinState`') !== 0
    || strpos($sql[2], 'CREATE TABLE IF NOT EXISTS `directoryPlacementIntent`') !== 0) { throw new \RuntimeException('helper must return the collector-exact schema 447 DDL array in official order'); }
foreach ($sql as $statement) {
    $pdo->exec($statement);
}
$expectedColumns = $manifest['tables']['hostDirectory']['columns'];
$actualColumns = $pdo->query('SHOW COLUMNS FROM hostDirectory')->fetchAll(\PDO::FETCH_ASSOC);
if (array_column($actualColumns, 'Field') !== array_keys($expectedColumns)) { throw new \RuntimeException('migration hostDirectory columns differ from schema-expected.php'); }
function mj1DbNormalizeColumnType($type) {
    $type = strtolower(trim($type));
    return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
}
foreach ($actualColumns as $column) {
    $definition = $expectedColumns[$column['Field']];
    $expectedType = preg_split('/\s+(?:NOT NULL|DEFAULT)\b/i', $definition, 2)[0];
    if (mj1DbNormalizeColumnType($column['Type']) !== mj1DbNormalizeColumnType($expectedType)
        || (strpos($definition, 'NOT NULL') !== false) !== ($column['Null'] === 'NO')) { throw new \RuntimeException('migration hostDirectory structure differs from schema-expected.php: '.$column['Field']); }
}
foreach (['hdRejoinConfig', 'hdRejoinState'] as $field) {
    $index = array_search($field, array_column($actualColumns, 'Field'), true);
    if ($actualColumns[$index]['Default'] !== null) { throw new \RuntimeException('migration column must default to NULL: '.$field); }
}
$intentColumns = $pdo->query('SHOW COLUMNS FROM directoryPlacementIntent')->fetchAll(PDO::FETCH_COLUMN);
if (array_diff(array_keys($manifest['tables']['directoryPlacementIntent']['columns']), $intentColumns)) { throw new \RuntimeException('schema 447 directoryPlacementIntent columns differ from schema-expected.php'); }
$pdo->exec("INSERT INTO hosts (hostID,hostName,hostADDomain,hostUseAD,hostAgentFingerprint) VALUES (7,'B','corp.example',1,'".str_repeat('f',64)."')");
$legacy = ['version'=>1,'phase'=>'ready','attempt_id'=>str_repeat('a',32),'source'=>['guid'=>'11111111-1111-1111-1111-111111111111','sid'=>'S-1-5-21-1-1'],'target'=>['guid'=>'22222222-2222-2222-2222-222222222222','sid'=>'S-1-5-21-1-2'],'clone_status'=>'detected','clone_origin'=>['host_id'=>99],'origin_host_id'=>99];
$legacyDecoded = $legacy;
unset($legacyDecoded['clone_status'], $legacyDecoded['clone_origin'], $legacyDecoded['origin_host_id']);
$q = $pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (7,?,?)');
$q->execute([json_encode(['version'=>1,'enabled'=>true]), json_encode($legacy)]);
$host = (new FOG\Items\Host())->set('id',7);
$method = new \ReflectionMethod(FOG\Agent\DirectoryRejoin::class, 'change');
$method->setAccessible(true);
$fake->pdo = $pdo;
if (FOG\Agent\DirectoryRejoin::settings($host)['state'] !== $legacyDecoded) { throw new \RuntimeException('legacy state decode did not discard clone classification'); }
try {
    FOG\Agent\DirectoryRejoin::beforePoll($host, []);
    throw new \RuntimeException('old agent bypassed pending operation guard');
} catch (\RuntimeException $e) {
    if ($e->getMessage() !== 'directory_target_feature_required') { throw $e; }
}
try {
    FOG\Agent\DirectoryRejoin::start($host, ['attempt_id' => $legacy['attempt_id']]);
    throw new \RuntimeException('legacy v1 attempt was executed');
} catch (\RuntimeException $e) {
    if ($e->getMessage() === 'legacy v1 attempt was executed') { throw $e; }
}
if (FOG\Agent\DirectoryRejoin::settings($host)['state'] !== $legacyDecoded) { throw new \RuntimeException('legacy start refusal changed the old operation'); }
$method->invoke(null,$host,function(&$config,&$state) {});
if (FOG\Agent\DirectoryRejoin::settings($host)['state'] !== $legacyDecoded) { throw new \RuntimeException('legacy state changed during no-op transaction'); }
try {
    $method->invoke(null,$host,function(&$config,&$state) {$state['phase']='started'; throw new \RuntimeException('injected');});
    throw new \RuntimeException('rollback injection failed');
} catch (\ReflectionException $e) { throw $e; }
catch (\RuntimeException $e) { if ($e->getMessage() !== 'injected') { throw $e; } }
$stored = json_decode($pdo->query('SELECT hdRejoinState FROM hostDirectory WHERE hdHostID=7')->fetchColumn(),true);
if ($stored !== $legacyDecoded) { throw new \RuntimeException('legacy state transaction did not roll back'); }
$q = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=NULL, hdRejoinState=NULL WHERE hdHostID=7');
$q->execute();
// An ordinary old Agent remains allowed when this Host has no MJ-1 operation.
FOG\Agent\DirectoryRejoin::beforePoll($host, []);
$row = $pdo->query('SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=7')->fetch(\PDO::FETCH_ASSOC);
if ($row['hdRejoinConfig'] !== null || $row['hdRejoinState'] !== null) { throw new \RuntimeException('ordinary old Agent changed directory state'); }
$q = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7');
$q->execute([json_encode(['version'=>1,'protocol'=>2,'allow_create'=>false]),json_encode(['version'=>1,'protocol'=>2,'phase'=>'unknown','attempt_id'=>str_repeat('b',32)])]);
try {
    FOG\Agent\DirectoryRejoin::beforePoll($host, []);
    throw new \RuntimeException('old Agent bypassed current v2 unknown state');
} catch (\RuntimeException $e) {
    if ($e->getMessage() !== 'directory_target_feature_required') { throw $e; }
}
$pdo->exec('ALTER TABLE hostDirectory ENGINE=MyISAM');
try { $method->invoke(null,$host,function(&$config,&$state) {}); throw new \RuntimeException('unsafe engine accepted'); }
catch (\RuntimeException $e) { if ($e->getMessage() !== 'rejoin_transactional_tables_required') { throw $e; } }
$pdo->exec('ALTER TABLE hostDirectory ENGINE=InnoDB');
echo "PASS MJ-1 DB migration, expected structure, legacy read/rollback, engine guard\n";
