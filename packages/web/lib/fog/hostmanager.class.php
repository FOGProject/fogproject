<?php
/**
 * Manager class for Hosts.
 *
 * PHP Version 5
 *
 * @category HostManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     * Try to find a unique host object based on UUID, system serial, and MB Serial
     *
     * @param string $sysuuid   The UUID to search
     * @param string $mbserial  The MB Serial to search
     * @param string $sysserial The system serial to search
     *
     * @thows Exception
     *
     * @return void
     */
    public static function getHostByUuidAndSerial(
        $sysuuid,
        $mbserial,
        $sysserial
    ) {
        self::$Host = new Host();
        /**
         * Can probably be removed by will keep this list for now in case
         * we need it.
         */
        $invalidUuids = [
            '00020003-0004-0005-0006-000700080009',
            '00000000-0000-0000-0000-000000000000',
            '00000000-0000-0000-0000-*',
            '12345678-1234-5678-90AB-CDDEEFAABBCC',
            'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
            'FFFFFF00-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
            'Not Present',
            'Not Settable'
        ];
        $invalidMbSerial = [
            'Type2 - Board Serial Number',
            'To be filled by O.E.M.',
            'Not Applicable',
            'Default string',
            'Base Board Serial Number',
            '.PCIE2'
        ];
        $invalidSysSerial = [
            '123456789'
        ];

        $filter = [];
        if (strlen($sysuuid) != 0 && !in_array($sysuuid, $invalidUuids)) {
            $filter['sysuuid'] = $sysuuid;
        }
        if (strlen($mbserial) != 0 && !in_array($mbserial, $invalidMbSerial)) {
            $filter['mbserial'] = $mbserial;
        }
        if (strlen($sysserial) != 0 && !in_array($sysserial, $invalidSysSerial)) {
            $filter['sysserial'] = $sysserial;
        }
        if (empty($filter)) {
            return;
        }
        $Inventories = Route::getList('inventory', $filter, 'OR');
        if (count($Inventories ?: []) < 1) {
            return;
        }
        if (count($Inventories ?: []) == 1) {
            self::$Host = new Host($Inventories[0]->hostID);
            return;
        }
        $highestScore = 0;
        foreach ($Inventories as &$Inventory) {
            $inventoryCompare = [];
            if (strlen($Inventory->sysuuid) != 0) {
                $inventoryCompare['sysuuid'] = $Inventory->sysuuid;
            }
            if (strlen($Inventory->mbserial) != 0) {
                $inventoryCompare['mbserial'] = $Inventory->mbserial;
            }
            if (strlen($Inventory->sysserial) != 0) {
                $inventoryCompare['sysserial'] = $Inventory->sysserial;
            }
            $score = count(array_intersect($inventoryCompare, $filter));
            if ($score > $highestScore) {
                $highestScore = $score;
                $hostID = $Inventory->hostID;
            }
            unset($Inventory);
        }
        if (is_numeric($hostID)) {
            self::$Host = new Host($hostID);
        }
        return;
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
