<?php
/**
 * An unset nullable integer column is written as NULL, not as 0.
 *
 * GH-1572 established this for the branch save() takes when a model key
 * ends in "id": once ADR 0031 gave a nullable int column a real foreign
 * key, 0 stopped being a spelling of "no reference" and became a reference
 * to a row that does not exist -- error 1452, and the whole save refused.
 *
 * That fix reached one branch of one method. Every model key that does NOT
 * end in "id" takes the other branch, which asks FOGBase::emptyValueFor()
 * what an unset optional field should be written as, and that answered 0
 * for every integer column whether or not it could hold NULL. So the same
 * bug survived under a different name.
 *
 * `multicastSessions`.`msSenderNode` is the column that proves it. Its
 * model key is `sendernode`; schema step 386 made the column nullable and
 * step 388 gave it a foreign key to `nfsGroupMembers`(`ngmID`). A session
 * is created before any udp-sender exists, so the field is ALWAYS unset at
 * INSERT -- which meant every multicast session save on this branch failed
 * with 1452, from all three places that create one:
 *
 *   Group::createImagePackage()      group multicast, and the host list's
 *                                    Queue Task, which routes through it
 *   Host::createImagePackage()       multicast from a single host
 *   ImageManagement::_newSession()   "create session" on the image page
 *
 * The failure was silent in the way that costs the most time. save()
 * reports it by RETURNING FALSE rather than throwing, so
 * createImagePackage() carried on and built the per-host task rows against
 * a session id that had never been allocated -- and what the user saw was
 * a complaint about `multicastSessionsAssoc`.`msID` being empty, naming a
 * table two steps downstream of the one that actually refused the write.
 *
 * WHAT IS ASSERTED, and why in two layers. The unit half pins
 * emptyValueFor() against the four shapes that have to stay distinguished;
 * the behavioral half drives the real save() through a fake database and
 * reads the parameters it bound, because emptyValueFor() answering null is
 * worth nothing if save() then declines to bind it. Either half alone
 * passes on a broken implementation.
 *
 * The premises are pinned too. Both facts this rests on live in files
 * nothing else in the suite watches for this purpose: the column is still
 * nullable in the manifest, and the foreign key that makes 0 unstorable is
 * still declared. If either changes, this test should be re-read rather
 * than silently kept green.
 *
 * Usage: php tests/nullable-int-columns-write-null.test.php
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

use FOG\Base\FOGBase;
use FOG\Items\MulticastSession;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('nullable-int-columns-write-null');
FogTestHarness::fakeDb();

$t = new FogChecks();

// -------------------------------------------------------------------------
// 1. emptyValueFor() itself. Reflection because it is protected, and it is
//    protected for a good reason -- this is the only caller outside the
//    class hierarchy and it is a test.
// -------------------------------------------------------------------------
$empty = new \ReflectionMethod(FOGBase::class, 'emptyValueFor');
$empty->setAccessible(true);
/**
 * What an unset optional field on this column would be written as.
 *
 * @param string $table  the database table
 * @param string $column the database column
 *
 * @return mixed
 */
$valueFor = static function ($table, $column) use ($empty) {
    return $empty->invoke(null, $table, $column);
};

// The column the bug was found on: nullable int, carries a foreign key.
$t->check(
    'a nullable int column answers NULL',
    null === $valueFor('multicastSessions', 'msSenderNode')
);
// Nullable for the same reason (step 386, taskStates has no tsID 0) but
// with no foreign key, so this one is the rule applying generally rather
// than the foreign key forcing it.
$t->check(
    'a nullable int column with no foreign key answers NULL too',
    null === $valueFor('multicastSessions', 'msState')
);
// The half that must NOT change. NULL is not storable in a NOT NULL
// column -- that is error 1048 -- and omitting the value is 1364, so 0
// remains the only answer here.
$t->check(
    'a NOT NULL int column still answers 0',
    0 === $valueFor('multicastSessions', 'msSenderPID')
);
$t->check(
    'a NOT NULL int column with no default still answers 0',
    0 === $valueFor('multicastSessions', 'msNFSGroupID')
);
// The neighbors, so a change to the integer rule cannot quietly take the
// others with it.
$t->check(
    'a nullable datetime column still answers NULL',
    null === $valueFor('multicastSessions', 'msStartDateTime')
);
$t->check(
    "a NOT NULL string column still answers ''",
    '' === $valueFor('multicastSessions', 'msName')
);
// An unknown table or column is the "assume a string" fallback, and it has
// to stay that way: guessing NULL for something the manifest cannot see
// would turn a plugin's NOT NULL column into error 1048.
$t->check(
    "an unknown column still answers ''",
    '' === $valueFor('multicastSessions', 'msNoSuchColumn')
);

// -------------------------------------------------------------------------
// 2. The premises, which live in two files nothing else watches for this.
// -------------------------------------------------------------------------
$manifest = (string) file_get_contents(
    dirname(__DIR__) . '/packages/web/commons/schema-expected.php'
);
$t->check(
    'msSenderNode is still declared nullable in the manifest',
    false !== strpos($manifest, "'msSenderNode' => 'int(11) DEFAULT NULL'")
);
$constraints = (string) file_get_contents(
    dirname(__DIR__) . '/packages/web/commons/schema-constraints.php'
);
$t->check(
    'msSenderNode still carries the foreign key that makes 0 unstorable',
    1 === preg_match(
        "/'child' => 'multicastSessions',\s*'column' => 'msSenderNode',"
        . "\s*'parent' => 'nfsGroupMembers'/",
        $constraints
    )
);

// -------------------------------------------------------------------------
// 3. The whole chain: what save() actually binds for a session built the
//    way all three creation sites build one.
// -------------------------------------------------------------------------
$db = FogTestHarness::fakeDb();
$captured = [];
$db->responder = static function ($sql, $params) use ($db, &$captured) {
    $db->error = false;
    if (0 === stripos(ltrim($sql), 'INSERT INTO `multicastSessions`')) {
        $captured = $params;
    }

    return null;
};

// Exactly the fields the three sites set, minus the ones that vary between
// them. Notably `sendernode` is NOT set -- that is the point: no
// udp-sender exists yet, so nothing has a value to give it.
$Session = new MulticastSession();
$Session->set('name', 'Multi-Cast Task - 3 selected hosts')
    ->set('port', 63100)
    ->set('logpath', '/images/test')
    ->set('image', 1)
    ->set('interface', 'eth0')
    ->set('stateID', null)
    ->set('starttime', '2026-08-31 21:44:09')
    ->set('percent', 0)
    ->set('isDD', 1)
    ->set('maxwait', 600)
    ->set('storagegroupID', 1);
$Session->save();

$t->check(
    'the INSERT was built at all',
    count($captured) > 0
);
/*
 * Absent from the parameter list is the other way save() spells NULL for a
 * nullable column -- the null guard omits it and the server's DEFAULT NULL
 * applies. Both spellings are correct; a bound 0 or '' is not. Asserting
 * "not 0" alone would pass on a bound empty string, so the check is that
 * the parameter is either absent or a real null.
 */
$sender = ':msSenderNode_insert';
$t->check(
    'msSenderNode is bound as NULL, or left out entirely',
    !array_key_exists($sender, $captured) || null === $captured[$sender]
);
// The counterpart, in the same statement, so the two halves of the rule
// are proven against one another rather than in isolation.
$pid = ':msSenderPID_insert';
$t->check(
    'msSenderPID in the same statement is still bound as 0',
    array_key_exists($pid, $captured) && 0 === (int) $captured[$pid]
);

$t->finish();
