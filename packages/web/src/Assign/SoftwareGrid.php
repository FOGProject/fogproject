<?php
/**
 * Builds the rows of the Software tab grid for a host and for a group.
 *
 * PHP version 7.4+
 *
 * @category SoftwareGrid
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Assign;

use FOG\Items\Software;

/**
 * Builds the rows of the Software tab grid for a host and for a group.
 *
 * The host and group Software tabs each show ONE grid: the owner's own links
 * in run order, and on a host also the entries the host inherits from its
 * groups. Every method here is a pure function over plain arrays. The pages
 * read the rows (links, entries, Resolver output, group names, statuses) and
 * hand them in, so the join and the source mapping are testable without a
 * database. That mapping is the part a page would otherwise get wrong
 * quietly: it decides what the admin is told a host will do.
 *
 * @category SoftwareGrid
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SoftwareGrid
{
    /**
     * Turns the software rows Route::getList() returns into the plain
     * entries the other methods take.
     *
     * @param iterable $objects the software rows (id, name, backend,
     *                          package, isEnabled, state)
     *
     * @return array softwareID => ['id', 'name', 'backend', 'package',
     *               'enabled', 'state']
     */
    public static function entries($objects)
    {
        $out = [];
        foreach ($objects as $object) {
            $id = (int)$object->id;
            $out[$id] = [
                'id' => $id,
                'name' => (string)$object->name,
                'backend' => (string)$object->backend,
                'package' => (string)$object->package,
                'enabled' => (int)$object->isEnabled,
                'state' => (string)$object->state
            ];
        }
        return $out;
    }
    /**
     * Turns the link rows Route::getList() returns (host softwareassociation
     * or groupsoftwareassociation) into the plain links the row builders
     * take, keeping their order.
     *
     * @param iterable $objects the link rows (softwareID, state)
     *
     * @return array [['id' => int, 'state' => mixed], ...]
     */
    public static function links($objects)
    {
        $out = [];
        foreach ($objects as $object) {
            $out[] = [
                'id' => (int)$object->softwareID,
                'state' => $object->state
            ];
        }
        return $out;
    }
    /**
     * Builds the host grid rows.
     *
     * Every link the host owns comes first, in the order given ($links is
     * already in swaSequence order). Every entry the Resolver returns that
     * the host does not link follows, in Resolver order: those are inherited
     * from a group and have no handle and no Remove in the grid.
     *
     * @param array $links      the host's links, in run order:
     *                          [['id' => int, 'state' => mixed], ...]
     * @param array $software   softwareID => ['id', 'name', 'backend',
     *                          'package', 'enabled', 'state']; an id with no
     *                          entry here is a stale link and is skipped
     * @param array $resolved   Resolver::resolveSoftwareDetail() for this
     *                          host: [['id', 'state', 'groupID',
     *                          'effective'], ...]
     * @param array $groupNames groupID => group name
     * @param array $statuses   softwareID => ['status', 'installedVersion',
     *                          'checked'], from softwareStatus
     *
     * @return array the rows
     */
    public static function hostRows(
        array $links,
        array $software,
        array $resolved,
        array $groupNames,
        array $statuses
    ) {
        $byID = [];
        foreach ($resolved as $row) {
            $byID[(int)$row['id']] = $row;
        }
        $rows = [];
        $linked = [];
        foreach ($links as $link) {
            $id = (int)$link['id'];
            if (!isset($software[$id]) || isset($linked[$id])) {
                continue;
            }
            $linked[$id] = true;
            $rows[] = self::_hostRow(
                $software[$id],
                true,
                (string)Software::linkState($link['state']),
                $byID,
                $groupNames,
                $statuses
            );
        }
        foreach ($resolved as $row) {
            $id = (int)$row['id'];
            if (isset($linked[$id]) || !isset($software[$id])) {
                continue;
            }
            // One id once: the Resolver lists an entry a single time.
            $linked[$id] = true;
            $rows[] = self::_hostRow(
                $software[$id],
                false,
                '',
                $byID,
                $groupNames,
                $statuses
            );
        }
        return $rows;
    }
    /**
     * Builds the group grid rows: the group's own links, in run order.
     *
     * A group has no parent, so a row carries no effective state, source or
     * status. Its fallback text is the entry's own state (entryState).
     *
     * @param array $links    the group's links, in run order:
     *                        [['id' => int, 'state' => mixed], ...]
     * @param array $software softwareID => entry, as for hostRows()
     *
     * @return array the rows
     */
    public static function groupRows(array $links, array $software)
    {
        $rows = [];
        $seen = [];
        foreach ($links as $link) {
            $id = (int)$link['id'];
            if (!isset($software[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $row = self::_baseRow(
                $software[$id],
                true,
                (string)Software::linkState($link['state'])
            );
            if (!$row['enabled']) {
                $row['note'] = 'disabled';
            }
            $rows[] = $row;
        }
        return $rows;
    }
    /**
     * Lists the entries the Link modal can offer: every entry the owner does
     * not already link, ordered by name.
     *
     * @param array $software  softwareID => entry, every entry there is
     * @param array $linkedIDs the ids the owner links
     *
     * @return array [['id' => int, 'name' => string], ...]
     */
    public static function available(array $software, array $linkedIDs)
    {
        $skip = array_flip(array_map('intval', $linkedIDs));
        $out = [];
        foreach ($software as $entry) {
            $id = (int)$entry['id'];
            if (isset($skip[$id])) {
                continue;
            }
            $out[] = ['id' => $id, 'name' => (string)$entry['name']];
        }
        usort(
            $out,
            function ($a, $b) {
                return strcasecmp($a['name'], $b['name'])
                    ?: $a['id'] <=> $b['id'];
            }
        );
        return $out;
    }
    /**
     * Previews what an Exclusive host could lose: the packages its software
     * backends installed that it does not get.
     *
     * Pure, like the rest of this class. It is an upper bound. The agent
     * also keeps dependencies of assigned packages and snap bases and
     * content providers, which the server cannot see, so it can remove
     * fewer.
     *
     * @param array $facts    the host's open hostSoftware rows
     *                        (hsRemovedAt IS NULL): [['name', 'source',
     *                        'version'], ...]
     * @param array $resolved what the host gets: [['backend', 'package'],
     *                        ...]. An empty backend is Chocolatey. The
     *                        package compares case-insensitively.
     *
     * @param array $retained the packages of disabled entries, which the
     *                        agent keeps: [['backend', 'package'], ...]
     *
     * @return array [['source', 'name', 'version'], ...], by source then name
     */
    public static function exclusivePreview(
        array $facts,
        array $resolved,
        array $retained = []
    ) {
        $gets = [];
        foreach (array_merge($resolved, $retained) as $row) {
            $backend = strtolower(trim((string)($row['backend'] ?? '')));
            if ('' === $backend) {
                $backend = Software::DEFAULT_BACKEND;
            }
            $gets[$backend . "\0" . strtolower(trim((string)($row['package'] ?? '')))] = true;
        }
        $out = [];
        foreach ($facts as $fact) {
            $source = (string)($fact['source'] ?? '');
            $name = (string)($fact['name'] ?? '');
            if (!array_key_exists($source, Software::BACKENDS)) {
                continue;
            }
            $lower = strtolower($name);
            if (isset($gets[$source . "\0" . $lower])) {
                continue;
            }
            // Chocolatey itself and its extensions are never removed.
            if ('choco' === $source
                && ('chocolatey' === $lower
                || 1 === preg_match('/^chocolatey-.*\.extension$/', $lower))
            ) {
                continue;
            }
            $out[] = [
                'source' => $source,
                'name' => $name,
                'version' => (string)($fact['version'] ?? '')
            ];
        }
        usort(
            $out,
            function ($a, $b) {
                return strcmp($a['source'], $b['source'])
                    ?: strcmp($a['name'], $b['name'])
                    ?: strcmp($a['version'], $b['version']);
            }
        );
        return $out;
    }
    /**
     * The fields every row carries, host or group.
     *
     * @param array  $entry  the software entry
     * @param bool   $linked whether the owner links it
     * @param string $state  the link's own state: '', 'present' or 'absent'
     *
     * @return array the row
     */
    private static function _baseRow(array $entry, $linked, $state)
    {
        return [
            'id' => (int)$entry['id'],
            'name' => (string)$entry['name'],
            'backend' => (string)$entry['backend'],
            'package' => (string)$entry['package'],
            'enabled' => (bool)(int)$entry['enabled'],
            'linked' => (bool)$linked,
            'state' => $state,
            'entryState' => self::_entryState($entry['state'])
        ];
    }
    /**
     * Builds one host row: the base fields plus what the Resolver decided
     * and what the host last reported.
     *
     * `effective` is null, with a note saying why, when the agent is not
     * sent the entry: a disabled entry, or an enabled one a first entry for
     * the same package (and backend) supersedes. `source` says who set the
     * state: the host's own link, a group's, or the entry itself.
     *
     * @param array  $entry      the software entry
     * @param bool   $linked     whether the host links it
     * @param string $state      the host link's own state
     * @param array  $byID       softwareID => Resolver row
     * @param array  $groupNames groupID => group name
     * @param array  $statuses   softwareID => status row
     *
     * @return array the row
     */
    private static function _hostRow(
        array $entry,
        $linked,
        $state,
        array $byID,
        array $groupNames,
        array $statuses
    ) {
        $row = self::_baseRow($entry, $linked, $state);
        $id = $row['id'];
        $res = $byID[$id] ?? null;
        if (!$row['enabled']) {
            // Resolver keeps a disabled entry, but the agent is not sent it.
            $row['effective'] = null;
            $row['note'] = 'disabled';
        } elseif (null === $res) {
            $row['effective'] = null;
            $row['note'] = 'superseded';
        } else {
            $row['effective'] = self::_entryState($res['effective']);
        }
        // Resolver's 'state' is the override that applies, null when no
        // link set one; then 'groupID' is only the first link and means
        // nothing, so the entry's own state is the source.
        $source = 'package';
        $row['sourceName'] = '';
        // The group that grants the entry when no link sets a state: the
        // Resolver's 'groupID' is then the first link, and 0 is the host.
        // It lets the grid say "package default, via group X".
        $row['viaName'] = '';
        if (null !== $res && null === ($res['state'] ?? null)
            && (int)$res['groupID'] > 0
        ) {
            $row['viaName'] = (string)($groupNames[(int)$res['groupID']] ?? '');
        }
        if (null !== $res && null !== ($res['state'] ?? null)) {
            if ((int)$res['groupID'] > 0) {
                $source = 'group';
                $row['sourceName'] = (string)($groupNames[(int)$res['groupID']] ?? '');
            } else {
                $source = 'host';
            }
        }
        if (null === $res && $linked && '' !== $state) {
            // Superseded: the Resolver dropped it, but the host's own link
            // still says what it set.
            $source = 'host';
        }
        $row['source'] = $source;
        $stat = $statuses[$id] ?? [];
        $row['status'] = (string)($stat['status'] ?? '');
        $row['installedVersion'] = (string)($stat['installedVersion'] ?? '');
        $row['checked'] = (string)($stat['checked'] ?? '');
        return $row;
    }
    /**
     * An entry's own state as 'present' or 'absent'.
     *
     * The column default is present, so anything but 'absent' is present.
     *
     * @param mixed $state the stored state
     *
     * @return string
     */
    private static function _entryState($state)
    {
        return 'absent' === (string)$state ? 'absent' : 'present';
    }
}
