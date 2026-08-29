<?php
/**
 * Who changed what, who was refused, and when.
 *
 * PHP version 7.4+
 *
 * @category AuditStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

/**
 * Who changed what, who was refused, and when.
 *
 * ADR 0030's change-and-access subject. The Audit Log page shows the rows;
 * this counts them. "Are we being probed", "who has been editing storage
 * nodes this month", "did the refusals start when we rolled that role out"
 * are all shape questions, and a paged list of 40,000 rows cannot answer a
 * shape question no matter how well it filters.
 *
 * IT AGGREGATES, IT DOES NOT RE-LIST. The grid here is the same rows the
 * Audit Log page serves and is deliberately narrower: the prose sentence,
 * the change detail and the correlation chain stay there. This grid exists
 * so a spike in the chart above it can be read as events.
 *
 * DENIED AND FAILED ARE NOT THE SAME THING and are never added together.
 * `denied` is authorization refusing an action; `failed` is an action that
 * was permitted and then went wrong. One is a security signal and the
 * other is an operational one, and a single "errors" number that mixed
 * them would be actionable as neither. They are separate tiles and
 * separate series.
 *
 * ITS OWN READS ARE NOT AUDITED, and that is a property of the table
 * rather than a decision here -- `auditLog` records actions, and reading a
 * report is not one. Worth stating because a reader will wonder whether
 * opening the page moves the numbers. It does not.
 *
 * @category AuditStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AuditStats extends WindowedStats
{
    /**
     * How many slices the actor and type charts show.
     *
     * @var int
     */
    const TOP_N = 8;

    /**
     * Events per day across the window.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _perDaySql()
    {
        return "SELECT DATE(`alCreatedTime`) AS `d`, COUNT(*) AS `c`
                  FROM `auditLog`
                 WHERE `alCreatedTime` BETWEEN :start AND :end
                 GROUP BY DATE(`alCreatedTime`)";
    }

    /**
     * Refusals per day across the window.
     *
     * Its own statement rather than a second column on _perDaySql(),
     * because the two series are drawn on one chart and both must be zero
     * filled across the SAME days. dailySeries() does that from the window,
     * not from the rows, so two statements produce two aligned series and
     * one statement with two columns would not.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _deniedPerDaySql()
    {
        return "SELECT DATE(`alCreatedTime`) AS `d`, COUNT(*) AS `c`
                  FROM `auditLog`
                 WHERE `alCreatedTime` BETWEEN :start AND :end
                   AND `alOutcome` = 'denied'
                 GROUP BY DATE(`alCreatedTime`)";
    }

    /**
     * Who is generating the events.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _byActorSql()
    {
        return "SELECT `alCreatedBy` AS `label`, COUNT(*) AS `c`
                  FROM `auditLog`
                 WHERE `alCreatedTime` BETWEEN :start AND :end
                 GROUP BY `alCreatedBy`
                 ORDER BY `c` DESC";
    }

    /**
     * What kind of events they are.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _byTypeSql()
    {
        return "SELECT `alType` AS `label`, COUNT(*) AS `c`
                  FROM `auditLog`
                 WHERE `alCreatedTime` BETWEEN :start AND :end
                 GROUP BY `alType`
                 ORDER BY `c` DESC";
    }

    /**
     * The headline counts, in one pass.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _totalsSql()
    {
        return "SELECT COUNT(*) AS `events`,
                       COUNT(DISTINCT NULLIF(`alCreatedBy`, '')) AS `actors`,
                       COUNT(DISTINCT NULLIF(`alIP`, '')) AS `addresses`,
                       SUM(`alOutcome` = 'denied') AS `denied`,
                       SUM(`alOutcome` = 'failed') AS `failed`,
                       SUM(`alOutcome` = 'partial') AS `partial`
                  FROM `auditLog`
                 WHERE `alCreatedTime` BETWEEN :start AND :end";
    }

    /**
     * The events themselves, newest first.
     *
     * `alText` IS NOT SELECTED. It is a longtext holding the stored prose
     * for a row, it is what the Audit Log page is for, and pulling it into
     * a 5000-row grid would carry the whole detail of every change through
     * a JSON response nobody reads it in.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _eventsSql()
    {
        return "SELECT `alID` AS `id`,
                       `alCreatedTime` AS `at`,
                       `alCreatedBy` AS `actor`,
                       `alAuthSource` AS `source`,
                       `alIP` AS `ip`,
                       `alType` AS `type`,
                       `alSubjectType` AS `subjectType`,
                       `alSubjectLabel` AS `subjectLabel`,
                       `alPermission` AS `permission`,
                       `alOutcome` AS `outcome`,
                       `alAffectedCount` AS `affected`
                  FROM `auditLog`
                 WHERE `alCreatedTime` BETWEEN :start AND :end
                 ORDER BY `alCreatedTime` DESC, `alID` DESC
                 LIMIT " . (self::MAX_ROWS + 1);
    }

    /**
     * Events per day, zero filled.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    public static function eventsPerDay(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::dailySeries(
            self::readWindow(self::_perDaySql(), $start, $end),
            $start,
            $end
        );
    }

    /**
     * Refusals per day, zero filled and aligned with eventsPerDay().
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['date' => 'Y-m-d', 'count' => int].
     */
    public static function deniedPerDay(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::dailySeries(
            self::readWindow(self::_deniedPerDaySql(), $start, $end),
            $start,
            $end
        );
    }

    /**
     * Who generated the events, biggest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    public static function byActor(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::topN(
            self::_named(
                self::readWindow(self::_byActorSql(), $start, $end)
            ),
            'label',
            'c',
            self::TOP_N
        );
    }

    /**
     * What kind of events they were, biggest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    public static function byType(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return self::topN(
            self::_named(
                self::readWindow(self::_byTypeSql(), $start, $end)
            ),
            'label',
            'c',
            self::TOP_N
        );
    }

    /**
     * The events themselves, newest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array Rows, capped at MAX_ROWS.
     */
    public static function events(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return array_slice(
            self::readWindow(self::_eventsSql(), $start, $end),
            0,
            self::MAX_ROWS
        );
    }

    /**
     * The headline numbers.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   Inclusive upper bound, FOG's clock.
     *
     * @return array events, actors, addresses, denied, failed, partial,
     *               truncated
     */
    public static function totals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_totalsSql(), $start, $end);
        $row = (array)($rows[0] ?? []);

        $out = [];
        foreach (['events', 'actors', 'addresses', 'denied', 'failed',
            'partial'] as $k
        ) {
            $out[$k] = (int)($row[$k] ?? 0);
        }
        $out['truncated'] = $out['events'] > self::MAX_ROWS;

        return $out;
    }

    /**
     * Give the empty label a word.
     *
     * An unauthenticated attempt has no actor and a row written before a
     * type existed has no type, both stored as ''. An empty legend entry
     * beside a large slice is the least readable way to show either -- and
     * on the actor chart it is the interesting one.
     *
     * @param array $rows rows carrying a `label` key
     *
     * @return array
     */
    private static function _named(array $rows)
    {
        foreach ($rows as &$row) {
            if (null === $row['label'] || '' === $row['label']) {
                $row['label'] = _('Unattributed');
            }
        }
        unset($row);

        return $rows;
    }
}
