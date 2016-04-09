<?php
class ImageReplicator extends FOGService {
    public $dev = '';
    public $log = '';
    public $zzz = '';
    public $sleeptime = 'IMAGEREPSLEEPTIME';
    public function __construct() {
        parent::__construct();
        $this->log = sprintf('%s%s',$this->logpath,$this->getSetting('IMAGEREPLICATORLOGFILENAME'));
        $this->dev = $this->getSetting('IMAGEREPLICATORDEVICEOUTPUT');
        $this->zzz = (int)$this->getSetting($this->sleeptime);
    }
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            static::out(' * I am the group manager',$this->dev);
            $this->wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            $this->outall(" * Starting Image Replication.");
            $this->outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            $this->outall(sprintf(" | We are group name: %s",static::getClass('StorageGroup',$myStorageGroupID)->get('name')));
            $this->outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            $this->outall(sprintf(" | We are node name: %s",static::getClass('StorageNode',$myStorageNodeID)->get('name')));
            $ImageIDs = $this->getSubObjectIDs('Image',array('isEnabled'=>1,'toReplicate'=>1));
            $ImageAssocs = $this->getSubObjectIDs('ImageAssociation',array('imageID'=>$ImageIDs),'imageID',true);
            if (count($ImageAssocs)) static::getClass('ImageAssociationManager')->destroy(array('imageID'=>$ImageAssocs));
            unset($ImageAssocs);
            $ImageAssocCount = static::getClass('ImageAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID,'imageID'=>$ImageIDs));
            $ImageCount = static::getClass('ImageManager')->count();
            if ($ImageAssocCount <= 0 || $ImageCount <= 0) throw new Exception(_('There is nothing to replicate'));
            unset($ImageAssocCount,$ImageCount);
            $Images = static::getClass('ImageManager')->find(array('id'=>$this->getSubObjectIDs('ImageAssociation',array('storageGroupID'=>$myStorageGroupID,'imageID'=>$ImageIDs),'imageID')));
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
        static::out(' ',$this->dev);
        static::out(' +---------------------------------------------------------',$this->dev);
        static::out(' * Checking if I am the group manager.',$this->dev);
        $this->wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
        $this->commonOutput();
        static::out(' +---------------------------------------------------------',$this->dev);
    }
}
