<?php
/**
 * P2-only directory placement intent table for isolated scratch-DB tests.
 * This is not an official migration and must never be loaded on a FOG DB.
 */

if (!function_exists('mj1InstallIntentFixture')) {
function mj1InstallIntentFixture(PDO $db): void
{
    if (getenv('MJ1_DB_ALLOW_RESET') !== '1') {
        throw new \RuntimeException('P2 intent fixture requires explicit scratch reset opt-in');
    }
    $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if (0 !== strpos($database, 'fog_mj1')) {
        throw new \RuntimeException('P2 intent fixture requires a fog_mj1-prefixed scratch database');
    }

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `directoryPlacementIntent` (
    `dpiGUID` BINARY(16) NOT NULL,
    `dpiSID` VARBINARY(184) NOT NULL,
    `dpiHostID` INT UNSIGNED NOT NULL,
    `dpiGeneration` BIGINT UNSIGNED NOT NULL,
    `dpiStatus` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `dpiPayload` MEDIUMTEXT NOT NULL,
    `dpiHash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `dpiResult` VARCHAR(512) NULL,
    `dpiUpdated` DATETIME(6) NOT NULL,
    PRIMARY KEY (`dpiGUID`),
    UNIQUE KEY `uq_directoryPlacementIntent_sid` (`dpiSID`),
    KEY `idx_directoryPlacementIntent_host_status` (`dpiHostID`, `dpiStatus`)
) ENGINE=InnoDB
SQL
    );

    $columns = [];
    foreach ($db->query('SHOW FULL COLUMNS FROM `directoryPlacementIntent`') as $column) {
        $type = strtolower(preg_replace('/\s+/', ' ', (string)$column['Type']));
        $type = preg_replace('/^(int|bigint)\(\d+\)( unsigned)?$/', '$1$2', $type);
        $columns[(string)$column['Field']] = [
            'type' => $type,
            'null' => (string)$column['Null'],
            'collation' => $column['Collation'] === null ? null : strtolower((string)$column['Collation']),
        ];
    }
    $expectedColumns = [
        'dpiGUID' => ['type' => 'binary(16)', 'null' => 'NO', 'collation' => null],
        'dpiSID' => ['type' => 'varbinary(184)', 'null' => 'NO', 'collation' => null],
        'dpiHostID' => ['type' => 'int unsigned', 'null' => 'NO', 'collation' => null],
        'dpiGeneration' => ['type' => 'bigint unsigned', 'null' => 'NO', 'collation' => null],
        'dpiStatus' => ['type' => 'varchar(20)', 'null' => 'NO', 'collation' => 'ascii_bin'],
        'dpiPayload' => ['type' => 'mediumtext', 'null' => 'NO', 'collation' => $columns['dpiPayload']['collation'] ?? null],
        'dpiHash' => ['type' => 'char(64)', 'null' => 'NO', 'collation' => 'ascii_bin'],
        'dpiResult' => ['type' => 'varchar(512)', 'null' => 'YES', 'collation' => $columns['dpiResult']['collation'] ?? null],
        'dpiUpdated' => ['type' => 'datetime(6)', 'null' => 'NO', 'collation' => null],
    ];
    if (array_keys($columns) !== array_keys($expectedColumns)) {
        throw new \RuntimeException('existing directoryPlacementIntent columns differ from the scratch fixture contract');
    }
    foreach ($expectedColumns as $name => $expected) {
        if ($columns[$name] !== $expected) {
            throw new \RuntimeException('existing directoryPlacementIntent column type differs: ' . $name);
        }
    }

    $indexes = [];
    foreach ($db->query('SHOW INDEX FROM `directoryPlacementIntent`') as $index) {
        $name = (string)$index['Key_name'];
        $indexes[$name]['non_unique'] = (int)$index['Non_unique'];
        $indexes[$name]['index_type'] = strtoupper((string)$index['Index_type']);
        $indexes[$name]['has_prefix'] = $index['Sub_part'] !== null;
        $indexes[$name]['columns'][(int)$index['Seq_in_index']] = (string)$index['Column_name'];
    }
    foreach ($indexes as &$index) {
        ksort($index['columns']);
        $index['columns'] = array_values($index['columns']);
    }
    unset($index);
    $indexSpecs = array_values($indexes);
    $expectedIndexSpecs = [
        ['non_unique' => 0, 'index_type' => 'BTREE', 'has_prefix' => false, 'columns' => ['dpiGUID']],
        ['non_unique' => 0, 'index_type' => 'BTREE', 'has_prefix' => false, 'columns' => ['dpiSID']],
        ['non_unique' => 1, 'index_type' => 'BTREE', 'has_prefix' => false, 'columns' => ['dpiHostID', 'dpiStatus']],
    ];
    $sortIndexes = static function (array &$specs): void {
        usort($specs, static function (array $left, array $right): int {
            return strcmp(json_encode($left), json_encode($right));
        });
    };
    $sortIndexes($indexSpecs);
    $sortIndexes($expectedIndexSpecs);
    if ($indexSpecs !== $expectedIndexSpecs) {
        throw new \RuntimeException('existing directoryPlacementIntent indexes differ from the scratch fixture contract');
    }

    $engine = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directoryPlacementIntent'")->fetchColumn();
    if (strtoupper((string)$engine) !== 'INNODB') {
        throw new \RuntimeException('directoryPlacementIntent scratch table must use InnoDB');
    }
    $foreignKeys = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'directoryPlacementIntent' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
    if ((int)$foreignKeys !== 0) {
        throw new \RuntimeException('directoryPlacementIntent scratch table must not have foreign keys');
    }
}
}

// check-php.sh uses this file as auto_prepend_file so the scratch-only table
// exists before each PHP regression. Existing schema reset fixtures do not
// enumerate this P2-only table, and repeated calls validate without truncating.
$mj1IntentDsn = getenv('MJ1_DB_DSN');
if ($mj1IntentDsn !== false && $mj1IntentDsn !== '') {
    $mj1IntentDb = new \PDO(
        $mj1IntentDsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
    mj1InstallIntentFixture($mj1IntentDb);
}
