<?php
/**
 * An optional *id with no reference is stored as NULL, never as 0.
 *
 * ADR 0031 gave the database real foreign keys, and schema step 388 first
 * converted the columns that can legitimately hold "no reference" to
 * nullable and rewrote every 0 already in them to NULL. That pairing is the
 * whole point: once `tasks`.`taskNFSGroupID` REFERENCES `nfsGroups`(`ngID`),
 * there is no `nfsGroups` row with id 0, so a 0 is not a weaker value than
 * NULL -- it is a value the column cannot hold at all. Writing one is
 *
 *   SQLSTATE[23000]: 1452 Cannot add or update a child row: a foreign key
 *   constraint fails (`fog`.`tasks`, CONSTRAINT `fk_tasks_taskNFSGroupID` ...)
 *
 * and the INSERT is refused outright.
 *
 * save() already wrote NULL for an optional *id that was EMPTY. What it did
 * not do was write NULL for one that was PRESENT and not a row id -- the 0
 * that FOG has used to mean "none" everywhere for thirteen years. That value
 * failed the `min_range => 1` validation, and the failure branch answered
 * with a literal 0, putting back exactly what the migration had just
 * removed. Reported against a Hardware Inventory task, which is not an
 * imaging task, so Host::createImagePackage() passed 0 for both the storage
 * group and the storage node and the task could not be created at all.
 *
 * The columns are the FIVE the manifest declares nullable on `tasks` and
 * `hosts`; the check is driven through the real save() against the fake
 * database, reading the parameters it would have bound. Both directions
 * matter and both are asserted:
 *
 *   nullable column  ->  omitted from the statement, so the server stores
 *                        its DEFAULT NULL
 *   NOT NULL column  ->  still 0, because NULL is not storable there and
 *                        omitting it would be error 1364
 *
 * Usage: php tests/optional-ids-write-null-not-zero.test.php
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

use FOG\Items\Task;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('optional-ids-write-null-not-zero');

$t = new FogChecks();

/*
 * The manifest is what columnIsNullable() reads for a core table, so the
 * test's premise is asserted from the same source rather than assumed. If a
 * later schema step makes one of these NOT NULL again the premise fails here
 * instead of the expectation failing further down for a reason that looks
 * like the ORM's fault.
 */
$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
$columns = $manifest['tables']['tasks']['columns'] ?? [];

$nullable = [
    'taskNFSGroupID',
    'taskNFSMemberID',
    'taskLastMemberID',
    'taskImageID',
];
foreach ($nullable as $column) {
    $t->check(
        "premise: tasks.$column is declared nullable",
        isset($columns[$column])
            && !preg_match('/\bNOT\s+NULL\b/i', $columns[$column])
    );
}
// The control. taskHostID is a foreign key too, but a task always belongs to
// a host, so "no host" is not a state it can be in -- the column stays NOT
// NULL and 0 stays the right answer for it.
$t->check(
    'premise: tasks.taskHostID is declared NOT NULL',
    isset($columns['taskHostID'])
        && (bool)preg_match('/\bNOT\s+NULL\b/i', $columns['taskHostID'])
);

/**
 * Saves a Task carrying $fields and returns the parameters save() bound.
 *
 * @param array $fields friendly key => value, as any caller would set them
 *
 * @return array bind name => value, e.g. ':taskNFSGroupID_insert' => null
 */
$boundBySaving = static function (array $fields) {
    $db = FogTestHarness::fakeDb();
    $captured = [];
    $db->responder = static function ($sql, $params) use ($db, &$captured) {
        $db->error = false;
        if (0 === stripos(ltrim($sql), 'INSERT INTO `tasks`')) {
            $captured = $params;
        }
        return null;
    };

    $Task = new Task();
    foreach ($fields as $key => $value) {
        $Task->set($key, $value);
    }
    $Task->save();

    return $captured;
};

// The reported save, verbatim in shape: a non-imaging task, so no storage
// group and no node serve it, and the legacy spelling of that is 0.
$bound = $boundBySaving(
    [
        'name' => 'Hardware Inventory Task - dr-wks-jjv1',
        'hostID' => 42,
        'stateID' => 1,
        'typeID' => 13,
        'storagegroupID' => 0,
        'storagenodeID' => 0,
        'NFSLastMemberID' => 0,
        'imageID' => 0,
    ]
);

$t->check(
    'the statement was built at all',
    count($bound) > 0
);

foreach ($nullable as $column) {
    /*
     * Absent from the parameter list is how save() spells NULL for a column
     * that carries DEFAULT NULL -- see the null guard in save(). Asserting
     * "not 0" alone would pass on a bound empty string, so both halves are
     * checked: the column is either absent, or bound as a real null.
     */
    $key = ':' . $column . '_insert';
    $value = array_key_exists($key, $bound) ? $bound[$key] : null;
    $t->check(
        "$column is stored as NULL, not 0",
        null === $value
    );
}

// The control again, this time through save(): a NOT NULL column still gets
// a number, because the fix is conditioned on the column and not on the key
// looking like an id.
$t->check(
    'taskHostID is untouched by the null rule',
    isset($bound[':taskHostID_insert'])
        && 42 === $bound[':taskHostID_insert']
);
// And a real reference is still written as itself.
$bound = $boundBySaving(
    [
        'name' => 'Deploy',
        'hostID' => 42,
        'stateID' => 1,
        'typeID' => 1,
        'storagegroupID' => 3,
        'storagenodeID' => 7,
    ]
);
$t->check(
    'a real storage group id is written unchanged',
    isset($bound[':taskNFSGroupID_insert'])
        && 3 === $bound[':taskNFSGroupID_insert']
);
$t->check(
    'a real storage node id is written unchanged',
    isset($bound[':taskNFSMemberID_insert'])
        && 7 === $bound[':taskNFSMemberID_insert']
);

/*
 * The call site the report came from. save() now corrects a 0 whoever sends
 * it, but Host::createImagePackage() should not be sending one: a
 * non-imaging task is served by no group and no node, and NULL is what that
 * means. Pinned as source because the method is private and reaching it
 * needs a valid image, storage group and optimal node -- none of which the
 * fault has anything to do with. The whole call is matched, so a changed
 * condition is a visible failure rather than a grep that passes on
 * unreachable code.
 */
$host = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Items/Host.php'
);
$t->check(
    'Host::createImagePackage passes null, not 0, for a non-imaging task',
    (bool)preg_match(
        '/\$imagingTypes\s*\?\s*\$StorageGroup->get\(\'id\'\)\s*:\s*null\s*,'
        . '\s*(?:\/\/[^\n]*\n\s*)*'
        . '\$imagingTypes\s*\?\s*\$StorageNode->get\(\'id\'\)\s*:\s*null\s*,/',
        $host
    )
);

$t->finish();
