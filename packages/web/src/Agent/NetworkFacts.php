<?php
/**
 * The interface list an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category WakeRelay
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;

/**
 * Reconciles a reported network block into `hostNetwork` (design 0011
 * section 3).
 *
 * A fact report like InventoryFacts, registered the same way: an entry in
 * State::FACT_REPORTS and a block in the poll, never a route of its own
 * (the route rule, protocol-v1.md).
 *
 * The contrast to draw is with `hosts.hostIP`, which this does not replace.
 * hostIP is one address with no prefix and no interface behind it, resolved
 * whenever FOG last looked; these rows are the machine's own account of its
 * links. The difference matters because a prefix is what turns an address
 * into a LINK, and "which awake machine is on the same link as this
 * sleeping one" is the question the wake relay is built out of.
 *
 * There is deliberately no audit line here. Interfaces move whenever a
 * laptop changes desk, a VPN comes up or a container engine starts, and an
 * audit entry per event would bury the results that matter under noise
 * nobody asked for.
 *
 * @category WakeRelay
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NetworkFacts extends FOGBase
{
    /**
     * Most interface addresses accepted from one host.
     *
     * A machine running containers legitimately carries dozens, so this is
     * generous rather than tight. It is here because the list is input from
     * an enrolled but otherwise untrusted host: a machine claiming a
     * million interfaces must fail this check rather than the database.
     */
    const MAX_INTERFACES = 128;

    /**
     * Column widths, so an overlong value is truncated here rather than
     * failing the insert under strict mode and costing the host its poll.
     */
    const WIDTHS = [
        'name' => 255,
        'mac' => 17,
        'ipv4' => 15,
        'network' => 15,
        'broadcast' => 15
    ];

    /**
     * Records the host's current interfaces.
     *
     * The list is complete by contract: any address currently recorded for
     * this host and absent from it is gone. That is why the agent sends no
     * block at all when it could not read its interfaces -- an empty list
     * here means "this machine is on no link", and would clear every row it
     * has (design 0006 section 6).
     *
     * @param Host  $Host  the host the certificate bound
     * @param array $block the reported network block
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return void
     */
    public static function report(Host $Host, array $block)
    {
        $list = $block['interfaces'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        if (count($list) > self::MAX_INTERFACES) {
            throw new \RuntimeException('interface list too large', 413);
        }

        $hostID = (int)$Host->get('id');
        $incoming = self::clean($list);
        $now = self::niceDate()->setTimezone(self::storageTimeZone())
            ->format('Y-m-d H:i:s');

        // Replace the set, in one transaction so nothing observes the
        // intermediate empty state -- which matters more here than it does
        // for printers, because a wake relay running against the empty
        // moment would conclude the estate had no machine on any link.
        self::$DB->query('START TRANSACTION');
        try {
            self::$DB->query(
                'DELETE FROM `hostNetwork` WHERE `hnHostID`=:host',
                [],
                [':host' => $hostID]
            );
            self::insert($hostID, $incoming, $now);
            self::$DB->query('COMMIT');
        } catch (\Exception $e) {
            self::$DB->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Normalizes the reported list, keyed by interface name and address.
     *
     * Keying deduplicates: a host reporting the same address twice would
     * otherwise hit the unique index mid-insert and roll back the whole
     * poll.
     *
     * Every address is validated here rather than trusted. The agent
     * computes the network and the broadcast itself, and this is the class
     * that decides whether to believe it -- so both are RECOMPUTED from the
     * address and prefix, and the reported values are discarded. A host
     * that claimed a network address it is not on would otherwise be a host
     * that could join any link's relay group it liked.
     *
     * @param array $list the reported interfaces
     *
     * @return array key => normalized row
     */
    private static function clean(array $list)
    {
        $out = [];
        foreach ($list as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = [];
            foreach (self::WIDTHS as $field => $width) {
                $row[$field] = substr(
                    trim((string)($entry[$field] ?? '')),
                    0,
                    $width
                );
            }
            $prefix = (int)($entry['prefix'] ?? 0);
            if ('' === $row['name'] || $prefix < 0 || $prefix > 32) {
                continue;
            }
            $long = self::ipToLong($row['ipv4']);
            if (null === $long) {
                // Not an IPv4 address. Dropped rather than stored: a row
                // with no address is on no link, so nothing reads it.
                continue;
            }
            $row['prefix'] = $prefix;
            $row['network'] = self::networkFor($long, $prefix);
            $row['broadcast'] = self::broadcastFor($long, $prefix);
            $row['mac'] = strtolower($row['mac']);
            $row['up'] = !empty($entry['up']) ? 1 : 0;
            $row['wireless'] = !empty($entry['wireless']) ? 1 : 0;
            $out[$row['name'] . '|' . $row['ipv4']] = $row;
        }

        return $out;
    }

    /**
     * An IPv4 address as an unsigned 32-bit integer, or null.
     *
     * ip2long() alone is not the check: it accepts shortened forms like
     * `10.1` that no interface reports, so the round trip through long2ip
     * is what pins the value to the dotted quad the agent sent.
     *
     * @param string $ip the address
     *
     * @return int|null
     */
    protected static function ipToLong($ip)
    {
        $long = ip2long($ip);
        if (false === $long || long2ip($long) !== $ip) {
            return null;
        }

        return $long;
    }

    /**
     * The network address for an address and prefix.
     *
     * @param int $long   the address
     * @param int $prefix the prefix length
     *
     * @return string
     */
    protected static function networkFor($long, $prefix)
    {
        return long2ip($long & self::mask($prefix));
    }

    /**
     * The broadcast address, empty where the link has none.
     *
     * A /31 is a point-to-point pair (RFC 3021) and a /32 is a host route;
     * neither has a broadcast address, and the all-ones address on a /31
     * names the peer rather than the link.
     *
     * @param int $long   the address
     * @param int $prefix the prefix length
     *
     * @return string
     */
    protected static function broadcastFor($long, $prefix)
    {
        if ($prefix >= 31) {
            return '';
        }

        return long2ip($long | (~self::mask($prefix) & 0xFFFFFFFF));
    }

    /**
     * The netmask for a prefix length, as an integer.
     *
     * A /0 is spelled out rather than shifted: `-1 << 32` is undefined
     * across platforms and PHP gives back -1, which would make every host
     * in the estate share one link.
     *
     * @param int $prefix the prefix length
     *
     * @return int
     */
    protected static function mask($prefix)
    {
        if ($prefix <= 0) {
            return 0;
        }

        return (-1 << (32 - $prefix)) & 0xFFFFFFFF;
    }

    /**
     * Inserts the reported interfaces.
     *
     * One statement rather than a row at a time, for PrinterFacts' reason:
     * a container host with fifty interfaces would otherwise cost fifty
     * round trips on every poll where anything moved.
     *
     * @param int    $hostID   the host
     * @param array  $incoming key => normalized row
     * @param string $now      the timestamp for this reconcile
     *
     * @return void
     */
    private static function insert($hostID, array $incoming, $now)
    {
        if (empty($incoming)) {
            return;
        }
        $values = [];
        $binds = [];
        $i = 0;
        foreach ($incoming as $row) {
            // A distinct placeholder name per value rather than reusing one
            // for the host id and the timestamp: a real prepared statement
            // binds each name once, and a driver that is not emulating them
            // rejects the repeat with a bound-parameter count error.
            $p = ':r' . $i++ . '_';
            $values[] = '(' . $p . 'h,' . $p . 'n,' . $p . 'm,' . $p . 'i,'
                . $p . 'p,' . $p . 'w,' . $p . 'b,' . $p . 'u,' . $p . 'l,'
                . $p . 'o)';
            $binds[$p . 'h'] = (int)$hostID;
            $binds[$p . 'n'] = $row['name'];
            $binds[$p . 'm'] = $row['mac'];
            $binds[$p . 'i'] = $row['ipv4'];
            $binds[$p . 'p'] = $row['prefix'];
            $binds[$p . 'w'] = $row['network'];
            $binds[$p . 'b'] = $row['broadcast'];
            $binds[$p . 'u'] = $row['up'];
            $binds[$p . 'l'] = $row['wireless'];
            $binds[$p . 'o'] = $now;
        }
        self::$DB->query(
            'INSERT INTO `hostNetwork` '
            . '(`hnHostID`,`hnName`,`hnMAC`,`hnIPv4`,`hnPrefix`,`hnNetwork`,'
            . '`hnBroadcast`,`hnUp`,`hnWireless`,`hnObservedAt`) VALUES '
            . implode(',', $values),
            [],
            $binds
        );
    }
}
