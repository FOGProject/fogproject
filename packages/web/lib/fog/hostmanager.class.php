<?php
class HostManager extends FOGManagerController {
    public function getHostByMacAddresses($MACs) {
        $MACHost = $this->getSubObjectIDs('MACAddressAssociation',array('pending'=>0,'mac'=>$MACs),'hostID');
        if (count($MACHost) > 1) throw new Exception(static::$foglang['ErrorMultipleHosts']);
        return static::getClass('Host',@min($MACHost));
    }
    public function destroy($findWhere = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC', $compare = '=', $groupBy = false, $not = false) {
        if (empty($findWhere)) return parent::destroy($field);
        if (isset($findWhere['id'])) {
            $fieldWhere = $findWhere;
            $findWhere = array('hostID'=>$findWhere['id']);
        }
        static::getClass('NodeFailureManager')->destroy($findWhere);
        static::getClass('ImagingLogManager')->destroy($findWhere);
        static::getClass('SnapinTaskManager')->destroy(array('jobID'=>$this->getSubObjectIDs('SnapinJob',$findWhere,'id')));
        static::getClass('SnapinJobManager')->destroy($findWhere);
        static::getClass('TaskManager')->destroy($findWhere);
        static::getClass('ScheduledTaskManager')->destroy($findWhere);
        static::getClass('HostAutoLogoutManager')->destroy($findWhere);
        static::getClass('HostScreenSettingsManager')->destroy($findWhere);
        static::getClass('GroupAssociationManager')->destroy($findWhere);
        static::getClass('SnapinAssociationManager')->destroy($findWhere);
        static::getClass('PrinterAssociationManager')->destroy($findWhere);
        static::getClass('ModuleAssociationManager')->destroy($findWhere);
        static::getClass('GreenFogManager')->destroy($findWhere);
        static::getClass('InventoryManager')->destroy($findWhere);
        static::getClass('UserTrackingManager')->destroy($findWhere);
        static::getClass('MACAddressAssociationManager')->destroy($findWhere);
        return parent::destroy($fieldWhere);
    }
}
