<?php
/**
 * The UTC storage boundary must stay a boundary.
 *
 * From schema step 396 an install stores dates in UTC and shows them in the
 * reader's zone. Nothing already stored is converted, because up to five
 * clocks have written FOG's date columns and no sweep can know which wrote
 * any given row. So the instant the convention changed is RECORDED, and
 * every value on its way to a screen is compared against it.
 *
 * Every failure in this area is silent and looks like data. A value wrong by
 * a whole number of hours is indistinguishable from a correct one in
 * isolation, there is no error, nothing is logged, and the reader has no way
 * to tell. That is why the invariants are pinned here rather than left to
 * review.
 *
 * Usage: php tests/utc-storage-boundary.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$base = file_get_contents($root . '/packages/web/src/Base/FOGBase.php');
$epoch = file_get_contents($root . '/packages/web/src/Base/StorageEpoch.php');
$pdodb = file_get_contents($root . '/packages/web/src/Db/PDODB.php');
$mgr = file_get_contents(
    $root . '/packages/web/src/Base/FOGManagerController.php'
);
$render = file_get_contents(
    $root . '/packages/web/src/Base/FOGPageRender.php'
);
$schema = file_get_contents($root . '/packages/web/commons/schema.php');
$system = file_get_contents($root . '/packages/web/src/Base/System.php');

$fails = [];

// ---------------------------------------------------- the flip itself

// Anchored on the whole guard. A check for the string 'UTC' anywhere in
// storageTimeZone() passes on the pre-existing fallback two lines below it,
// which is the one thing this must not be confused with: that fallback fires
// when FOG_TZ_INFO is UNSET, and it always has.
if (!preg_match(
    '#function storageTimeZone\(\)\s*\{.*?'
    . 'if \(class_exists\(StorageEpoch::class\) && StorageEpoch::active\(\)\) \{\s*'
    . "return new \\\\DateTimeZone\('UTC'\);#s",
    $base
)) {
    $fails[] = 'storageTimeZone() no longer answers UTC once the boundary '
        . 'row exists, so new writes would go back to FOG_TZ_INFO while '
        . 'everything reading them assumes UTC';
}

// The display default must NOT be the storage zone. This is the one line
// that decides what every user who has never opened their preferences sees:
// pointed at storageTimeZone() it shows them UTC.
if (!preg_match(
    '#function displayTimeZone\(\).*?\$storage = self::defaultDisplayTimeZone\(\);#s',
    $base
)) {
    $fails[] = 'displayTimeZone() no longer defaults to '
        . 'defaultDisplayTimeZone(); every reader without a preference '
        . 'would be shown UTC instead of the install\'s own zone';
}
if (!preg_match(
    '#function defaultDisplayTimeZone\(\)\s*\{.*?'
    . 'return new \\\\DateTimeZone\(self::\$TimeZone\);#s',
    $base
)) {
    $fails[] = 'defaultDisplayTimeZone() no longer reads FOG_TZ_INFO';
}

// ------------------------------------------------- the database session

if (!preg_match('#self::_pinSessionZone\(\);#', $pdodb)) {
    $fails[] = 'PDODB no longer pins the session zone on connect, so NOW() '
        . 'and DEFAULT current_timestamp() go back to the database host\'s '
        . 'own zone while PHP writes UTC';
}
// The drain is load bearing, not tidiness: this link is unbuffered, so a
// statement left open makes the SET throw, and the method's own catch then
// swallows it. The failure is invisible -- the session simply stays on
// SYSTEM.
if (!preg_match(
    '#function _pinSessionZone\(\).*?'
    . '\$rows = \$st->fetchAll\(\\\\PDO::FETCH_NUM\);\s*'
    . '\$st->closeCursor\(\);.*?'
    . 'SET SESSION time_zone = \'\+00:00\'#s',
    $pdodb
)) {
    $fails[] = '_pinSessionZone() no longer drains and closes its gate '
        . 'query before the SET; on an unbuffered link that throws, the '
        . 'catch swallows it, and the session silently stays on SYSTEM';
}

// -------------------------------------------------- the classification

// A TIMESTAMP column has always held a UTC instant and can never be
// pre-boundary. Getting this backwards moves every old timestamp by the
// offset -- the single most damaging thing in this change.
if (!preg_match(
    '#function isPreBoundary\(\$value, \$isDatetime = true\)\s*\{\s*'
    . 'if \(!\$isDatetime \|\| !self::active\(\)\) \{\s*return false;#s',
    $epoch
)) {
    $fails[] = 'isPreBoundary() no longer exempts non-DATETIME columns; a '
        . 'TIMESTAMP column always held a UTC instant, so treating one as '
        . 'pre-boundary shifts every old row by the offset';
}
if (!preg_match('#const BAND_HOURS = 26;#', $epoch)) {
    $fails[] = 'the 26-hour band is gone; UTC-12 to UTC+14 is the full span '
        . 'of real offsets, and a narrower band lets a value written just '
        . 'before the boundary be read as though it were after it';
}
// Never invents a boundary. Every failure path has to answer "no boundary",
// which is the pre-change behavior; answering "yes" on an install that never
// migrated would claim its values are UTC when they are not.
if (!preg_match(
    '#function active\(\)\s*\{\s*return null !== self::_row\(\);#s',
    $epoch
)) {
    $fails[] = 'StorageEpoch::active() is no longer decided by the row '
        . 'alone';
}

// ------------------------------------------------------ the read paths

// Both display chokepoints, and both must pass the column type through.
if (!preg_match(
    '#\$row\[\$key\] = self::toDisplayStored\(\s*'
    . '\(string\)\$row\[\$key\],\s*\$isDatetime\s*\)#s',
    $mgr
)) {
    $fails[] = 'the grid no longer renders dates through toDisplayStored() '
        . 'with the column type, so a pre-boundary value would be read as '
        . 'UTC and shown at the wrong hour';
}
// The early return this replaced compared the two zones and skipped the
// whole thing when they matched -- which is exactly the reader (no
// preference, post-upgrade install) a pre-boundary value must NOT be
// converted for.
if (preg_match(
    '#function displayDates\(.*?if \(self::displayTimeZone\(\)->getName\(\)#s',
    $mgr
)) {
    $fails[] = 'displayDates() has its zones-match early return back; that '
        . 'skips pre-boundary handling for every reader without a '
        . 'preference';
}
if (!preg_match(
    '#function dateOrNever\(\$value, \$table = \'\', \$column = \'\'\)#',
    $render
)) {
    $fails[] = 'dateOrNever() no longer takes the table and column it needs '
        . 'to tell a DATETIME from a TIMESTAMP';
}
// Every core caller has to hand them over, or the default silently decides.
foreach ([
    'HostManagement' => 4,
    'ImageManagement' => 1,
] as $page => $expected) {
    $src = file_get_contents(
        $root . '/packages/web/src/Pages/' . $page . '.php'
    );
    $hinted = preg_match_all(
        // The PROPERTY name is [A-Za-z], not [a-z]: most of Host's friendly
        // names are lowercase but not all of them are -- 'agentCheckin' is
        // camelCase in Host::$databaseFields -- and a hinted call was
        // reported as unhinted purely for its casing.
        "#dateOrNever\(\s*\\\$this->obj->get\('[A-Za-z]+'\),\s*'[a-z]+',\s*'[A-Za-z]+'\s*\)#s",
        $src
    );
    $all = preg_match_all('#self::dateOrNever\(#', $src);
    if ($hinted < $expected || $hinted !== $all) {
        $fails[] = sprintf(
            '%s.php has %d dateOrNever() calls and %d of them name '
            . 'their table and column; an unhinted one is assumed DATETIME',
            $page,
            $all,
            $hinted
        );
    }
}

// ------------------------------------------------------- the migration

if (!preg_match(
    '#CREATE TABLE IF NOT EXISTS `storageEpoch`#',
    $schema
)) {
    $fails[] = 'schema.php no longer creates the storageEpoch table';
}
// Written once and never again. Moving the boundary re-interprets every
// date in the database, so the guard is the whole safety of the step.
if (!preg_match(
    '#SELECT COUNT\(`seID`\) AS `c` FROM `storageEpoch`.*?'
    . 'if \(\(int\)\$existing > 0\) \{\s*return true;#s',
    $schema
)) {
    $fails[] = 'the boundary insert is no longer guarded by a row count; '
        . 're-running the step would move the boundary and silently '
        . 're-interpret every stored date';
}
// AT LEAST 396, not exactly 396. The invariant is that the gate reaches the
// boundary step so it can run; pinning equality instead made this test fail
// on the next schema step anybody appended, which says nothing about the
// boundary and trains people to edit the assertion rather than read it.
if (!preg_match("#define\('FOG_SCHEMA', (\d+)\);#", $system, $gate)
    || (int)$gate[1] < 396
) {
    $fails[] = 'FOG_SCHEMA is below 396, so step 396 never runs and the '
        . 'boundary is never recorded';
}
$manifest = require $root . '/packages/web/commons/schema-expected.php';
if (!isset($manifest['tables']['storageEpoch']['columns']['seBoundary'])) {
    $fails[] = 'storageEpoch is missing from the schema manifest, so the '
        . 'reconciler would not create it on an install that skipped the '
        . 'step';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: the UTC storage boundary holds -- the flip, the session, the "
    . "classification, both read paths and the migration\n";
exit(0);
