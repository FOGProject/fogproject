<?php
/**
 * What a set of hosts holds in common, per column.
 *
 * PHP version 7.4+
 *
 * @category SharedHostValues
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Util;

use FOG\Base\FOGBase;

/**
 * What a set of hosts holds in common, per column.
 *
 * A form that edits many hosts at once has to show what they currently hold
 * before it changes anything, and it has to be honest when they disagree.
 * Forty hosts with six images must read as "(varies)" -- never as one of the
 * six, which is how an admin ends up overwriting thirty-nine machines while
 * believing they confirmed what was already there.
 *
 * This was private to GroupManagement, keyed off the group's own membership.
 * It is lifted out unchanged in behavior and re-keyed on a HOST ID LIST so
 * that the group page and any mass edit over a selection share one
 * implementation. Two copies of "do these hosts agree" would drift silently:
 * nothing fails when they disagree, one form just starts telling a different
 * truth than the other about the same hosts.
 *
 * ONE QUERY over the whole selection, whatever the size of the column map.
 * COUNT(DISTINCT ...) and MIN(...) per column in a single pass, rather than a
 * read per host or per column -- the same reason the assignment resolver takes
 * a set: a helper whose natural unit is one host becomes a query storm the
 * first time somebody points it at four hundred of them.
 *
 * NULL AND '' ARE THE SAME THING HERE, deliberately. A column set on some
 * hosts and merely blank on others is a column the hosts disagree about, and
 * COALESCE makes both spellings collapse to one so the disagreement is
 * visible rather than being hidden by SQL's NULL semantics -- where
 * COUNT(DISTINCT) skips NULLs entirely and would report agreement among rows
 * that have nothing in common.
 *
 * @category SharedHostValues
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SharedHostValues extends FOGBase
{
    /**
     * Computes, per column, whether the hosts agree and what they hold.
     *
     * @param array $hostIDs the hosts to look at
     * @param array $columns map of friendly key => `hosts` table column
     *
     * @return array friendly key => ['uniform' => bool, 'value' => string].
     *               EVERY key in $columns is present, so a caller rendering a
     *               form never has to test for a missing entry; an empty
     *               selection reports every column as not uniform, which
     *               renders as "(varies)" and is the safe thing for a form
     *               that is about to offer to overwrite them.
     */
    public static function forHosts(array $hostIDs, array $columns)
    {
        $result = [];
        foreach ($columns as $key => $col) {
            $result[$key] = ['uniform' => false, 'value' => ''];
        }
        $hostIDs = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $hostIDs),
                    function ($id) {
                        return $id > 0;
                    }
                )
            )
        );
        if (count($hostIDs) < 1 || count($columns) < 1) {
            return $result;
        }
        $selects = ['COUNT(*) AS `_n`'];
        foreach ($columns as $key => $col) {
            $safe = self::_alias($key);
            $selects[] = sprintf(
                "COUNT(DISTINCT COALESCE(`%s`, '')) AS `d_%s`",
                $col,
                $safe
            );
            // The COALESCE on the MIN is belt-and-braces and is NOT
            // observable through the answer this returns -- proven by
            // mutation, not assumed. `value` is only meaningful when
            // `uniform` is true, and uniform means every coalesced value
            // was equal; on the all-NULL selection MIN returns NULL and the
            // string cast below flattens it to '' anyway. It stays so that
            // both expressions describe the same value space: a reader
            // comparing the two should not have to work out that one of
            // them tolerates NULL and the other does not.
            $selects[] = sprintf(
                "MIN(COALESCE(`%s`, '')) AS `v_%s`",
                $col,
                $safe
            );
        }
        $sql = sprintf(
            'SELECT %s FROM `hosts` WHERE `hostID` IN (%s)',
            implode(',', $selects),
            implode(',', $hostIDs)
        );
        $row = self::$DB->query($sql)->fetch();
        $n = (int)$row->get('_n');
        foreach ($columns as $key => $col) {
            $safe = self::_alias($key);
            $distinct = (int)$row->get('d_' . $safe);
            $result[$key] = [
                'uniform' => ($n > 0 && $distinct <= 1),
                'value' => (string)$row->get('v_' . $safe),
            ];
        }

        return $result;
    }

    /**
     * forHosts() for a table holding at most one row per host.
     *
     * Auto-logout and screen resolution are not `hosts` columns -- each is a
     * row in its own table, and a host with no row is a host with no
     * override. That asymmetry is the whole reason this is a second method
     * rather than a parameter: "every host agrees" has to mean every SELECTED
     * host, not every host that happens to have a row. A selection where
     * thirty hosts share a value and ten have no row at all is NOT uniform,
     * and a query that only counted the thirty would confidently say it was.
     *
     * So the row count is compared against the selection size, not against
     * itself.
     *
     * @param array  $hostIDs the selected host ids
     * @param string $table   the table holding one row per host
     * @param string $hostCol the column in it naming the host
     * @param array  $columns map of friendly key => column in that table
     *
     * @return array friendly key => ['uniform' => bool, 'value' => string]
     */
    public static function forHostRows(
        array $hostIDs,
        $table,
        $hostCol,
        array $columns
    ) {
        $result = [];
        foreach ($columns as $key => $col) {
            $result[$key] = ['uniform' => false, 'value' => ''];
        }
        $hostIDs = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $hostIDs),
                    function ($id) {
                        return $id > 0;
                    }
                )
            )
        );
        if (count($hostIDs) < 1 || count($columns) < 1) {
            return $result;
        }
        $selects = ['COUNT(*) AS `_n`'];
        foreach ($columns as $key => $col) {
            $safe = self::_alias($key);
            $selects[] = sprintf(
                "COUNT(DISTINCT COALESCE(`%s`, '')) AS `d_%s`",
                $col,
                $safe
            );
            $selects[] = sprintf(
                "MIN(COALESCE(`%s`, '')) AS `v_%s`",
                $col,
                $safe
            );
        }
        $sql = sprintf(
            'SELECT %s FROM `%s` WHERE `%s` IN (%s)',
            implode(',', $selects),
            $table,
            $hostCol,
            implode(',', $hostIDs)
        );
        $row = self::$DB->query($sql)->fetch();
        $n = (int)$row->get('_n');
        // The line this method exists for. Every selected host must have a
        // row before the values in those rows can be called uniform.
        $everyHostHasOne = ($n === count($hostIDs));
        foreach ($columns as $key => $col) {
            $safe = self::_alias($key);
            $distinct = (int)$row->get('d_' . $safe);
            $result[$key] = [
                'uniform' => ($everyHostHasOne && $n > 0 && $distinct <= 1),
                'value' => (string)$row->get('v_' . $safe),
            ];
        }

        return $result;
    }

    /**
     * The text a form shows for one column's shared state.
     *
     * @param array $info   an entry from forHosts() or forHostRows()
     * @param bool  $redact  report agreement without reporting the value
     *
     * @return string ready-to-render, with the value HTML-escaped
     */
    public static function text($info, $redact = false)
    {
        if (empty($info['uniform'])) {
            return _('(varies)');
        }
        if ('' === (string)$info['value']) {
            return _('(empty on all)');
        }
        // A credential's shared state is still worth reporting -- "they all
        // have the same one" is what an admin needs to know before changing
        // it across four hundred hosts -- but the VALUE is not. ADR 0038
        // decision 11: ADPass and productKey both match
        // Redaction::CREDENTIAL_PATTERN, and a form editing hundreds of them
        // at once is the last place either should be rendered.
        if ($redact) {
            return _('(set on all)');
        }

        return \Initiator::e((string)$info['value']) . ' ' . _('(all)');
    }

    /**
     * The muted "Hosts: ..." hint beneath a control.
     *
     * @param array $info   an entry from forHosts() or forHostRows()
     * @param bool  $redact  report agreement without reporting the value
     *
     * @return string
     */
    public static function hint($info, $redact = false)
    {
        return '<p class="form-text help-block-tight">'
            . _('Hosts:') . ' ' . self::text($info, $redact)
            . '</p>';
    }

    /**
     * A column key reduced to something safe to use as a SQL alias.
     *
     * The keys are the CALLER's, so they are not trusted to be identifiers
     * even though every caller today passes a literal. Stripping rather than
     * quoting because an alias only has to be stable and unique within one
     * statement, and this is read back by the same code that wrote it.
     *
     * @param string $key the friendly key
     *
     * @return string
     */
    private static function _alias($key)
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', (string)$key);
    }
}
