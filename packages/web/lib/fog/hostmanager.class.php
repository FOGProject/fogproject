<?php
class HostManager extends FOGManagerController {
    public function getHostByMacAddresses($MACs) {
        $MACHost = $this->getSubObjectIDs('MACAddressAssociation',array('pending'=>0,'mac'=>$MACs),'hostID');
        if (count($MACHost) > 1) throw new Exception(self::$foglang['ErrorMultipleHosts']);
        return self::getClass('Host',@min($MACHost));
    }
    public function destroy($findWhere = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC', $compare = '=', $groupBy = false, $not = false) {
        if (empty($findWhere)) return parent::destroy($field);
        if (isset($findWhere['id'])) {
            $fieldWhere = $findWhere;
            $findWhere = array('hostID'=>$findWhere['id']);
        }
        self::getClass('NodeFailureManager')->destroy($findWhere);
        self::getClass('ImagingLogManager')->destroy($findWhere);
        self::getClass('SnapinTaskManager')->destroy(array('jobID'=>$this->getSubObjectIDs('SnapinJob',$findWhere,'id')));
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
        return parent::destroy($fieldWhere);
    }
}
