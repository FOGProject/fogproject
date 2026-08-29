<?php
/**
 * The parts every windowed rollup does the same way.
 *
 * PHP version 7.4+
 *
 * @category WindowedStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

use FOG\Base\FOGBase;

/**
 * The parts every windowed rollup does the same way.
 *
 * ADR 0030 decision 2 puts each subject's aggregation in its own class under
 * `src/`. Those classes differ in their SQL and in nothing else: they all
 * clamp the same window, bind it the same way, and turn a sparse GROUP BY
 * into a dense daily series. Extracted when the second one arrived rather
 * than the fifth, because two of the three are traps and copying a trap is
 * how it stops being fixed in one place.
 *
 * THE ZERO FILL IS THE TRAP. A day on which nothing happened produces no
 * row, and a chart library draws a line straight across a missing day -- so
 * an idle week reads as steady activity. Every series that leaves a subclass
 * has one entry per day in the window, in order, so a quiet day is a point
 * at zero. It is a contract, not a courtesy to one caller.
 *
 * THE CLAMP IS THE OTHER. A window is a filter, not a limit: "the last year"
 * on a server mid-rollout is every row it has. The cap moves the START, so a
 * caller asking for more gets the recent end of what it asked for; clipping
 * the other way would answer with the oldest window and give no sign it had.
 *
 * BOUNDS ARE ON FOG'S CLOCK, which is the reason the bind lives here rather
 * than in each subclass. The columns being compared are stamped by
 * FOGController::save() through FOGBase::niceDate(), using the configured
 * FOG timezone; a bound built with PHP's own date() is offset by however far
 * apart the two are and does not error -- BETWEEN just matches a shifted
 * window and answers a question nobody asked. Callers hand in
 * DateTimeInterface values from ReportWindow, which reads them that way.
 *
 * @category WindowedStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class WindowedStats extends FOGBase
{
    /**
     * The longest window one call will read.
     *
     * 366 rather than 365 because the dashboard's widest view is a year and
     * a leap year is 366 days -- a cap that clips the widest shipped view
     * would be a bug reported as "the 1 Year graph is missing a day".
     *
     * @var int
     */
    const MAX_DAYS = 366;

    /**
     * The most rows a grid will carry back.
     *
     * Same stance as ActivityWindow::MAX_ROWS. A report says so on screen
     * when it truncates rather than quietly showing a prefix.
     *
     * @var int
     */
    const MAX_ROWS = 5000;

    /**
     * Run one of a subclass' statements over a clamped window.
     *
     * ONLY THE PLACEHOLDERS THE STATEMENT ACTUALLY USES are bound. A
     * prepared statement handed a named parameter it does not mention fails
     * with SQLSTATE[HY093] Invalid parameter number, which is a fatal rather
     * than a wrong answer -- but it is a fatal that appears only for the
     * subset of statements that happen not to need one of the two bounds,
     * and every rollup here has at least one. A staleness question is
     * "as of :end" with no lower bound at all; a census question uses
     * neither. Filtering here is what lets a subclass write the statement
     * its question needs rather than padding it with a bound it ignores.
     *
     * @param string             $sql   a statement using any of :start, :end
     * @param \DateTimeInterface $start the lower bound as given
     * @param \DateTimeInterface $end   the upper bound as given
     * @param array              $binds any further placeholders the
     *                                  statement uses, already resolved;
     *                                  filtered the same way
     *
     * @return array
     */
    protected static function readWindow(
        $sql,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        array $binds = []
    ) {
        list($start, $end) = static::clamp($start, $end);

        $all = array_merge(
            $binds,
            [
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s')
            ]
        );
        $used = [];
        foreach ($all as $name => $value) {
            // Word boundary on the right, so :start does not match a
            // :started that a future statement introduces.
            if (preg_match('/' . preg_quote($name, '/') . '\b/', $sql)) {
                $used[$name] = $value;
            }
        }

        return (array)self::$DB->query($sql, [], $used)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
    }

    /**
     * A sparse day => count result turned into a dense daily series.
     *
     * @param array              $rows  rows carrying a date and a count
     * @param \DateTimeInterface $start the lower bound as given
     * @param \DateTimeInterface $end   the upper bound as given
     * @param string             $dateK the rows' date column
     * @param string             $countK the rows' count column
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    protected static function dailySeries(
        array $rows,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        $dateK = 'd',
        $countK = 'c'
    ) {
        // Clamped here as well as inside readWindow(), because this walks
        // the window itself -- an unclamped one would emit a point per day
        // for however long was asked for while the query answered for
        // MAX_DAYS, and the series would run off the data.
        list($start, $end) = static::clamp($start, $end);

        $counts = [];
        foreach ($rows as $row) {
            if (isset($row[$dateK])) {
                $counts[(string)$row[$dateK]] = (int)$row[$countK];
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
     * A grouped result capped to the biggest N, with the rest folded up.
     *
     * Folded into one "Other" entry rather than dropped, so the slices still
     * add up to the total on the tile above them -- a chart that silently
     * omits the tail is a chart that disagrees with its own headline.
     *
     * @param array  $rows  rows ordered biggest first
     * @param string $labelK the rows' label column
     * @param string $countK the rows' count column
     * @param int    $limit  how many keep their own entry
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    protected static function topN(
        array $rows,
        $labelK,
        $countK = 'c',
        $limit = 10
    ) {
        $out = [];
        $other = 0;
        $limit = (int)$limit;
        foreach (array_values($rows) as $i => $row) {
            $count = (int)($row[$countK] ?? 0);
            if ($limit > 0 && $i >= $limit) {
                $other += $count;
                continue;
            }
            $out[] = [
                'label' => (string)($row[$labelK] ?? ''),
                'count' => $count
            ];
        }
        if ($other > 0) {
            $out[] = ['label' => _('Other'), 'count' => $other];
        }

        return $out;
    }

    /**
     * The window, ordered and capped.
     *
     * Reversed rather than rejected when the bounds arrive the other way
     * round: somebody who passes the two dates backwards means the range
     * between them, and returning nothing looks exactly like "nothing
     * happened".
     *
     * @param \DateTimeInterface $start the lower bound as given
     * @param \DateTimeInterface $end   the upper bound as given
     *
     * @return array [\DateTimeImmutable, \DateTimeImmutable]
     */
    protected static function clamp(
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
        if ($span > static::MAX_DAYS) {
            $lo = new \DateTimeImmutable(
                $hi->modify('-' . (static::MAX_DAYS - 1) . ' days')
                    ->format('Y-m-d') . ' ' . $lo->format('H:i:s')
            );
        }

        return [$lo, $hi];
    }
}
