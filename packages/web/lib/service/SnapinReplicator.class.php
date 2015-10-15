<?php
class SnapinReplicator extends FOGService {
    public $dev = SNAPINREPDEVICEOUTPUT;
    public $log = SNAPINREPLOGPATH;
    public $zzz = SNAPINREPSLEEPTIME;
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            $this->outall(" * Starting Snapin Replication.");
            $this->outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            $this->outall(sprintf(" | We are group name: %s",$this->getClass('StorageGroup',$myStorageGroupID)->get('name')));
            $this->outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            $this->outall(sprintf(" | We are node name: %s",$this->getClass('StorageNode',$myStorageNodeID)->get('name')));
            $SnapinAssocCount = $this->getClass('SnapinGroupAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID));
            $SnapinCount = $this->getClass('SnapinManager')->count();
            if ($SnapinAssocCount <= 0 || $SnapinCount <= 0) throw new Exception(_('There is nothing to replicate'));
            $Snapins = $this->getSubObjectIDs('SnapinGroupAssociation',array('storageGroupID'=>$myStorageGroupID),'snapinID');
            foreach ($Snapins AS $i => &$Snapin) $this->replicate_items($myStorageGroupID,$myStorageNodeID,$this->getClass('Snapin',$Snapin),true);
            unset($Snapin);
            foreach ($Snapins AS $i => &$Snapin) $this->replicate_items($myStorageGroupID,$myStorageNodeID,$this->getClass('Snapin',$Snapin),false);
            unset($Snapin);
        } catch (Exception $e) {
            $this->outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun() {
        $this->out(' ',$this->dev);
        $this->out(' +---------------------------------------------------------',$this->dev);
        $this->commonOutput();
        $this->out(' +---------------------------------------------------------',$this->dev);
    }
}
