<?php
/**
 * A mass edit clears a foreign-key column to NULL, never to a sentinel.
 *
 * THE BUG THIS EXISTS FOR. `massEditCoreFields()` declared the Image field
 * as clearing to `0`. `hosts`.`hostImage` carries a foreign key to
 * `images`.`imageID` (ADR 0031), and 0 is NOT exempt from a constraint just
 * because it reads as an absence -- no image has id 0, so the database
 * refused the UPDATE and "Clear on all" on Image failed outright. The column
 * is nullable and SchemaReconciler had already swept every legacy 0 to NULL
 * when it added the constraint, so NULL is what "no image" actually IS.
 *
 * WHY THE LIST IS DERIVED RATHER THAN WRITTEN DOWN HERE. Twelve columns
 * carry the same shape -- a foreign key plus a `'sentinel' => 0` entry in
 * commons/schema-constraints.php saying 0 was once its "none" value. Every
 * one of them rejects a literal 0 today. A hand-written list would be a
 * second place that has to agree with the constraint file, and the failure
 * when it did not agree would be exactly this bug again, silently. So the
 * constraint file is read, the column is mapped back to its FOGController
 * field name through the Items' own $databaseFields, and any mass-edit spec
 * naming one of those fields is required to clear it to null.
 *
 * A NEW field added to the spec for an FK-bearing column fails here on the
 * day it is added rather than on the day somebody clears it.
 *
 * The second half is executed rather than grepped: columnUpdates() used
 * `$spec[$key]['empty'] ?? ''`, and `??` treats null as absent -- so even
 * with the spec corrected, the coalesce turned the intended NULL straight
 * back into '', which an int column stores as 0. The fix and the spec change
 * are worthless without each other, so both are checked.
 *
 * Usage: php tests/fk-columns-clear-to-null.test.php
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

require_once __DIR__ . '/lib/fog-test-harness.php';

// boot() owns the temp dirs, the constants, Initiator and the language
// globals -- the same preamble every other test in the suite uses, rather
// than a hand-rolled copy that drifts from it.
FogTestHarness::boot('fk-clear');

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';

use FOG\Util\MassEdit;

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

// --- The FK-bearing columns, straight off the constraint file --------------

$constraintFile = $webroot . '/commons/schema-constraints.php';
$constraintSrc = (string)file_get_contents($constraintFile);
$check(
    'the constraint file is readable',
    '' !== $constraintSrc
);

// Every relation that declares a 0 sentinel: the constraint file saying so
// IS the statement that 0 used to mean "none" here and no longer may.
preg_match_all(
    "/'child'\s*=>\s*'([^']+)'\s*,\s*'column'\s*=>\s*'([^']+)'"
    . "(?=[^\]]*'sentinel'\s*=>\s*0)/",
    $constraintSrc,
    $m,
    PREG_SET_ORDER
);
$sentinelColumns = [];
foreach ($m as $hit) {
    $sentinelColumns[strtolower($hit[2])] = $hit[1];
}
$check(
    'the constraint file still declares sentinel columns ('
    . count($sentinelColumns) . ' found)',
    count($sentinelColumns) > 0
);

// --- Map each column back to the field name a spec would name --------------

// Scoped to ONE Item class, because a field name is only unique within one.
// `imageID` is `hosts`.`hostImage` on Host and `tasks`.`taskImageID` on Task,
// and a global map keyed on the field name silently picks whichever file was
// read last -- which would name the wrong column in a failure message and,
// worse, would flag a field that is a plain int here because it is a foreign
// key somewhere else.
$fieldMap = static function ($itemClass) use ($webroot) {
    $src = (string)@file_get_contents(
        $webroot . '/src/Items/' . $itemClass . '.php'
    );
    $map = [];
    if ('' === $src
        || !preg_match(
            '/\$databaseFields\s*=\s*\[(.*?)\n    \];/s',
            $src,
            $block
        )
    ) {
        return $map;
    }
    preg_match_all(
        "/'([A-Za-z0-9_]+)'\s*=>\s*'([A-Za-z0-9_]+)'/",
        $block[1],
        $pairs,
        PREG_SET_ORDER
    );
    foreach ($pairs as $pair) {
        $map[$pair[1]] = strtolower($pair[2]);
    }
    return $map;
};

// massEditCoreFields() names Host's fields, so Host's map is the right one.
$hostFields = $fieldMap('Host');
$check(
    'Host\'s databaseFields map was parsed (' . count($hostFields) . ' fields)',
    count($hostFields) > 10
);
$fieldsForColumn = [];
foreach ($hostFields as $field => $column) {
    if (isset($sentinelColumns[$column])) {
        $fieldsForColumn[$field] = $column;
    }
}
$check(
    'at least one sentinel column maps to a field name ('
    . implode(', ', array_keys($fieldsForColumn)) . ')',
    count($fieldsForColumn) > 0
);
$check(
    'imageID maps to `hosts`.`hostImage` -- the field the original bug was on',
    ($fieldsForColumn['imageID'] ?? null) === 'hostimage'
);

// --- No mass-edit spec may clear one of them to anything but null ---------

$class = \FOG\Pages\HostManagement::class;
$page = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
$call = static function ($method) use ($class, $page) {
    $m = new \ReflectionMethod($class, $method);
    $m->setAccessible(true);
    return $m->invoke($page);
};
$core = $call('massEditCoreFields');

$wrong = [];
$guarded = 0;
foreach ($core as $key => $spec) {
    if (!isset($spec['field']) || !isset($fieldsForColumn[$spec['field']])) {
        continue;
    }
    $guarded++;
    // array_key_exists, not isset: a spec that correctly says null must not
    // read here as a spec that forgot to say anything.
    if (!array_key_exists('empty', $spec) || null !== $spec['empty']) {
        $wrong[] = $key . ' (' . $spec['field'] . ' -> `'
            . $fieldsForColumn[$spec['field']] . '`)';
    }
}
$check(
    'the spec actually covers a foreign-key field, or this test proves nothing',
    $guarded > 0
);
$check(
    'every foreign-key field clears to NULL, not to a sentinel ('
    . implode(', ', $wrong) . ')',
    0 === count($wrong)
);

// --- columnUpdates() must PRESERVE that null ------------------------------

$resolved = MassEdit::resolve(
    ['image'],
    ['image' => MassEdit::CLEAR],
    []
);
$check(
    'a clear with no posted value still resolves to CLEAR',
    MassEdit::CLEAR === $resolved['image']['action']
);
$updates = MassEdit::columnUpdates($resolved, $core);
$check(
    'clearing the image reaches the column map at all',
    array_key_exists('imageID', $updates)
);
$check(
    'and reaches it as NULL -- `?? \'\'` would have made it the empty string,'
    . ' which an int column stores as the 0 the constraint refuses',
    array_key_exists('imageID', $updates) && null === $updates['imageID']
);

// A spec that genuinely omits `empty` still gets the empty string, so the
// change above did not quietly turn every unspecified field into NULL.
$plain = MassEdit::columnUpdates(
    MassEdit::resolve(['thing'], ['thing' => MassEdit::CLEAR], []),
    ['thing' => ['field' => 'thing']]
);
$check(
    'a spec with no `empty` at all still clears to the empty string',
    array_key_exists('thing', $plain) && '' === $plain['thing']
);
// And an explicit 0 is still an explicit 0, for the plain int columns that
// genuinely clear that way.
$zero = MassEdit::columnUpdates(
    MassEdit::resolve(['lvl'], ['lvl' => MassEdit::CLEAR], []),
    ['lvl' => ['field' => 'lvl', 'empty' => 0]]
);
$check(
    'an explicit 0 empty is still written as 0',
    array_key_exists('lvl', $zero) && 0 === $zero['lvl']
);

// --- The write must not report success when the database refused ----------

$source = (string)file_get_contents($webroot . '/src/Pages/HostManagement.php');
$check(
    'massEditPost() checks the update return rather than discarding it',
    1 === preg_match(
        '/if\s*\(\s*!\s*self::getClass\(\s*.HostManager.\s*\)\s*'
        . '->update\(\s*\[\s*.id.\s*=>\s*\$hosts\s*\]/s',
        $source
    )
);

if (count($failures)) {
    fwrite(
        STDERR,
        "FAIL: a mass edit would write a sentinel into a foreign-key column:\n"
    );
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  foreign-key columns clear to NULL: %d checks\n", $checks);
exit(0);
