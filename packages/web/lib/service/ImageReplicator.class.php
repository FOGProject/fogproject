<?php
class ImageReplicator extends FOGService {
    public $dev = REPLICATORDEVICEOUTPUT;
    public $log = REPLICATORLOGPATH;
    public $zzz = REPLICATORSLEEPTIME;
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            $this->out(' * I am the group manager',$this->dev);
            $this->wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            $this->outall(" * Starting Image Replication.");

            $this->outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            $this->outall(sprintf(" | We are group name: %s",$this->getClass('StorageGroup',$myStorageGroupID)->get('name')));
            $this->outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            $this->outall(sprintf(" | We are node name: %s",$this->getClass('StorageNode',$myStorageNodeID)->get('name')));
            $ImageAssocCount = $this->getClass('ImageAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID));
            $ImageCount = $this->getClass('ImageManager')->count();
            if ($ImageAssocCount <= 0 || $ImageCount <= 0) throw new Exception(_('There is nothing to replicate'));
            $Images = $this->getSubObjectIDs('ImageAssociation',array('storageGroupID'=>$myStorageGroupID),'imageID');
            foreach ($Images AS $i => &$Image) $this->replicate_items($myStorageGroupID,$myStorageNodeID,$this->getClass('Image',$Image),true);
            unset($Image);
            foreach ($Images AS $i => &$Image) $this->replicate_items($myStorageGroupID,$myStorageNodeID,$this->getClass('Image',$Image),false);
            unset($Image);
        } catch (Exception $e) {
            $this->outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun() {
        $this->out(' ',$this->dev);
        $this->out(' +---------------------------------------------------------',$this->dev);
        $this->out(' * Checking if I am the group manager.',$this->dev);
        $this->wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
        $this->commonOutput();
        $this->out(' +---------------------------------------------------------',$this->dev);
    }
}
