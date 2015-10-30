<?php
class HostManager extends FOGManagerController {
    public function getHostByMacAddresses($MACs) {
        $MACHost = $this->getSubObjectIDs('MACAddressAssociation',array('pending'=>0,'mac'=>$MACs),'hostID');
        if (count($MACHost) > 1) throw new Exception($this->foglang['ErrorMultipleHosts']);
        return $this->getClass('Host',@min($MACHost));
    }
    public function destroy($findWhere = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC', $compare = '=', $groupBy = false, $not = false) {
        if (empty($findWhere)) return parent::destroy($field);
        if (isset($findWhere['id'])) $findWhere = array('hostID'=>$findWhere['id']);
        $this->getClass('NodeFailureManager')->destroy($findWhere);
        $this->getClass('ImagingLogManager')->destroy($findWhere);
        $this->getClass('SnapinTaskManager')->destroy(array('jobID'=>$this->getSubObjectIDs('SnapinJob',$findWhere,'id')));
        $this->getClass('SnapinJobManager')->destroy($findWhere);
        $this->getClass('TaskManager')->destroy($findWhere);
        $this->getClass('ScheduledTaskManager')->destroy($findWhere);
        $this->getClass('HostAutoLogoutManager')->destroy($findWhere);
        $this->getClass('HostScreenSettingsManager')->destroy($findWhere);
        $this->getClass('GroupAssociationManager')->destroy($findWhere);
        $this->getClass('SnapinAssociationManager')->destroy($findWhere);
        $this->getClass('PrinterAssociationManager')->destroy($findWhere);
        $this->getClass('ModuleAssociationManager')->destroy($findWhere);
        $this->getClass('GreenFogManager')->destroy($findWhere);
        $this->getClass('InventoryManager')->destroy($findWhere);
        $this->getClass('UserTrackingManager')->destroy($findWhere);
        $this->getClass('MACAddressAssociationManager')->destroy($findWhere);
        return parent::destroy($fieldWhere);
    }
}
