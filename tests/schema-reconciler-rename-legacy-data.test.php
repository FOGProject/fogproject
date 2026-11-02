<?php
/**
 * A reconciler rename normalizes legacy values the new type cannot hold.
 *
 * A 1.5 database sits at a schema count past 1.6's step 268, so the msAnon3
 * and msAnon4 renames never run there and SchemaReconciler::plan() emits them
 * from the manifest. 1.5 multicastSessions rows hold '' in those VARCHAR
 * columns, and under 1.6's strict sql_mode
 *   CHANGE msAnon3 msShutdown ENUM('0','1')  -> 1265 Data truncated
 *   CHANGE msAnon4 msMaxwait INT(11)         -> 1292 Truncated incorrect INTEGER value
 * halt the update. plan() now emits an UPDATE before such a CHANGE.
 *
 * No database: plan() is pure.
 *
 * Usage: php tests/schema-reconciler-rename-legacy-data.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Db\SchemaReconciler;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-reconciler-rename-legacy-data');

$t = new FogChecks();

$rename = static function ($from, $to, $type) {
    return ['table' => 't', 'from' => $from, 'to' => $to, 'type' => $type];
};
$manifest = ['tables' => ['t' => ['columns' => []]], 'renames' => [
    $rename('eCol', 'eNew', "ENUM('0','1') NOT NULL DEFAULT '0'"),
    $rename('fCol', 'fNew', "ENUM('a','b') NOT NULL"),
    $rename('iCol', 'iNew', 'INT(11) NOT NULL'),
    $rename('uCol', 'uNew', 'BIGINT(20) UNSIGNED NOT NULL'),
    $rename('lCol', 'lNew', 'LONGTEXT NOT NULL'),
    $rename('qCol', 'qNew', "ENUM('it''s','b\\'c') NOT NULL"),
]];
$have = ['t' => ['ecol', 'fcol', 'icol', 'ucol', 'lcol', 'qcol']];
$plan = SchemaReconciler::plan($manifest, $have);
$at = static function ($needle) use (&$plan) {
    foreach ($plan as $i => $sql) {
        if (strpos($sql, $needle) !== false) {
            return $i;
        }
    }
    return -1;
};

$e = $at('UPDATE `t` SET `eCol`');
$t->check('ENUM rename gets an UPDATE', $e >= 0);
$t->check(
    'ENUM UPDATE uses the DEFAULT and the literal list',
    ($plan[$e] ?? '') === "UPDATE `t` SET `eCol` = '0' WHERE `eCol` IS NULL OR `eCol` NOT IN ('0','1')"
);
$t->check(
    'ENUM UPDATE comes right before its CHANGE',
    $e >= 0 && strpos($plan[$e + 1] ?? '', 'CHANGE `eCol` `eNew`') !== false
);
$f = $at('UPDATE `t` SET `fCol`');
$t->check(
    'ENUM without a DEFAULT falls back to the first literal',
    strpos($plan[$f] ?? '', "SET `fCol` = 'a' WHERE") !== false
);
$q = $at('UPDATE `t` SET `qCol`');
$t->check(
    'escaped quotes in ENUM literals are parsed and re-escaped',
    strpos($plan[$q] ?? '', "NOT IN ('it''s','b''c')") !== false
);
$i = $at('UPDATE `t` SET `iCol`');
$t->check(
    'INT rename gets an UPDATE before its CHANGE',
    $i >= 0 && strpos($plan[$i], "= '0' WHERE `iCol` IS NULL OR `iCol` NOT REGEXP") !== false
        && strpos($plan[$i + 1] ?? '', 'CHANGE `iCol` `iNew`') !== false
);
$t->check('BIGINT UNSIGNED rename gets an UPDATE', $at('UPDATE `t` SET `uCol`') >= 0);
$t->check('LONGTEXT rename gets no UPDATE', $at('UPDATE `t` SET `lCol`') === -1);
$t->check('LONGTEXT rename still gets its CHANGE', $at('CHANGE `lCol` `lNew`') >= 0);

// An already-renamed column: the target exists, so neither statement is made.
$plan = SchemaReconciler::plan($manifest, ['t' => ['enew', 'inew']]);
$t->check(
    'an already-renamed column gets neither UPDATE nor CHANGE',
    $at('`eCol`') === -1 && $at('`iCol`') === -1
);

$t->finish();
