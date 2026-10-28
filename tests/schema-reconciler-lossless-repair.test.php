<?php
/**
 * SchemaReconciler repairs a column type only when no value can be lost.
 *
 * GH-2000. A 1.5.x server carries a schema count that lands on a different
 * 1.6 step, so the updater skips the steps below it. A skipped step that
 * changed an existing column's type is lost: users.uName stayed
 * varchar(40) NOT NULL on an upgraded server, where a fresh install has
 * varchar(255) NULL. planWidenings() turns that kind of drift into a MODIFY,
 * and leaves every change that could truncate a value or refuse a row to
 * reportShapeDrift().
 *
 * No database: planWidenings() is pure.
 *
 * Usage: php tests/schema-reconciler-lossless-repair.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Db\SchemaReconciler;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-reconciler-lossless-repair');

$t = new FogChecks();

$manifest = ['tables' => ['users' => ['columns' => [
    'uName' => 'varchar(255) DEFAULT NULL',
    'uCode' => 'varchar(10) NOT NULL',
    'uFlag' => 'tinyint(1) NOT NULL DEFAULT 0',
    'uId' => 'int(11) NOT NULL',
    'uBig' => 'bigint(20) NOT NULL',
    'uSigned' => 'int(11) NOT NULL',
    'uChar' => 'char(8) NOT NULL',
    'uText' => 'longtext NOT NULL',
]]]];
$charsets = ['users' => ['uname' => ['utf8mb3', 'utf8mb3_general_ci']]];

$col = static function ($name, $expected, $actual) {
    return ['table' => 'users', 'kind' => 'column', 'name' => $name,
        'expected' => $expected, 'actual' => $actual];
};
$plan = static function (array $drift) use ($manifest, $charsets) {
    return SchemaReconciler::planWidenings($drift, $manifest, $charsets);
};

// --- the GH-2000 case: longer and nullable ---------------------------------
$sql = $plan([$col('uName', 'varchar(255) NULL', 'varchar(40) NOT NULL')]);
$t->check('varchar(40) NOT NULL -> varchar(255) NULL is repaired', count($sql) === 1);
$t->check(
    'the MODIFY keeps the column charset and the manifest default',
    ($sql[0] ?? '') === 'ALTER TABLE `users` MODIFY `uName` varchar(255)'
        . ' CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL'
);

// --- lossless, each on its own ------------------------------------------------
$t->check('a longer char is repaired', count($plan([$col('uChar', 'char(8) NOT NULL', 'char(4) NOT NULL')])) === 1);
$t->check('a wider integer is repaired', count($plan([$col('uBig', 'bigint(20) NOT NULL', 'int(11) NOT NULL')])) === 1);
$t->check('NOT NULL -> NULL alone is repaired', count($plan([$col('uName', 'varchar(255) NULL', 'varchar(255) NOT NULL')])) === 1);

// --- could lose data: left alone --------------------------------------------
$t->check('a shorter varchar is not repaired', $plan([$col('uCode', 'varchar(10) NOT NULL', 'varchar(40) NOT NULL')]) === []);
$t->check('NULL -> NOT NULL is not repaired', $plan([$col('uCode', 'varchar(10) NOT NULL', 'varchar(10) NULL')]) === []);
$t->check('longer but NULL -> NOT NULL is not repaired', $plan([$col('uCode', 'varchar(10) NOT NULL', 'varchar(5) NULL')]) === []);
$t->check('enum -> tinyint is not repaired', $plan([$col('uFlag', 'tinyint(1) NOT NULL', "enum('0','1') NOT NULL")]) === []);
$t->check('a narrower integer is not repaired', $plan([$col('uId', 'int(11) NOT NULL', 'bigint(20) NOT NULL')]) === []);
$t->check('unsigned -> signed is not repaired', $plan([$col('uSigned', 'int(11) NOT NULL', 'int(10) unsigned NOT NULL')]) === []);
$t->check('varchar -> longtext is not repaired', $plan([$col('uText', 'longtext NOT NULL', 'varchar(40) NOT NULL')]) === []);
$t->check('char -> varchar is not repaired', $plan([$col('uChar', 'char(8) NOT NULL', 'varchar(4) NOT NULL')]) === []);

// --- no change at all ---------------------------------------------------------
$t->check('int(11) against int (MySQL 8) is not a change', $plan([$col('uId', 'int(11) NOT NULL', 'int NOT NULL')]) === []);
$t->check('a UNIQUE index finding is not a column repair', $plan([
    ['table' => 'users', 'kind' => 'unique', 'name' => 'name', 'expected' => 'UNIQUE(uname)', 'actual' => 'absent'],
]) === []);
$t->check('a column the manifest does not declare is skipped', $plan([$col('uGone', 'int(11) NOT NULL', 'int(4) NOT NULL')]) === []);

// --- AUTO_INCREMENT survives the MODIFY -------------------------------------
// The manifest's column declaration does not carry AUTO_INCREMENT; only its
// CREATE does. Widening modules.id (mediumint -> int) from the declaration
// alone dropped it, and step 419's INSERT then failed with 1364.
$sql = SchemaReconciler::planWidenings(
    [$col('uId', 'int(11) NOT NULL', 'mediumint(9) NOT NULL')],
    $manifest,
    ['users' => ['uid' => ['', '', true]]]
);
$t->check(
    'an AUTO_INCREMENT column keeps AUTO_INCREMENT',
    ($sql[0] ?? '') === 'ALTER TABLE `users` MODIFY `uId` int(11) NOT NULL AUTO_INCREMENT'
);
$sql = SchemaReconciler::planWidenings(
    [$col('uId', 'int(11) NOT NULL', 'mediumint(9) NOT NULL')],
    $manifest,
    ['users' => ['uid' => ['', '', false]]]
);
$t->check('a plain column does not gain AUTO_INCREMENT', ($sql[0] ?? '') === 'ALTER TABLE `users` MODIFY `uId` int(11) NOT NULL');

// --- without a charset reading, no charset clause ---------------------------
$sql = SchemaReconciler::planWidenings([$col('uChar', 'char(8) NOT NULL', 'char(4) NOT NULL')], $manifest, []);
$t->check('no live charset: the manifest declaration alone', ($sql[0] ?? '') === 'ALTER TABLE `users` MODIFY `uChar` char(8) NOT NULL');

// --- reconcile() runs the repair before the foreign keys ----------------------
$src = file_get_contents(dirname(__DIR__) . '/packages/web/src/Db/SchemaReconciler.php');
$t->check(
    'reconcile() calls repairShape() before applyConstraints() and reportShapeDrift()',
    (bool)preg_match('/self::repairShape\(\$manifest\);.*self::applyConstraints\(null, \$map\);.*self::reportShapeDrift\(\$manifest\);/s', $src)
);

$t->finish();
