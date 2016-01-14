<?php
class ImageReplicator extends FOGService {
    public $dev = '';
    public $log = '';
    public $zzz = '';
    public function __construct() {
        parent::__construct();
        $this->log = sprintf('%s%s',$this->logpath,$this->getSetting('IMAGEREPLICATORLOGFILENAME'));
        $this->dev = $this->getSetting('IMAGEREPLICATORDEVICEOUTPUT');
        $this->zzz = $this->getSetting('IMAGEREPSLEEPTIME');
    }
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
            $ImageIDs = $this->getSubObjectIDs('Image',array('isEnabled'=>1,'toReplicate'=>1));
            $ImageAssocs = $this->getSubObjectIDs('ImageAssociation',array('imageID'=>$ImageIDs),'imageID',true);
            if (count($ImageAssocs)) $this->getClass('ImageAssociationManager')->destroy(array('imageID'=>$ImageAssocs));
            unset($ImageAssocs);
            $ImageAssocCount = $this->getClass('ImageAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID,'imageID'=>$ImageIDs));
            $ImageCount = $this->getClass('ImageManager')->count();
            if ($ImageAssocCount <= 0 || $ImageCount <= 0) throw new Exception(_('There is nothing to replicate'));
            unset($ImageAssocCount,$ImageCount);
            $Images = $this->getClass('ImageManager')->find(array('id'=>$this->getSubObjectIDs('ImageAssociation',array('storageGroupID'=>$myStorageGroupID,'imageID'=>$ImageIDs),'imageID')));
            unset($ImageIDs);
            foreach ((array)$Images AS $Image) {
                if (!$Image->isValid()) continue;
                if (!$Image->getPrimaryGroup($myStorageGroupID)) {
                    $this->outall(_(" | Not syncing Image: {$Image->get(name)}"));
                    $this->outall(_(' | This is not the primary group'));
                    continue;
                }
                $this->replicate_items($myStorageGroupID,$myStorageNodeID,$Image,true);
            }
            foreach ($Images AS $i => &$Image) {
                $this->replicate_items($myStorageGroupID,$myStorageNodeID,$Image,false);
                unset($Image);
            }
            unset($Images);
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
