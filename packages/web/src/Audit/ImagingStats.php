<?php
/**
 * How many machines were imaged, and when.
 *
 * PHP version 7.4+
 *
 * @category ImagingStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

use FOG\Base\FOGBase;

/**
 * How many machines were imaged, and when.
 *
 * ADR 0030 decisions 2 and 3. Counting an imaging run out of `taskLog` takes
 * three rules that are not obvious from the schema, and every one of them
 * fails SILENTLY -- the query still returns a number, and a wrong count is
 * indistinguishable from a right one by looking at it. They lived as a
 * comment inside DashboardPage::get30day(); this is the one place they live
 * now, and get30day() is a caller.
 *
 * WHY THE RULES EXIST. ADR 0022 decision 3 retired `imagingLog` and made
 * `taskLog` the record of what was imaged. imagingLog held one row per RUN;
 * taskLog holds one row per state TRANSITION. So:
 *
 *   fold to one row per task     a task that moved through three states
 *                                is otherwise counted as three images
 *   exclude the canceled state   TaskLog::recordState() writes logImageName
 *                                on every transition of an imaging task,
 *                                cancellation included, so a deploy that
 *                                was queued and then canceled without ever
 *                                starting carries an image name on its only
 *                                row. Counting it says a machine was imaged
 *                                when none was touched. A task canceled MID
 *                                image still has its In-Progress row and
 *                                still counts, which is the answer wanted
 *   attribute to MIN(createTime) a run that starts before midnight and
 *                                finishes after it writes rows on two days,
 *                                and would otherwise be counted on both
 *
 * `logImageName <> ''` is what makes a row an imaging one, rather than a
 * task type name -- a site can rename a task type, and several do.
 *
 * BOUNDS MUST BE ON FOG'S CLOCK. `taskLog.createTime` is stamped by
 * FOGController::save() through FOGBase::niceDate(), which uses the
 * configured FOG timezone. A bound built with PHP's own date() is offset by
 * however far apart the two are, and it does not error -- BETWEEN just
 * matches a shifted window and the answer is to a question nobody asked.
 * Run History was caught by exactly this in a lab five hours out, where a
 * task created seconds earlier did not appear in a window ending "now". Get
 * bounds from FOGBase::niceDate(), which is what every caller here does.
 *
 * The window is the only filter, so it is also the only bound -- see
 * MAX_DAYS. Schema step 379 indexes `taskLog.createTime` so finding the
 * range is a range scan rather than a scan of the whole table.
 *
 * @category ImagingStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImagingStats extends FOGBase
{
    /**
     * The longest window one call will read.
     *
     * A window is a filter and not a limit: "the last year" on a server
     * mid-rollout is every imaging row it has. 366 rather than 365 because
     * the dashboard's widest view is a year and a leap year is 366 days --
     * a cap that clips the widest shipped view would be a bug reported as
     * "the 1 Year graph is missing a day".
     *
     * @var int
     */
    const MAX_DAYS = 366;

    /**
     * One row per imaging run, with the day it started on.
     *
     * Kept separate from runsPerDay() so a test can run the real statement
     * rather than a copy of it -- the same reason TaskManagement has
     * _logQueryFrom(). The three rules in the class docblock are all here,
     * and a test that cannot reach this method can only pin that they were
     * once written down.
     *
     * The inner query folds a task's transition rows to one and takes its
     * earliest; the outer one counts the folded runs per day. Both the
     * range and the canceled state are bound, never interpolated.
     *
     * @return string SQL taking :start, :end and :canceled.
     */
    private static function _runsPerDaySql()
    {
        return "SELECT DATE(`started`) AS `d`, COUNT(*) AS `c`
                  FROM (
                    SELECT `taskID`, MIN(`createTime`) AS `started`
                      FROM `taskLog`
                     WHERE `createTime` BETWEEN :start AND :end
                       AND `logImageName` <> ''
                       AND `taskStateID` <> :canceled
                     GROUP BY `taskID`
                  ) AS `runs`
                 GROUP BY DATE(`started`)";
    }

    /**
     * Imaging runs per day across a window, with no gaps.
     *
     * ZERO FILLED, which is part of the contract rather than a courtesy to
     * one caller. A day on which nothing was imaged produces no row, and a
     * series with a missing day is drawn by every chart library as a line
     * straight across it -- so an idle week reads as steady activity. The
     * series returned here always has one entry per day in the window, in
     * order, so a quiet day is a point at zero.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    public static function runsPerDay(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        list($start, $end) = self::_clamp($start, $end);

        $rows = self::$DB->query(
            self::_runsPerDaySql(),
            [],
            [
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s'),
                ':canceled' => self::getCancelledState()
            ]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $counts = [];
        foreach ((array)$rows as $row) {
            if (isset($row['d'])) {
                $counts[$row['d']] = (int)$row['c'];
            }
        }

        // DatePeriod's end is EXCLUSIVE, so the last day of the window would
        // be dropped without this. The bound is moved rather than the period
        // extended, because $end carries a time of day the caller chose and
        // adding a day to it would reach into the day after.
        $through = (new \DateTimeImmutable($end->format('Y-m-d')))
            ->modify('+1 day');
        $period = new \DatePeriod(
            new \DateTimeImmutable($start->format('Y-m-d')),
            new \DateInterval('P1D'),
            $through
        );

        $series = [];
        foreach ($period as $day) {
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'count' => isset($counts[$key]) ? $counts[$key] : 0
            ];
        }

        return $series;
    }

    /**
     * The window, ordered and capped.
     *
     * Reversed rather than rejected when the bounds arrive the other way
     * round: somebody who passes the two dates backwards means the range
     * between them, and returning nothing looks exactly like "nothing was
     * imaged".
     *
     * Capped by moving the START, not the end. A caller asking for more than
     * MAX_DAYS wants the recent end of it -- clipping the other way would
     * answer with the oldest window and no indication that it had.
     *
     * @param \DateTimeInterface $start the lower bound as given
     * @param \DateTimeInterface $end   the upper bound as given
     *
     * @return array [\DateTimeImmutable, \DateTimeImmutable]
     */
    private static function _clamp(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $lo = new \DateTimeImmutable($start->format('Y-m-d H:i:s'));
        $hi = new \DateTimeImmutable($end->format('Y-m-d H:i:s'));
        if ($lo > $hi) {
            list($lo, $hi) = [$hi, $lo];
        }

        // Counted in whole days between the two dates, matching what the cap
        // is expressed in -- a window of "today 00:00 to today 23:59" is one
        // day, not zero.
        $span = (int)$lo->setTime(0, 0, 0)
            ->diff($hi->setTime(0, 0, 0))
            ->days + 1;
        if ($span > self::MAX_DAYS) {
            $lo = new \DateTimeImmutable(
                $hi->modify('-' . (self::MAX_DAYS - 1) . ' days')
                    ->format('Y-m-d') . ' ' . $lo->format('H:i:s')
            );
        }

        return [$lo, $hi];
    }
}
