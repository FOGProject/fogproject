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
     * hosts: any disagreement is a conflict.
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
        $macHosts = [];
        $macs = $identity['macs'] ?? [];
        if (!is_array($macs) || count($macs) > 64) {
            return ['status' => 'conflict', 'host_id' => 0];
        }
        foreach ($macs as $mac) {
            if (!is_string($mac) || !preg_match('/^(?:[0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/iD', $mac)) {
                return ['status' => 'conflict', 'host_id' => 0];
            }
            $mac = strtolower(str_replace('-', ':', $mac));
            foreach ((array)Route::getList('macaddressassociation', ['mac' => $mac, 'pending' => 0]) as $row) {
                $id = self::candidateHostID($row);
                if ($id > 0) {
                    $macHosts[$id] = $id;
                }
            }
        }
        $answer = self::choose($smbios, !empty($rows) && null === $smbios, array_values($macHosts));
        if ('resolved' === $answer['status']) {
            $host = new Host($answer['host_id']);
            if (!$host->isValid() || (!$allowPending && $host->get('pending'))) {
                return ['status' => 'unresolved', 'host_id' => 0];
            }
        }
        return $answer;
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
     * without it cannot acquire it or change its identity afterward.
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
        unset($saved['_directoryIdentity']);
        if (!empty($meta['required']) && !$required) {
            return 'feature_required';
        }
        if ($required && (empty($meta['required']) || $saved !== $incoming)) {
            return 'upgrade_blocked';
        }
        return 'allowed';
    }

    /**
     * Combines the SMBIOS answer and the MAC answers into one host.
     *
     * @param int|null $smbios    the host SMBIOS names, or null
     * @param bool     $ambiguous whether SMBIOS matched several hosts
     * @param array    $macHosts  the host ids the MACs name
     *
     * @return array [status => resolved|unresolved|conflict, host_id => int]
     */
    public static function choose($smbios, $ambiguous, array $macHosts)
    {
        $macHosts = array_values(array_unique(array_map('intval', $macHosts)));
        if ($ambiguous || count($macHosts) > 1
            || ($smbios && $macHosts && (int)$smbios !== $macHosts[0])) {
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
