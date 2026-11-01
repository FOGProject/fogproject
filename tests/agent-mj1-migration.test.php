<?php
/** Real MariaDB execution of the official 441-447 schema steps. */
if (!getenv('MJ1_DB_DSN')) {
    echo "SKIP MJ-1 migration DB: MJ1_DB_DSN not set\n";
    exit(0);
}

// The collector runs in isolation from the FOG bootstrap.
require __DIR__ . '/lib/fog-schema-collector.php';

$pdo = new \PDO(
    getenv('MJ1_DB_DSN'),
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD'),
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (getenv('MJ1_DB_ALLOW_RESET') !== '1' || 0 !== strpos($database, 'fog_mj1')) {
    throw new \RuntimeException('MJ-1 migration test requires an explicitly resettable fog_mj1 scratch database');
}
$root = dirname(__DIR__);
$manifest = require $root . '/packages/web/commons/schema-expected.php';
$steps = fogCollectSchemaSteps($root . '/packages/web/commons/schema.php', $database, 447);
$step440 = $steps[440] ?? null; // Official schema 441.
$step441 = $steps[441] ?? null; // Official schema 442.
$step442 = $steps[442] ?? null; // Official schema 443.
$step443 = $steps[443] ?? null; // Official schema 444.
$step444 = $steps[444] ?? null; // Official schema 445.
$step445 = $steps[445] ?? null; // Official schema 446.
$step446 = $steps[446] ?? null; // Official schema 447.
foreach ([$step440, $step441, $step442, $step443, $step444, $step445] as $step) {
    if (!is_array($step) || !$step || count(array_filter($step, 'is_string')) !== count($step)) {
        throw new \RuntimeException('official schema steps 442-446 must be non-empty SQL lists at indexes 441-445');
    }
}
if (count($step440) !== 1 || false === strpos($step440[0], "'tasktype.*'")
    || count($step441) !== 1 || false === strpos($step441[0], 'msTransferStart')
    || count($step442) !== 1 || false === strpos($step442[0], 'INSERT IGNORE INTO `moduleStatusByHost`')
    || count($step443) !== 2
    || false === strpos($step443[0], 'INSERT IGNORE INTO `taskTypes`')
    || false === strpos($step443[0], "(27,'Performance Test'")
    || false === strpos($step443[1], 'CREATE TABLE IF NOT EXISTS `hostPerfTests`')
    || count($step444) !== 1 || false === strpos($step444[0], 'FOG_KERNEL_RAMDISK_SIZE')
    || count($step445) !== 1 || false === strpos($step445[0], 'MODIFY `uName` VARCHAR(255)')) {
    throw new \RuntimeException('official schema indexes 441-445 do not match migrations 442-446');
}
if (!is_array($step446) || array_keys($step446) !== [0, 1, 2]
    || false === strpos($step446[0] ?? '', 'hdRejoinConfig')
    || false === strpos($step446[1] ?? '', 'hdRejoinState')
    || false === strpos($step446[2] ?? '', 'CREATE TABLE IF NOT EXISTS `directoryPlacementIntent`')) {
    throw new \RuntimeException('official schema index 446 must contain the three schema 447 SQL statements in order');
}
$ddl442 = $step441[0];
$ddl443 = $step442[0];
$ddl447 = $step446;

function mj1MigrationCreateTables(PDO $pdo, array $manifest, $has442)
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('DROP TABLE IF EXISTS hostDirectory');
    $pdo->exec('DROP TABLE IF EXISTS multicastSessions');
    $pdo->exec('DROP TABLE IF EXISTS moduleStatusByHost');
    $pdo->exec('DROP TABLE IF EXISTS groupModuleAssoc');
    $pdo->exec('DROP TABLE IF EXISTS groupMembers');
    $pdo->exec('DROP TABLE IF EXISTS hostPerfTests');
    $pdo->exec('DROP TABLE IF EXISTS directoryPlacementIntent');
    $pdo->exec('DROP TABLE IF EXISTS taskTypes');
    $pdo->exec('DROP TABLE IF EXISTS globalSettings');
    $pdo->exec('DROP TABLE IF EXISTS users');
    $pdo->exec('DROP TABLE IF EXISTS modules');
    $pdo->exec('DROP TABLE IF EXISTS hosts');
    $pdo->exec($manifest['tables']['hosts']['create']);
    $pdo->exec("INSERT INTO hosts (hostID,hostName,hostADDomain,hostUseAD,hostAgentFingerprint) VALUES (7,'default-missing','corp.example',1,'" . str_repeat('f', 64) . "'),(8,'explicit-on','corp.example',1,'" . str_repeat('e', 64) . "'),(9,'explicit-off','corp.example',1,'" . str_repeat('d', 64) . "'),(10,'group-grant','corp.example',1,'" . str_repeat('c', 64) . "'),(11,'default-off','corp.example',1,'" . str_repeat('b', 64) . "')");

    $hostDirectory = preg_replace(
        '/`hdRejoin(?:Config|State)`\s+text\s+(?:DEFAULT NULL|NULL DEFAULT NULL),\s*/i',
        '',
        $manifest['tables']['hostDirectory']['create']
    );
    $pdo->exec($hostDirectory);
    $pdo->exec($manifest['tables']['multicastSessions']['create']);
    foreach (['modules', 'moduleStatusByHost', 'groupMembers', 'groupModuleAssoc'] as $table) {
        $pdo->exec($manifest['tables'][$table]['create']);
    }
    foreach (['taskTypes', 'globalSettings', 'users'] as $table) {
        $pdo->exec($manifest['tables'][$table]['create']);
    }
    if (!$has442) {
        $pdo->exec('ALTER TABLE `multicastSessions` DROP COLUMN `msTransferStart`');
    }
    $pdo->exec("INSERT INTO hostDirectory (hdHostID,hdDomain,hdNetbios) VALUES (7,'old.example','OLD7'),(8,'old.example','OLD8'),(9,'old.example','OLD9'),(10,'old.example','OLD10'),(11,'old.example','OLD11')");
}

function mj1MigrationRows(PDO $pdo, $before = null)
{
    $rows = [];
    foreach (['hosts', 'hostDirectory', 'multicastSessions'] as $table) {
        $columns = isset($before[$table])
            ? $before[$table]['columns']
            : $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(\PDO::FETCH_COLUMN);
        $tableRows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(\PDO::FETCH_ASSOC);
        $projected = [];
        foreach ($tableRows as $row) {
            $projectedRow = [];
            foreach ($columns as $column) {
                $projectedRow[$column] = $row[$column];
            }
            $projected[] = json_encode($projectedRow);
        }
        sort($projected, SORT_STRING);
        $rows[$table] = ['columns' => $columns, 'rows' => $projected];
    }
    return $rows;
}

function mj1MigrationNormalizeColumnType($type)
{
    $type = strtolower(trim($type));
    return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
}

function mj1MigrationModuleStates(PDO $pdo)
{
    // PDO returns numeric columns as ints or numeric strings depending on PHP
    // and driver version; compare the same database values strictly everywhere.
    return array_map(
        function ($row) {
            return [(int)$row[0], (int)$row[1], (int)$row[2]];
        },
        $pdo->query('SELECT `msHostID`,`msModuleID`,`msState` FROM `moduleStatusByHost` ORDER BY `msHostID`,`msModuleID`')->fetchAll(\PDO::FETCH_NUM)
    );
}

function mj1MigrationAssertExpectedHostDirectory(PDO $pdo, array $manifest)
{
    $expected = $manifest['tables']['hostDirectory']['columns'];
    $actual = $pdo->query('SHOW COLUMNS FROM `hostDirectory`')->fetchAll(\PDO::FETCH_ASSOC);
    if (array_column($actual, 'Field') !== array_keys($expected)) {
        throw new \RuntimeException('schema 447 hostDirectory columns do not match schema-expected.php');
    }
    foreach ($actual as $column) {
        $definition = $expected[$column['Field']];
        $expectedType = preg_split('/\s+(?:NOT NULL|DEFAULT)\b/i', $definition, 2)[0];
        if (mj1MigrationNormalizeColumnType($column['Type']) !== mj1MigrationNormalizeColumnType($expectedType)
            || (false !== strpos($definition, 'NOT NULL')) !== ('NO' === $column['Null'])) {
            throw new \RuntimeException('schema 447 hostDirectory column structure differs from schema-expected.php: ' . $column['Field']);
        }
    }
    foreach (['hdRejoinConfig', 'hdRejoinState'] as $field) {
        $column = $actual[array_search($field, array_column($actual, 'Field'), true)];
        if (null !== $column['Default']) {
            throw new \RuntimeException('schema 447 nullable text column must default to NULL: ' . $field);
        }
    }
}

function mj1MigrationPrepare443Fixture(PDO $pdo, $ddl442, $ddl443, array $manifest, $apply442, $apply443)
{
    if (!$apply442) {
        $columns = $pdo->query('SHOW COLUMNS FROM `multicastSessions`')->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('msTransferStart', $columns, true)) {
            throw new \RuntimeException('official 442 fixture must already contain msTransferStart');
        }
    }
    $before442 = $pdo->query('SHOW COLUMNS FROM `multicastSessions`')->fetchAll(\PDO::FETCH_COLUMN);
    if ($apply442 && in_array('msTransferStart', $before442, true)) {
        throw new \RuntimeException('official 441 fixture unexpectedly contains msTransferStart');
    }
    if (in_array('hdRejoinConfig', $pdo->query('SHOW COLUMNS FROM `hostDirectory`')->fetchAll(\PDO::FETCH_COLUMN), true)) {
        throw new \RuntimeException('MJ-1 columns must not exist before schema 447');
    }
    if ($apply442) {
        $pdo->exec($ddl442);
    }
    $after442 = $pdo->query('SHOW COLUMNS FROM `multicastSessions`')->fetchAll(\PDO::FETCH_COLUMN);
    if (!in_array('msTransferStart', $after442, true)) {
        throw new \RuntimeException('schema 442 did not add msTransferStart');
    }
    if (in_array('hdRejoinConfig', $pdo->query('SHOW COLUMNS FROM `hostDirectory`')->fetchAll(\PDO::FETCH_COLUMN), true)) {
        throw new \RuntimeException('MJ-1 columns appeared before schema 447');
    }
    $pdo->exec("INSERT INTO `modules` (`id`,`name`,`short_name`,`description`,`default`) VALUES (1,'Default on','on','',1),(2,'Default off','off','',0)");
    $pdo->exec("INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`,`msState`) VALUES (8,1,1),(9,1,0)");
    $pdo->exec("INSERT INTO `groupMembers` (`gmHostID`,`gmGroupID`) VALUES (10,5)");
    $pdo->exec("INSERT INTO `groupModuleAssoc` (`gmaGroupID`,`gmaModuleID`) VALUES (5,1)");
    $pdo->exec("INSERT INTO `multicastSessions` (`msName`,`msBasePort`,`msLogPath`,`msImage`,`msInterface`,`msNFSGroupID`) VALUES ('fixture',1,'','','',1)");
    $pdo->exec("UPDATE `multicastSessions` SET `msTransferStart`='2026-01-02 03:04:05'");
    $transferBefore = $pdo->query('SELECT `msTransferStart` FROM `multicastSessions`')->fetchColumn();
    if ('2026-01-02 03:04:05' !== $transferBefore) {
        throw new \RuntimeException('442 msTransferStart marker value was not stored');
    }
    if ($apply443) {
        $rowsBefore443 = mj1MigrationRows($pdo);
        $pdo->exec($ddl443);
        if ($rowsBefore443 !== mj1MigrationRows($pdo, $rowsBefore443)) {
            throw new \RuntimeException('schema 443 changed existing rows');
        }
    } else {
        // Model an already-upgraded official 443 database without replaying 443.
        $pdo->exec("INSERT INTO `moduleStatusByHost` (`msHostID`,`msModuleID`,`msState`) VALUES (7,1,0),(11,1,0)");
    }
    $hostDirectoryColumns = $pdo->query('SHOW COLUMNS FROM `hostDirectory`')->fetchAll(\PDO::FETCH_COLUMN);
    foreach (['hdRejoinConfig', 'hdRejoinState'] as $field) {
        if (in_array($field, $hostDirectoryColumns, true)) {
            throw new \RuntimeException('schema 443 fixture unexpectedly has schema 447 column: ' . $field);
        }
    }
    $states = mj1MigrationModuleStates($pdo);
    if ($states !== [[7, 1, 0], [8, 1, 1], [9, 1, 0], [11, 1, 0]]) {
        throw new \RuntimeException('schema 443 default ON/OFF, explicit ON/OFF, or group grant behavior is incorrect');
    }
    $moduleTwoRows = (int)$pdo->query('SELECT COUNT(*) FROM `moduleStatusByHost` WHERE `msModuleID`=2')->fetchColumn();
    if (0 !== $moduleTwoRows) {
        throw new \RuntimeException('schema 443 must leave default-OFF module rows missing');
    }
    return $transferBefore;
}

function mj1MigrationApply447AndAssert(PDO $pdo, array $ddl447, array $manifest, $transferBefore, $states)
{
    $rowsBefore447 = mj1MigrationRows($pdo);
    foreach ($ddl447 as $ddl) {
        $pdo->exec($ddl);
    }
    if ($rowsBefore447 !== mj1MigrationRows($pdo, $rowsBefore447)) {
        throw new \RuntimeException('schema 447 changed existing host, directory, or multicast values');
    }
    mj1MigrationAssertExpectedHostDirectory($pdo, $manifest);
    $after447 = $pdo->query('SELECT `hdRejoinConfig`, `hdRejoinState` FROM `hostDirectory`')->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($after447 as $row) {
        if (null !== $row['hdRejoinConfig'] || null !== $row['hdRejoinState']) {
            throw new \RuntimeException('schema 447 fields must be NULL for existing rows');
        }
    }
    if ($transferBefore !== $pdo->query('SELECT `msTransferStart` FROM `multicastSessions`')->fetchColumn()) {
        throw new \RuntimeException('schema 442 msTransferStart value was not preserved');
    }
    if ($states !== mj1MigrationModuleStates($pdo)) {
        throw new \RuntimeException('schema 447 changed schema 443 module state');
    }
}

// Keep the original detailed module-row fixtures for starts at schema 441,
// 442 and 443. Each reaches the current rejoin/intent DDL after the preserved
// 442/443 behavior has been established.
mj1MigrationCreateTables($pdo, $manifest, false);
$transferBefore = mj1MigrationPrepare443Fixture($pdo, $ddl442, $ddl443, $manifest, true, true);
$states = mj1MigrationModuleStates($pdo);
mj1MigrationApply447AndAssert($pdo, $ddl447, $manifest, $transferBefore, $states);

// Official 442 -> 443, with schema 442 already present.
mj1MigrationCreateTables($pdo, $manifest, true);
$transferBefore = mj1MigrationPrepare443Fixture($pdo, $ddl442, $ddl443, $manifest, false, true);
$states = mj1MigrationModuleStates($pdo);
mj1MigrationApply447AndAssert($pdo, $ddl447, $manifest, $transferBefore, $states);

// Official 443 fixture: prepare its persisted state without replaying 443.
mj1MigrationCreateTables($pdo, $manifest, true);
$transferBefore = mj1MigrationPrepare443Fixture($pdo, $ddl442, $ddl443, $manifest, false, false);
$states = mj1MigrationModuleStates($pdo);
mj1MigrationApply447AndAssert($pdo, $ddl447, $manifest, $transferBefore, $states);

// Schema 441 backfills task-type permission for each role that could already
// start a task, without duplicating an existing wildcard grant.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS rolePermissions');
$pdo->exec($manifest['tables']['rolePermissions']['create']);
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec("INSERT INTO rolePermissions (rpRoleID,rpName) VALUES (51,'host.task'),(51,'group.*'),(52,'image.task'),(52,'task.*'),(53,'tasktype.*'),(54,'audit.read')");
$pdo->exec($step440[0]);
$taskPermissionRows = array_map(
    function ($row) {
        return [(int)$row[0], $row[1]];
    },
    $pdo->query("SELECT rpRoleID,rpName FROM rolePermissions WHERE rpName='tasktype.*' ORDER BY rpRoleID")->fetchAll(\PDO::FETCH_NUM)
);
if ($taskPermissionRows !== [[51, 'tasktype.*'], [52, 'tasktype.*'], [53, 'tasktype.*']]) {
    throw new \RuntimeException('schema 441 must backfill tasktype.* once for task-capable roles and preserve an existing grant');
}
if ((int)$pdo->query("SELECT COUNT(*) FROM rolePermissions WHERE rpRoleID=54 AND rpName='audit.read'")->fetchColumn() !== 1) {
    throw new \RuntimeException('schema 441 changed an unrelated role permission');
}

// Schema 444 adds the task type and result table. Force the table to be absent
// after fixture setup so CREATE TABLE IF NOT EXISTS must create it.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS directoryPlacementIntent');
$pdo->exec('DROP TABLE IF EXISTS hostPerfTests');
$pdo->exec('DROP TABLE IF EXISTS taskTypes');
$pdo->exec('DROP TABLE IF EXISTS globalSettings');
$pdo->exec('DROP TABLE IF EXISTS users');
$pdo->exec($manifest['tables']['taskTypes']['create']);
$pdo->exec($manifest['tables']['globalSettings']['create']);
$pdo->exec($manifest['tables']['users']['create']);
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec('DROP TABLE IF EXISTS hostPerfTests');
foreach ($step443 as $ddl) {
    $pdo->exec($ddl);
}
$task = $pdo->query('SELECT `ttID`,`ttName`,`ttKernelArgs`,`ttIsAdvanced`,`ttIsAccess` FROM `taskTypes` WHERE `ttID`=27')->fetch(\PDO::FETCH_ASSOC);
if (!$task || (int)$task['ttID'] !== 27 || $task['ttName'] !== 'Performance Test'
    || $task['ttKernelArgs'] !== 'mode=perftest' || (int)$task['ttIsAdvanced'] !== 1
    || $task['ttIsAccess'] !== 'both') {
    throw new \RuntimeException('schema 444 did not install official Performance Test task type 27');
}
$perfColumns = $pdo->query('SHOW COLUMNS FROM hostPerfTests')->fetchAll(\PDO::FETCH_COLUMN);
if (array_diff(array_keys($manifest['tables']['hostPerfTests']['columns']), $perfColumns)) {
    throw new \RuntimeException('schema 444 hostPerfTests columns differ from schema-expected.php');
}

// Schema 445 changes the help text. Preserve the configured value exactly.
$setting = 'FOG_KERNEL_RAMDISK_SIZE';
$pdo->exec("INSERT INTO globalSettings (settingKey,settingDesc,settingValue,settingCategory) VALUES ('$setting','legacy description','321000','FOG Linux Service')");
$pdo->exec($step444[0]);
$settingRow = $pdo->query("SELECT settingDesc,settingValue FROM globalSettings WHERE settingKey='$setting'")->fetch(\PDO::FETCH_ASSOC);
if (!$settingRow || $settingRow['settingValue'] !== '321000'
    || false === strpos($settingRow['settingDesc'], '/tmp RAM disk')) {
    throw new \RuntimeException('schema 445 must update the ramdisk description and preserve its configured value');
}

// Schema 446 widens the two 1.5-era fields without changing stored values.
$pdo->exec('ALTER TABLE users MODIFY uName VARCHAR(40) NOT NULL, MODIFY uCreateBy VARCHAR(40) NOT NULL');
$oldName = str_repeat('n', 40);
$oldCreator = str_repeat('c', 40);
$pdo->exec("INSERT INTO users (uName,uPass,uCreateBy) VALUES ('$oldName','hash','$oldCreator')");
$pdo->exec($step445[0]);
$user = $pdo->query('SELECT uName,uCreateBy FROM users')->fetch(\PDO::FETCH_ASSOC);
$userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(\PDO::FETCH_ASSOC);
foreach (['uName', 'uCreateBy'] as $field) {
    $column = $userColumns[array_search($field, array_column($userColumns, 'Field'), true)];
    if (strtolower($column['Type']) !== 'varchar(255)' || $column['Null'] !== 'YES') {
        throw new \RuntimeException('schema 446 did not widen and make nullable users.' . $field);
    }
}
if (!$user || $user['uName'] !== $oldName || $user['uCreateBy'] !== $oldCreator) {
    throw new \RuntimeException('schema 446 did not preserve existing user values');
}

// Prepare the host fixture with its pre-447 shape. Schema 447 is an array of
// separate statements; apply the collected array in order so both ALTERs and
// the intent-table creation are exercised against that starting state.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS directoryPlacementIntent');
$pdo->exec('DROP TABLE IF EXISTS hostDirectory');
$hostDirectory = preg_replace(
    '/`hdRejoin(?:Config|State)`\s+text\s+(?:DEFAULT NULL|NULL DEFAULT NULL),\s*/i',
    '',
    $manifest['tables']['hostDirectory']['create']
);
$pdo->exec($hostDirectory);
$pdo->exec("INSERT INTO hostDirectory (hdHostID,hdDomain,hdNetbios) VALUES (77,'old.example','OLD77')");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
foreach ($ddl447 as $ddl) {
    $pdo->exec($ddl);
}
$directoryColumns = $pdo->query('SHOW COLUMNS FROM hostDirectory')->fetchAll(\PDO::FETCH_COLUMN);
if (array_diff(['hdRejoinConfig', 'hdRejoinState'], $directoryColumns)) {
    throw new \RuntimeException('schema 447 did not add both hostDirectory rejoin columns');
}
$intentColumns = $pdo->query('SHOW COLUMNS FROM directoryPlacementIntent')->fetchAll(\PDO::FETCH_COLUMN);
if (array_diff(array_keys($manifest['tables']['directoryPlacementIntent']['columns']), $intentColumns)) {
    throw new \RuntimeException('schema 447 directoryPlacementIntent columns differ from schema-expected.php');
}
$intentIndexes = $pdo->query('SHOW INDEX FROM directoryPlacementIntent')->fetchAll(\PDO::FETCH_ASSOC);
if (!in_array('PRIMARY', array_column($intentIndexes, 'Key_name'), true)
    || !in_array('uq_directoryPlacementIntent_sid', array_column($intentIndexes, 'Key_name'), true)
    || !in_array('idx_directoryPlacementIntent_host_status', array_column($intentIndexes, 'Key_name'), true)) {
    throw new \RuntimeException('schema 447 directoryPlacementIntent indexes differ from the official DDL');
}

echo "PASS schemas 441-447: module rows preserved; task permission and task 27 migrations applied; hostPerfTests installed; setting value and user fields preserved; three schema 447 statements applied in order\n";
