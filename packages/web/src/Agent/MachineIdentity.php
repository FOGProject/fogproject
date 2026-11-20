<?php
/**
 * Live directory identity resolution for an enrolled agent.
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Base\SmbiosIdentity;
use FOG\Items\Host;
use FOG\Router\Route;

/**
 * Decides which host a machine's live SMBIOS and MAC values name.
 *
 * Authentication remains the existing client certificate. This class only
 * answers whether the identity the machine reports still names the host the
 * certificate is bound to, so a directory join cannot act on the wrong host.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MachineIdentity extends FOGBase
{
    /**
     * The feature token an agent sends to opt in to this check.
     *
     * @var string
     */
    const FEATURE = 'directory-join-target-v1';

    /**
     * Did the request declare the directory identity feature?
     *
     * @param array $body the decoded JSON body
     *
     * @return bool
     */
    public static function requested(array $body)
    {
        return in_array(self::FEATURE, (array)($body['features'] ?? []), true);
    }

    /**
     * Resolves the host a live identity names.
     *
     * Unlike the legacy MAC resolver, it never votes between different
     * hosts: any disagreement is a conflict. A MAC host whose stored serial
     * proves it is a different machine is not a disagreement (choose()).
     *
     * @param array $identity     the identity block from the request
     * @param bool  $allowPending whether a pending host may resolve
     *
     * @return array [status => resolved|unresolved|conflict, host_id => int]
     */
    public static function resolve(array $identity, $allowPending = false)
    {
        foreach (array_keys(Enrollment::IDENTITY_MAP) as $key) {
            if (isset($identity[$key]) && (!is_string($identity[$key]) || strlen($identity[$key]) > 256)) {
                return ['status' => 'conflict', 'host_id' => 0];
            }
        }
        $ids = [];
        if ('off' !== self::getSetting('FOG_HOST_IDENTIFY_SMBIOS')) {
            foreach (Enrollment::IDENTITY_MAP as $key => $field) {
                $ids[$field] = SmbiosIdentity::canonicalize($identity[$key] ?? '');
            }
        }
        $filter = SmbiosIdentity::usable($ids);
        $rows = empty($filter) ? [] : (array)Route::getList('inventory', $filter, 'OR');
        $smbios = SmbiosIdentity::pick($filter, $rows);
        $macHosts = self::macHostIDs($identity['macs'] ?? []);
        if (null === $macHosts) {
            return ['status' => 'conflict', 'host_id' => 0];
        }
        // A serial that contradicts a MAC host's means a different machine
        // carries that MAC (a shared USB adapter, forum 18319). Firmware
        // that names no host cannot vouch, but it can still refuse. Firmware
        // that names a host wins over such a MAC host.
        $macClash = null === $smbios && 1 === count($macHosts)
            && null !== self::serialClash((int)reset($macHosts), $filter);
        // The firmware host must not contradict the serial either. A UUID
        // match alone (a copied UUID) never moves the host past a MAC.
        $sharedHosts = [];
        foreach ($macHosts as $id) {
            if (self::sharedAdapter((int)$smbios, $id, $filter)) {
                $sharedHosts[] = $id;
            }
        }
        $answer = self::choose(
            $smbios,
            !empty($rows) && null === $smbios,
            array_values($macHosts),
            $macClash,
            $sharedHosts
        );
        if ('resolved' === $answer['status']) {
            $host = new Host($answer['host_id']);
            if (!$host->isValid() || (!$allowPending && $host->get('pending'))) {
                return ['status' => 'unresolved', 'host_id' => 0];
            }
        }
        return $answer;
    }

    /**
     * Is the MAC host's MAC on a shared adapter, so the firmware's host wins?
     *
     * True only when the firmware names a different host, the MAC host's
     * stored serial contradicts the reported one (a different machine), and
     * the firmware host's stored serial does not (a copied UUID alone never
     * wins). Forum 18319. Both enrollment resolvers use this one rule.
     *
     * @param int   $smbiosID the host the firmware names, or 0
     * @param int   $macID    the host a MAC names
     * @param array $ids      field => value the machine reported
     *
     * @return bool
     */
    public static function sharedAdapter($smbiosID, $macID, array $ids)
    {
        return $smbiosID > 0 && $macID > 0 && $smbiosID !== $macID
            && null !== self::serialClash($macID, $ids)
            && null === self::serialClash($smbiosID, $ids);
    }

    /**
     * The hosts the reported MACs belong to, by approved MAC rows.
     *
     * @param mixed $macs the MAC list from the identity block
     *
     * @return array|null host id => host id, or null when the list is not
     *                    a list of at most 64 valid MACs
     */
    public static function macHostIDs($macs)
    {
        if (!is_array($macs) || count($macs) > 64) {
            return null;
        }
        $hosts = [];
        foreach ($macs as $mac) {
            if (!is_string($mac) || !preg_match('/^(?:[0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/iD', $mac)) {
                return null;
            }
            $mac = strtolower(str_replace('-', ':', $mac));
            foreach ((array)Route::getList('macaddressassociation', ['mac' => $mac, 'pending' => 0]) as $row) {
                $id = self::candidateHostID($row);
                if ($id > 0) {
                    $hosts[$id] = $id;
                }
            }
        }
        return $hosts;
    }

    /**
     * The host id of one association row, from an object or an array.
     *
     * @param object|array $row a Route::getList() row
     *
     * @return int 0 when the row names no host
     */
    public static function candidateHostID($row)
    {
        if (is_object($row) && method_exists($row, 'get')) {
            return (int)$row->get('hostID');
        }
        $row = (array)$row;
        return (int)($row['hostID'] ?? 0);
    }

    /**
     * Decides whether an enroll request may use the stored row.
     *
     * A key enrolled with the feature cannot drop it, and a key enrolled
     * without it cannot acquire it or change its SMBIOS identity afterward.
     *
     * The MAC list may change. Docks, USB adapters, and an agent that stops
     * sending a software adapter (a VPN TAP copied in an image) all change
     * it, and the agent keeps its key across each of them. Allowing the
     * change never writes the stored identity: a repeat is answered from
     * the stored row, and issuance revalidates the stored identity
     * (Enrollment::guardDirectoryIdentity), never this request's.
     *
     * @param array $saved    the identity stored with the row
     * @param array $incoming the identity in this request
     * @param bool  $required whether this request declares the feature
     *
     * @return string allowed, feature_required or upgrade_blocked
     */
    public static function enrollmentGate(array $saved, array $incoming, $required)
    {
        $meta = $saved['_directoryIdentity'] ?? [];
        if (!empty($meta['required']) && !$required) {
            return 'feature_required';
        }
        if ($required) {
            if (empty($meta['required'])) {
                return 'upgrade_blocked';
            }
            foreach (array_keys(Enrollment::IDENTITY_MAP) as $key) {
                if (($saved[$key] ?? null) !== ($incoming[$key] ?? null)) {
                    return 'upgrade_blocked';
                }
            }
        }
        return 'allowed';
    }

    /**
     * The serial on which a host's stored inventory contradicts what a
     * machine reports, or null (SmbiosIdentity::contradicts()).
     *
     * A host with no inventory row has nothing to contradict.
     *
     * @param int   $hostID the host a MAC named
     * @param array $ids    field => value the machine reported
     *
     * @return string|null
     */
    public static function serialClash($hostID, array $ids)
    {
        if ($hostID < 1 || empty($ids)) {
            return null;
        }
        $Inventory = (new Host($hostID))->get('inventory');
        if (!$Inventory || !$Inventory->isValid()) {
            return null;
        }
        $stored = [];
        foreach (SmbiosIdentity::SERIALS as $field) {
            $stored[$field] = $Inventory->get($field);
        }
        return SmbiosIdentity::contradicts($stored, $ids);
    }

    /**
     * Combines the SMBIOS answer and the MAC answers into one host.
     *
     * SMBIOS and a MAC that name different hosts are a conflict. The one
     * exception is a host in $sharedHosts: its stored serial proves that it
     * is a different machine, so its MAC is on a shared adapter. When
     * SMBIOS names a host, such a MAC host is ignored and the firmware's
     * host wins (forum 18319). Boot does the same in enforce and register
     * mode.
     *
     * @param int|null $smbios      the host SMBIOS names, or null
     * @param bool     $ambiguous   whether SMBIOS matched several hosts
     * @param array    $macHosts    the host ids the MACs name
     * @param bool     $macClash    whether the reported serial contradicts
     *                              the MAC host's stored one (serialClash())
     * @param array    $sharedHosts the MAC host ids sharedAdapter() accepts
     *
     * @return array [status => resolved|unresolved|conflict, host_id => int]
     */
    public static function choose($smbios, $ambiguous, array $macHosts, $macClash = false, array $sharedHosts = [])
    {
        $macHosts = array_values(array_unique(array_map('intval', $macHosts)));
        if ($smbios) {
            $macHosts = array_values(array_diff($macHosts, array_map('intval', $sharedHosts)));
        }
        if ($ambiguous || count($macHosts) > 1
            || ($smbios && $macHosts && (int)$smbios !== $macHosts[0])
            || (!$smbios && $macHosts && $macClash)) {
            return ['status' => 'conflict', 'host_id' => 0];
        }
        $id = (int)($smbios ?: ($macHosts[0] ?? 0));
        return ['status' => $id > 0 ? 'resolved' : 'unresolved', 'host_id' => $id];
    }

    /**
     * Answers POST /agent/v1/identity/check for the calling host.
     *
     * host_id is the resolved host only on a match; otherwise it is 0, so a
     * mismatch does not reveal which host owns the values sent.
     *
     * @param Host  $host the host the client certificate is bound to
     * @param array $body the decoded JSON body
     *
     * @throws \RuntimeException 400 when the request is not a valid check
     *
     * @return array [status => ok|identity_blocked, identity => array]
     */
    public static function check(Host $host, array $body)
    {
        if (!self::requested($body) || !is_array($body['identity'] ?? null)) {
            throw new \RuntimeException('identity_request_invalid', 400);
        }
        $answer = self::resolve($body['identity']);
        $principal = (int)$host->get('id');
        if ('resolved' === $answer['status']) {
            $answer['status'] = $answer['host_id'] === $principal ? 'matched' : 'mismatch';
        }
        // Only a match may name a host. On a mismatch the resolved id would
        // tell an enrolled agent which host owns the values it sent.
        if ('matched' !== $answer['status']) {
            $answer['host_id'] = 0;
        }
        return ['status' => 'matched' === $answer['status'] ? 'ok' : 'identity_blocked',
            'identity' => $answer + ['principal_host_id' => $principal]];
    }
}
