<?php
/**
 * Which snapins ran, where, and whether they worked.
 *
 * PHP version 7.4+
 *
 * @category SnapinStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

/**
 * Which snapins ran, where, and whether they worked.
 *
 * ADR 0030's snapin subject. Simpler than the imaging one because
 * `snapinTasks` already holds one row per RUN -- there is no fold, and the
 * three counting rules imaging needs do not apply here.
 *
 * THE OUTCOME IS `stReturnCode`, NOT `stState`, and that is the one decision
 * in this class worth arguing about. ADR 0022 decision 2 says state is
 * authoritative for WHAT happened, and it is -- for the LIFECYCLE. It says
 * whether the run is queued, checked in, complete or canceled. It does not
 * say whether the software installed. `stReturnCode` is the exit code the
 * client reported, and SnapinClient sets both in the same save(), so a row
 * carrying a completion date carries the outcome beside it.
 *
 * The two can disagree, and when they do the disagreement is real rather
 * than a defect to hide: a job canceled after its tasks finished leaves
 * rows that ran successfully under a canceled job. The grid shows both
 * columns for exactly that reason. On the lab all 44 rows read Canceled
 * with 43 exit codes of 0.
 *
 * ZERO IS SUCCESS, everything else is not. That is the POSIX convention the
 * client already relies on -- SnapinClient defaults a non-numeric exit code
 * to 1 with "Invalid exit code received" in the details, so "not a number"
 * is already normalized to a failure before it reaches the column. -1 shows
 * up in practice and is a failure like any other.
 *
 * A RUN IS ONE WITH A COMPLETION DATE IN THE WINDOW. Bounding on
 * `stCompleteDate` rather than `stCheckinDate` is what makes this a report
 * about outcomes: a task that checked in and never came back has no outcome
 * to report yet, and BETWEEN excludes its NULL without a special case.
 * Task Management's own panes are where an in-flight snapin is watched.
 *
 * @category SnapinStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinStats extends WindowedStats
{
    /**
     * The joins every statement here shares.
     *
     * A snapin task knows its snapin by id and its host only through the
     * job, so the names cost two joins and are worth them: a report whose
     * rows say "snapin 3 on host 12" is a report nobody reads. LEFT joins
     * throughout, because a snapin or host deleted since the run must not
     * delete the evidence that it ran.
     *
     * @return string
     */
    private static function _joins()
    {
        return "LEFT OUTER JOIN `snapins`
                       ON `snapins`.`sID` = `st`.`stSnapinID`
                LEFT OUTER JOIN `snapinJobs`
                       ON `snapinJobs`.`sjID` = `st`.`stJobID`
                LEFT OUTER JOIN `hosts`
                       ON `hosts`.`hostID` = `snapinJobs`.`sjHostID`";
    }

    /**
     * Runs per day across the window.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _runsPerDaySql()
    {
        return "SELECT DATE(`st`.`stCompleteDate`) AS `d`, COUNT(*) AS `c`
                  FROM `snapinTasks` AS `st`
                 WHERE `st`.`stCompleteDate` BETWEEN :start AND :end
                 GROUP BY DATE(`st`.`stCompleteDate`)";
    }

    /**
     * Failures per day across the window.
     *
     * A separate statement rather than a second column on the one above,
     * so each series is a plain day/count pair the shared zero fill can
     * take without either of them learning about the other.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _failuresPerDaySql()
    {
        return "SELECT DATE(`st`.`stCompleteDate`) AS `d`, COUNT(*) AS `c`
                  FROM `snapinTasks` AS `st`
                 WHERE `st`.`stCompleteDate` BETWEEN :start AND :end
                   AND `st`.`stReturnCode` <> 0
                 GROUP BY DATE(`st`.`stCompleteDate`)";
    }

    /**
     * Failures grouped by snapin, biggest first.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _failuresBySnapinSql()
    {
        return "SELECT COALESCE(NULLIF(`snapins`.`sName`, ''),
                       CONCAT('#', `st`.`stSnapinID`)) AS `snapin`,
                       COUNT(*) AS `c`
                  FROM `snapinTasks` AS `st`
                  " . self::_joins() . "
                 WHERE `st`.`stCompleteDate` BETWEEN :start AND :end
                   AND `st`.`stReturnCode` <> 0
                 GROUP BY `snapin`
                 ORDER BY `c` DESC, `snapin` ASC";
    }

    /**
     * The runs themselves, newest first.
     *
     * Bounded in the query for the same reason ImagingStats' is: a slice
     * after the fetch caps what a caller sees and not what the server
     * reads. MAX_ROWS + 1 so "there were more" is answerable without a
     * second COUNT.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _runsSql()
    {
        return "SELECT `st`.`stID` AS `id`,
                       COALESCE(NULLIF(`snapins`.`sName`, ''),
                           CONCAT('#', `st`.`stSnapinID`)) AS `snapin`,
                       COALESCE(`hosts`.`hostName`, '') AS `hostName`,
                       `snapinJobs`.`sjHostID` AS `hostID`,
                       `st`.`stCompleteDate` AS `completed`,
                       `st`.`stReturnCode` AS `code`,
                       `st`.`stStatus` AS `status`,
                       `st`.`stReturnDetails` AS `details`,
                       `st`.`stState` AS `stateID`
                  FROM `snapinTasks` AS `st`
                  " . self::_joins() . "
                 WHERE `st`.`stCompleteDate` BETWEEN :start AND :end
                 ORDER BY `st`.`stCompleteDate` DESC, `st`.`stID` DESC
                 LIMIT " . (self::MAX_ROWS + 1);
    }

    /**
     * Runs per day, zero filled.
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
        return self::dailySeries(
            self::readWindow(self::_runsPerDaySql(), $start, $end),
            $start,
            $end
        );
    }

    /**
     * Failures per day, zero filled.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    public static function failuresPerDay(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::dailySeries(
            self::readWindow(self::_failuresPerDaySql(), $start, $end),
            $start,
            $end
        );
    }

    /**
     * Failures grouped by snapin, biggest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     * @param int                $limit how many snapins keep their own entry
     *
     * @return array Ordered list of ['snapin' => string, 'count' => int].
     */
    public static function failuresBySnapin(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        $limit = 10
    ) {
        $top = self::topN(
            self::readWindow(self::_failuresBySnapinSql(), $start, $end),
            'snapin',
            'c',
            $limit
        );

        $out = [];
        foreach ($top as $row) {
            $out[] = ['snapin' => $row['label'], 'count' => $row['count']];
        }

        return $out;
    }

    /**
     * The runs themselves, newest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Rows, capped at MAX_ROWS.
     */
    public static function runs(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return array_slice(
            self::readWindow(self::_runsSql(), $start, $end),
            0,
            self::MAX_ROWS
        );
    }

    /**
     * The headline numbers, off the same rows the grid shows.
     *
     * Derived from the run rows rather than asked of the database
     * separately, so a tile cannot disagree with the grid under it.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array runs, failures, snapins, hosts, truncated
     */
    public static function totals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_runsSql(), $start, $end);
        $capped = array_slice($rows, 0, self::MAX_ROWS);

        $failures = 0;
        $snapins = [];
        $hosts = [];
        foreach ($capped as $row) {
            if (0 !== (int)($row['code'] ?? 0)) {
                $failures++;
            }
            $snapins[(string)($row['snapin'] ?? '')] = true;
            // Keyed on the id where there is one and the name otherwise, so
            // a run whose host has since been deleted still counts as a
            // machine rather than collapsing every deleted host into one.
            $hosts[(string)($row['hostID'] ?? '') . '/'
                . (string)($row['hostName'] ?? '')] = true;
        }

        return [
            'runs' => count($capped),
            'failures' => $failures,
            'snapins' => count($snapins),
            'hosts' => count($hosts),
            'truncated' => count($rows) > self::MAX_ROWS
        ];
    }
}
