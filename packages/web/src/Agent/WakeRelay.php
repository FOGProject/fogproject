<?php
/**
 * Waking a machine on a link no FOG server can reach.
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

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Router\Route;

/**
 * Asks an already-awake agent to broadcast a magic packet for a sleeping
 * neighbor (design 0011).
 *
 * A magic packet is a link-layer broadcast, so FOG can only send one from a
 * machine it owns. `FOGBase::wakeUp()` already fans out to every enabled,
 * online storage node, which covers every link FOG has a machine on -- and
 * in a routed estate a subnet routinely has FOG hosts on it and no FOG
 * server or storage node at all. The documented answer, a directed
 * broadcast, has been off by default on enterprise routers since the smurf
 * attack, and asking a security team to re-enable it is asking them to undo
 * a decision that was right.
 *
 * The sender that was always there is a machine already ON that link,
 * already awake, already authenticated to FOG. That is what this class
 * finds and asks.
 *
 * The security shape is the whole design, so both halves are stated here
 * rather than only in the document:
 *
 * - THE SERVER PICKS BOTH ENDS. An agent never chooses a target, and the
 *   target is always a row in `hosts` whose MACs are that host's own
 *   `hostMAC` rows. There is no path from an arbitrary MAC to the wire.
 * - THE AGENT IS NEVER TOLD WHERE TO SEND. The block carries host ids and
 *   MACs and no destination at all; the agent broadcasts on its own
 *   interfaces. An agent that accepted a destination would be a UDP
 *   reflector for whoever could feed it one.
 *
 * This is ADDITIONAL. The node fan-out still runs first and unchanged, and
 * an estate with a storage node on the link never needs any of this.
 *
 * @category WakeRelay
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WakeRelay extends FOGBase
{
    /**
     * The setting that turns the relay on for the whole install.
     *
     * Off by default. This asks one customer machine to put traffic on the
     * network on behalf of another, which is a thing an estate owner opts
     * into rather than discovers after an upgrade.
     */
    const RELAY_SETTING = 'FOG_AGENT_WAKE_RELAY_ENABLED';

    /**
     * How many neighbors are asked for one wake.
     *
     * More than one on purpose. A magic packet is a single UDP datagram, so
     * asking three costs nothing measurable, and the alternative is a wake
     * that silently does nothing because the one chosen sender went to
     * sleep between the poll that told it and the moment it would have
     * sent.
     */
    const MAX_SENDERS = 3;

    /**
     * Seconds a request stays askable.
     *
     * This is what keeps a wake from becoming a standing instruction. A
     * laptop that comes back next Tuesday must not be handed a wake
     * somebody ordered last week, by which time the machine is either
     * already awake or deliberately off.
     */
    const TTL = 600;

    /**
     * Seconds since a host's last check-in for it to count as awake.
     *
     * A sender that last polled an hour ago is a sender that is asleep, and
     * asking it is how a wake gets recorded as pending forever. Three poll
     * intervals at the default five minutes: long enough to survive one
     * missed poll, short enough to mean something.
     */
    const AWAKE_WITHIN = 900;

    /**
     * Most targets in one poll answer, mirroring the agent's own constant.
     *
     * The agent enforces its own ceiling regardless of what arrives, which
     * is the half that matters; this one keeps the server from composing a
     * block it knows will be truncated.
     */
    const MAX_TARGETS = 32;

    /**
     * What an agent may report for one relay.
     */
    const STATUS_SENT = 'sent';
    const STATUSES = ['sent', 'failed'];

    /**
     * The state of a request nobody got to in time.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';

    /**
     * Longest detail kept: the column is a varchar(255) because this is a
     * line an admin reads in a report, not a log.
     */
    const MAX_DETAIL = 255;

    /**
     * Asks this host's awake neighbors to wake it.
     *
     * Called alongside the existing node fan-out, never instead of it. A
     * return of zero is the normal answer in an estate that does not need
     * this: the relay is off, or FOG already owns a machine on the link.
     *
     * @param Host   $Target the host to wake
     * @param string $by     who asked, for the record
     *
     * @return int how many neighbors were asked
     */
    public static function request(Host $Target, $by = '')
    {
        if (!self::enabled() || !$Target->isValid()) {
            return 0;
        }
        $targetID = (int)$Target->get('id');
        $senders = self::senders($targetID);
        if (empty($senders)) {
            return 0;
        }

        $now = self::niceDate();
        $requestedAt = self::stamp($now);
        $expiresAt = self::stamp(
            (clone $now)->modify('+' . self::TTL . ' seconds')
        );
        foreach ($senders as $senderID) {
            $Wake = new \FOG\Items\AgentWake();
            $Wake
                ->set('targetID', $targetID)
                ->set('senderID', (int)$senderID)
                ->set('requestedAt', $requestedAt)
                ->set('expiresAt', $expiresAt)
                ->set('status', self::STATUS_PENDING)
                ->set('requestedBy', substr(trim((string)$by), 0, 255))
                ->save();
        }

        Audit::record(
            [
                'type' => 'agent.wake',
                'subjectType' => 'host',
                'subjectID' => $targetID,
                'subjectLabel' => (string)$Target->get('name'),
                'renderable' => 1,
                'affectedCount' => count($senders),
                'text' => substr(
                    sprintf(
                        'asked %d neighboring agent(s) to broadcast a wake',
                        count($senders)
                    ),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );

        return count($senders);
    }

    /**
     * The wake block for a host, or null when it has nothing to relay.
     *
     * Null is the answer essentially always: a wake is a rare event and it
     * is pending for only a few minutes.
     *
     * @param Host $Host the principal
     *
     * @return array|null
     */
    public static function desired(Host $Host)
    {
        if (!self::enabled()) {
            return null;
        }
        self::expire();

        $rows = self::$DB->query(
            'SELECT `awTargetID` FROM `agentWake` '
            . 'WHERE `awSenderID`=:sender AND `awStatus`=:pending '
            . 'ORDER BY `awRequestedAt` ASC LIMIT ' . (int)self::MAX_TARGETS,
            [],
            [
                ':sender' => (int)$Host->get('id'),
                ':pending' => self::STATUS_PENDING
            ]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $targets = [];
        foreach ((array)$rows as $row) {
            $targetID = (int)($row['awTargetID'] ?? 0);
            $macs = self::macsFor($targetID);
            if (empty($macs)) {
                // Nothing to send. Left pending so it expires on its own
                // rather than being reported as a failure by a machine
                // that never had anything to fail at.
                continue;
            }
            $targets[] = ['id' => $targetID, 'macs' => $macs];
        }
        if (empty($targets)) {
            return null;
        }

        return ['targets' => $targets];
    }

    /**
     * Records what an agent did about one relay.
     *
     * The authorization is the pending row itself. A host may only report
     * on a wake it was actually ASKED to send, which is the check that
     * makes the item id safe to be another host's: without it any enrolled
     * agent could write a result against any host in the estate.
     *
     * @param Host  $Host     the host the certificate bound
     * @param int   $targetID the host it says it woke
     * @param array $body     the reported result
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return string the status recorded
     */
    public static function report(Host $Host, $targetID, array $body)
    {
        $status = (string)($body['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('unknown status', 400);
        }

        $ids = Route::getIds(
            'agentwake',
            [
                'senderID' => (int)$Host->get('id'),
                'targetID' => (int)$targetID,
                'status' => self::STATUS_PENDING
            ],
            'id'
        );
        $id = (int)(array_shift($ids) ?: 0);
        if ($id < 1) {
            throw new \RuntimeException('no wake was requested of this host', 404);
        }

        $Wake = new \FOG\Items\AgentWake($id);
        $Wake
            ->set('status', $status)
            // The packet count, because "sent" with a count of zero would
            // be a lie -- and FOG's existing wake path cannot tell the
            // difference at all.
            ->set('packets', max(0, (int)($body['packets'] ?? 0)))
            ->set('detail', substr(
                trim((string)($body['details'] ?? '')),
                0,
                self::MAX_DETAIL
            ))
            ->set('reportedAt', self::stamp(self::niceDate()))
            ->save();

        return $status;
    }

    /**
     * The hosts that could broadcast for this one.
     *
     * The query is the design in one place. A candidate has to be:
     *
     * 1. on the same LINK -- the same network address AND the same prefix,
     *    which is what makes two addresses neighbors rather than merely
     *    similar,
     * 2. able to broadcast there: the interface up, the link carrying a
     *    broadcast address at all, and not wireless (an access point will
     *    not bridge a broadcast to a station that is asleep and therefore
     *    not associated, so a wireless relay sends into a link the target
     *    has already left),
     * 3. awake, judged by its own agent check-in, and
     * 4. not the target, which is asleep and is the reason we are here.
     *
     * Ordered by the most recent check-in, because the machine that spoke
     * most recently is the one most likely to still be listening.
     *
     * @param int $targetID the host to wake
     *
     * @return int[] host ids
     */
    protected static function senders($targetID)
    {
        $rows = self::$DB->query(
            'SELECT DISTINCT `mine`.`hnHostID` AS `hostID` '
            . 'FROM `hostNetwork` AS `theirs` '
            . 'INNER JOIN `hostNetwork` AS `mine` '
            . 'ON `mine`.`hnNetwork` = `theirs`.`hnNetwork` '
            . 'AND `mine`.`hnPrefix` = `theirs`.`hnPrefix` '
            . 'INNER JOIN `hosts` ON `hostID` = `mine`.`hnHostID` '
            . 'WHERE `theirs`.`hnHostID` = :target '
            . 'AND `mine`.`hnHostID` <> :target2 '
            . 'AND `mine`.`hnUp` = 1 '
            . 'AND `mine`.`hnWireless` = 0 '
            . "AND `mine`.`hnBroadcast` <> '' "
            . 'AND `hostAgentCheckin` >= :fresh '
            . 'ORDER BY `hostAgentCheckin` DESC '
            . 'LIMIT ' . (int)self::MAX_SENDERS,
            [],
            [
                ':target' => (int)$targetID,
                ':target2' => (int)$targetID,
                ':fresh' => self::stamp(
                    self::niceDate()
                        ->modify('-' . self::AWAKE_WITHIN . ' seconds')
                )
            ]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $out = [];
        foreach ((array)$rows as $row) {
            $out[] = (int)$row['hostID'];
        }

        return $out;
    }

    /**
     * The target's MAC addresses.
     *
     * PENDING MACs are excluded, the way `Group::wakeOnLAN()` already does
     * and `Host::wakeOnLAN()` does not. A pending MAC is one FOG has seen
     * and nobody has accepted, and asking the fleet to broadcast at it is
     * exactly the wrong default. This is a deliberate behavior difference
     * from the existing path, and it is confined to the new one --
     * narrowing `Host::wakeOnLAN()` is a separate change with its own blast
     * radius.
     *
     * @param int $targetID the host to wake
     *
     * @return string[]
     */
    protected static function macsFor($targetID)
    {
        $macs = Route::getIds(
            'macaddressassociation',
            ['hostID' => (int)$targetID, 'pending' => [0, '']],
            'mac'
        );
        $out = [];
        foreach ((array)$macs as $mac) {
            $mac = trim((string)$mac);
            if ('' !== $mac) {
                $out[] = $mac;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Ages out requests nobody got to.
     *
     * Run on the read rather than on a cron: the rows only matter when a
     * poll is composing a block, and a request that expired unnoticed is
     * one nobody was going to act on anyway.
     *
     * @return void
     */
    protected static function expire()
    {
        self::$DB->query(
            'UPDATE `agentWake` SET `awStatus`=:expired '
            . 'WHERE `awStatus`=:pending AND `awExpiresAt` < :now',
            [],
            [
                ':expired' => self::STATUS_EXPIRED,
                ':pending' => self::STATUS_PENDING,
                ':now' => self::stamp(self::niceDate())
            ]
        );
    }

    /**
     * Whether the relay is on for this install.
     *
     * @return bool
     */
    protected static function enabled()
    {
        return (bool)self::getSetting(self::RELAY_SETTING);
    }

    /**
     * A moment in storage time.
     *
     * @param \DateTime $at the moment
     *
     * @return string
     */
    protected static function stamp($at)
    {
        // Cloned, because setTimezone() and modify() both mutate in place:
        // stamping a moment must not move the caller's copy of it.
        $when = clone $at;

        return $when->setTimezone(self::storageTimeZone())
            ->format('Y-m-d H:i:s');
    }
}
