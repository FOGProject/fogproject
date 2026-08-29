<?php
/**
 * How current the fleet is, and which machines have fallen behind.
 *
 * PHP version 7.4+
 *
 * @category FleetStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

/**
 * How current the fleet is, and which machines have fallen behind.
 *
 * ADR 0030's fleet subject, and the first one where the window means
 * something different. Everywhere else it selects EVENTS: rows that happened
 * between two dates. Staleness is not an event, it is a state -- "this
 * machine has not been imaged since March" is true of a machine that did
 * nothing at all in the window. So here the window's END is an AS-OF date
 * and its START is what counts as current: a host deployed inside the window
 * is up to date, and everything else is bucketed by how far behind it is.
 *
 * That is a real difference and it is stated on the page, because a window
 * control that means two different things on two reports is worse than no
 * control at all.
 *
 * NEVER IS ITS OWN ANSWER, not a very large age. A host that has never been
 * imaged and one imaged three years ago are different problems -- the first
 * is usually a registration that never went anywhere, the second is a
 * machine in service running something old. Bucketing them together hides
 * whichever is rarer. On the lab, 83 of 86 hosts have never been deployed,
 * which is what a test fleet looks like and is exactly the number a real
 * one wants to see separated out.
 *
 * BOTH FORMS OF "NO DATE" COUNT AS NEVER. `hostLastDeploy` is NULL on a host
 * that has never been imaged, but installs upgraded across the zero-date
 * schema work carry '0000-00-00 00:00:00' instead, and a comparison that
 * checks only one of them silently reports the other as imaged in the year
 * zero. Both are tested for everywhere a date is read here.
 *
 * @category FleetStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FleetStats extends WindowedStats
{
    /**
     * The age buckets, in days, as upper bounds.
     *
     * Ordered, and the last one is open ended. Chosen to match how people
     * already talk about a fleet -- a month, a quarter, a year -- rather
     * than to divide the range evenly.
     *
     * @var array
     */
    const AGE_BUCKETS = [30, 90, 365];

    /**
     * Deploy age counted in whole days back from the as-of date.
     *
     * @return string
     */
    private static function _ageExpr()
    {
        return "DATEDIFF(:end, `hosts`.`hostLastDeploy`)";
    }

    /**
     * Hosts per age bucket, as of the window's end.
     *
     * A CASE rather than one query per bucket: five round trips to place
     * every host into exactly one of five groups is five chances for the
     * groups to overlap or to leave a gap, and the numbers would still add
     * up to something.
     *
     * THE AGE IS COMPUTED IN A DERIVED TABLE so that :end is written once.
     * The obvious shape puts DATEDIFF(:end, ...) in each WHEN, and a named
     * placeholder cannot be repeated in a statement under native prepares
     * -- which FOG uses. That does not raise here: PDODB logs the failure
     * and hands back false, dailySeries-style zero filling turns the empty
     * result into a full set of buckets, and the chart draws five zeros
     * next to tiles saying 86 hosts. Caught by the shared placeholder gate
     * in tests/lib/report-wiring.php, which is there because of this.
     *
     * Bucketing off a NULL ageDays also matches _hostsSql(), so "never" is
     * expressed the same way in both.
     *
     * @return string SQL taking :end.
     */
    private static function _ageBucketSql()
    {
        $never = self::noDateSql('`hosts`.`hostLastDeploy`');
        $age = self::_ageExpr();
        $cases = '';
        foreach (self::AGE_BUCKETS as $i => $days) {
            $cases .= " WHEN `a`.`ageDays` <= " . (int)$days
                . " THEN " . ($i + 1);
        }
        $last = count(self::AGE_BUCKETS) + 1;

        return "SELECT CASE WHEN `a`.`ageDays` IS NULL THEN 0$cases
                            ELSE $last END AS `b`,
                       COUNT(*) AS `c`
                  FROM (SELECT CASE WHEN $never THEN NULL
                                    ELSE $age END AS `ageDays`
                          FROM `hosts`) AS `a`
                 GROUP BY `b`
                 ORDER BY `b` ASC";
    }

    /**
     * Hosts registered per day across the window.
     *
     * `hostCreateDate`, not `hostLastDeploy` -- this is the other half of
     * the staleness picture. A fleet whose age profile is drifting older
     * because nothing is being re-imaged looks identical, on the buckets
     * alone, to one that is drifting older because it keeps growing.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _addedPerDaySql()
    {
        return "SELECT DATE(`hostCreateDate`) AS `d`, COUNT(*) AS `c`
                  FROM `hosts`
                 WHERE `hostCreateDate` BETWEEN :start AND :end
                 GROUP BY DATE(`hostCreateDate`)";
    }

    /**
     * The headline counts, in one pass over `hosts`.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _totalsSql()
    {
        $never = self::noDateSql('`hosts`.`hostLastDeploy`');
        $noCheckin = self::noDateSql('`hosts`.`hostLastCheckin`');

        return "SELECT COUNT(*) AS `hosts`,
                       SUM($never) AS `never`,
                       SUM(NOT $never
                           AND `hosts`.`hostLastDeploy`
                               BETWEEN :start AND :end) AS `current`,
                       SUM(`inventory`.`iID` IS NULL) AS `noInventory`,
                       SUM($noCheckin) AS `noCheckin`,
                       SUM(`hosts`.`hostPending` = 1) AS `pending`
                  FROM `hosts`
                  LEFT OUTER JOIN `inventory`
                         ON `inventory`.`iHostID` = `hosts`.`hostID`";
    }

    /**
     * The hosts themselves, stalest first.
     *
     * ORDERED BY AGE DESCENDING WITH NEVER FIRST, which is the whole point
     * of the grid: the rows somebody has to act on are the ones at the top,
     * and a default sort by name would bury them among machines that are
     * fine. `hostLastDeploy IS NULL` sorts before everything under MySQL's
     * ascending NULL ordering, so the never-imaged lead without a second
     * sort key.
     *
     * @return string SQL taking :end.
     */
    private static function _hostsSql()
    {
        $age = self::_ageExpr();

        return "SELECT `hosts`.`hostID` AS `hostID`,
                       `hosts`.`hostName` AS `hostName`,
                       COALESCE(`images`.`imageName`, '') AS `imageName`,
                       `hosts`.`hostLastDeploy` AS `lastDeploy`,
                       `hosts`.`hostLastCheckin` AS `lastCheckin`,
                       `hosts`.`hostCreateDate` AS `created`,
                       CASE WHEN " . self::noDateSql('`hosts`.`hostLastDeploy`')
                           . " THEN NULL ELSE $age END AS `ageDays`,
                       (`inventory`.`iID` IS NOT NULL) AS `hasInventory`
                  FROM `hosts`
                  LEFT OUTER JOIN `images`
                         ON `images`.`imageID` = `hosts`.`hostImage`
                  LEFT OUTER JOIN `inventory`
                         ON `inventory`.`iHostID` = `hosts`.`hostID`
                 ORDER BY `ageDays` DESC, `hosts`.`hostName` ASC
                 LIMIT " . (self::MAX_ROWS + 1);
    }

    /**
     * Human labels for the buckets, in the order _ageBucketSql() numbers.
     *
     * A method rather than a constant so the labels can be wrapped in _().
     * xgettext extracts from the literal call site only, so a runtime-built
     * msgid never reaches the catalog.
     *
     * @return array bucket number => label
     */
    public static function bucketLabels()
    {
        return [
            0 => _('Never imaged'),
            1 => _('Within 30 days'),
            2 => _('31 to 90 days'),
            3 => _('91 to 365 days'),
            4 => _('Over a year')
        ];
    }

    /**
     * Hosts per age bucket, as of the window's end.
     *
     * Zero filled across every bucket, for the same reason a daily series
     * is: a bucket that is missing from a chart reads as a bucket that does
     * not apply, when it means nothing is in it -- which is usually the
     * good news somebody wanted to see.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    public static function ageBuckets(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_ageBucketSql(), $start, $end);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['b']] = (int)$row['c'];
        }

        $out = [];
        foreach (self::bucketLabels() as $b => $label) {
            $out[] = [
                'label' => $label,
                'count' => isset($counts[$b]) ? $counts[$b] : 0
            ];
        }

        return $out;
    }

    /**
     * Hosts registered per day, zero filled.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    public static function addedPerDay(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::dailySeries(
            self::readWindow(self::_addedPerDaySql(), $start, $end),
            $start,
            $end
        );
    }

    /**
     * The hosts themselves, stalest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array Rows, capped at MAX_ROWS.
     */
    public static function hosts(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return array_slice(
            self::readWindow(self::_hostsSql(), $start, $end),
            0,
            self::MAX_ROWS
        );
    }

    /**
     * The headline numbers.
     *
     * Asked of the database rather than derived from hosts(), which is the
     * opposite of what the imaging and snapin rollups do -- and deliberately.
     * Those two count rows the grid also shows, so deriving is what stops a
     * tile disagreeing with it. Here the grid is capped at MAX_ROWS while
     * the tiles describe the WHOLE fleet: a site with more hosts than the
     * cap would otherwise be told it has exactly MAX_ROWS machines, which is
     * a wrong answer rather than a truncated one.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array hosts, never, current, noInventory, noCheckin, pending
     */
    public static function totals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_totalsSql(), $start, $end);
        $row = (array)($rows[0] ?? []);

        $out = [];
        foreach (['hosts', 'never', 'current', 'noInventory', 'noCheckin',
            'pending'] as $k) {
            $out[$k] = (int)($row[$k] ?? 0);
        }

        return $out;
    }
}
