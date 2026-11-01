<?php

/** Scratch-only shape and row-preservation check for the P2 intent fixture. */
if (getenv('MJ1_DB_DSN') === false) {
    echo "SKIP P2 intent schema: no scratch DB\n";
    exit(0);
}
if (getenv('MJ1_DB_ALLOW_RESET') !== '1') {
    throw new \RuntimeException('P2 intent schema test requires scratch reset opt-in');
}

require_once __DIR__ . '/fixtures/u2-p2-intent-schema.php';
$db = new \PDO(
    getenv('MJ1_DB_DSN'),
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if (0 !== strpos($database, 'fog_mj1')) {
    throw new \RuntimeException('P2 intent schema test requires a fog_mj1-prefixed scratch DB');
}

mj1InstallIntentFixture($db);
$columns = [];
foreach ($db->query('SHOW FULL COLUMNS FROM `directoryPlacementIntent`') as $column) {
    $columns[(string)$column['Field']] = [
        'type' => strtolower((string)$column['Type']),
        'null' => (string)$column['Null'],
        'collation' => $column['Collation'] === null ? null : strtolower((string)$column['Collation']),
    ];
}
$expectedNames = [
    'dpiGUID', 'dpiSID', 'dpiHostID', 'dpiGeneration', 'dpiStatus',
    'dpiPayload', 'dpiHash', 'dpiResult', 'dpiUpdated',
];
if (array_keys($columns) !== $expectedNames) {
    throw new \RuntimeException('P2 intent table columns/order differ from the fixture contract');
}
if ($columns['dpiGUID']['type'] !== 'binary(16)' || $columns['dpiGUID']['null'] !== 'NO'
    || $columns['dpiSID']['type'] !== 'varbinary(184)' || $columns['dpiSID']['null'] !== 'NO'
    || !preg_match('/^int(?:\(\d+\))? unsigned$/', $columns['dpiHostID']['type'])
    || !preg_match('/^bigint(?:\(\d+\))? unsigned$/', $columns['dpiGeneration']['type'])
    || $columns['dpiStatus']['type'] !== 'varchar(20)' || $columns['dpiStatus']['collation'] !== 'ascii_bin'
    || $columns['dpiPayload']['type'] !== 'mediumtext' || $columns['dpiPayload']['null'] !== 'NO'
    || $columns['dpiHash']['type'] !== 'char(64)' || $columns['dpiHash']['collation'] !== 'ascii_bin'
    || $columns['dpiResult']['type'] !== 'varchar(512)' || $columns['dpiResult']['null'] !== 'YES'
    || $columns['dpiUpdated']['type'] !== 'datetime(6)' || $columns['dpiUpdated']['null'] !== 'NO') {
    throw new \RuntimeException('P2 intent table column definition mismatch');
}
$engine = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directoryPlacementIntent'")->fetchColumn();
if (strtoupper((string)$engine) !== 'INNODB') {
    throw new \RuntimeException('P2 intent table is not InnoDB');
}
$foreignKeys = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directoryPlacementIntent' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
if ((int)$foreignKeys !== 0) {
    throw new \RuntimeException('P2 intent table unexpectedly has a foreign key');
}

$guid = random_bytes(16);
$sid = 'u2-p2-fixture-' . bin2hex(random_bytes(12));
$payload = '{"fixture":"preserve-existing-row"}';
$hash = str_repeat('a', 64);
try {
    $insert = $db->prepare('INSERT INTO `directoryPlacementIntent` (`dpiGUID`,`dpiSID`,`dpiHostID`,`dpiGeneration`,`dpiStatus`,`dpiPayload`,`dpiHash`,`dpiResult`,`dpiUpdated`) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP(6))');
    $insert->bindValue(1, $guid, \PDO::PARAM_LOB);
    $insert->bindValue(2, $sid, \PDO::PARAM_STR);
    $insert->bindValue(3, 2147483000, \PDO::PARAM_INT);
    $insert->bindValue(4, 9, \PDO::PARAM_INT);
    $insert->bindValue(5, 'unknown', \PDO::PARAM_STR);
    $insert->bindValue(6, $payload, \PDO::PARAM_STR);
    $insert->bindValue(7, $hash, \PDO::PARAM_STR);
    $insert->bindValue(8, null, \PDO::PARAM_NULL);
    $insert->execute();

    // Re-running the installer must validate, not erase existing intent rows.
    mj1InstallIntentFixture($db);
    $read = $db->prepare('SELECT `dpiGUID`,`dpiSID`,`dpiGeneration`,`dpiStatus`,`dpiPayload`,`dpiHash`,`dpiResult` FROM `directoryPlacementIntent` WHERE `dpiGUID` = ?');
    $read->bindValue(1, $guid, \PDO::PARAM_LOB);
    $read->execute();
    $row = $read->fetch(\PDO::FETCH_ASSOC);
    if (!is_array($row) || !hash_equals($guid, (string)$row['dpiGUID'])
        || $row['dpiSID'] !== $sid || (int)$row['dpiGeneration'] !== 9
        || $row['dpiStatus'] !== 'unknown' || $row['dpiPayload'] !== $payload
        || $row['dpiHash'] !== $hash || $row['dpiResult'] !== null) {
        throw new \RuntimeException('P2 intent fixture did not preserve the existing row');
    }
} finally {
    $delete = $db->prepare('DELETE FROM `directoryPlacementIntent` WHERE `dpiGUID` = ?');
    $delete->bindValue(1, $guid, \PDO::PARAM_LOB);
    $delete->execute();
}

echo "PASS P2 intent scratch schema and preservation\n";
