<?php
/**
 * Manager class for Hosts.
 *
 * PHP version 7.4+
 *
 * @category HostManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Base\SmbiosIdentity;
use FOG\Items\Host;
use FOG\Router\Route;

/**
 * Manager class for Hosts.
 *
 * @category HostManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'hosts';
    /**
     * Resolve a booting machine to a host by what its firmware reports.
     *
     * Returns an id rather than setting self::$Host so the caller can hold
     * the MAC's answer and this one side by side -- FOG_HOST_IDENTIFY_SMBIOS
     * in `log` mode exists to report where the two disagree.
     *
     * A pending host is never returned: it is not a host yet, and the MAC
     * path applies the same rule.
     *
     * @param array $ids field => raw value, keyed by SmbiosIdentity::FIELDS
     *
     * @return int|null
     */
    public static function resolveHostBySmbios(array $ids)
    {
        $filter = SmbiosIdentity::usable($ids);
        if (empty($filter)) {
            return null;
        }
        $rows = Route::getList('inventory', $filter, 'OR');
        $hostID = SmbiosIdentity::pick($filter, (array)$rows);
        if (null === $hostID) {
            return null;
        }
        $Host = new Host($hostID);
        if (!$Host->isValid() || $Host->get('pending')) {
            return null;
        }
        return $hostID;
    }
    /**
     * Find a unique host by UUID, system serial, board serial and asset tag,
     * leaving it in self::$Host.
     *
     * Kept for callers of the original signature; the decision now lives in
     * SmbiosIdentity::pick() behind resolveHostBySmbios().
     *
     * @param string $sysuuid   The UUID to search
     * @param string $mbserial  The MB Serial to search
     * @param string $sysserial The system serial to search
     * @param string $caseasset The chassis asset tag to search
     *
     * @return void
     */
    public static function getHostByUuidAndSerial(
        $sysuuid,
        $mbserial,
        $sysserial,
        $caseasset = ''
    ) {
        $hostID = self::resolveHostBySmbios(
            [
                'sysuuid' => $sysuuid,
                'sysserial' => $sysserial,
                'mbserial' => $mbserial,
                'caseasset' => $caseasset
            ]
        );
        self::$Host = new Host((int)$hostID);
    }
    /**
     * Returns a single host object based on the passed MACs.
     *
     * @param array $macs the macs to search for the host
     *
     * @throws Exception
     *
     * @return void
     */
    public function getHostByMacAddresses($macs)
    {
        self::$Host = new Host();
        if (!is_array($macs)) {
            $macs = [$macs];
        }
        // Coerce each entry to a string (a MACAddress object, for example,
        // stringifies to its mac) and drop empties. Anything that cannot be
        // stringified is discarded rather than fataling strlen() on PHP 8.
        $macs = array_values(
            array_filter(
                array_map(
                    static function ($mac) {
                        if (is_scalar($mac)
                            || (is_object($mac)
                            && method_exists($mac, '__toString'))
                        ) {
                            return (string)$mac;
                        }
                        return '';
                    },
                    $macs
                ),
                'strlen'
            )
        );
        if (count($macs) < 1) {
            return;
        }
        $find = [
            'pending' => [0, ''],
            'mac' => $macs
        ];
        $MACHost = array_unique(Route::getIds('macaddressassociation', $find, 'hostID'));
        if (count($MACHost) < 1) {
            return;
        }
        if (count($MACHost) > 1) {
            $find['primary'] = 1;
            $MACHost = array_unique(Route::getIds('macaddressassociation', $find, 'hostID'));
            $macs = (array)$macs;

            if (count($MACHost ?? []) < 1) {
                return;
            }
            if (count($MACHost ?? []) > 1 && count($macs ?? []) > 0) {
                $hostIDCounts = [];
                error_log(self::$foglang['ErrorMultipleHosts'].'.');
                foreach ($macs as $mac) {

                    // So we can loop and print the individual host IDs
                    // as they're associated to devices.
                    $hostIDs = Route::getIds('macaddressassociation', ['pending' => [0, ''], 'mac' => [$mac]], 'hostID');
                    // Loop through the hostIDs
                    // and whichever host id occurs the most
                    // can be suspected "true" host and we can
                    // return that one.
                    foreach ($hostIDs as $hostID) {
                        $err = sprintf(
                            '%s: %s, %s: %s, %s: %s',
                            _('MAC'),
                            $mac,
                            _('Host ID'),
                            $hostID,
                            _('Hostname'),
                            self::getClass('Host', $hostID)->get('name')
                        );
                        // Print it in the error log.
                        error_log($err);
                        if (!isset($hostIDCounts[$hostID])) {
                            $hostIDCounts[$hostID] = 0;
                        }
                        $hostIDCounts[$hostID]++;
                    }
                }
                // Sort host ID by frequency (descending order) and get the most frequent one
                arsort($hostIDCounts);
                $mostFrequentHostIDs = array_keys($hostIDCounts, self::maxId($hostIDCounts));

                // Check if there is a tie for the most frequent host ID
                if (count($mostFrequentHostIDs) > 1) {
                    throw new \Exception(
                        _('Unable to determine the suspected true host'). '.'
                        . ' ' . _('Most Frequent Host IDs') . ': '
                        . '[' . _('Host ID') . '] => ' . _('Count'). ': '
                        . print_r($hostIDCounts, 1)
                    );
                }

                // Logging for notice somewhere
                error_log(
                    sprintf(
                        '%s: %s. %s: %s. %s. %s: %s',
                        _('Found the most used ID'),
                        $mostFrequentHostIDs[0],
                        _('Hostname'),
                        self::getClass('Host', $mostFrequentHostIDs[0])->get('name'),
                        _('Assuming this is the intended host to resolve the MAC conflict'),
                        _('List of MACs'),
                        implode(', ', $macs)
                    )
                );
                $MACHost = $mostFrequentHostIDs;
            }
        }
        self::$Host = new Host(self::maxId($MACHost));
    }
}
