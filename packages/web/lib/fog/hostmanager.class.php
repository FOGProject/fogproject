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
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'hostID',
                'hostName',
                'hostDesc',
                'hostIP',
                'hostImage',
                'hostBuilding',
                'hostCreateDate',
                'hostCreateBy',
                'hostLastDeploy',
                'hostUseAD',
                'hostADDomain',
                'hostADOU',
                'hostADUser',
                'hostADPass',
                'hostADPassLegacy',
                'hostProductKey',
                'hostPrinterLevel',
                'hostKernelArgs',
                'hostKernel',
                'hostDevice',
                'hostInit',
                'hostPending',
                'hostPubKey',
                'hostSecToken',
                'hostSecTime',
                'hostPingCode',
                'hostExitBios',
                'hostExitEfi',
                'hostEnforce'
            ],
            [
                'INTEGER',
                'VARCHAR(16)',
                'LONGTEXT',
                'VARCHAR(25)',
                'INTEGER',
                'INTEGER',
                'TIMESTAMP',
                'VARCHAR(40)',
                'DATETIME',
                "ENUM('0', '1')",
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'LONGTEXT',
                'LONGTEXT',
                'VARCHAR(2)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'LONGTEXT',
                "ENUM('0', '1')",
                'LONGTEXT',
                'LONGTEXT',
                'TIMESTAMP',
                'VARCHAR(20)',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1')"
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                '0000-00-00 00:00:00',
                false,
                false,
                false,
                '1'
            ],
            [
                'hostID',
                'hostName'
            ],
            'InnoDB',
            'utf8',
            'hostID',
            'hostID'
        );
    }
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
        Route::listem('inventory', $filter, false, 'OR');
        $Inventories = json_decode(Route::getData());
        $Inventories = $Inventories->data;
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
        $find = [
            'pending' => [0, ''],
            'mac' => $macs
        ];
        Route::ids(
            'macaddressassociation',
            $find,
            'hostID'
        );
        $MACHost = array_unique(json_decode(Route::getData(), true));
        if (count($MACHost) < 1) {
            return;
        }
        if (count($MACHost) > 1) {
            $find['primary'] = 1;
            Route::ids(
                'macaddressassociation',
                $find,
                'hostID'
            );
            $MACHost = array_unique(json_decode(Route::getData(), true));
            $macs = (array)$macs;

            if (count($MACHost ?? []) < 1) {
                return;
            }
            if (count($MACHost ?? []) > 1 && count($macs ?? []) > 0) {
                $hostIDCounts = [];
                foreach ($macs as $mac) {
                    Route::ids(
                        'macaddressassociation',
                        [
                            'pending' => [0, ''],
                            'mac' => [$mac]
                        ],
                        'hostID'
                    );

                    // So we can loop and print the individual host IDs
                    // as they're associated to devices.
                    $hostIDs = json_decode(Route::getData(), true);
                    $err = sprintf(
                        '%s, %s: %s, %s: %s',
                        self::$foglang['ErrorMultipleHosts'],
                        _('MAC'),
                        $mac,
                        _('Host IDs'),
                        implode(', ', $hostIDs)
                    );
                    // Print it in the error log.
                    error_log($err);
                    // Loop through the hostIDs
                    // and whichever host id occurs the most
                    // can be suspected "true" host and we can
                    // return that one.
                    foreach ($hostIDs as $hostID) {
                        if (!isset($hostIDCounts[$hostID])) {
                            $hostIDCounts[$hostID] = 0;
                        }
                        $hostIDCounts[$hostID]++;
                    }
                }
                // Sort host ID by frequency (descending order) and get the most frequent one
                arsort($hostIDCounts);
                $mostFrequentHostIDs = array_keys($hostIDCounts, max($hostIDCounts));

                // Check if there is a tie for the most frequent host ID
                if (count($mostFrequentHostIDs) > 1) {
                    throw new Exception(_('Unable to determine the suspected true host.'));
                }

                $mostFrequentHostID = $mostFrequentHostIDS[0];
            }
        }
        self::$Host = new Host($mostFrequentHostID ?: @max($MACHost));
    }
}
