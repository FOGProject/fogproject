<?php
/**
 * What hardware the fleet is actually made of.
 *
 * PHP version 7.4+
 *
 * @category InventoryStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

/**
 * What hardware the fleet is actually made of.
 *
 * ADR 0030's hardware subject. FOG has collected this for every registered
 * machine for years and has only ever shown it one host at a time, on the
 * Inventory tab -- so "how many of these are still on 4GB" was a question
 * you answered by clicking through the fleet.
 *
 * THE WINDOW IS AS-OF, the same meaning FleetStats gives it and for the
 * same reason: a census describes what exists now, not what happened
 * between two dates. The range picks out inventory RECORDED in it, which
 * is what the per-day chart and the freshness tile are counting, and the
 * breakdowns cover every host that has inventory at all.
 *
 * BLANK IS ITS OWN ANSWER. Every one of these columns is NOT NULL DEFAULT
 * '', and a machine whose firmware reports nothing for the manufacturer is
 * a real and reasonably common case -- white-box builds, some VMs, and
 * anything where dmidecode came back empty. Grouping on the raw column
 * puts all of them in one unnamed slice that renders as a blank legend
 * entry, so they are labeled here instead. Folding them into the largest
 * real vendor, which is what a WHERE would do, would be worse: it would
 * quietly inflate whoever is biggest.
 *
 * THE COLUMN IS CHOSEN FROM A FIXED MAP, never taken from the request. The
 * breakdown is one statement parameterised by column name, and a column
 * name cannot be bound -- it is concatenated. `breakdowns()` is the only
 * thing that names one.
 *
 * `iDeleteDate` exists on the table and nothing in FOG has ever written
 * it, so there is no soft-delete to filter on. Said here because its
 * presence otherwise reads as a filter somebody forgot.
 *
 * @category InventoryStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class InventoryStats extends WindowedStats
{
    /**
     * The breakdowns offered, as key => inventory column.
     *
     * A FIXED MAP because the value is concatenated into SQL. See the
     * class docblock.
     *
     * @var array
     */
    const BREAKDOWNS = [
        'manufacturer' => 'iSysman',
        'model' => 'iSysproduct',
        'memory' => 'iMem',
        'cpu' => 'iCpuversion'
    ];

    /**
     * How many slices a breakdown chart shows before folding the tail.
     *
     * @var int
     */
    const TOP_N = 8;

    /**
     * One breakdown, biggest first.
     *
     * @param string $column an inventory column from BREAKDOWNS
     *
     * @return string SQL taking no window.
     */
    private static function _breakdownSql($column)
    {
        return "SELECT CASE WHEN TRIM(`inventory`.`$column`) = ''
                            THEN NULL
                            ELSE TRIM(`inventory`.`$column`) END AS `label`,
                       COUNT(*) AS `c`
                  FROM `inventory`
                 GROUP BY `label`
                 ORDER BY `c` DESC";
    }

    /**
     * Inventory records collected per day across the window.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _recordedPerDaySql()
    {
        return "SELECT DATE(`iCreateDate`) AS `d`, COUNT(*) AS `c`
                  FROM `inventory`
                 WHERE `iCreateDate` BETWEEN :start AND :end
                 GROUP BY DATE(`iCreateDate`)";
    }

    /**
     * The headline counts, in one pass.
     *
     * Counted over `inventory` rather than over `hosts` LEFT JOIN
     * `inventory`, because every number here is about machines that HAVE
     * inventory. How many do not is the fleet report's question and is
     * already a tile there.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _totalsSql()
    {
        return "SELECT COUNT(*) AS `records`,
                       COUNT(DISTINCT NULLIF(TRIM(`iSysman`), '')) AS `vendors`,
                       COUNT(DISTINCT NULLIF(TRIM(`iSysproduct`), ''))
                           AS `models`,
                       SUM(`iCreateDate` BETWEEN :start AND :end) AS `recorded`,
                       SUM(TRIM(`iSysserial`) = '') AS `noSerial`
                  FROM `inventory`";
    }

    /**
     * The machines themselves.
     *
     * @return string SQL taking no window.
     */
    private static function _hostsSql()
    {
        return "SELECT `hosts`.`hostID` AS `hostID`,
                       `hosts`.`hostName` AS `hostName`,
                       TRIM(`inventory`.`iSysman`) AS `vendor`,
                       TRIM(`inventory`.`iSysproduct`) AS `model`,
                       TRIM(`inventory`.`iSysserial`) AS `serial`,
                       TRIM(`inventory`.`iCpuversion`) AS `cpu`,
                       TRIM(`inventory`.`iMem`) AS `memory`,
                       `inventory`.`iCreateDate` AS `recorded`
                  FROM `inventory`
                  LEFT OUTER JOIN `hosts`
                         ON `hosts`.`hostID` = `inventory`.`iHostID`
                 ORDER BY `vendor` ASC, `model` ASC, `hosts`.`hostName` ASC
                 LIMIT " . (self::MAX_ROWS + 1);
    }

    /**
     * One breakdown, folded to TOP_N with the tail as "Other".
     *
     * @param string             $key   a key of BREAKDOWNS
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    public static function breakdown(
        $key,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $column = self::BREAKDOWNS[$key] ?? null;
        if (null === $column) {
            return [];
        }

        $rows = self::readWindow(
            self::_breakdownSql($column),
            $start,
            $end
        );

        // NULL is the blank-column case the SQL folded together; it gets a
        // word rather than an empty legend entry. See the class docblock.
        foreach ($rows as &$row) {
            if (null === $row['label'] || '' === $row['label']) {
                $row['label'] = _('Not reported');
            }
        }
        unset($row);

        return self::topN($rows, 'label', 'c', self::TOP_N);
    }

    /**
     * Inventory records collected per day, zero filled.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    public static function recordedPerDay(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::dailySeries(
            self::readWindow(self::_recordedPerDaySql(), $start, $end),
            $start,
            $end
        );
    }

    /**
     * The machines themselves.
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
     * Asked of the database rather than derived from hosts(), for the
     * reason FleetStats::totals() gives: the grid is capped and the tiles
     * describe the whole estate.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array records, vendors, models, recorded, noSerial
     */
    public static function totals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_totalsSql(), $start, $end);
        $row = (array)($rows[0] ?? []);

        $out = [];
        foreach (['records', 'vendors', 'models', 'recorded', 'noSerial']
            as $k
        ) {
            $out[$k] = (int)($row[$k] ?? 0);
        }

        return $out;
    }
}
