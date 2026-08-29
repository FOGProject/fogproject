<?php
/**
 * A refused delete is reported as a sentence, and the refusal is not lost.
 *
 * ADR 0031 put real foreign keys in the database, which made a delete
 * refusable for the first time in FOG's history. Two things had to follow
 * from that, and both fail silently when wrong:
 *
 *   - the refusal has to REACH the caller. `PDODB::query()` catches the
 *     PDOException, records the text on `->error` and returns `$this`, which
 *     is truthy. `Route::deletemass()` used to end with a bare
 *     `return self::$DB->query(...)`, so a delete the server refused
 *     answered HTTP 200 with the row still in place and the UI drew a
 *     success toast over it. That arm was unreachable until this work gave
 *     the database something to refuse with.
 *   - what reaches the caller has to be readable. MariaDB's own text names
 *     the constraint, the tables and the columns; an admin deleting a
 *     storage group needs to be told a location still uses it.
 *
 * ConstraintViolation is pure, so most of this is asserted without a
 * database. The deletemass() arm is not pure, so it is driven through the
 * fake DB with an error set on it -- pinning the THROW, not the presence of
 * a line of code, because a `return` where a `throw` belongs is exactly the
 * regression this exists to catch.
 *
 * Usage: php tests/constraint-violation.test.php
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

use FOG\Db\ConstraintViolation;
use FOG\Db\SchemaReconciler;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('constraint-violation');

$t = new FogChecks();

// MariaDB's own wording, verbatim from the lab server (11.8.8) refusing to
// delete storage group 1 while a location still points at it. Kept whole
// rather than trimmed: the parser has to work on what the server actually
// sends, and the newline and the backticks are part of that.
$refusal = 'SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot'
    . ' delete or update a parent row: a foreign key constraint fails'
    . ' (`fog`.`location`, CONSTRAINT `fk_location_lStorageGroupID` FOREIGN'
    . ' KEY (`lStorageGroupID`) REFERENCES `nfsGroups` (`ngID`))';

// The opposite direction: a child row pointing at a parent that is not
// there. Comes from an INSERT or an UPDATE, never from a delete.
$orphan = 'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add'
    . ' or update a child row: a foreign key constraint fails'
    . ' (`fog`.`location`, CONSTRAINT `fk_location_lStorageGroupID` FOREIGN'
    . ' KEY (`lStorageGroupID`) REFERENCES `nfsGroups` (`ngID`))';

// --- isRefusal discriminates the two directions -----------------------------
$t->check(
    'a 1451 is recognized as a refusal',
    ConstraintViolation::isRefusal($refusal)
);
$t->check(
    'a 1452 is NOT a refusal -- different direction, different advice',
    !ConstraintViolation::isRefusal($orphan)
);
$t->check(
    'an unrelated error is not a refusal',
    !ConstraintViolation::isRefusal('Table `fog`.`nope` does not exist')
);
$t->check(
    'an empty error is not a refusal',
    !ConstraintViolation::isRefusal('')
);

// --- explain() names both ends ---------------------------------------------
$msg = ConstraintViolation::explain($refusal, 'storage group');
$t->check(
    'explain() names what is being deleted',
    is_string($msg) && false !== strpos($msg, 'this storage group')
);
$t->check(
    'explain() names what is holding it, in words not table names',
    is_string($msg) && false !== strpos($msg, 'a location still refers')
);
$t->check(
    'explain() says what to do about it',
    is_string($msg) && false !== strpos($msg, 'Reassign or remove it first')
);
$t->check(
    'explain() leaks no table name, column name or constraint name',
    is_string($msg)
    && false === strpos($msg, 'nfsGroups')
    && false === strpos($msg, 'lStorageGroupID')
    && false === strpos($msg, 'fk_')
);

$t->check(
    'without a noun the subject degrades to "this record", not to blank',
    ConstraintViolation::explain($refusal) === ConstraintViolation::explain(
        $refusal,
        ''
    )
    && false !== strpos(
        (string)ConstraintViolation::explain($refusal),
        'this record'
    )
);

// --- explain() declines rather than guessing --------------------------------
$t->check(
    'a 1452 explains nothing -- the caller keeps its own error',
    null === ConstraintViolation::explain($orphan, 'storage group')
);
$t->check(
    'a refusal naming no constraint explains nothing',
    null === ConstraintViolation::explain(
        'Cannot delete or update a parent row: a foreign key constraint fails',
        'storage group'
    )
);
$t->check(
    'a constraint the map does not declare explains nothing',
    null === ConstraintViolation::explain(
        str_replace('fk_location_lStorageGroupID', 'fk_hand_made', $refusal),
        'storage group'
    )
);

// --- relationship() resolves through the map, not the message ---------------
$rel = ConstraintViolation::relationship('fk_location_lStorageGroupID');
$t->check(
    'relationship() returns the declared entry, both ends',
    is_array($rel)
    && $rel['child'] === 'location'
    && $rel['parent'] === 'nfsGroups'
    && $rel['column'] === 'lStorageGroupID'
);
// is_array() as well as the comparison: a lookup that had stopped folding
// case would return null for BOTH spellings, and `null === null` would pass
// this while proving the opposite of what it claims.
$upper = ConstraintViolation::relationship('FK_LOCATION_LSTORAGEGROUPID');
$t->check(
    'relationship() is case-insensitive on the constraint name',
    is_array($upper) && $upper === $rel
);
$t->check(
    'an unknown constraint resolves to null, not to the first entry',
    null === ConstraintViolation::relationship('fk_nothing_at_all')
);

// --- label() ----------------------------------------------------------------
$t->check(
    'label() gives the human name',
    ConstraintViolation::label('nfsGroups') === 'storage group'
);
$t->check(
    'an unlabeled table degrades to its own name rather than to blank',
    ConstraintViolation::label('someTableNobodyNamed') === 'someTableNobodyNamed'
);

/*
 * Every table that can appear on either side of a RESTRICT must have a label.
 *
 * This is the entry that keeps the bounded list honest. The label map is
 * deliberately not a parallel copy of the schema -- only a RESTRICT can
 * refuse a delete -- but that bound is only safe while the map and the
 * declaration agree. Flipping any relationship to RESTRICT, or adding one,
 * puts two more tables in reach of a user-facing sentence; without this
 * check the first anyone would know is a message reading "because a
 * dircleanuptasks still refers to it".
 */
// Read the list itself rather than comparing label() against the table
// name: `location` is called "location" in both, so the comparison cannot
// tell a declared label from a missing one.
$labelProp = new \ReflectionProperty('FOG\Db\ConstraintViolation', '_labels');
$labelProp->setAccessible(true);
$labels = $labelProp->getValue();

$unlabeled = [];
foreach (SchemaReconciler::constraints() as $r) {
    if (empty($r['enabled']) || 'RESTRICT' !== ($r['action'] ?? '')) {
        continue;
    }
    foreach ([$r['child'], $r['parent']] as $table) {
        if (!isset($labels[$table])) {
            $unlabeled[$table] = true;
        }
    }
}
$t->check(
    'every table either side of an enabled RESTRICT has a label ('
    . (count($unlabeled) ? implode(', ', array_keys($unlabeled)) : 'none')
    . ')',
    $unlabeled === []
);

/*
 * A refused delete must not return.
 *
 * Driven rather than grepped: the failure this guards against is a `throw`
 * becoming a `return`, and a source scan for the words cannot tell the two
 * apart once someone has moved them. The fake DB reports the refusal the way
 * PDODB does -- text on ->error, itself returned -- and the only acceptable
 * outcome is that deletemass() does not hand that object back.
 */
$db = FogTestHarness::fakeDb();

// deletemass() catches its own exception and hands it to _sendCaught(),
// which normally sets the status and exits -- so the throw cannot be
// observed from outside without the router's own escape hatch. ADR 0011 put
// one there for the CLI daemons: with _rethrowDepth raised, sendResponse()
// raises a RuntimeException carrying the status code and message instead of
// ending the process. Driving through that rather than around it means the
// code asserted below is the code an HTTP caller would have received,
// including _sendCaught()'s 4xx passthrough.
FogTestHarness::setStatic('Route', '_rethrowDepth', 1);

$call = static function () {
    $r = new \ReflectionMethod('FOG\Router\Route', 'deletemass');
    $r->setAccessible(true);
    return $r->invoke(null, 'storagegroup', [1]);
};

// Asserted on the throw alone, not also on the returned value: the method
// is declared `@return void` while in fact returning the DB object, so a
// comparison against what came back is degenerate to a static analyzer and
// says nothing here either. Reaching the catch is the whole claim -- the
// pre-fix code returned instead, which is the regression being pinned.
$db->error = $refusal;
$thrown = null;
try {
    $call();
} catch (\Throwable $e) {
    $thrown = $e;
}
$t->check(
    'a refused delete throws instead of returning the DB object',
    null !== $thrown
);
$t->check(
    'what it throws is the readable sentence, not the SQL',
    null !== $thrown
    && false !== strpos($thrown->getMessage(), 'still refers to it')
    && false === strpos($thrown->getMessage(), 'SQLSTATE')
);
$t->check(
    'it carries 409 -- the request was fine, the state was not',
    null !== $thrown && 409 === $thrown->getCode()
);

// A refusal the map cannot describe still has to stop the request; only the
// wording degrades.
$db->error = str_replace('fk_location_lStorageGroupID', 'fk_hand_made', $refusal);
$thrown = null;
try {
    $call();
} catch (\Throwable $e) {
    $thrown = $e;
}
$t->check(
    'an undescribable refusal still throws, carrying the raw text',
    null !== $thrown && false !== strpos($thrown->getMessage(), 'SQLSTATE')
);
$t->check(
    'and still carries 409 -- the status is about the refusal, not the words',
    null !== $thrown && 409 === $thrown->getCode()
);

// An error that is not a refusal at all. Still has to stop the request --
// that is the silent-success bug either way -- but it is not a conflict and
// must not claim to be one, because 409 tells a client the request will
// work once something else changes.
$db->error = 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded';
$thrown = null;
try {
    $call();
} catch (\Throwable $e) {
    $thrown = $e;
}
$t->check(
    'a non-constraint failure still stops the request',
    null !== $thrown
);
$t->check(
    'a non-constraint failure is not dressed up as a conflict',
    null !== $thrown && 409 !== $thrown->getCode()
);

// And the success path must be untouched: an ordinary delete still returns.
$db->error = '';
$thrown = null;
try {
    $call();
} catch (\Throwable $e) {
    $thrown = $e;
}
$t->check(
    'a delete the server accepted still returns normally',
    null === $thrown
);

/*
 * The spec has to list the status the route now answers.
 *
 * A client generated from an OpenAPI document handles the responses the
 * document names. Delete answered 200-or-error for FOG's whole history and
 * the spec said so; it can now answer 409, and a generated client meeting an
 * undocumented status either throws or -- worse -- treats it as a generic
 * failure and drops the sentence that says what to do about it.
 *
 * Read out of the emitted document rather than grepped out of the source:
 * the responses are assembled by _op() from several helpers, so the source
 * saying `_conflictResponse(` proves the call exists, not that a 409 came
 * out the other end for THIS operation.
 */
$doc = \FOG\Router\OpenAPI::document();
$deleteOp = $doc['paths']['/host/{id}']['delete'] ?? null;
$t->check(
    'the delete operation exists in the emitted document',
    is_array($deleteOp)
);
$t->check(
    'and it documents the 409 a refused delete now answers',
    isset($deleteOp['responses']['409'])
);
$t->check(
    'the 409 description says what a caller should do, not just that it failed',
    isset($deleteOp['responses']['409']['description'])
    && false !== strpos(
        $deleteOp['responses']['409']['description'],
        'retry'
    )
);

$t->finish();
