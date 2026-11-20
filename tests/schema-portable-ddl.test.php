<?php
/**
 * SchemaReconciler::portableDdl() and isUnsupportedMysql() are pure.
 *
 * The schema and the manifest are captured from MariaDB, which accepts a
 * literal TEXT/BLOB default. MySQL 8.0.13+ refuses it (error 1101) and needs
 * the expression form. portableDdl() rewrites it for a MySQL server and
 * returns MariaDB SQL byte-identical. No database is needed: the server
 * version is a parameter.
 *
 * Usage: php tests/schema-portable-ddl.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Db\SchemaReconciler;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-portable-ddl');

$t = new FogChecks();

$maria = '11.8.9-MariaDB-ubu2604';
$mysql = '8.4.13';

// [label, input, server version, expected output]
$cases = [];

// MariaDB and an unknown version: the SQL is returned byte-identical.
foreach ([
    "`a` text NOT NULL DEFAULT ''",
    "ALTER TABLE `t` ADD COLUMN IF NOT EXISTS `b` text NOT NULL DEFAULT ''",
    "CREATE TABLE IF NOT EXISTS `t` (`a` longtext NOT NULL DEFAULT 'x')",
] as $sql) {
    $cases[] = ['MariaDB unchanged', $sql, $maria, $sql];
    $cases[] = ['empty version unchanged', $sql, '', $sql];
}
$cases[] = ['non-string null unchanged', null, $mysql, null];
$cases[] = ['non-string array unchanged', ['x'], $mysql, ['x']];

// MySQL: TEXT/BLOB literal defaults become expression defaults.
$cases[] = [
    'text', "`a` text NOT NULL DEFAULT ''", $mysql,
    "`a` text NOT NULL DEFAULT ('')",
];
$cases[] = [
    'tinytext', "`a` tinytext NOT NULL DEFAULT ''", $mysql,
    "`a` tinytext NOT NULL DEFAULT ('')",
];
$cases[] = [
    'mediumtext', "`a` mediumtext NOT NULL DEFAULT ''", $mysql,
    "`a` mediumtext NOT NULL DEFAULT ('')",
];
$cases[] = [
    'longtext', "`a` longtext NOT NULL DEFAULT ''", $mysql,
    "`a` longtext NOT NULL DEFAULT ('')",
];
$cases[] = [
    'blob', "`a` blob NOT NULL DEFAULT ''", $mysql,
    "`a` blob NOT NULL DEFAULT ('')",
];
$cases[] = [
    'mediumblob', "`a` mediumblob NOT NULL DEFAULT ''", $mysql,
    "`a` mediumblob NOT NULL DEFAULT ('')",
];
$cases[] = [
    'uppercase TEXT', "`a` TEXT NOT NULL DEFAULT ''", $mysql,
    "`a` TEXT NOT NULL DEFAULT ('')",
];
$cases[] = [
    'uppercase LONGBLOB', "`a` LONGBLOB NOT NULL DEFAULT ''", $mysql,
    "`a` LONGBLOB NOT NULL DEFAULT ('')",
];
$cases[] = [
    'fragment that starts with the type', "text NOT NULL DEFAULT ''", $mysql,
    "text NOT NULL DEFAULT ('')",
];
$cases[] = [
    'doubled-quote literal', "`a` text DEFAULT 'it''s'", $mysql,
    "`a` text DEFAULT ('it''s')",
];
$cases[] = [
    'backslash-escaped quote in the literal',
    "`a` text DEFAULT 'it\\'s'", $mysql,
    "`a` text DEFAULT ('it\\'s')",
];
$cases[] = [
    'DEFAULT after CHARACTER SET ... COLLATE',
    "`a` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL"
    . " DEFAULT ''", $mysql,
    "`a` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL"
    . " DEFAULT ('')",
];
$cases[] = [
    'an expression default is not wrapped twice',
    "`a` text NOT NULL DEFAULT ('')", $mysql,
    "`a` text NOT NULL DEFAULT ('')",
];
$cases[] = [
    'only the TEXT column of a mixed table',
    "`a` varchar(10) NOT NULL DEFAULT '', `b` text NOT NULL DEFAULT ''",
    $mysql,
    "`a` varchar(10) NOT NULL DEFAULT '', `b` text NOT NULL DEFAULT ('')",
];

// MySQL: things that must NOT change.
$cases[] = [
    'varchar default left alone',
    "`a` varchar(10) NOT NULL DEFAULT ''", $mysql,
    "`a` varchar(10) NOT NULL DEFAULT ''",
];
$cases[] = [
    'a column NAMED text is left alone',
    "`text` int NOT NULL DEFAULT '0'", $mysql,
    "`text` int NOT NULL DEFAULT '0'",
];
$cases[] = [
    'CREATE TABLE IF NOT EXISTS left alone',
    'CREATE TABLE IF NOT EXISTS `t` (`a` int)', $mysql,
    'CREATE TABLE IF NOT EXISTS `t` (`a` int)',
];

// MySQL: ADD ... IF NOT EXISTS loses the guard.
foreach ([
    'ADD COLUMN' => ['`b` int', '`b` int'],
    'ADD INDEX' => ['`k` (`b`)', '`k` (`b`)'],
    'ADD KEY' => ['`k` (`b`)', '`k` (`b`)'],
    'ADD UNIQUE INDEX' => ['`k` (`b`)', '`k` (`b`)'],
    'ADD UNIQUE KEY' => ['`k` (`b`)', '`k` (`b`)'],
] as $verb => $rest) {
    $cases[] = [
        $verb . ' IF NOT EXISTS',
        'ALTER TABLE `t` ' . $verb . ' IF NOT EXISTS ' . $rest[0], $mysql,
        'ALTER TABLE `t` ' . $verb . ' ' . $rest[1],
    ];
}

$cases[] = [
    'lowercase add column if not exists',
    'alter table `t` add column if not exists `b` int', $mysql,
    'alter table `t` add column `b` int',
];

foreach ($cases as $c) {
    $t->check(
        'portableDdl: ' . $c[0],
        SchemaReconciler::portableDdl($c[1], $c[2]) === $c[3]
    );
}

$versions = [
    '8.0.12' => true,
    '8.0.13' => false,
    '9.7.4-0ubuntu0.26.10.1' => false,
    '11.8.9-MariaDB-ubu2604' => false,
    '5.7.44' => true,
    '' => false,
];
foreach ($versions as $version => $expect) {
    $t->check(
        sprintf(
            'isUnsupportedMysql(%s) is %s',
            $version,
            $expect ? 'true' : 'false'
        ),
        SchemaReconciler::isUnsupportedMysql($version) === $expect
    );
}

$t->finish();
