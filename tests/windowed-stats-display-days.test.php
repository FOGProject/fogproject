<?php
/**
 * A per-day series counts on the viewer's days, not on UTC days (#1935).
 *
 * Storage is UTC once the boundary is crossed. The rollups grouped on
 * DATE(column), which is the UTC day, so a run at 21:00 in New York counted
 * on the next day. The dashboard then sent each day as UTC midnight in epoch
 * milliseconds, which a browser west of UTC drew on the day before.
 *
 * Now each statement groups on a 15-minute slot, and dailySeries() moves the
 * slot to the display zone and takes the day there. This drives the real
 * ImagingStats::runsPerDay() with UTC storage and a display zone set, and
 * answers the query with slots from a fake connection. It checks three
 * zones that a fixed offset or DATE() gets wrong: an evening run west of
 * UTC, a window across the DST change, and a half-hour zone.
 *
 * Usage: php tests/windowed-stats-display-days.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Audit\ImagingStats;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('windowed-stats-display-days');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

// UTC storage: the boundary row exists and is in the past.
FogTestHarness::setStatic(
    'StorageEpoch',
    '_row',
    ['seBoundary' => '2026-01-01 00:00:00', 'seZone' => 'America/New_York']
);

/**
 * Run the rollup over a display-zone window, answering with $slots.
 *
 * @return array [series, the bounds the query was bound with]
 */
function seriesFor($zone, $from, $to, array $slots, $db)
{
    FogTestHarness::setStatic('FOGBase', 'TimeZone', $zone);
    $asked = [];
    $db->responder = function ($sql, $params) use (&$asked, $slots) {
        if (false === strpos($sql, 'taskLog')) {
            return null;
        }
        $asked = $params;
        $rows = [];
        foreach ($slots as $slot => $c) {
            $rows[] = ['d' => $slot, 'c' => (string)$c];
        }
        return $rows;
    };
    $tz = new \DateTimeZone($zone);
    $series = ImagingStats::runsPerDay(
        new \DateTimeImmutable($from . ' 00:00:00', $tz),
        new \DateTimeImmutable($to . ' 23:59:59', $tz)
    );
    return [array_column($series, 'count', 'date'), $asked];
}

// 1. The report: New York, a run in the afternoon and one in the evening.
[$days, $asked] = seriesFor(
    'America/New_York',
    '2026-10-21',
    '2026-10-23',
    [
        '2026-10-22 14:00' => 5,   // 10:00 EDT on the 22nd
        '2026-10-23 01:00' => 2,   // 21:00 EDT on the 22nd
        '2026-10-23 04:15' => 1,   // 00:15 EDT on the 23rd
    ],
    $db
);
$t->check(
    'the series walks the viewer\'s days',
    ['2026-10-21', '2026-10-22', '2026-10-23'] === array_keys($days)
);
$t->check(
    'an evening run west of UTC counts on its own day, not the next',
    7 === $days['2026-10-22']
);
$t->check(
    'a run just after the viewer\'s midnight counts on the new day',
    1 === $days['2026-10-23'] && 0 === $days['2026-10-21']
);
$t->check(
    'the window is bound in the storage zone',
    '2026-10-21 04:00:00' === ($asked[':start'] ?? null)
        && '2026-10-24 03:59:59' === ($asked[':end'] ?? null)
);

// 2. Across the DST change (New York leaves EDT on 1 Nov 2026). A fixed
//    offset puts one of these two on the wrong day.
[$days] = seriesFor(
    'America/New_York',
    '2026-10-31',
    '2026-11-01',
    [
        '2026-10-31 04:30' => 1,   // 00:30 EDT on the 31st
        '2026-11-02 04:30' => 1,   // 23:30 EST on the 1st
    ],
    $db
);
$t->check(
    'both sides of the DST change land on their own days',
    ['2026-10-31' => 1, '2026-11-01' => 1] === $days
);

// 3. A half-hour zone: India is UTC+05:30, so its midnight is 18:30 UTC.
[$days] = seriesFor(
    'Asia/Kolkata',
    '2026-10-22',
    '2026-10-23',
    [
        '2026-10-22 18:15' => 1,   // 23:45 IST on the 22nd
        '2026-10-22 18:30' => 3,   // 00:00 IST on the 23rd
    ],
    $db
);
$t->check(
    'a slot in a half-hour zone lands on the right side of midnight',
    ['2026-10-22' => 1, '2026-10-23' => 3] === $days
);

$t->finish();
