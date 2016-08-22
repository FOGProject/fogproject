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
     * Returns a single host object based on the passed MACs.
     *
     * @param array $MACs the macs to search for the host.
     *
     * @throws Exception
     * @return object
     */
    public function getHostByMacAddresses($MACs)
    {
        $MACHost = self::getSubObjectIDs(
            'MACAddressAssociation',
            array(
                'pending' => array(
                    0,
                    '',
                    null
                ),
                'mac' => $MACs
            ),
            'hostID'
        );
        if (count($MACHost) > 1) {
            throw new Exception(self::$foglang['ErrorMultipleHosts']);
        }
        return new Host(@max($MACHost));
    }
    /**
     * Removes fields.
     *
     * Customized for hosts
     *
     * @param array  $findWhere     What to search for
     * @param string $whereOperator Join multiple where fields
     * @param string $orderBy       Order returned fields by
     * @param string $sort          How to sort, ascending, descending
     * @param string $compare       How to compare fields
     * @param mixed  $groupBy       How to group fields
     * @param mixed  $not           Comparator but use not instead.
     *
     * @return parent::destroy
     */
    public function destroy(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false
    ) {
        if (empty($findWhere)) {
            return parent::destroy($field);
        }
        if (isset($findWhere['id'])) {
            $fieldWhere = $findWhere;
            $findWhere = array('hostID'=>$findWhere['id']);
        }
        $SnapinJobIDs = array(
            'jobID' => self::getSubObjectIDs('SnapinJob', $findWhere),
        );
        self::getClass('NodeFailureManager')->destroy($findWhere);
        self::getClass('ImagingLogManager')->destroy($findWhere);
        self::getClass('SnapinTaskManager')->destroy($SnapinJobIDs);
        self::getClass('SnapinJobManager')->destroy($findWhere);
        self::getClass('TaskManager')->destroy($findWhere);
        self::getClass('ScheduledTaskManager')->destroy($findWhere);
        self::getClass('HostAutoLogoutManager')->destroy($findWhere);
        self::getClass('HostScreenSettingsManager')->destroy($findWhere);
        self::getClass('GroupAssociationManager')->destroy($findWhere);
        self::getClass('SnapinAssociationManager')->destroy($findWhere);
        self::getClass('PrinterAssociationManager')->destroy($findWhere);
        self::getClass('ModuleAssociationManager')->destroy($findWhere);
        self::getClass('GreenFogManager')->destroy($findWhere);
        self::getClass('InventoryManager')->destroy($findWhere);
        self::getClass('UserTrackingManager')->destroy($findWhere);
        self::getClass('MACAddressAssociationManager')->destroy($findWhere);
        self::getClass('PowerManagementManager')->destroy($findWhere);
        return parent::destroy($fieldWhere);
    }
}
