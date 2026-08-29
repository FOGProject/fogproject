<?php
/**
 * How much the image estate weighs, and where it is meant to live.
 *
 * PHP version 7.4+
 *
 * @category StorageStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

/**
 * How much the image estate weighs, and where it is meant to live.
 *
 * ADR 0030's storage subject. Image Management shows one image's size on
 * one line; nothing has ever added them up, so "what is this actually
 * costing us in disk", "which images are we replicating that nobody has
 * deployed in a year" and "what is the biggest thing we keep on every
 * node" were questions you answered with a spreadsheet.
 *
 * `imageServerSize` IS THE MASTER NODE'S BYTES, and this report says so on
 * the page. FOG stores one size per image -- what it measured on the node
 * it was captured to -- and replication copies that to every other member
 * of the group. So the group totals here are what the group is SUPPOSED to
 * hold, not what any node currently does. Reporting them as actual usage
 * would need a live call to every node, which is the dashboard's job and
 * not a report's; reporting them without saying which they are would be a
 * number that is wrong in a way nobody could see.
 *
 * AN IMAGE IN TWO GROUPS COUNTS IN BOTH, deliberately. The bytes really are
 * on both sets of nodes, so a per-group total that divided them would
 * understate every group. It does mean the group totals sum to more than
 * the estate total, which is why the estate total is its own query rather
 * than the sum of the chart.
 *
 * THE WINDOW IS AS-OF, matching Fleet and Hardware. Size is a state. The
 * range selects images ADDED inside it, which is the growth half.
 *
 * NO NODE CREDENTIALS ARE SELECTED HERE. `nfsGroupMembers` carries
 * `ngmUser`, `ngmPass` and `ngmKey` in the same row as the node name, and
 * a rollup that took SELECT * would put them one JSON response away from a
 * report grid. Every column is named.
 *
 * @category StorageStats
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageStats extends WindowedStats
{
    /**
     * How many images the largest-images chart shows.
     *
     * @var int
     */
    const TOP_IMAGES = 10;

    /**
     * Bytes the group is meant to hold, per storage group.
     *
     * @return string SQL taking no window.
     */
    private static function _sizeByGroupSql()
    {
        return "SELECT `nfsGroups`.`ngName` AS `label`,
                       SUM(`images`.`imageServerSize`) AS `c`
                  FROM `imageGroupAssoc`
                  LEFT OUTER JOIN `images`
                         ON `images`.`imageID` = `imageGroupAssoc`.`igaImageID`
                  LEFT OUTER JOIN `nfsGroups`
                         ON `nfsGroups`.`ngID`
                            = `imageGroupAssoc`.`igaStorageGroupID`
                 GROUP BY `nfsGroups`.`ngName`
                 ORDER BY `c` DESC";
    }

    /**
     * The largest images.
     *
     * @return string SQL taking no window.
     */
    private static function _largestSql()
    {
        return "SELECT `imageName` AS `label`,
                       `imageServerSize` AS `c`
                  FROM `images`
                 ORDER BY `imageServerSize` DESC
                 LIMIT " . self::TOP_IMAGES;
    }

    /**
     * Images added per day across the window.
     *
     * @return string SQL taking :start and :end.
     */
    private static function _addedPerDaySql()
    {
        return "SELECT DATE(`imageDateTime`) AS `d`, COUNT(*) AS `c`
                  FROM `images`
                 WHERE `imageDateTime` BETWEEN :start AND :end
                 GROUP BY DATE(`imageDateTime`)";
    }

    /**
     * The headline counts, in one pass over `images`.
     *
     * `stale` counts images that have never been deployed OR were last
     * deployed before the window opened -- the two together, because the
     * question the tile answers is "what are we keeping for nothing" and a
     * never-deployed image is the strongest case of it. The grid shows
     * which is which.
     *
     * IT NEEDS THE START DATE TWICE, so the second use gets its own name.
     * A named placeholder cannot be repeated in one statement under native
     * prepares and the failure is silent -- see FleetStats::_ageBucketSql()
     * and the gate in tests/lib/report-wiring.php. `_readTotals()` binds
     * it, from the CLAMPED start, so the two can never drift apart.
     *
     * @return string SQL taking :start, :end and :staleBefore.
     */
    private static function _totalsSql()
    {
        $never = "(`imageLastDeploy` IS NULL
                   OR `imageLastDeploy` = '0000-00-00 00:00:00')";

        return "SELECT COUNT(*) AS `images`,
                       COALESCE(SUM(`imageServerSize`), 0) AS `bytes`,
                       SUM(`imageReplicate` = 0) AS `notReplicated`,
                       SUM($never) AS `neverDeployed`,
                       SUM($never
                           OR `imageLastDeploy` < :staleBefore) AS `stale`,
                       SUM(`imageDateTime` BETWEEN :start AND :end) AS `added`
                  FROM `images`";
    }

    /**
     * How many storage groups and nodes there are.
     *
     * Two counts over two tables, so a subquery rather than a join: a join
     * would multiply groups by their members and count each group once per
     * node. Named columns only -- see the class docblock.
     *
     * @return string SQL taking no window.
     */
    private static function _estateSql()
    {
        return "SELECT (SELECT COUNT(*) FROM `nfsGroups`) AS `groups`,
                       (SELECT COUNT(*) FROM `nfsGroupMembers`) AS `nodes`,
                       (SELECT COUNT(*) FROM `nfsGroupMembers`
                         WHERE `ngmIsEnabled` = '1') AS `enabledNodes`";
    }

    /**
     * The images themselves, largest first.
     *
     * @return string SQL taking no window.
     */
    private static function _imagesSql()
    {
        return "SELECT `images`.`imageID` AS `imageID`,
                       `images`.`imageName` AS `imageName`,
                       `images`.`imageServerSize` AS `bytes`,
                       `images`.`imageDateTime` AS `created`,
                       `images`.`imageLastDeploy` AS `lastDeploy`,
                       `images`.`imageReplicate` AS `replicate`,
                       `images`.`imageEnabled` AS `enabled`,
                       COUNT(`imageGroupAssoc`.`igaID`) AS `groups`
                  FROM `images`
                  LEFT OUTER JOIN `imageGroupAssoc`
                         ON `imageGroupAssoc`.`igaImageID` = `images`.`imageID`
                 GROUP BY `images`.`imageID`
                 ORDER BY `images`.`imageServerSize` DESC
                 LIMIT " . (self::MAX_ROWS + 1);
    }

    /**
     * The totals statement, with its extra bound date.
     *
     * Clamped here rather than left to readWindow(), so that :staleBefore
     * carries exactly the start readWindow() will bind and not the one the
     * caller asked for. A window wider than MAX_DAYS is moved forward, and
     * a stale cutoff still sitting on the original start would count a
     * year of images as fresh with nothing to show for it.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array
     */
    private static function _readTotals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        list($lo, $hi) = self::clamp($start, $end);

        return self::readWindow(
            self::_totalsSql(),
            $lo,
            $hi,
            [':staleBefore' => $lo->format('Y-m-d H:i:s')]
        );
    }

    /**
     * Bytes per storage group, biggest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    public static function sizeByGroup(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_sizeByGroupSql(), $start, $end);

        // An association pointing at a group that has since been deleted
        // leaves a NULL name. The bytes are still allocated somewhere, so
        // the row is kept and named rather than dropped.
        foreach ($rows as &$row) {
            if (null === $row['label'] || '' === $row['label']) {
                $row['label'] = _('Unassigned');
            }
        }
        unset($row);

        return self::topN($rows, 'label', 'c', self::TOP_IMAGES);
    }

    /**
     * The largest images.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array Ordered list of ['label' => string, 'count' => int].
     */
    public static function largest(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::readWindow(self::_largestSql(), $start, $end);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'label' => (string)($row['label'] ?? ''),
                'count' => (int)($row['c'] ?? 0)
            ];
        }

        return $out;
    }

    /**
     * Images added per day, zero filled.
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
     * The images themselves, largest first.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array Rows, capped at MAX_ROWS.
     */
    public static function images(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        return array_slice(
            self::readWindow(self::_imagesSql(), $start, $end),
            0,
            self::MAX_ROWS
        );
    }

    /**
     * The headline numbers.
     *
     * @param \DateTimeInterface $start Inclusive lower bound, FOG's clock.
     * @param \DateTimeInterface $end   The as-of date, FOG's clock.
     *
     * @return array images, bytes, notReplicated, neverDeployed, stale,
     *               added, groups, nodes, enabledNodes
     */
    public static function totals(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ) {
        $rows = self::_readTotals($start, $end);
        $row = (array)($rows[0] ?? []);

        $estate = self::readWindow(self::_estateSql(), $start, $end);
        $row += (array)($estate[0] ?? []);

        $out = [];
        foreach (['images', 'bytes', 'notReplicated', 'neverDeployed',
            'stale', 'added', 'groups', 'nodes', 'enabledNodes'] as $k
        ) {
            $out[$k] = (int)($row[$k] ?? 0);
        }

        return $out;
    }
}
