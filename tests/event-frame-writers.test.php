<?php
/**
 * ADR 0020 phase 3: the frame columns have writers, and the frame keys are
 * the ones the ADR fixed.
 *
 * Phase 2 added seven columns and mapped none of them, which was the point:
 * "an install that stops here is unchanged in behaviour". Phase 3 is the
 * inverse claim -- rows written after the upgrade carry both the prose and
 * the structure -- and it has a failure mode phase 2 did not: a column that
 * is mapped but never set looks exactly like a column that is filled, from
 * anywhere except the row itself.
 *
 * So this pins the two halves that CI can see without a database:
 *
 *   1. The models map the frame's friendly keys onto the phase 2 columns.
 *      Spelling matters more than it looks: FOGController::save() gives
 *      `createdBy` and `createdTime` their auto-fill by matching the KEY,
 *      so `userTracking` gets its actor for free purely by mapping
 *      utCreatedBy to `createdBy` and would silently get nothing under any
 *      other name. That is ADR 0020 decision 3, and it is one string.
 *
 *   2. Every FOGController call site that writes a history row passes a
 *      type. Four of them exist -- save success, save failure, destroy
 *      success, destroy failure -- and a fifth in FOGBase::log(). A call
 *      site that keeps writing prose with no frame is not an error and
 *      raises nothing; it just quietly produces a row phase 4's reader will
 *      fall back to prose for, forever.
 *
 * What this canNOT check is that a WRITE fills the columns, which needs a
 * database and a real save(). That is proven separately by
 * /home/telliott/labs/adr0020/prove_phase3.php against a lab copy at schema
 * 353 -- the counterpart to phase 2's prove_inert.php, and deliberately not
 * committed: it creates a host and writes log rows, which is not something
 * to leave one mistyped argument away from a production database.
 *
 * DB-free by construction: everything here is a class property, a class
 * constant or the text of a method, so it runs in CI where there is no
 * server. Same approach as relationship-filter-in-join.test.php.
 *
 * Usage: php tests/event-frame-writers.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('event-frame');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * One model's field map.
 *
 * @param string $class the class to read
 *
 * @return array friendly key => column
 */
function frameFields($class)
{
    $obj = FOGCore::getClass($class);
    $p = new \ReflectionProperty(get_class($obj), 'databaseFields');
    $p->setAccessible(true);
    return (array)$p->getValue($obj);
}

/*
 * 1. The maps. Asserted key BY key rather than as a whole array, so a
 *    failure names the one that moved instead of printing two maps.
 */
$expected = [
    'History' => [
        // hUser/hTime/hIP predate the ADR; the four below are phase 2's.
        'createdBy' => 'hUser',
        'createdTime' => 'hTime',
        'ip' => 'hIP',
        'type' => 'hType',
        'subjectType' => 'hSubjectType',
        'subjectID' => 'hSubjectID',
        'subjectLabel' => 'hSubjectLabel'
    ],
    'UserTracking' => [
        // No 'type': utAction already is one, and it is domain payload with
        // its own constants rather than the frame's event kind.
        'createdTime' => 'utDateTime',
        'createdBy' => 'utCreatedBy',
        'ip' => 'utIP',
        // The subject is the host. utHostID has always been subjectID in
        // everything but name; utHostName is the label that outlives it.
        'subjectLabel' => 'utHostName'
    ],
    'TaskLog' => [
        // Already correct before this ADR; here so that losing it fails.
        'createdBy' => 'createdBy',
        'createdTime' => 'createTime',
        'ip' => 'ip',
        'type' => 'logType',
        'text' => 'logText'
    ]
];
foreach ($expected as $class => $map) {
    $fields = frameFields($class);
    foreach ($map as $key => $column) {
        $t->check(
            "$class maps '$key' to `$column`",
            isset($fields[$key]) && $fields[$key] === $column
        );
    }
}

/*
 * 2. userTracking's actor is NOT set by any writer -- it is save()'s
 *    auto-fill, and a writer setting it would be writing the operator who
 *    happened to be signed in over the fog-client that caused the row.
 *    ADR 0020 decision 3 is the whole reason this table gained a separate
 *    actor column instead of reusing utUserName.
 */
$track = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Client/UserTrack.php'
);
$t->check(
    'UserTrack::json() does not set createdBy itself',
    false === strpos($track, "set('createdBy'")
);
$t->check(
    'UserTrack::json() sets the origin address',
    false !== strpos($track, "set('ip', self::\$remoteaddr)")
);
$t->check(
    'UserTrack::json() sets the denormalized host name',
    false !== strpos($track, "set('subjectLabel', self::\$Host->get('name'))")
);

/*
 * 3. The type constants exist and are distinct. Distinctness is the point:
 *    two constants sharing a value makes two kinds of event indistinguishable
 *    in the column that exists to tell them apart, and nothing else would say
 *    so.
 */
$types = [];
foreach (
    [
        'TYPE_UPDATE',
        'TYPE_UPDATE_FAILED',
        'TYPE_DELETE',
        'TYPE_DELETE_FAILED',
        'TYPE_LOG'
    ] as $const
) {
    $name = 'FOG\Items\History::' . $const;
    $t->check("History::$const is defined", defined($name));
    if (defined($name)) {
        $types[] = constant($name);
    }
}
$t->check(
    'the History type constants are distinct',
    count($types) === count(array_unique($types))
);

/*
 * 4. Every history writer passes a frame.
 *
 * Read as text rather than driven, because driving them needs a database
 * and a signed-in user -- and the thing being checked is a call site, which
 * text sees exactly. The count is asserted too: a fifth call site added
 * without a type would otherwise pass simply by not being looked at.
 */
$controller = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Base/FOGController.php'
);
$calls = preg_match_all('/self::logHistory\(/', $controller);
$t->check(
    "FOGController has its four logHistory call sites (found $calls)",
    4 === $calls
);
$typed = preg_match_all('/self::logHistory\(\s*\$msg,\s*\[\s*\'type\'/', $controller);
$t->check(
    "all four FOGController history writes pass a type (found $typed)",
    4 === $typed
);
foreach (
    [
        'TYPE_UPDATE',
        'TYPE_UPDATE_FAILED',
        'TYPE_DELETE',
        'TYPE_DELETE_FAILED'
    ] as $const
) {
    $t->check(
        "FOGController writes History::$const",
        false !== strpos($controller, 'History::' . $const)
    );
}

$base = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Base/FOGBase.php'
);
$t->check(
    'FOGBase::log() writes History::TYPE_LOG',
    false !== strpos($base, "logHistory(\$txt, ['type' => History::TYPE_LOG])")
);
$t->check(
    'logHistory() takes an optional frame',
    false !== strpos($base, 'protected static function logHistory($string, array $frame = [])')
);
/*
 * The frame is applied with array_key_exists, not isset, and not by
 * defaulting the missing keys. A null subjectID must reach save() as UNSET
 * so the column keeps its NULL default -- writing 0 there is a real id
 * pointing at nothing, and `history` has no foreign key to catch it.
 */
$t->check(
    'an absent frame key is skipped rather than defaulted',
    false !== strpos($base, "if (!array_key_exists(\$k, \$frame)) {")
);

$t->finish();
