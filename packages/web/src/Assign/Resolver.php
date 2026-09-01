<?php
/**
 * Resolves what a host is actually assigned, host-direct plus group-granted.
 *
 * PHP version 7.4+
 *
 * @category Resolver
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Assign;

use FOG\Base\FOGBase;

/**
 * Resolves what a host is actually assigned, host-direct plus group-granted.
 *
 * ADR 0038. A group GRANTS a snapin or a printer; it does not copy rows onto
 * whichever hosts happened to be members when a button was pressed. The grant
 * is a row keyed by group (`groupSnapinAssoc`, `groupPrinterAssoc`), and what
 * a given host ends up with is computed here, from that grant plus the host's
 * own direct associations.
 *
 * ONE function answers it, for every caller -- task creation, the client's
 * printer request, and any UI preview. Three sorts in three files drift, and
 * the symptom is a preview that does not match what runs, which is
 * unfalsifiable from a bug report and miserable to diagnose. The precedent is
 * SiteScope::_inScopeSelect(), whose docblock states the same reasoning for
 * the same reason.
 *
 * TWO THINGS HERE ARE LOAD-BEARING AND BOTH LOOK LIKE STYLE.
 *
 * 1. THE UNIT IS A SET OF HOSTS, NOT A HOST. GH-707 was exactly this: the
 *    "all snapins" path queried snapinAssoc once per member host inside a
 *    loop -- a thousand round trips for a thousand-host group. A resolver
 *    whose natural unit is one host reintroduces that the first time a group
 *    task calls it. The single-host client path passes a one-element array.
 *
 * 2. IT READS THE ASSOCIATION TABLES DIRECTLY, NOT THROUGH THEIR MANAGERS.
 *    FOGController::buildQuery() walks $databaseFieldClassRelationships
 *    TRANSITIVELY and folds a fourth-element filter into the WHERE rather
 *    than the ON, so every query whose class chain reaches Host picks up
 *    `AND hostMAC.hmPrimary = '1'` from Host's own MACAddressAssociation
 *    declaration. That turns the LEFT JOIN into an effective inner one and
 *    silently drops every host with no primary MAC -- measured on the 1.5
 *    lab as 95 rows where the raw COUNT(*) was 1000. There is no flag that
 *    suppresses it; PR #1233 fixed the same problem the same way. A resolver
 *    that silently omits a host is a printer resolver that strips that host's
 *    printers, so this is not a performance note.
 *
 * ORDER (decision 6). Host-direct first, then group-granted:
 *
 *   1. host-direct, by the association's sequence, then by its id.
 *   2. group-granted, groups by `groupOrder`, then `groupName`, then
 *      `groupID`; within a group by the grant's own sequence, then its id.
 *
 * The id tiebreaks are not decoration. `saSequence` defaults to 0 and
 * Host::loadSnapins() orders by sequence alone, so any two rows both sitting
 * at 0 -- which is every row Group::addSnapin() wrote before
 * appendSnapinSequence() numbered them -- come back in whatever order the
 * engine chose. Ordering groups on NAME alone was rejected outright: renaming
 * a group must not silently reorder what installs on a thousand machines.
 *
 * DEDUPLICATION TAKES THE HOST'S POSITION (decision 7). A snapin reached both
 * directly and through a group appears once, where the host put it. A group
 * grant does not get to move an order an admin chose. It is done here rather
 * than by leaning on `UNIQUE (stJobID, stSnapinID)`, because insertBatch()
 * UPSERTS -- a duplicate reaching the insert would overwrite the sequence
 * silently instead of being rejected.
 *
 * FAILURE THROWS. It never returns an empty list to mean failure (decision
 * 9). Under printer level `ar` the resolved list is authoritative in both
 * directions -- the client removes what is not on it -- so a resolver that
 * errs by returning nothing strips printers from every machine that polls,
 * one machine at a time, for as long as nobody notices. The shipped client
 * (fog-client 0.13.0, Modules/PrinterManager/PrinterManager.cs) does bail on
 * `if (data.Error) return;` before touching a printer, so an exception is
 * genuinely safe; an empty list that reaches the wire as the string `np` is
 * not, because `np` is the one and only removal-on-empty trigger there.
 *
 * @category Resolver
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Resolver extends FOGBase
{
    /**
     * Resolves the ordered snapin list for each host.
     *
     * @param array $hostIDs the hosts to resolve for
     *
     * @return array hostID => [snapinID, ...] in run order. EVERY host id
     *               passed in is a key, with an empty array when it has
     *               nothing. A missing key would be indistinguishable from
     *               "resolved to nothing" at the call site, and the two want
     *               opposite handling.
     * @throws \RuntimeException on any query failure
     */
    public static function resolveSnapins(array $hostIDs)
    {
        $hostIDs = self::_ids($hostIDs);
        if (count($hostIDs) < 1) {
            return [];
        }
        $resolved = [];
        $direct = [];
        $rows = self::_rows(
            'SELECT `saHostID`, `saSnapinID` FROM `snapinAssoc` '
            . 'WHERE `saHostID` IN (' . implode(',', $hostIDs) . ') '
            . 'ORDER BY `saHostID`, `saSequence`, `saID`'
        );
        foreach ($rows as $row) {
            $direct[(int)$row['saHostID']][] = (int)$row['saSnapinID'];
        }

        list($groupsByHost, $groupIDs) = self::_membership($hostIDs);
        $byGroup = [];
        if (count($groupIDs) > 0) {
            $rows = self::_rows(
                'SELECT `gsaGroupID`, `gsaSnapinID` FROM `groupSnapinAssoc` '
                . 'WHERE `gsaGroupID` IN (' . implode(',', $groupIDs) . ') '
                . 'ORDER BY `gsaSequence`, `gsaID`'
            );
            foreach ($rows as $row) {
                $byGroup[(int)$row['gsaGroupID']][] = (int)$row['gsaSnapinID'];
            }
        }
        $ordered = self::_orderedGroupIDs($groupIDs);

        foreach ($hostIDs as $hostID) {
            $out = $direct[$hostID] ?? [];
            $seen = array_flip($out);
            foreach ($ordered as $groupID) {
                if (!isset($groupsByHost[$hostID][$groupID])) {
                    continue;
                }
                foreach ($byGroup[$groupID] ?? [] as $snapinID) {
                    if (isset($seen[$snapinID])) {
                        continue;
                    }
                    $seen[$snapinID] = true;
                    $out[] = $snapinID;
                }
            }
            $resolved[$hostID] = $out;
        }

        return $resolved;
    }

    /**
     * Resolves the printer list and default printer for each host.
     *
     * @param array $hostIDs the hosts to resolve for
     *
     * @return array hostID => ['printers' => [printerID, ...],
     *               'default' => printerID|null]. Every host id passed in is
     *               a key; see resolveSnapins() for why.
     * @throws \RuntimeException on any query failure
     */
    public static function resolvePrinters(array $hostIDs)
    {
        $hostIDs = self::_ids($hostIDs);
        if (count($hostIDs) < 1) {
            return [];
        }
        $resolved = [];
        $direct = [];
        $directDefault = [];
        $rows = self::_rows(
            'SELECT `paHostID`, `paPrinterID`, `paIsDefault` '
            . 'FROM `printerAssoc` '
            . 'WHERE `paHostID` IN (' . implode(',', $hostIDs) . ') '
            . 'ORDER BY `paHostID`, `paID`'
        );
        foreach ($rows as $row) {
            $hostID = (int)$row['paHostID'];
            $printerID = (int)$row['paPrinterID'];
            $direct[$hostID][] = $printerID;
            // paIsDefault is varchar(2) on a 1.5-origin database and carries
            // '1', '0' and '' -- it predates booleans-are-tinyint. Compare it
            // as the string the writers actually store rather than casting,
            // which would make the empty string a 0 and read the same either
            // way, but only by luck.
            if ('1' === (string)$row['paIsDefault']
                && !isset($directDefault[$hostID])
            ) {
                $directDefault[$hostID] = $printerID;
            }
        }

        list($groupsByHost, $groupIDs) = self::_membership($hostIDs);
        $byGroup = [];
        $groupDefault = [];
        if (count($groupIDs) > 0) {
            $rows = self::_rows(
                'SELECT `gpaGroupID`, `gpaPrinterID`, `gpaIsDefault` '
                . 'FROM `groupPrinterAssoc` '
                . 'WHERE `gpaGroupID` IN (' . implode(',', $groupIDs) . ') '
                . 'ORDER BY `gpaID`'
            );
            foreach ($rows as $row) {
                $groupID = (int)$row['gpaGroupID'];
                $printerID = (int)$row['gpaPrinterID'];
                $byGroup[$groupID][] = $printerID;
                if ((int)$row['gpaIsDefault'] > 0
                    && !isset($groupDefault[$groupID])
                ) {
                    $groupDefault[$groupID] = $printerID;
                }
            }
        }
        $ordered = self::_orderedGroupIDs($groupIDs);

        foreach ($hostIDs as $hostID) {
            $out = $direct[$hostID] ?? [];
            $seen = array_flip($out);
            // A host-direct default wins outright. Otherwise the default
            // comes from the FIRST group in the resolved order that names
            // one -- same precedence as the list itself, so an admin who can
            // predict the order can predict the default.
            $default = $directDefault[$hostID] ?? null;
            foreach ($ordered as $groupID) {
                if (!isset($groupsByHost[$hostID][$groupID])) {
                    continue;
                }
                foreach ($byGroup[$groupID] ?? [] as $printerID) {
                    if (isset($seen[$printerID])) {
                        continue;
                    }
                    $seen[$printerID] = true;
                    $out[] = $printerID;
                }
                if (null === $default && isset($groupDefault[$groupID])) {
                    $default = $groupDefault[$groupID];
                }
            }
            $resolved[$hostID] = ['printers' => $out, 'default' => $default];
        }

        return $resolved;
    }

    /**
     * Reads group membership for a set of hosts.
     *
     * Straight off `groupMembers`, for the transitive-filter reason in the
     * class docblock: a membership read that goes through a manager drops
     * every host with no primary MAC.
     *
     * @param array $hostIDs the hosts, already sanitized
     *
     * @return array [hostID => [groupID => true], [groupID, ...]]
     * @throws \RuntimeException on any query failure
     */
    private static function _membership(array $hostIDs)
    {
        $byHost = [];
        $groupIDs = [];
        $rows = self::_rows(
            'SELECT `gmHostID`, `gmGroupID` FROM `groupMembers` '
            . 'WHERE `gmHostID` IN (' . implode(',', $hostIDs) . ')'
        );
        foreach ($rows as $row) {
            $groupID = (int)$row['gmGroupID'];
            $byHost[(int)$row['gmHostID']][$groupID] = true;
            $groupIDs[$groupID] = true;
        }

        return [$byHost, array_keys($groupIDs)];
    }

    /**
     * Puts group ids into the one order every resolution uses.
     *
     * Factored out so the snapin list, the printer list and the default
     * printer are ordered by ONE piece of code. Decision 6 is a promise about
     * what an admin sees, and a promise implemented three times is a promise
     * that stops holding somewhere without anything failing.
     *
     * @param array $groupIDs the groups to order, already sanitized
     *
     * @return array the ids, ordered
     * @throws \RuntimeException on any query failure
     */
    private static function _orderedGroupIDs(array $groupIDs)
    {
        if (count($groupIDs) < 1) {
            return [];
        }
        $rows = self::_rows(
            'SELECT `groupID` FROM `groups` '
            . 'WHERE `groupID` IN (' . implode(',', $groupIDs) . ') '
            // groupID last, and kept even though the manifest declares
            // groupName UNIQUE. It costs one clause, and it is the
            // difference between a resolver that is deterministic and one
            // that is deterministic as long as an index nobody re-checks is
            // still there. roles.rName is the precedent for the manifest and
            // the disk disagreeing; schema step 401 exists because of it.
            . 'ORDER BY `groupOrder`, `groupName`, `groupID`'
        );
        $ordered = [];
        foreach ($rows as $row) {
            $ordered[] = (int)$row['groupID'];
        }

        return $ordered;
    }

    /**
     * Runs a read and returns its rows, or throws.
     *
     * PDODB::query() does NOT throw by default -- it records the message on
     * ->error and hands the object back, so an unchecked caller reads a
     * successful-looking empty result out of a failed query. That is the
     * exact shape decision 9 forbids here, so every read goes through this.
     *
     * @param string $sql the statement to run
     *
     * @return array the rows, as associative arrays
     * @throws \RuntimeException when the query failed
     */
    private static function _rows($sql)
    {
        $res = self::$DB->query($sql);
        if (false !== $res->error) {
            throw new \RuntimeException(
                sprintf(
                    'Assignment resolution failed: %s',
                    (string)$res->error
                )
            );
        }
        $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        return (array)$rows;
    }

    /**
     * Sanitizes an id list.
     *
     * @param array $ids the ids
     *
     * @return array positive ints, deduplicated, renumbered
     */
    private static function _ids(array $ids)
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map('intval', $ids),
                    function ($id) {
                        return $id > 0;
                    }
                )
            )
        );
    }
}
