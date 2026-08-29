<?php
/**
 * Storage and display are two different timezones, and the code must not
 * confuse them.
 *
 * FOG_TZ_INFO is not a display setting despite the name: niceDate() uses it to
 * INTERPRET a stored string, and every site that writes a timestamp formats
 * through the same call, so it is the zone the database is written in.
 * Showing a user times in their own zone therefore means converting on the way
 * OUT, and converting back on the way IN, while leaving writes exactly where
 * they were.
 *
 * Each way of getting that wrong is silent:
 *
 *  - niceDate() converting to the display zone would make every NEW timestamp
 *    land in whichever user happened to trigger it, so two users writing the
 *    same event would store two different times and neither would look wrong
 *    on screen. That is data corruption that only shows up in comparisons.
 *  - formatTime() comparing a time in one zone against 'now' in another makes
 *    the "today / yesterday / runs today" branches straddle a day boundary, so
 *    a task that ran this afternoon reads "Ran Yesterday".
 *  - a grid that SHOWS the viewer's zone but FILTERS in the storage zone
 *    answers a different question than the one on screen; near midnight it
 *    returns the wrong day and looks like missing rows.
 *  - converting REST responses would make a script's answer depend on whose
 *    token it used, with no zone marker on the value to tell.
 *
 * Usage: php tests/display-timezone-split.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$base = file_get_contents($web . '/src/Base/FOGBase.php');
$manager = file_get_contents($web . '/src/Base/FOGManagerController.php');
$render = file_get_contents($web . '/src/Base/FOGPageRender.php');

$fails = [];

/**
 * Isolates one method's body so a match elsewhere in a 3000-line class cannot
 * stand in for the thing being tested, with its comments stripped so a gate
 * cannot pass on its own documentation.
 *
 * @param string $src  The file contents.
 * @param string $sig  The method signature to find.
 * @param string $name What to call it in a failure message.
 *
 * @return string
 */
function tzMethod($src, $sig, $name)
{
    global $fails;
    $pattern = '#' . preg_quote($sig, '#') . '.*?\n    \}\n#s';
    if (!preg_match($pattern, $src, $m)) {
        $fails[] = "$name not found -- the gate cannot see its subject";

        return '';
    }

    return preg_replace('#^\s*//.*$#m', '', $m[0]);
}

// ------------------------------------------------- writes do not move

$niceDate = tzMethod(
    $base,
    "public static function niceDate(\$date = 'now', \$utc = false)",
    'FOGBase::niceDate()'
);
if ('' !== $niceDate) {
    if (!preg_match('#self::storageTimeZone\(\)#', $niceDate)) {
        $fails[] = 'niceDate() does not interpret through storageTimeZone()';
    }
    if (preg_match('#displayTimeZone\(\)|toDisplay\(#', $niceDate)) {
        $fails[] = 'niceDate() reaches for the DISPLAY zone. It is what the '
            . 'write sites format through, so every new timestamp would be '
            . 'stored in whichever user triggered it';
    }
}

// -------------------------------------------------------- the two zones

foreach (['storageTimeZone', 'displayTimeZone', 'toDisplay', 'displayToStorage'] as $fn) {
    if (!preg_match('#public static function ' . $fn . '\(#', $base)) {
        $fails[] = "FOGBase::$fn() is missing";
    }
}

$display = tzMethod(
    $base,
    'public static function displayTimeZone()',
    'FOGBase::displayTimeZone()'
);
if ('' !== $display) {
    if (!preg_match('#self::storageTimeZone\(\)#', $display)) {
        $fails[] = 'displayTimeZone() does not fall back to the install '
            . 'default; an account with no preference must be unaffected';
    }
    if (!preg_match('#self::TIMEZONE_PREF#', $display)) {
        $fails[] = 'displayTimeZone() does not read the preference key';
    }
    // A user-supplied zone name reaching DateTimeZone unguarded is a fatal on
    // every page that shows a date.
    if (!preg_match('#catch \(\\\\Exception \$e\)#', $display)) {
        $fails[] = 'displayTimeZone() does not guard the stored zone name; an '
            . 'unusable value would throw on every page that shows a date';
    }
}

$toDisplay = tzMethod(
    $base,
    "public static function toDisplay(\$date = 'now')",
    'FOGBase::toDisplay()'
);
if ('' !== $toDisplay) {
    if (!preg_match('#setTimezone\(self::displayTimeZone\(\)\)#', $toDisplay)) {
        $fails[] = 'toDisplay() does not move the value into the display zone';
    }
    if (!preg_match('#validDate#', $toDisplay)) {
        $fails[] = 'toDisplay() does not exempt an invalid/zero date; shifting '
            . 'one moves it across a day boundary and changes how it renders';
    }
}

// ---------------------------------------------------- formatTime output

$format = tzMethod(
    $base,
    'public static function formatTime($time, $format = false, $utc = false)',
    'FOGBase::formatTime()'
);
if ('' !== $format) {
    if (!preg_match('#if \(!\$utc\) \{\s*\$time = self::toDisplay\(\$time\);#s', $format)) {
        $fails[] = 'formatTime() does not render in the display zone (and an '
            . 'explicit $utc must still win)';
    }
    // $now has to move with $time or the calendar-day comparisons straddle.
    if (!preg_match("#\\\$now = \\\$utc\s*\?\s*self::niceDate\('now', true\)\s*:\s*self::toDisplay\('now'\);#s", $format)) {
        $fails[] = "formatTime()'s 'now' is not resolved in the same zone as "
            . 'the value it is compared against; the today/yesterday branches '
            . 'would straddle a day boundary';
    }
}

$dateOrNever = tzMethod(
    $render,
    'protected static function dateOrNever($value)',
    'FOGPageRender::dateOrNever()'
);
if ('' !== $dateOrNever && !preg_match('#self::toDisplay\(\$value\)#', $dateOrNever)) {
    $fails[] = 'dateOrNever() does not convert; niceDate() formatted straight '
        . 'back hands you the stored string, so no form date would ever move';
}

// ------------------------------------------------------------ grid cells

$displayDates = tzMethod(
    $manager,
    'public static function displayDates($rows, $types)',
    'FOGManagerController::displayDates()'
);
if ('' !== $displayDates) {
    if (!preg_match('#Route::\$apiRequest#', $displayDates)) {
        $fails[] = 'displayDates() does not exempt REST responses; a script '
            . 'would get a different answer depending on whose token it used';
    }
    if (!preg_match('#displayTimeZone\(\)->getName\(\)\s*===\s*self::storageTimeZone\(\)->getName\(\)#s', $displayDates)) {
        $fails[] = 'displayDates() does not short-circuit when the zones agree, '
            . 'which is every account that has not set a preference';
    }
    if (!preg_match('#validDate#', $displayDates)) {
        $fails[] = 'displayDates() does not exempt the zero date';
    }
}
if (!preg_match('#self::displayDates\(\s*self::dataOutput\(\$columns, \$data\),#s', $manager)) {
    $fails[] = 'the grid payload is not passed through displayDates(), so a '
        . 'chosen timezone would apply everywhere except the lists, which is '
        . 'where the timestamps people read actually live';
}

// --------------------------------------------------------- filter bounds

$sbDate = tzMethod(
    $manager,
    'private static function _sbDate($col, $condition, $values, &$bindings)',
    'FOGManagerController::_sbDate()'
);
if ('' !== $sbDate) {
    if (!preg_match("#\\\$lower = self::displayToStorage\(\\\$dates\[0\] \. ' 00:00:00'\);#", $sbDate)) {
        $fails[] = "the filter's lower bound is not converted from the "
            . "viewer's zone; the grid would filter a different day than it shows";
    }
    if (!preg_match('#\$upper = self::displayToStorage\(#', $sbDate)) {
        $fails[] = "the filter's upper bound is not converted from the "
            . "viewer's zone";
    }
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: storage and display timezones stay separate -- writes, output, "
    . "grid cells and filter bounds all pinned\n";
exit(0);
