<?php
/** Live identity resolution for MJ-1; authentication remains the existing certificate. */
namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Base\SmbiosIdentity;
use FOG\Items\Host;
use FOG\Router\Route;

class MachineIdentity extends FOGBase
{
    const FEATURE = 'directory-join-mj1';

    public static function requested(array $body)
    {
        return in_array(self::FEATURE, (array)($body['features'] ?? []), true);
    }

    /** Unlike the legacy MAC resolver, never vote between different hosts. */
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

    public static function candidateHostID($row)
    {
        if (is_object($row) && method_exists($row, 'get')) {
            return (int)$row->get('hostID');
        }
        $row = (array)$row;
        return (int)($row['hostID'] ?? 0);
    }

    public static function enrollmentGate(array $saved, array $incoming, $required)
    {
        $meta = $saved['_mj1'] ?? [];
        unset($saved['_mj1']);
        if (!empty($meta['required']) && !$required) {
            return 'feature_required';
        }
        if (!empty($meta['upgrade_blocked'])
            || ($required && (empty($meta['required']) || $saved !== $incoming))) {
            return 'upgrade_blocked';
        }
        return 'allowed';
    }

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
        return ['status' => 'matched' === $answer['status'] ? 'ok' : 'identity_blocked',
            'identity' => $answer + ['principal_host_id' => $principal]];
    }
}
