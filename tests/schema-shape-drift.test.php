<?php
/**
 * SchemaReconciler reports columns and UNIQUE indexes whose shape has drifted.
 *
 * plan()'s four passes ask "is this thing PRESENT?" and create it when it is
 * not. Nothing asks whether something present has the right SHAPE, so drift is
 * invisible -- and both ways it bites are silent (GH-1542):
 *
 *   - a column whose type no longer matches its foreign key's parent is
 *     refused with errno 1005 on every upgrade from now on, and no orphan
 *     scan can explain why, because no row is involved.
 *   - a UNIQUE index that is absent for any reason is never restored, and the
 *     guarantee it carried is simply gone.
 *
 * Both were then OBSERVED, not merely predicted: a real 1.5.10 database
 * upgraded to the current schema came out with `multicastSessions`.`msShutdown`
 * still `enum('0','1')` and `roles`.`rName` missing its UNIQUE index. The first
 * is a renamed `msAnon3` -- the reconciler's rename pass preserves the old type
 * and the enum conversion step had already gone by; the second came from the
 * 1.5 accesscontrol plugin, which created `roles` without it.
 *
 * What is pinned here is that the pass REPORTS and never repairs, and that it
 * is quiet on a database that matches. A pass that reported everything would
 * be worse than none: it would be ignored.
 *
 * Usage: php tests/schema-shape-drift.test.php
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

FogTestHarness::boot('schema-shape-drift');

$t = new FogChecks();

$manifest = [
    'tables' => [
        'hostMAC' => [
            'create' => 'CREATE TABLE IF NOT EXISTS `hostMAC` ( `hmID`'
                . ' int(11) NOT NULL AUTO_INCREMENT, `hmMAC` varchar(59) NOT'
                . ' NULL, PRIMARY KEY (`hmID`), UNIQUE KEY `hmMAC` (`hmMAC`),'
                . ' KEY `idxMac` (`hmMAC`) ) ENGINE=InnoDB',
            'columns' => [
                'hmID' => 'int(11) NOT NULL',
                'hmMAC' => 'varchar(59) NOT NULL',
                'hmDesc' => 'longtext NOT NULL DEFAULT \'\'',
                // No nullability keyword at all -- SQL's own default, which
                // is NULLABLE. Several dozen manifest columns are declared
                // this way, so reading the absence as NOT NULL would report
                // every one of them as drifted on a correct server.
                'hmPending' => 'tinyint(1) DEFAULT NULL',
            ],
        ],
    ],
];

// A live server, described as information_schema would answer for it.
//
// The index answer is filtered HERE, by reading the SQL, rather than being
// handed back whole. Modelling the server as always returning only unique
// indexes would make the NON_UNIQUE filter untestable -- the fixture would
// enforce it whether or not the code did, and the "a plain KEY is not
// reported" check below would be green against code that had dropped it.
$serve = static function (array $cols, array $indexes) {
    $db = FogTestHarness::fakeDb();
    $db->responder = static function ($sql) use ($db, $cols, $indexes) {
        $db->error = false;
        if (false !== strpos($sql, 'STATISTICS')) {
            $uniqueOnly = false !== strpos($sql, 'NON_UNIQUE` = 0');
            $out = [];
            foreach ($indexes as $rows) {
                foreach ((array)$rows as $idx) {
                    if ($uniqueOnly && (int)$idx['NON_UNIQUE'] !== 0) {
                        continue;
                    }
                    $out[] = $idx;
                }
            }
            return $out;
        }
        if (false !== strpos($sql, 'COLUMNS')) {
            return $cols;
        }
        return null;
    };
    return $db;
};

// An index is its COLUMNS, not its name, so the fixture has to carry them --
// and $cols defaults to hmMAC rather than to $name because both indexes the
// manifest declares here cover that one column under two different names,
// which is precisely the shape GH-1566 was about.
$idx = static function ($name, $nonUnique = 0, $cols = ['hmMAC'], $sub = null) {
    $rows = [];
    foreach ((array)$cols as $i => $c) {
        $rows[] = [
            'TABLE_NAME' => 'hostMAC',
            'INDEX_NAME' => $name,
            'COLUMN_NAME' => $c,
            'SEQ_IN_INDEX' => $i + 1,
            'SUB_PART' => $sub,
            'NON_UNIQUE' => $nonUnique,
        ];
    }
    return $rows;
};

$col = static function ($name, $type, $null = 'NO') {
    return [
        'TABLE_NAME' => 'hostMAC',
        'COLUMN_NAME' => $name,
        'COLUMN_TYPE' => $type,
        'IS_NULLABLE' => $null,
    ];
};
$matching = [
    $col('hmID', 'int(11)'),
    $col('hmMAC', 'varchar(59)'),
    $col('hmDesc', 'longtext'),
    $col('hmPending', 'tinyint(1)', 'YES'),
];
$uniqueOk = [$idx('hmMAC'), $idx('idxMac', 1)];

// --- quiet when the server matches -----------------------------------------
$serve($matching, $uniqueOk);
$t->check('a matching server reports no drift', SchemaReconciler::shapeDrift($manifest) === []);
// Named separately from the line above so a regression says which half broke:
// `hmPending` is the column carrying no nullability keyword, and it is quiet
// only if the parser reads that absence as NULLABLE, the way SQL does.
$serve($matching, $uniqueOk);
$t->check(
    'a column declared with no nullability keyword is read as nullable',
    [] === array_filter(
        SchemaReconciler::shapeDrift($manifest),
        static function ($d) {
            return 'hmPending' === ($d['name'] ?? '');
        }
    )
);

// --- a drifted column type -------------------------------------------------
$serve(
    [$col('hmID', 'int(11)'), $col('hmMAC', 'varchar(80)'), $col('hmDesc', 'longtext'), $col('hmPending', 'tinyint(1)', 'YES')],
    $uniqueOk
);
$drift = SchemaReconciler::shapeDrift($manifest);
$t->check('a widened column is reported', count($drift) === 1);
$t->check(
    'the report names the column and both shapes',
    ($drift[0]['name'] ?? '') === 'hmMAC'
        && false !== strpos((string)($drift[0]['expected'] ?? ''), 'varchar(59)')
        && false !== strpos((string)($drift[0]['actual'] ?? ''), 'varchar(80)')
);

// --- a drifted nullability -------------------------------------------------
// Separate from the type: a SET NULL foreign key over a NOT NULL column is
// refused errno 150 with no row involved, which is the exact failure this
// whole area exists to make visible.
$serve(
    [$col('hmID', 'int(11)'), $col('hmMAC', 'varchar(59)', 'YES'), $col('hmDesc', 'longtext'), $col('hmPending', 'tinyint(1)', 'YES')],
    $uniqueOk
);
$drift = SchemaReconciler::shapeDrift($manifest);
$t->check(
    'a column that became nullable is reported',
    count($drift) === 1
        && false !== strpos((string)($drift[0]['expected'] ?? ''), 'NOT NULL')
        && false !== strpos((string)($drift[0]['actual'] ?? ''), 'NULL')
);

// --- a missing UNIQUE index ------------------------------------------------
$serve($matching, [$idx('idxMac', 1)]);
$drift = SchemaReconciler::shapeDrift($manifest);
$t->check(
    'a missing UNIQUE index is reported',
    count($drift) === 1
        && ($drift[0]['kind'] ?? '') === 'unique'
        && ($drift[0]['name'] ?? '') === 'hmMAC'
);

// --- a UNIQUE that degraded to a plain KEY is still reported ---------------
// The case that makes the NON_UNIQUE filter load-bearing rather than
// decorative. Here `hmMAC` EXISTS as an index -- it has simply lost its
// uniqueness, which is the whole guarantee. A pass that asked
// information_schema for indexes by name alone would see `hmMAC` present and
// say nothing, and the drift it exists to find would be the one it missed.
$serve($matching, [$idx('hmMAC', 1), $idx('idxMac', 1)]);
$drift = SchemaReconciler::shapeDrift($manifest);
$t->check(
    'a UNIQUE index degraded to a plain KEY is reported',
    count($drift) === 1
        && ($drift[0]['kind'] ?? '') === 'unique'
        && ($drift[0]['name'] ?? '') === 'hmMAC'
);

// --- and a plain KEY that is genuinely absent is NOT reported --------------
// `idxMac` is declared in the manifest's CREATE statement as a plain KEY. A
// missing one costs speed; a missing UNIQUE costs a guarantee. Only the
// second is a correctness question, and reporting both would bury it.
$serve($matching, [$idx('hmMAC')]);
$t->check(
    'a plain KEY that is absent is not reported as drift',
    SchemaReconciler::shapeDrift($manifest) === []
);

// --- GH-1566: the same name over a DIFFERENT column is not a pass ----------
// The false NEGATIVE, and the reason this was filed. Reading only
// INDEX_NAME, a unique index called `hmMAC` sitting on `hmDesc` satisfied
// the declaration: drift reported clean while nothing whatsoever enforced
// uniqueness of a MAC. Proven against a real database before the fix --
// all three of hostMAC's declared UNIQUE keys were repointed at `hmDesc`,
// `shape` printed 0 differences, and two hosts were then given one MAC.
$serve($matching, [$idx('hmMAC', 0, ['hmDesc']), $idx('idxMac', 1)]);
$drift = SchemaReconciler::shapeDrift($manifest);
$t->check(
    'a UNIQUE index with the right name over the wrong column is reported',
    count($drift) === 1
        && ($drift[0]['kind'] ?? '') === 'unique'
        && ($drift[0]['name'] ?? '') === 'hmMAC'
);
// Reported as what it IS, not as "absent". An index that exists over the
// wrong columns is the more alarming state of the two -- it looks correct
// in every listing that prints names -- so the line has to say so or the
// admin goes looking for a missing index that is right there.
$t->check(
    'the report names the columns on both sides',
    false !== strpos((string)($drift[0]['expected'] ?? ''), 'hmmac')
        && false !== strpos((string)($drift[0]['actual'] ?? ''), 'hmdesc')
);

// --- GH-1566: the guarantee under a different NAME is not drift ------------
// The false POSITIVE, and the one a real server hits without anybody doing
// anything odd: a restore or a hand-run `ADD UNIQUE uniq_mac (hmMAC)`
// leaves the guarantee intact under another label. The manifest describes
// an end state, and that end state is satisfied -- saying otherwise sends
// an admin to fix a database that is already correct.
$serve($matching, [$idx('uniq_mac', 0, ['hmMAC']), $idx('idxMac', 1)]);
$t->check(
    'the declared UNIQUE under a different name is not reported',
    SchemaReconciler::shapeDrift($manifest) === []
);

// --- GH-1566: a PREFIX index is a weaker guarantee -------------------------
// `hmMAC(10)` constrains the first ten characters, not the value, so two
// different MACs sharing a prefix still collide. Compared exactly for that
// reason: treating `col` and `col(10)` as equal would accept a weaker
// index as though it satisfied the declaration.
$serve($matching, [$idx('hmMAC', 0, ['hmMAC'], 10), $idx('idxMac', 1)]);
$drift = SchemaReconciler::shapeDrift($manifest);
$t->check(
    'a prefix index does not satisfy an unprefixed UNIQUE declaration',
    count($drift) === 1 && ($drift[0]['kind'] ?? '') === 'unique'
);

// --- an ABSENT column is not drift -----------------------------------------
// plan() creates those. Reporting them here would double every finding on a
// server that is merely behind, which is every server mid-upgrade.
$serve([$col('hmID', 'int(11)')], $uniqueOk);
$t->check(
    'a column plan() will create is not reported as drift',
    SchemaReconciler::shapeDrift($manifest) === []
);

// --- an absent TABLE is not drift ------------------------------------------
$serve([], []);
$t->check(
    'a table plan() will create is not reported as drift',
    SchemaReconciler::shapeDrift($manifest) === []
);

// --- it never issues a write -----------------------------------------------
// The property the whole change rests on. Repairing is a bigger decision than
// this pass is allowed to make.
$db = $serve(
    [$col('hmID', 'int(11)'), $col('hmMAC', 'varchar(80)'), $col('hmDesc', 'longtext'), $col('hmPending', 'tinyint(1)', 'YES')],
    [$idx('idxMac', 1)]
);
$mark = count($db->log);
SchemaReconciler::reportShapeDrift($manifest);
$wrote = array_filter(
    array_slice($db->log, $mark),
    static function ($sql) {
        return preg_match('/^\s*(ALTER|CREATE|DROP|UPDATE|DELETE|INSERT)/i', $sql);
    }
);
$t->check('the pass issues no write of any kind', $wrote === []);

// --- and it says what it found ---------------------------------------------
$logfile = tempnam(sys_get_temp_dir(), 'shapedrift');
$prev = ini_get('error_log');
ini_set('error_log', $logfile);
SchemaReconciler::reportShapeDrift($manifest);
ini_set('error_log', $prev);
$logged = (string)file_get_contents($logfile);
unlink($logfile);
$t->check(
    'the log names the table, the column and both shapes',
    false !== strpos($logged, 'hostMAC.hmMAC')
        && false !== strpos($logged, 'varchar(80)')
        && false !== strpos($logged, 'varchar(59)')
);
$t->check(
    'the log says nothing was changed',
    false !== stripos($logged, 'reported, not repaired')
);

$t->finish();
