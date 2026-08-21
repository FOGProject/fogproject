<?php
/**
 * An empty date reads as "no value", not as the current time.
 *
 * new \DateTime('') and new \DateTime(null) both return the CURRENT time, so
 * FOGBase::niceDate() used to answer "now" for a column holding nothing. The
 * zero date FOG actually stores parses to year -0001 instead, which
 * validDate() rejects and formatTime() renders as "No Data" -- so the two
 * spellings of "this never happened" rendered as opposites, and only the one
 * that is about to go away rendered correctly.
 *
 * That matters now because the date columns are moving to NULL (GH-1245), and
 * FOGController::get() hands back '' for a NULL column -- isset() is false for
 * null. Without this normalisation, making a column nullable would silently
 * turn every "No Data" into the current timestamp: no error, no log line, a
 * plausible value in every grid. It is already wrong for the three columns
 * that are nullable today, tasks.stateChangedTime among them.
 *
 * The static half guards the seam. Eight call sites passed '' to formatTime()
 * meaning "now" -- five of them building a backup or export FILENAME, which
 * would have become "fog_backup_No Data.sql". They now say 'now', which is
 * niceDate()'s own default spelling, and nothing may reintroduce the empty
 * literal.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = [];
$checks = 0;

// ---------------------------------------------------------------
// 1. Behaviour: empty, null and the zero date all mean the same thing.
// ---------------------------------------------------------------
require_once $web . '/lib/fog/fogbase.class.php';

$zero = FOGBase::niceDate('0000-00-00 00:00:00')->format('Y-m-d H:i:s');
foreach ([['', "empty string"], [null, 'null']] as $case) {
    list($in, $label) = $case;
    $checks++;
    $got = FOGBase::niceDate($in)->format('Y-m-d H:i:s');
    if ($got !== $zero) {
        $failures[] = sprintf(
            'niceDate(%s) gave %s; the zero date gives %s, and the two have '
            . 'to agree or a nullable column renders differently from a '
            . 'zero-dated one',
            $label,
            $got,
            $zero
        );
    }
}

// The failure this exists to prevent, stated directly.
$checks++;
if (FOGBase::niceDate('')->format('Y') === FOGBase::niceDate('now')->format('Y')) {
    $failures[] = 'niceDate(\'\') resolved to the current time -- an empty '
        . 'date column would render as a real timestamp';
}

// What the display layer does with it, which is the whole point.
foreach ([['', "empty string"], [null, 'null'], ['0000-00-00 00:00:00', 'zero date']] as $case) {
    list($in, $label) = $case;
    $checks++;
    $got = FOGBase::formatTime($in);
    if ($got !== 'No Data') {
        $failures[] = sprintf(
            'formatTime(%s) rendered "%s", expected "No Data"',
            $label,
            $got
        );
    }
}

// 'now' still means now, in both the formatted and the relative arm.
$checks++;
if (FOGBase::formatTime('now', 'Y') !== date('Y')) {
    $failures[] = 'formatTime(\'now\', \'Y\') did not give the current year';
}
$checks++;
if (FOGBase::formatTime('now') !== 'Moments ago') {
    $failures[] = 'formatTime(\'now\') did not give "Moments ago"';
}

// ---------------------------------------------------------------
// 2. The seam stays reserved: nothing passes '' meaning "now".
// ---------------------------------------------------------------
$dirs = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web, \FilesystemIterator::SKIP_DOTS)
);
foreach ($dirs as $file) {
    if ('php' !== strtolower($file->getExtension())) {
        continue;
    }
    $path = $file->getPathname();
    if (false !== strpos($path, '/lib/plugins/')) {
        continue;
    }
    $lines = file($path);
    foreach ($lines as $n => $line) {
        if (preg_match('/\b(niceDate|formatTime)\(\s*(\'\'|"")\s*[,)]/', $line)) {
            $failures[] = sprintf(
                '%s:%d passes an empty literal, which now means "no value": '
                . '%s',
                substr($path, strlen($root) + 1),
                $n + 1,
                trim($line)
            );
        }
    }
    $checks++;
}

if (count($failures) > 0) {
    echo "FAIL nicedate-empty-is-no-value (" . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS nicedate-empty-is-no-value ($checks checks)\n";
exit(0);
