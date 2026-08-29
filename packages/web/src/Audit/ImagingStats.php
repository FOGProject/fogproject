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
class ImagingStats extends WindowedStats
{
    /*
     * MAX_DAYS and MAX_ROWS are WindowedStats'. Both were written here
     * first and moved when the second rollup needed the same two numbers
     * for the same two reasons.
     */

    /**
     * readWindow() with the canceled-state bind this class' fold needs.
     *
     * Written once here rather than at each of the four call sites, where
     * one could drift from the others without anything noticing.
     *
     * @param string             $sql   one of the _*Sql() statements
     * @param \DateTimeInterface $start the lower bound as given
     * @param \DateTimeInterface $end   the upper bound as given
     *
     * @return array
     */
    private static function _read(
        $sql,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::readWindow(
            $sql,
            $start,
            $end,
            [':canceled' => self::getCancelledState()]
        );
    }

    /**
     * The fold: one row per imaging RUN, out of a table of transitions.
     *
     * THE SINGLE DEFINITION OF "A RUN". Every panel on the imaging report
     * and the dashboard's own graph read through this, so a chart and the
     * grid beneath it cannot disagree about what they are counting. Two
     * near-identical folds is how that disagreement gets in: it is not a
     * conflict anyone sees, just a total that does not match the rows.
     *
     * All three rules from the class docblock are here and nowhere else:
     *
     *   GROUP BY `taskID`     the fold itself
     *   HAVING SUM(...) > 0   at least one non-canceled row, which is the
     *                         canceled rule stated over the RUN rather than
     *                         row by row. A deploy whose only rows are
     *                         cancellations drops out; one canceled part way
     *                         through keeps its In-Progress row and stays
     *   MIN(`createTime`)     the run's day is the day it started
     *
     * WHY `HAVING` AND NOT A `WHERE` ON THE STATE, which is the change from
     * the first cut of this class. Filtering canceled rows out before the
     * fold gets the same set of runs, but every aggregate is then computed
     * over a subset of the run's rows -- so `MAX(id)` is the last row that
     * was not a cancellation, and a grid built on it reports a canceled
     * deploy as still In-Progress. Stating the rule as a HAVING keeps the
     * run's whole history in the group and asks the question about the run.
     *
     * Kept as a method so a test can run the real statement rather than a
     * copy -- the same reason TaskManagement has _logQueryFrom(). A test
     * that cannot reach this can only pin that the rules were once written
     * down.
     *
     * @return string SQL taking :start, :end and :canceled. Columns:
     *                taskID, started, ended, lastID.
     */
    private static function _foldSql()
    {
        return "SELECT `taskID`,
                       MIN(`createTime`) AS `started`,
                       MAX(`createTime`) AS `ended`,
                       MAX(`id`) AS `lastID`
                  FROM `taskLog`
                 WHERE `createTime` BETWEEN :start AND :end
                   AND `logImageName` <> ''
                 GROUP BY `taskID`
                HAVING SUM(`taskStateID` <> :canceled) > 0";
    }

    /**
     * Runs per day, counted off the fold.
     *
     * @return string SQL taking :start, :end and :canceled.
     */
    private static function _runsPerDaySql()
    {
        return "SELECT DATE(`started`) AS `d`, COUNT(*) AS `c`
                  FROM (" . self::_foldSql() . ") AS `runs`
                 GROUP BY DATE(`started`)";
    }

    /**
     * Runs per image, counted off the fold.
     *
     * The image name comes from the run's LAST row rather than from the
     * group, because `logImageName` is not in the GROUP BY -- and a task
     * cannot change image mid-run, so the last row's name is the run's.
     * Selecting it bare would be an aggregate-free non-grouped column,
     * which MySQL 5.7+ and MariaDB with ONLY_FULL_GROUP_BY reject and
     * older servers silently answer with an arbitrary row.
     *
     * @return string SQL taking :start, :end and :canceled.
     */
    private static function _runsByImageSql()
    {
        return "SELECT `l`.`logImageName` AS `image`, COUNT(*) AS `c`
                  FROM (" . self::_foldSql() . ") AS `runs`
                  JOIN `taskLog` AS `l` ON `l`.`id` = `runs`.`lastID`
                 GROUP BY `l`.`logImageName`
                 ORDER BY `c` DESC, `image` ASC";
    }

    /**
     * The runs themselves, newest first, for the grid under the charts.
     *
     * Every identifying column is taken from the run's last row, which is
     * the row taskLog denormalized the identity onto (schema 341/373). That
     * is what lets a run still name its host and image after both have been
     * deleted -- the same property tests/tasklog-report-retention.test.php
     * pins for the task log pane.
     *
     * BOUNDED IN THE QUERY, not in PHP. A slice after the fetch still
     * materializes every row a wide window matched -- which on a fleet with
     * years of taskLog is the "grid All -> blank 500" this codebase has
     * already fixed once, arriving through a report instead of a grid.
     *
     * MAX_ROWS + 1, so that "there were more" is answerable without a second
     * COUNT query over the same fold. The extra row is dropped by the
     * callers, which is what makes `truncated` mean it.
     *
     * @return string SQL taking :start, :end and :canceled.
     */
    private static function _runsSql()
    {
        return "SELECT `runs`.`taskID` AS `taskID`,
                       `runs`.`started` AS `started`,
                       `runs`.`ended` AS `ended`,
                       `l`.`logHostID` AS `hostID`,
                       `l`.`logHostName` AS `hostName`,
                       `l`.`logImageName` AS `imageName`,
                       `l`.`logTaskTypeName` AS `taskTypeName`,
                       `l`.`taskStateID` AS `stateID`,
                       `l`.`createdBy` AS `createdBy`
                  FROM (" . self::_foldSql() . ") AS `runs`
                  JOIN `taskLog` AS `l` ON `l`.`id` = `runs`.`lastID`
                 ORDER BY `runs`.`started` DESC
                 LIMIT " . (self::MAX_ROWS + 1);
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
        $rows = self::_read(self::_runsPerDaySql(), $start, $end);

        return self::dailySeries($rows, $start, $end);
    }

    /**
     * Runs grouped by image, biggest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     * @param int                $limit How many images to name; the rest are
     *                                  folded into one "Other" entry rather
     *                                  than dropped, so the bars still add
     *                                  up to the total on the tile above.
     *
     * @return array Ordered list of ['image' => string, 'count' => int].
     */
    public static function runsByImage(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        $limit = 10
    ) {
        $rows = self::_read(self::_runsByImageSql(), $start, $end);

        $top = self::topN($rows, 'image', 'c', $limit);
        $out = [];
        foreach ($top as $row) {
            // Renamed on the way out rather than in the shared helper: this
            // class' callers ask for images, and a generic 'label' key here
            // would make every one of them say what it already knows.
            $out[] = ['image' => $row['label'], 'count' => $row['count']];
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
        $rows = (array)self::_read(self::_runsSql(), $start, $end);

        return array_slice($rows, 0, self::MAX_ROWS);
    }

    /**
     * The headline numbers, off the same fold the charts use.
     *
     * Derived from runs() rather than asked of the database separately, so
     * a tile cannot disagree with the grid under it -- which is the failure
     * this class exists to prevent, one level up. The row cap is the only
     * thing that could make them differ, and `truncated` says when it has.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array runs, hosts, images, truncated
     */
    public static function totals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = (array)self::_read(self::_runsSql(), $start, $end);
        $capped = array_slice($rows, 0, self::MAX_ROWS);

        $hosts = [];
        $images = [];
        foreach ($capped as $row) {
            // Keyed on the id where there is one and the name otherwise, so
            // a run whose host has since been deleted still counts as a
            // machine rather than collapsing every deleted host into one.
            $hosts[(string)($row['hostID'] ?? '') . '/'
                . (string)($row['hostName'] ?? '')] = true;
            $images[(string)($row['imageName'] ?? '')] = true;
        }

        return [
            'runs' => count($capped),
            'hosts' => count($hosts),
            'images' => count($images),
            'truncated' => count($rows) > self::MAX_ROWS
        ];
    }
}
