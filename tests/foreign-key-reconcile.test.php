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
 *   - a SET NULL declared over a NOT NULL column is SKIPPED rather than
 *     attempted. InnoDB refuses it errno 150 with no row involved, so the
 *     orphan scanner the failure log points at reports nothing and the
 *     trail ends. Found on a real 1.5.10 upgrade, where a plugin table
 *     left behind by 1.5 had never had the owning plugin's step run
 *     against it.
 *   - a refusal that IS structural says so, and the summary line stops
 *     telling the admin to run a scan that will find nothing.
 *
 * planConstraints() is pure, so most of this is asserted without a
 * database; the reporting arms drive a fake one.
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

// --- the group filter -------------------------------------------------------
//
// A schema step passes its own group so it lands only what its preconditions
// allow. Without it, the FIRST constraint step reached in an upgrade applies
// every enabled relationship in the map -- including ones whose column a
// later step has not made nullable yet -- and logs a refusal on every
// upgrade for a constraint the correct step then applies cleanly.
$g = static function ($n) use ($rel) {
    return $rel(['group' => $n]);
};

$plan = SchemaReconciler::planConstraints([$g(5)], $have, [], 5);
$t->check('a matching group is planned', count($plan) === 1);

$plan = SchemaReconciler::planConstraints([$g(5)], $have, [], 1);
$t->check('a different group is not planned', $plan === []);

$plan = SchemaReconciler::planConstraints([$rel()], $have, [], 1);
$t->check('an entry with no group is not planned by a filtered call', $plan === []);

$plan = SchemaReconciler::planConstraints([$g(5)], $have, [], null);
$t->check('an unfiltered call plans every group', count($plan) === 1);

// The filter must not read "not my group" as "retired". A step-1 call that
// dropped group 5's constraints would undo the previous run's work every
// time anyone upgraded.
$plan = SchemaReconciler::planConstraints(
    [$g(5)],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()],
    1
);
$t->check(
    'a filtered call leaves another group\'s constraint alone',
    $plan === []
);

// Nor may it leave a window with no constraint: a wrong declaration that
// belongs to another group is left for the unfiltered reconcile to correct,
// not dropped here and re-added later.
$plan = SchemaReconciler::planConstraints(
    [$g(5) + ['action' => 'SET NULL']],
    $have,
    ['fk_groupmembers_gmhostid' => $fk(['action' => 'CASCADE'])],
    1
);
$t->check(
    'a wrong declaration outside the filtered group is not dropped',
    $plan === []
);

// A RETIREMENT is never filtered, though -- a constraint the map no longer
// declares has to be removable from whichever step runs next.
$plan = SchemaReconciler::planConstraints(
    [$rel(['enabled' => false])],
    $have,
    ['fk_groupmembers_gmhostid' => $fk()],
    1
);
$t->check(
    'a retired constraint is dropped even by a filtered call',
    count($plan) === 1 && strpos($plan[0], 'DROP FOREIGN KEY') !== false
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

$result = SchemaReconciler::applyConstraints(null, [$rel()]);
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
$result = SchemaReconciler::applyConstraints(null, [$rel()]);
$since = array_slice($db->log, $mark);
$t->check('a clean run records no failure', SchemaReconciler::constraintFailures() === []);
$t->check('a clean run still returns true', $result === true);
$t->check(
    'a clean run really issued the ALTER',
    count(array_filter($since, static function ($sql) {
        return strpos($sql, 'ADD CONSTRAINT `fk_groupMembers_gmHostID`') !== false;
    })) === 1
);

// --- planSweep(): what a group's orphans get -------------------------------
//
// ADR 0031 decision 8. `ADD CONSTRAINT` validates existing rows, so a table
// holding orphans answers 1452 and simply never gets the constraint. The
// sweep is what makes a group applicable, and the repair each orphan gets is
// decided by the COLUMN's nullability, not by the relationship's action --
// deciding on the action would delete rows a nullable column could have kept,
// and would try to write NULL into columns that reject it.

// Nullable-column snapshot, in the shape nullableSnapshot() returns.
$nullable = ['tasks' => ['taskstateid']];

$plan = SchemaReconciler::planSweep([$rel(['group' => 'ldap'])], $have, $nullable, 'ldap');
$t->check('planSweep plans one statement for a matching group', count($plan) === 1);
$t->check(
    'a NOT NULL column DELETEs its orphans',
    strpos($plan[0] ?? '', 'DELETE FROM `groupMembers` WHERE') === 0
);
$t->check(
    'the sweep excludes NULL, which a foreign key already accepts',
    strpos($plan[0] ?? '', '`gmHostID` IS NOT NULL') !== false
);
$t->check(
    'the sweep excludes the 0 sentinel, which it is not the sweep\'s job to convert',
    strpos($plan[0] ?? '', '`gmHostID` <> 0') !== false
);
$t->check(
    'the orphan test is a subquery, not a self-join (MariaDB 1093)',
    strpos($plan[0] ?? '', 'NOT IN (SELECT `hostID` FROM (SELECT `hostID` FROM `hosts`) `p`)') !== false
);

$plan = SchemaReconciler::planSweep(
    [$rel(['child' => 'tasks', 'column' => 'taskStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'action' => 'SET NULL', 'group' => 'ldap'])],
    $have,
    $nullable,
    'ldap'
);
$t->check(
    'a nullable column NULLs its orphans instead of deleting the row',
    strpos($plan[0] ?? '', 'UPDATE `tasks` SET `taskStateID` = NULL WHERE') === 0
);

// The same relationship over the same column with a CASCADE action still
// NULLs, because nullability decides and the action does not. Without this
// the check above passes for the wrong reason -- SET NULL happening to line
// up with a nullable column.
$plan = SchemaReconciler::planSweep(
    [$rel(['child' => 'tasks', 'column' => 'taskStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'action' => 'CASCADE', 'group' => 'ldap'])],
    $have,
    $nullable,
    'ldap'
);
$t->check(
    'nullability decides the repair, not the action',
    strpos($plan[0] ?? '', 'UPDATE `tasks` SET `taskStateID` = NULL') === 0
);

// --- planSweep(): what it refuses to touch ---------------------------------
$t->check(
    'a null group sweeps nothing; there is no "everything" mode',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap'])], $have, $nullable, null) === []
        && SchemaReconciler::planSweep([$rel()], $have, $nullable, null) === []
);
$t->check(
    'another group is not swept',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap'])], $have, $nullable, 'oidc') === []
);
$t->check(
    'a disabled relationship is not swept',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap', 'enabled' => false])], $have, $nullable, 'ldap') === []
);
// ADR 0021: the audit trail outlives its subject. An audit relationship is
// action `none`, so no sweep can reach one -- which is the property that
// keeps a destructive helper away from the history tables.
$t->check(
    'an action `none` relationship is not swept (ADR 0021)',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap', 'action' => 'none'])], $have, $nullable, 'ldap') === []
);
$t->check(
    'an absent child table is not swept',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap', 'child' => 'nosuch'])], $have, $nullable, 'ldap') === []
);
$t->check(
    'an absent parent table is not swept',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap', 'parent' => 'nosuch'])], $have, $nullable, 'ldap') === []
);
$t->check(
    'an absent column is not swept',
    SchemaReconciler::planSweep([$rel(['group' => 'ldap', 'column' => 'nosuch'])], $have, $nullable, 'ldap') === []
);
$t->check(
    'a relationship with no group at all is not swept',
    SchemaReconciler::planSweep([$rel()], $have, $nullable, 'ldap') === []
);

// --- the group match is strict ---------------------------------------------
//
// The whole reason core groups are ints and plugin groups are strings: one
// map serves both spaces only if 5 and 'location' cannot match each other.
//
// The pair below is `5` against `'5'`, not `5` against `'location'`. On PHP 8
// `5 == 'location'` is already false, so a loose comparison would pass a test
// written that way and still be wrong on FOG's actual floor of 7.4, where it
// is true. `5 == '5'` is true on every version, so this holds the code to ===
// on the PHP that runs it.
$core = $rel(['group' => 5]);
$plug = $rel(['child' => 'locationAssoc', 'column' => 'laHostID', 'group' => '5']);
$both = [$core, $plug];
$haveBoth = $have + ['locationassoc' => ['laid', 'lahostid', 'lalocationid']];

$plan = SchemaReconciler::planConstraints($both, $haveBoth, [], 5);
$t->check(
    'planConstraints: int 5 does not match string 5',
    count($plan) === 1 && strpos($plan[0], '`groupMembers`') !== false
);
$plan = SchemaReconciler::planConstraints($both, $haveBoth, [], '5');
$t->check(
    'planConstraints: string 5 does not match int 5',
    count($plan) === 1 && strpos($plan[0], '`locationAssoc`') !== false
);
$plan = SchemaReconciler::planSweep($both, $haveBoth, [], 5);
$t->check(
    'planSweep: int 5 does not match string 5',
    count($plan) === 1 && strpos($plan[0], '`groupMembers`') !== false
);
$plan = SchemaReconciler::planSweep($both, $haveBoth, [], '5');
$t->check(
    'planSweep: string 5 does not match int 5',
    count($plan) === 1 && strpos($plan[0], '`locationAssoc`') !== false
);

// And the real-world pair, which proves the shipped map's two spaces are
// separated -- on PHP 8 by == already, on 7.4 only by the === above.
$plan = SchemaReconciler::planConstraints(
    [$rel(['group' => 5]), $rel(['child' => 'locationAssoc', 'column' => 'laHostID', 'group' => 'location'])],
    $haveBoth,
    [],
    'location'
);
$t->check(
    'a plugin group applies only its own',
    count($plan) === 1 && strpos($plan[0], '`locationAssoc`') !== false
);

// --- sweepOrphans() runs the plan and reports a failure --------------------
//
// planSweep() is pure, so nothing above proves the statements are issued or
// that a failed one stops the step. Unlike applyConstraints(), a sweep that
// fails MUST return the error: the constraints that follow it depend on the
// rows being gone, and a silent skip would leave the group declared and
// unenforced.
$db = FogTestHarness::fakeDb();
$sweepStructure = [
    ['TABLE_NAME' => 'groupMembers', 'COLUMN_NAME' => 'gmHostID'],
    ['TABLE_NAME' => 'hosts', 'COLUMN_NAME' => 'hostID'],
];
$db->responder = static function ($sql) use ($db, $sweepStructure) {
    $db->error = false;
    if (strpos($sql, 'IS_NULLABLE') !== false) {
        return [];
    }
    if (strpos($sql, 'information_schema') !== false) {
        return $sweepStructure;
    }
    if (strpos($sql, 'DELETE FROM') === 0) {
        $db->error = 'SQLSTATE[HY000]: lock wait timeout';
        return [];
    }
    return [];
};
$result = SchemaReconciler::sweepOrphans('ldap', [$rel(['group' => 'ldap'])]);
$t->check(
    'a failed sweep returns the error, it does not carry on',
    is_string($result) && strpos($result, 'lock wait timeout') !== false
);

$db->responder = static function ($sql) use ($db, $sweepStructure) {
    $db->error = false;
    if (strpos($sql, 'IS_NULLABLE') !== false) {
        return [];
    }
    if (strpos($sql, 'information_schema') !== false) {
        return $sweepStructure;
    }
    return [];
};
$mark = count($db->log);
$result = SchemaReconciler::sweepOrphans('ldap', [$rel(['group' => 'ldap'])]);
$since = array_slice($db->log, $mark);
$t->check('a clean sweep returns true', $result === true);
$t->check(
    'a clean sweep really issued the DELETE',
    count(array_filter($since, static function ($sql) {
        return strpos($sql, 'DELETE FROM `groupMembers`') === 0;
    })) === 1
);
// The count in the log line is the only operator-visible evidence a sweep
// left behind. "Swept 0" and "never ran" are different situations and want
// different responses, so the line is written either way and has to carry
// the real number.
$logfile = tempnam(sys_get_temp_dir(), 'fksweep');
$prev = ini_get('error_log');
ini_set('error_log', $logfile);
$db->affected = 7;
SchemaReconciler::sweepOrphans('ldap', [$rel(['group' => 'ldap'])]);
$db->affected = 0;
SchemaReconciler::sweepOrphans('ldap', [$rel(['group' => 'ldap'])]);
ini_set('error_log', $prev);
$logged = (string)file_get_contents($logfile);
unlink($logfile);
$t->check(
    'the sweep log carries the real row count',
    strpos($logged, '(ldap): 7 row(s)') !== false
);
$t->check(
    'a sweep that removed nothing still logs, with 0',
    strpos($logged, '(ldap): 0 row(s)') !== false
);

$t->check(
    'a null group issues nothing at all',
    (function () use ($db, $rel) {
        $mark = count($db->log);
        SchemaReconciler::sweepOrphans(null, [$rel(['group' => 'ldap'])]);
        return array_slice($db->log, $mark) === [];
    })()
);

// --- a SET NULL declared over a NOT NULL column is skipped, not attempted ---
//
// Found on a real 1.5.10 database (2079 hosts, schema 278) upgraded to 398:
// fk_location_lStorageNodeID was the ONE constraint of 80 the run could not
// add. Not orphans -- the scan found zero -- but InnoDB refusing errno 150
// on a SET NULL over a NOT NULL column.
//
// The preparation (make the column nullable, convert the `0` sentinel) lives
// in the OWNING plugin's schema(), verified in FOGProject/fog-plugins at
// location/src/Managers/LocationManager.php. 1.5's plugins lived in the web
// tree, so their tables survive an upgrade with no 1.6 step ever run against
// them, and planConstraints() reads the table being present as "applicable".
//
// Skipping is right and absence is CORRECT here: the constraint lands when
// the plugin installs. Attempting it puts a permanent, misdirected failure
// in the log of every server carrying a 1.5-era plugin table.
$nullRel = static function (array $over = []) {
    return $over + [
        'child' => 'location',
        'column' => 'lStorageNodeID',
        'parent' => 'nfsGroupMembers',
        'pcolumn' => 'ngmID',
        'class' => 'config',
        'action' => 'SET NULL',
        'enabled' => true,
    ];
};
$nullHave = [
    'location' => ['lid', 'lstoragenodeid'],
    'nfsgroupmembers' => ['ngmid'],
];

$plan = SchemaReconciler::planConstraints(
    [$nullRel()],
    $nullHave,
    [],
    null,
    ['location' => []]
);
$t->check(
    'SET NULL over a NOT NULL column plans nothing',
    $plan === []
);

// The other arm, and the one that stops the guard from being a blanket
// refusal of every SET NULL in the map. Without it the check above passes
// with the whole action disabled.
$plan = SchemaReconciler::planConstraints(
    [$nullRel()],
    $nullHave,
    [],
    null,
    ['location' => ['lstoragenodeid']]
);
$t->check(
    'SET NULL over a nullable column still plans the constraint',
    count($plan) === 1
        && strpos($plan[0], 'ADD CONSTRAINT `fk_location_lStorageNodeID`') !== false
        && strpos($plan[0], 'ON DELETE SET NULL') !== false
);

// No snapshot means no opinion. planConstraints() is called without one from
// tests and from any caller that has not read information_schema, and a
// missing snapshot must not read as "nothing is nullable" -- that would
// silently drop every SET NULL constraint in the map.
$plan = SchemaReconciler::planConstraints([$nullRel()], $nullHave, [], null, null);
$t->check(
    'no nullability snapshot leaves SET NULL planning unchanged',
    count($plan) === 1
);

// The guard is specific to SET NULL. CASCADE over a NOT NULL column is the
// normal shape of every junction in the map -- groupMembers.gmHostID is NOT
// NULL and must stay constrained.
$plan = SchemaReconciler::planConstraints(
    [$nullRel(['action' => 'CASCADE'])],
    $nullHave,
    [],
    null,
    ['location' => []]
);
$t->check(
    'CASCADE over a NOT NULL column is unaffected by the guard',
    count($plan) === 1
);

// --- a structural refusal is reported as structural -------------------------
//
// 1452 and 1005/150 are different problems with different remedies, and both
// used to be reported with "Run bin/fk-orphan-scan.php to find the rows".
// For a structural refusal that scan returns nothing and the admin has
// nowhere to go next -- the worst shape a diagnostic can take, because it
// looks like an answer.
$structFail = static function ($errno) use ($rel) {
    $db = FogTestHarness::fakeDb();
    $cols = [
        ['TABLE_NAME' => 'groupMembers', 'COLUMN_NAME' => 'gmHostID'],
        ['TABLE_NAME' => 'hosts', 'COLUMN_NAME' => 'hostID'],
    ];
    $db->responder = static function ($sql) use ($db, $cols, $errno) {
        if (strpos($sql, 'REFERENTIAL_CONSTRAINTS') !== false) {
            $db->error = false;
            return [];
        }
        if (strpos($sql, 'information_schema') !== false
            && strpos($sql, 'COLUMNS') !== false
        ) {
            $db->error = false;
            // IS_NULLABLE = 'YES' is nullableSnapshot()'s own query. Answer
            // it with the column present so the plan is not skipped by the
            // guard above -- this arm is about REPORTING a refusal, and a
            // skipped constraint never reaches the reporting path at all.
            return $cols;
        }
        if (stripos($sql, 'ADD CONSTRAINT') !== false) {
            $db->error = "SQLSTATE[HY000]: General error: $errno Can't create"
                . " table (errno: 150)\nQuery: $sql";
            $db->errorCode = $errno;
            return [];
        }
        return null;
    };
    SchemaReconciler::applyConstraints(null, [$rel()]);
    return SchemaReconciler::constraintFailures();
};

$failures = $structFail(1005);
$t->check(
    'errno 1005 is recorded as a structural refusal',
    count($failures) === 1 && !empty($failures[0]['structural'])
);

$failures = $structFail(1452);
$t->check(
    'errno 1452 is NOT recorded as structural',
    count($failures) === 1 && empty($failures[0]['structural'])
);

// The flag only matters because it changes what the admin is told to do
// next. Asserting the field alone would pass with the summary line still
// pointing every failure at the orphan scanner.
$summaryFor = static function ($errno) use ($structFail) {
    $logfile = tempnam(sys_get_temp_dir(), 'fkstruct');
    $prev = ini_get('error_log');
    ini_set('error_log', $logfile);
    $structFail($errno);
    ini_set('error_log', $prev);
    $logged = (string)file_get_contents($logfile);
    unlink($logfile);
    return $logged;
};

$logged = $summaryFor(1005);
$t->check(
    'an all-structural summary does not send the admin to the orphan scan',
    strpos($logged, 'fk-orphan-scan.php will report none') !== false
        && strpos($logged, 'Run bin/fk-orphan-scan.php') === false
);

$logged = $summaryFor(1452);
$t->check(
    'an orphan summary still sends the admin to the orphan scan',
    strpos($logged, 'Run bin/fk-orphan-scan.php') !== false
);

$t->finish();
