<?php
/**
 * Schema 448 on a real server: the directoryPlacementIntent table that the
 * real step builds has the shape the manifest and the code expect (column
 * types, byte-exact status and hash collations, keys, InnoDB, no foreign key),
 * and running the step again keeps an existing row.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-intent-schema-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory intent schema DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-intent-schema-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];
$t = new FogChecks();

$columns = [];
foreach ($pdo->query('SHOW FULL COLUMNS FROM `directoryPlacementIntent`')->fetchAll(\PDO::FETCH_ASSOC) as $column) {
    $columns[(string)$column['Field']] = [
        'type' => strtolower((string)$column['Type']),
        'null' => (string)$column['Null'],
        'collation' => null === $column['Collation'] ? null : strtolower((string)$column['Collation'])
    ];
}
$t->check(
    'the table has the manifest columns in order',
    array_keys($columns) === array_keys($manifest['tables']['directoryPlacementIntent']['columns'])
);
$t->check(
    'identity columns are binary and the generation is unsigned',
    'binary(16)' === $columns['dpiGUID']['type'] && 'varbinary(184)' === $columns['dpiSID']['type']
        && preg_match('/^bigint(?:\(\d+\))? unsigned$/', $columns['dpiGeneration']['type'])
        && preg_match('/^int(?:\(\d+\))?$/', $columns['dpiHostID']['type'])
);
$t->check(
    'status and hash compare byte for byte',
    'utf8mb3_bin' === $columns['dpiStatus']['collation'] && 'utf8mb3_bin' === $columns['dpiHash']['collation']
);
$t->check(
    'only the result may be NULL',
    'YES' === $columns['dpiResult']['null']
        && ['dpiResult'] === array_keys(array_filter($columns, static function ($c) {
            return 'YES' === $c['null'];
        }))
);

$keys = [];
foreach ($pdo->query('SHOW INDEX FROM `directoryPlacementIntent`')->fetchAll(\PDO::FETCH_ASSOC) as $index) {
    $keys[$index['Key_name']]['unique'] = !(int)$index['Non_unique'];
    $keys[$index['Key_name']]['columns'][(int)$index['Seq_in_index']] = $index['Column_name'];
}
$shapes = [];
foreach ($keys as $name => $key) {
    ksort($key['columns']);
    $shapes[] = ($key['unique'] ? 'UNIQUE:' : 'INDEX:') . implode(',', $key['columns']);
}
sort($shapes);
$t->check(
    'the keys are the GUID primary key, a unique SID and a host-status index',
    ['INDEX:dpiHostID,dpiStatus', 'UNIQUE:dpiGUID', 'UNIQUE:dpiSID'] === $shapes
);

/** One value from a statement, with its cursor closed (the connection is unbuffered). */
$scalar = static function ($sql) use ($pdo) {
    $q = $pdo->query($sql);
    $value = $q->fetchColumn();
    $q->closeCursor();
    return $value;
};
$engine = (string)$scalar("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directoryPlacementIntent'");
$foreign = (int)$scalar("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directoryPlacementIntent' AND REFERENCED_TABLE_NAME IS NOT NULL");
$t->check('the table is InnoDB with no foreign key', 0 === strcasecmp('InnoDB', $engine) && 0 === $foreign);

// A second run of the real step must keep what is there.
$guid = random_bytes(16);
$insert = $pdo->prepare('INSERT INTO directoryPlacementIntent (dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult,dpiUpdated) VALUES (?,?,?,?,?,?,?,NULL,UTC_TIMESTAMP(6))');
$insert->execute([$guid, 'S-1-5-21-9-9', 7, 9, 'UNKNOWN', '{"keep":1}', str_repeat('a', 64)]);
$steps = fogCollectSchemaSteps(dirname(__DIR__) . '/packages/web/commons/schema.php', $GLOBALS['database'], 448);
$pdo->exec($steps[447][0]);
$kept = $pdo->prepare('SELECT dpiStatus,dpiGeneration,dpiPayload FROM directoryPlacementIntent WHERE dpiGUID=?');
$kept->execute([$guid]);
$row = $kept->fetch(\PDO::FETCH_ASSOC);
$kept->closeCursor();
$t->check(
    'running the step again keeps an existing row',
    is_array($row) && 'UNKNOWN' === $row['dpiStatus'] && 9 === (int)$row['dpiGeneration'] && '{"keep":1}' === $row['dpiPayload']
);

// The status column is case-sensitive, so a differently cased value is a different value.
$case = $pdo->prepare("SELECT COUNT(*) FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiStatus='unknown'");
$case->execute([$guid]);
$sameCase = (int)$case->fetchColumn();
$case->closeCursor();
$t->check('a status of different case does not match', 0 === $sameCase);
$pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?')->execute([$guid]);

$t->finish();
