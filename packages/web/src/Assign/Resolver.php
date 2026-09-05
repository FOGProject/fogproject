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
 * ADR 0038. A group GRANTS a snapin, a printer or a module; it does not copy
 * rows onto whichever hosts happened to be members when a button was pressed.
 * The grant is a row keyed by group (`groupSnapinAssoc`, `groupPrinterAssoc`,
 * `groupModuleAssoc`), and what a given host ends up with is computed here,
 * from that grant plus the host's own direct associations.
 *
 * Modules are the one of the three that is a SWITCH rather than a thing, so
 * they resolve in three tiers rather than two and a host may hold one OFF
 * against every group that grants it. resolveModules() carries the reasoning.
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
     * Resolves the ordered software set for each host: the same shape and
     * rule as resolveSnapins (direct first, then groups in group order,
     * deduplicated), over the software tables (design 0003).
     *
     * @param array $hostIDs the hosts to resolve for
     *
     * @return array hostID => [softwareID, ...]; every host id is a key
     * @throws \RuntimeException on any query failure
     */
    public static function resolveSoftware(array $hostIDs)
    {
        $hostIDs = self::_ids($hostIDs);
        if (count($hostIDs) < 1) {
            return [];
        }
        $resolved = [];
        $direct = [];
        $rows = self::_rows(
            'SELECT `swaHostID`, `swaSoftwareID` FROM `softwareAssoc` '
            . 'WHERE `swaHostID` IN (' . implode(',', $hostIDs) . ') '
            . 'ORDER BY `swaHostID`, `swaSequence`, `swaID`'
        );
        foreach ($rows as $row) {
            $direct[(int)$row['swaHostID']][] = (int)$row['swaSoftwareID'];
        }

        list($groupsByHost, $groupIDs) = self::_membership($hostIDs);
        $byGroup = [];
        if (count($groupIDs) > 0) {
            $rows = self::_rows(
                'SELECT `gswaGroupID`, `gswaSoftwareID` FROM `groupSoftwareAssoc` '
                . 'WHERE `gswaGroupID` IN (' . implode(',', $groupIDs) . ') '
                . 'ORDER BY `gswaSequence`, `gswaID`'
            );
            foreach ($rows as $row) {
                $byGroup[(int)$row['gswaGroupID']][] = (int)$row['gswaSoftwareID'];
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
                foreach ($byGroup[$groupID] ?? [] as $softwareID) {
                    if (isset($seen[$softwareID])) {
                        continue;
                    }
                    $seen[$softwareID] = true;
                    $out[] = $softwareID;
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
            // Schema 426 made paIsDefault a tinyint(1), so it now carries
            // 0 or 1 like gpaIsDefault below and is read the same way. It
            // was a varchar(2) carrying '1', '0' and '' -- a 1.5-origin
            // column that predated booleans-are-tinyint -- and the upgrade
            // normalizes the empty string to 0 before retyping.
            if ((int)$row['paIsDefault'] > 0
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
     * Resolves the enabled module list for each host.
     *
     * MODULES ARE THE THIRD DECLARATIVE GRANT, and they are not shaped like
     * the other two. A snapin or a printer is a thing a host either has or
     * does not; a module is a switch, and a host is allowed to hold it OFF
     * against every group that grants it. So this is a three-tier answer,
     * not a two-tier union:
     *
     *   host row, msState = 0   the host says OFF. Beats everything.
     *   host row, msState = 1   the host says ON.
     *   group grant             any group the host is in says ON.
     *   nothing anywhere        OFF.
     *
     * Lowest tier wins, and only the host is allowed to say OFF. A group
     * grant is presence-only -- `groupModuleAssoc` has no state column -- so
     * two groups can only ever union and can never contradict each other.
     * That is the whole reason the table is shaped that way: "this group
     * says disable, that one says enable, which wins?" is a question with no
     * defensible answer, so the schema refuses to let it be asked.
     *
     * WHY "NOTHING ANYWHERE" IS OFF RATHER THAN `modules`.`default`. Before
     * group grants, a host's rows in `moduleStatusByHost` WERE its enabled
     * set and nothing read msState -- ServiceModule::checkPassiveModule()
     * still computes its disabled list as array_diff(globalModules,
     * hostRows). Absence therefore already means OFF on every existing
     * install, and falling back to `default` here would silently switch
     * modules on across a fleet at upgrade. `default` keeps the one job it
     * has: seeding a newly registered host's rows.
     *
     * WHY msState CAN BE TRUSTED NOW. It was a varchar(1) that only ever
     * held '1', because the only writer -- FOGController::addRemItem() --
     * hardcodes it. Schema step 409 makes it the tinyint(1) it always was.
     * A host row that means OFF is new, so no upgraded database has one, and
     * this resolver returns exactly what the old code did until someone
     * turns a module off in the UI.
     *
     * Order is by module id. Modules have no sequence anywhere in the
     * schema -- unlike snapins, nothing runs them in turn -- so the order is
     * for determinism only, and sorting by id keeps it stable across the
     * host-direct and group-granted halves rather than exposing which half a
     * module arrived through.
     *
     * @param array $hostIDs the hosts to resolve for
     *
     * @return array hostID => [moduleID, ...] ascending. Every host id
     *               passed in is a key, with an empty array when it has
     *               nothing; see resolveSnapins() for why.
     * @throws \RuntimeException on any query failure
     */
    public static function resolveModules(array $hostIDs)
    {
        $hostIDs = self::_ids($hostIDs);
        if (count($hostIDs) < 1) {
            return [];
        }
        $resolved = [];
        $on = [];
        $off = [];
        $rows = self::_rows(
            'SELECT `msHostID`, `msModuleID`, `msState` '
            . 'FROM `moduleStatusByHost` '
            . 'WHERE `msHostID` IN (' . implode(',', $hostIDs) . ')'
        );
        foreach ($rows as $row) {
            $hostID = (int)$row['msHostID'];
            $moduleID = (int)$row['msModuleID'];
            if ((int)$row['msState'] > 0) {
                $on[$hostID][$moduleID] = true;
                continue;
            }
            $off[$hostID][$moduleID] = true;
        }

        list($groupsByHost, $groupIDs) = self::_membership($hostIDs);
        $byGroup = [];
        if (count($groupIDs) > 0) {
            $rows = self::_rows(
                'SELECT `gmaGroupID`, `gmaModuleID` FROM `groupModuleAssoc` '
                . 'WHERE `gmaGroupID` IN (' . implode(',', $groupIDs) . ')'
            );
            foreach ($rows as $row) {
                $byGroup[(int)$row['gmaGroupID']][] = (int)$row['gmaModuleID'];
            }
        }

        foreach ($hostIDs as $hostID) {
            $out = $on[$hostID] ?? [];
            foreach (array_keys($groupsByHost[$hostID] ?? []) as $groupID) {
                foreach ($byGroup[$groupID] ?? [] as $moduleID) {
                    // The host's OFF is checked here rather than by
                    // subtracting at the end, so a module the host turned
                    // off can never be in $out even momentarily -- there is
                    // no ordering of the two halves that could leak it.
                    if (isset($off[$hostID][$moduleID])) {
                        continue;
                    }
                    $out[$moduleID] = true;
                }
            }
            $ids = array_keys($out);
            sort($ids);
            $resolved[$hostID] = $ids;
        }

        return $resolved;
    }
    /**
     * Resolves the power-management SCHEDULES for each host.
     *
     * ADR 0038. Host-direct `powerManagement` rows unioned with the grants of
     * every group the host belongs to, in the order decision 6 sets.
     *
     * SCHEDULES ONLY -- `pmOndemand` rows are excluded, and that is not a
     * filter for convenience. An on-demand row is an immediate shutdown,
     * reboot or wake that the client consumes and deletes on its next
     * check-in: it is a task, acting on the membership at the moment it was
     * created, and it has no group-granted counterpart to union with. There
     * is no `gpmOndemand` column for the same reason.
     *
     * EVERY ACTION IS RETURNED, `wol` included, and the caller filters. Two
     * very different consumers read these rows -- the FOG client, which runs
     * shutdown and reboot itself on a Quartz cron, and TaskScheduler, which
     * sends the magic packet for `wol` because a sleeping machine cannot ask
     * for anything. Filtering here would mean two resolvers, and two orderings
     * that drift; the same reasoning the class docblock gives for there being
     * one of these at all.
     *
     * THE IDENTITY OF A SCHEDULE IS ITS CRON PLUS ITS ACTION, which is what
     * deduplication keys on. That is the same key `powerManagement`.`cron` and
     * `groupPowerManagement`.`gpmCron` are unique on, and it is the only key
     * that means anything: two rows saying "reboot at 03:00" are one
     * instruction however many places they arrived from, and running the
     * second would reboot a machine that had just come back up.
     *
     * @param array $hostIDs the hosts to resolve for
     *
     * @return array hostID => [['cron' => string, 'action' => string], ...].
     *               Every host id passed in is a key, with an empty array when
     *               it has nothing; see resolveSnapins() for why.
     * @throws \RuntimeException on any query failure
     */
    public static function resolvePowerManagement(array $hostIDs)
    {
        $hostIDs = self::_ids($hostIDs);
        if (count($hostIDs) < 1) {
            return [];
        }
        $resolved = [];
        $direct = [];
        $rows = self::_rows(
            'SELECT `pmHostID`, `pmMin`, `pmHour`, `pmDom`, `pmMonth`, '
            . '`pmDow`, `pmAction` FROM `powerManagement` '
            . 'WHERE `pmHostID` IN (' . implode(',', $hostIDs) . ') '
            // `= 0` rather than `<> 1`: the column is NOT NULL DEFAULT 0, so
            // there is no third state to be careful about, and an explicit
            // equality is what an index can use.
            . 'AND `pmOndemand` = 0 '
            . 'ORDER BY `pmHostID`, `pmID`'
        );
        foreach ($rows as $row) {
            $direct[(int)$row['pmHostID']][] = self::_schedule($row, 'pm');
        }

        list($groupsByHost, $groupIDs) = self::_membership($hostIDs);
        $byGroup = [];
        if (count($groupIDs) > 0) {
            $rows = self::_rows(
                'SELECT `gpmGroupID`, `gpmMin`, `gpmHour`, `gpmDom`, '
                . '`gpmMonth`, `gpmDow`, `gpmAction` '
                . 'FROM `groupPowerManagement` '
                . 'WHERE `gpmGroupID` IN (' . implode(',', $groupIDs) . ') '
                . 'ORDER BY `gpmID`'
            );
            foreach ($rows as $row) {
                $byGroup[(int)$row['gpmGroupID']][] = self::_schedule(
                    $row,
                    'gpm'
                );
            }
        }
        $ordered = self::_orderedGroupIDs($groupIDs);

        foreach ($hostIDs as $hostID) {
            $out = [];
            $seen = [];
            foreach ($direct[$hostID] ?? [] as $schedule) {
                $key = $schedule['cron'] . '|' . $schedule['action'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $schedule;
            }
            foreach ($ordered as $groupID) {
                if (!isset($groupsByHost[$hostID][$groupID])) {
                    continue;
                }
                foreach ($byGroup[$groupID] ?? [] as $schedule) {
                    $key = $schedule['cron'] . '|' . $schedule['action'];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $out[] = $schedule;
                }
            }
            $resolved[$hostID] = $out;
        }

        return $resolved;
    }

    /**
     * Joins five cron fields into the one expression everything else reads.
     *
     * PUBLIC because it is the only implementation. The host table and the
     * group table must format the same five fields identically -- the
     * deduplication key in resolvePowerManagement() is the formatted string,
     * so a stray space would make one schedule look like two and run it twice
     * -- and the group page's grid has to show the admin the same expression
     * the client will be given. GroupPowerManagement::getCron() calls this
     * rather than repeating it.
     *
     * `-1` for the weekday becomes `7`. FOG's own cron picker writes -1 for
     * Sunday and Quartz, which the client schedules against, will not take it.
     * Client\PM::json() normalized it inline and nothing else did, which is
     * how the server-side WOL path came to disagree with the client one.
     *
     * THE is_numeric() GUARD IS A BUG FIX, not defensive padding. The inline
     * version read `if ($dow < 0)`, and on PHP 8 `'*' < 0` is TRUE: comparing
     * a non-numeric string with a number casts the NUMBER to string, and '*'
     * (42) sorts below '0' (48). So every schedule with a wildcard weekday --
     * which is every daily schedule anyone has ever set -- was handed to the
     * client as `... 7`, meaning Sundays only. On PHP 7.4 the same expression
     * is false, because that version cast the string to int instead, so the
     * defect appeared when a server was upgraded past PHP 8 and nothing about
     * FOG changed. Verified both ways, 2026-09-01.
     *
     * @param string $min   the minute field
     * @param string $hour  the hour field
     * @param string $dom   the day-of-month field
     * @param string $month the month field
     * @param string $dow   the day-of-week field
     *
     * @return string the five-field cron expression
     */
    public static function cronExpression($min, $hour, $dom, $month, $dow)
    {
        $dow = trim((string)$dow);
        if (is_numeric($dow) && (int)$dow < 0) {
            $dow = 7;
        }

        return sprintf(
            '%s %s %s %s %s',
            trim((string)$min),
            trim((string)$hour),
            trim((string)$dom),
            trim((string)$month),
            $dow
        );
    }

    /**
     * Turns one schedule row into a cron expression and an action.
     *
     * @param array  $row    the row, as an associative array
     * @param string $prefix the column prefix, `pm` or `gpm`
     *
     * @return array ['cron' => string, 'action' => string]
     */
    private static function _schedule(array $row, $prefix)
    {
        return [
            'cron' => self::cronExpression(
                $row[$prefix . 'Min'],
                $row[$prefix . 'Hour'],
                $row[$prefix . 'Dom'],
                $row[$prefix . 'Month'],
                $row[$prefix . 'Dow']
            ),
            'action' => (string)$row[$prefix . 'Action']
        ];
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
