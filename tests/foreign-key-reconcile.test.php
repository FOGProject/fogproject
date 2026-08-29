<?php
/**
 * SchemaReconciler's constraint pass plans the right ALTERs, and only those.
 *
 * tests/foreign-key-map.test.php gates the MAP -- that every id column is
 * classified and that an enabled entry is one the database could accept.
 * This gates the CODE that turns the map into statements, which is a
 * different thing and fails in different ways.
 *
 * Four behaviors matter enough to pin, all of them silent when wrong:
 *
 *   - a DISABLED entry plans nothing. This is the whole phasing mechanism.
 *     If `enabled` were ever ignored, one release would land all 87
 *     constraints at once against unswept data, most would fail 1452, and
 *     the result would be a long log nobody asked for -- exactly the
 *     un-reviewable single migration this work exists to avoid.
 *   - a constraint the database ALREADY has plans nothing. reconcile() runs
 *     at the end of every update, so a pass that re-planned would return
 *     errno 121 on every upgrade forever and bury real failures in noise.
 *   - a relationship whose child or parent table is ABSENT plans nothing.
 *     Plugin tables are created on install and dropped on uninstall, so
 *     "not there" is the normal state for most of this map on most servers
 *     (ADR 0031 decision 8). Planning it would put a permanent failure in
 *     the log of every server that does not run that plugin.
 *   - the statement itself carries the declared ON DELETE action and
 *     ON UPDATE RESTRICT. The action is per relationship, not per class --
 *     tasks.taskHostID is CASCADE while tasks.taskStateID is RESTRICT -- so
 *     a pass that derived it from the class would quietly refuse host
 *     deletions that must succeed.
 *
 * planConstraints() is pure, so all of this is asserted without a database.
 *
 * Usage: php tests/foreign-key-reconcile.test.php
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

FogTestHarness::boot('foreign-key-reconcile');

$t = new FogChecks();

// A structure snapshot in the shape snapshot() returns: lowercased table =>
// lowercased columns.
$have = [
    'hosts' => ['hostid', 'hostname', 'hostimage'],
    'groupmembers' => ['gmid', 'gmhostid', 'gmgroupid'],
    'groups' => ['groupid', 'groupname'],
    'tasks' => ['taskid', 'taskhostid', 'taskstateid'],
    'taskstates' => ['tsid', 'tsname'],
    'images' => ['imageid', 'imagename'],
];

$rel = static function (array $over = []) {
    return $over + [
        'child' => 'groupMembers',
        'column' => 'gmHostID',
        'parent' => 'hosts',
        'pcolumn' => 'hostID',
        'class' => 'junction',
        'action' => 'CASCADE',
        'enabled' => true,
    ];
};

// --- disabled plans nothing -------------------------------------------------
$plan = SchemaReconciler::planConstraints([$rel(['enabled' => false])], $have, []);
$t->check('a disabled relationship plans nothing', $plan === []);

$plan = SchemaReconciler::planConstraints([$rel(['enabled' => true])], $have, []);
$t->check('an enabled relationship plans one statement', count($plan) === 1);

// --- the statement itself ---------------------------------------------------
$sql = $plan[0] ?? '';
$t->check(
    'names the constraint fk_<child>_<column>',
    strpos($sql, 'ADD CONSTRAINT `fk_groupMembers_gmHostID`') !== false
);
$t->check(
    'targets the declared parent column',
    strpos($sql, 'REFERENCES `hosts` (`hostID`)') !== false
);
$t->check('carries the declared ON DELETE', strpos($sql, 'ON DELETE CASCADE') !== false);
$t->check('always ON UPDATE RESTRICT', strpos($sql, 'ON UPDATE RESTRICT') !== false);

// The action is per relationship. A pass that derived it from `class` would
// pass every check above and still be wrong here.
$plan = SchemaReconciler::planConstraints(
    [$rel(['child' => 'tasks', 'column' => 'taskStateID', 'parent' => 'taskStates',
        'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT'])],
    $have,
    []
);
$t->check(
    'a RESTRICT relationship plans RESTRICT, not its class default',
    isset($plan[0]) && strpos($plan[0], 'ON DELETE RESTRICT') !== false
);

$plan = SchemaReconciler::planConstraints(
    [$rel(['child' => 'hosts', 'column' => 'hostImage', 'parent' => 'images',
        'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'SET NULL'])],
    $have,
    []
);
$t->check(
    'a SET NULL relationship plans SET NULL',
    isset($plan[0]) && strpos($plan[0], 'ON DELETE SET NULL') !== false
);

// --- action none ------------------------------------------------------------
$plan = SchemaReconciler::planConstraints(
    [$rel(['class' => 'audit', 'action' => 'none'])],
    $have,
    []
);
$t->check('action `none` plans nothing even when enabled', $plan === []);

// --- idempotence ------------------------------------------------------------
$fk = static function (array $over = []) {
    return $over + [
        'parent' => 'hosts',
        'pcolumn' => 'hostid',
        'action' => 'CASCADE',
    ];
};

$plan = SchemaReconciler::planConstraints(
    [$rel()],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()]
);
$t->check('an existing constraint is not re-planned', $plan === []);

// --- correcting a constraint the database already holds ---------------------
//
// The name does not encode ON DELETE, so a pass that decided by name alone
// left the old rule in place forever. That is not hypothetical: step 384
// shipped nfsGroupMembers.ngmGroupID as CASCADE and it had to become SET
// NULL. Each of these three checks fails against a name-only comparison.
$plan = SchemaReconciler::planConstraints(
    [$rel(['action' => 'SET NULL'])],
    $have,
    ['fk_groupmembers_gmhostid' => $fk(['action' => 'CASCADE'])]
);
$t->check(
    'a changed action drops then re-adds',
    count($plan) === 2
        && strpos($plan[0], 'DROP FOREIGN KEY `fk_groupMembers_gmHostID`') !== false
        && strpos($plan[1], 'ON DELETE SET NULL') !== false
);

$plan = SchemaReconciler::planConstraints(
    [$rel(['parent' => 'groups', 'pcolumn' => 'groupID'])],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()]
);
$t->check(
    'a changed parent drops then re-adds',
    count($plan) === 2
        && strpos($plan[0], 'DROP FOREIGN KEY') !== false
        && strpos($plan[1], 'REFERENCES `groups` (`groupID`)') !== false
);

$plan = SchemaReconciler::planConstraints(
    [$rel(['pcolumn' => 'hostName'])],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()]
);
$t->check(
    'a changed parent column drops then re-adds',
    count($plan) === 2 && strpos($plan[1], '(`hostName`)') !== false
);

// --- retiring a constraint --------------------------------------------------
$plan = SchemaReconciler::planConstraints(
    [$rel(['enabled' => false])],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()]
);
$t->check(
    'a disabled relationship the database still holds is dropped, not re-added',
    count($plan) === 1
        && strpos($plan[0], 'DROP FOREIGN KEY `fk_groupMembers_gmHostID`') !== false
);

$plan = SchemaReconciler::planConstraints(
    [$rel(['action' => 'none'])],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()]
);
$t->check('action `none` drops one the database still holds', count($plan) === 1);

// A constraint nothing in the map names is never touched, whatever it is
// called. Dropping by name is only safe because the name is generated.
$plan = SchemaReconciler::planConstraints(
    [$rel()],
    $have,
    [
        'fk_groupmembers_gmhostid' => $fk(),
        'admins_own_constraint' => $fk(['action' => 'RESTRICT']),
        'fk_groupmembers_gmhostid_v2' => $fk(['action' => 'RESTRICT']),
    ]
);
$t->check(
    'a constraint the map does not name is left alone',
    $plan === []
);

$plan = SchemaReconciler::planConstraints([$rel(), $rel()], $have, []);
$t->check('the same relationship twice plans one statement', count($plan) === 1);

// --- absent tables and columns ----------------------------------------------
$plan = SchemaReconciler::planConstraints(
    [$rel(['child' => 'locationAssoc', 'column' => 'laHostID'])],
    $have,
    []
);
$t->check('an absent child table plans nothing', $plan === []);

$plan = SchemaReconciler::planConstraints(
    [$rel(['parent' => 'ou', 'pcolumn' => 'ouID'])],
    $have,
    []
);
$t->check('an absent parent table plans nothing', $plan === []);

$plan = SchemaReconciler::planConstraints(
    [$rel(['column' => 'gmNotAColumn'])],
    $have,
    []
);
$t->check('an absent child column plans nothing', $plan === []);

$plan = SchemaReconciler::planConstraints(
    [$rel(['pcolumn' => 'notAColumn'])],
    $have,
    []
);
$t->check('an absent parent column plans nothing', $plan === []);

// --- the shipped map --------------------------------------------------------
// Whatever is enabled today must plan cleanly against a structure that has
// every core table, and nothing that is still disabled may appear.
$map = require dirname(__DIR__) . '/packages/web/commons/schema-constraints.php';
$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
$full = [];
foreach ($manifest['tables'] as $table => $def) {
    $full[strtolower($table)] = array_map(
        'strtolower',
        array_keys($def['columns'] ?? [])
    );
}
$plan = SchemaReconciler::planConstraints($map, $full, []);
$enabled = array_filter($map, static function ($r) use ($full) {
    return !empty($r['enabled']) && ($r['action'] ?? 'none') !== 'none'
        && isset($full[strtolower($r['child'])])
        && isset($full[strtolower($r['parent'])]);
});
$t->check(
    sprintf(
        'the shipped map plans exactly its enabled core entries (%d)',
        count($enabled)
    ),
    count($plan) === count($enabled)
);

// --- reconcile() actually REACHES the constraint pass ----------------------
//
// The bug this pins was live for the length of one lab run and is invisible
// from planConstraints(): reconcile() returned early when the STRUCTURAL plan
// was empty, and an up-to-date database -- missing no table and no column --
// is the normal case. So on almost every server the constraint pass would
// never have executed, reconcile() would have returned true, and the
// constraints would simply never have appeared. Nothing would have said so.
//
// Driven through the harness's fake database, which logs every statement.
$db = FogTestHarness::fakeDb();
$db->responder = static function ($sql) {
    // The structure read. Every table and column present, so plan() has
    // nothing structural to do -- which is exactly the state that used to
    // skip the constraint pass.
    if (strpos($sql, 'information_schema') !== false
        && strpos($sql, 'COLUMNS') !== false
    ) {
        return [
            ['TABLE_NAME' => 'groupMembers', 'COLUMN_NAME' => 'gmID'],
            ['TABLE_NAME' => 'groupMembers', 'COLUMN_NAME' => 'gmHostID'],
            ['TABLE_NAME' => 'hosts', 'COLUMN_NAME' => 'hostID'],
        ];
    }
    // No constraints yet.
    if (strpos($sql, 'REFERENTIAL_CONSTRAINTS') !== false) {
        return [];
    }
    return null;
};

$manifest = [
    'tables' => [
        'groupMembers' => [
            'create' => 'CREATE TABLE IF NOT EXISTS `groupMembers` (`gmID` int(11))',
            'columns' => ['gmID' => 'int(11)', 'gmHostID' => 'int(11)'],
        ],
        'hosts' => [
            'create' => 'CREATE TABLE IF NOT EXISTS `hosts` (`hostID` int(11))',
            'columns' => ['hostID' => 'int(11)'],
        ],
    ],
];

// Marked rather than cleared. Assigning [] here would let static analysis
// narrow the property to an empty array and prove the assertion below can
// never hold -- a green check for a reason that has nothing to do with the
// code under test.
$mark = count($db->log);
$result = SchemaReconciler::reconcile($manifest, [$rel()]);
$since = array_slice($db->log, $mark);

$altered = array_filter($since, static function ($sql) {
    return strpos($sql, 'ADD CONSTRAINT `fk_groupMembers_gmHostID`') !== false;
});
$t->check(
    'reconcile() runs the constraint pass when the structural plan is empty',
    count($altered) === 1
);
$t->check('reconcile() still reports success', $result === true);

// And the structural plan really was empty, or the check above proved nothing.
$structural = array_filter($since, static function ($sql) {
    return stripos($sql, 'ADD COLUMN') !== false
        || stripos($sql, 'CREATE TABLE') !== false;
});
$t->check(
    'the structural plan was genuinely empty for that run',
    $structural === []
);

// --- applyConstraints() never fails the update ------------------------------
//
// The property the whole phased rollout rests on, and the one that cannot be
// seen from planConstraints(). ADD CONSTRAINT validates existing rows, so a
// server holding an orphan this release did not anticipate answers 1452. If
// that reached the updater as an error the run would abort and leave the
// server on ?node=schema over data that is otherwise intact, with no way
// forward from the browser -- so a refusal is collected and reported and the
// update carries on.
//
// Driven by making the fake database FAIL the ALTER, which is the only way to
// tell the reporting path from the applying one: both return true.
$db = FogTestHarness::fakeDb();
$structure = [
    ['TABLE_NAME' => 'groupMembers', 'COLUMN_NAME' => 'gmHostID'],
    ['TABLE_NAME' => 'hosts', 'COLUMN_NAME' => 'hostID'],
];
$db->responder = static function ($sql) use ($db, $structure) {
    if (strpos($sql, 'REFERENTIAL_CONSTRAINTS') !== false) {
        $db->error = false;
        return [];
    }
    if (strpos($sql, 'information_schema') !== false
        && strpos($sql, 'COLUMNS') !== false
    ) {
        $db->error = false;
        return $structure;
    }
    if (stripos($sql, 'ADD CONSTRAINT') !== false) {
        // Shaped like PDODB's: the message, then the statement and its
        // params on following lines. applyConstraints() must keep only the
        // first line, or one refusal becomes a screenful of log.
        $db->error = "SQLSTATE[23000]: Integrity constraint violation: 1452"
            . " Cannot add or update a child row\nQuery: $sql\nParams: none";
        $db->errorCode = '23000';
        return [];
    }
    return null;
};

$result = SchemaReconciler::applyConstraints([$rel()]);
$failures = SchemaReconciler::constraintFailures();

$t->check('applyConstraints() returns true even when a constraint is refused', $result === true);
$t->check('the refusal is recorded', count($failures) === 1);
$t->check(
    'the refusal names the constraint, not the statement',
    ($failures[0]['name'] ?? '') === 'fk_groupMembers_gmHostID'
);
$t->check(
    'the reason is the first line only',
    strpos((string)($failures[0]['reason'] ?? ''), 'Query:') === false
        && strpos((string)($failures[0]['reason'] ?? ''), '1452') !== false
);

// The other arm. Without it "returns true" proves nothing: it returns true
// either way, and only the failure list separates the two.
$db->error = false;
$db->responder = static function ($sql) use ($db, $structure) {
    $db->error = false;
    if (strpos($sql, 'REFERENTIAL_CONSTRAINTS') !== false) {
        return [];
    }
    if (strpos($sql, 'information_schema') !== false
        && strpos($sql, 'COLUMNS') !== false
    ) {
        return $structure;
    }
    return [];
};
$mark = count($db->log);
$result = SchemaReconciler::applyConstraints([$rel()]);
$since = array_slice($db->log, $mark);
$t->check('a clean run records no failure', SchemaReconciler::constraintFailures() === []);
$t->check('a clean run still returns true', $result === true);
$t->check(
    'a clean run really issued the ALTER',
    count(array_filter($since, static function ($sql) {
        return strpos($sql, 'ADD CONSTRAINT `fk_groupMembers_gmHostID`') !== false;
    })) === 1
);

$t->finish();
