<?php
class SnapinReplicator extends FOGService {
    public $dev = '';
    public $log = '';
    public $zzz = '';
    public function __construct() {
        parent::__construct();
        $this->log = sprintf('%s%s',$this->logpath,$this->getSetting('SNAPINREPLICATORLOGFILENAME'));
        $this->dev = $this->getSetting('SNAPINREPLICATORDEVICEOUTPUT');
        $this->zzz = $this->getSetting('SNAPINREPLEEPTIME');
    }
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            $this->out(' * I am the group manager',$this->dev);
            $this->wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            $this->outall(" * Starting Snapin Replication.");
            $this->outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            $this->outall(sprintf(" | We are group name: %s",$this->getClass('StorageGroup',$myStorageGroupID)->get('name')));
            $this->outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            $this->outall(sprintf(" | We are node name: %s",$this->getClass('StorageNode',$myStorageNodeID)->get('name')));
            $SnapinIDs = $this->getSubObjectIDs('Snapin',array('isEnabled'=>1,'toReplicate'=>1));
            $SnapinAssocs = $this->getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$SnapinIDs),'snapinID',true);
            if (count($SnapinAssocs)) $this->getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$SnapinAssocs));
            unset($SnapinAssocs);
            $SnapinAssocCount = $this->getClass('SnapinGroupAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID,'snapinID'=>$SnapinIDs));
            $SnapinCount = $this->getClass('SnapinManager')->count();
            if ($SnapinAssocCount <= 0 || $SnapinCount <= 0) throw new Exception(_('There is nothing to replicate'));
            unset($SnapinAssocCount,$SnapinCount);
            $Snapins = $this->getClass('SnapinManager')->find(array('id'=>$this->getSubObjectIDs('SnapinGroupAssociation',array('storageGroupID'=>$myStorageGroupID,'snapinID'=>$SnapinIDs),'snapinID')));
            unset($SnapinIDs);
            foreach ((array)$Snapins AS $i => &$Snapin) {
                if (!$Snapin->isValid()) continue;
                if (!$Snapin->getPrimaryGroup($myStorageGroupID)) {
                    $this->outall(_(" | Not syncing Snapin: {$Snapin->get(name)}"));
                    $this->outall(_(' | This is not the primary group'));
                    continue;
                }
                $this->replicate_items($myStorageGroupID,$myStorageNodeID,$Snapin,true);
                unset($Snapin);
            }
            foreach ($Snapins AS $i => &$Snapin) {
                $this->replicate_items($myStorageGroupID,$myStorageNodeID,$Snapin,false);
                unset($Snapin);
            }
            unset($Snapins);
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
