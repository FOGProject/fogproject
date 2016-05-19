<?php
class ImageReplicator extends FOGService {
    public static $sleeptime = 'IMAGEREPSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',self::$logpath,self::getSetting('IMAGEREPLICATORLOGFILENAME'));
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = self::getSetting('IMAGEREPLICATORDEVICEOUTPUT');
        static::$zzz = (int)self::getSetting(static::$sleeptime);
    }
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            self::out(' * I am the group manager',static::$dev);
            self::wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            self::outall(" * Starting Image Replication.");
            self::outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            self::outall(sprintf(" | We are group name: %s",self::getClass('StorageGroup',$myStorageGroupID)->get('name')));
            self::outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            self::outall(sprintf(" | We are node name: %s",self::getClass('StorageNode',$myStorageNodeID)->get('name')));
            $ImageIDs = self::getSubObjectIDs('Image',array('isEnabled'=>1,'toReplicate'=>1));
            $ImageAssocs = self::getSubObjectIDs('ImageAssociation',array('imageID'=>$ImageIDs),'imageID',true);
            if (count($ImageAssocs)) self::getClass('ImageAssociationManager')->destroy(array('imageID'=>$ImageAssocs));
            unset($ImageAssocs);
            $ImageAssocCount = self::getClass('ImageAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID,'imageID'=>$ImageIDs));
            $ImageCount = self::getClass('ImageManager')->count();
            if ($ImageAssocCount <= 0 || $ImageCount <= 0) throw new Exception(_('There is nothing to replicate'));
            unset($ImageAssocCount,$ImageCount);
            $Images = self::getClass('ImageManager')->find(array('id'=>self::getSubObjectIDs('ImageAssociation',array('storageGroupID'=>$myStorageGroupID,'imageID'=>$ImageIDs),'imageID')));
            unset($ImageIDs);
            foreach ((array)$Images AS $Image) {
                if (!$Image->isValid()) continue;
                if (!$Image->getPrimaryGroup($myStorageGroupID)) {
                    self::outall(_(" | Not syncing Image: {$Image->get(name)}"));
                    self::outall(_(' | This is not the primary group'));
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
            self::outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun() {
        self::out(' ',static::$dev);
        self::out(' +---------------------------------------------------------',static::$dev);
        self::out(' * Checking if I am the group manager.',static::$dev);
        self::wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
        $this->commonOutput();
        self::out(' +---------------------------------------------------------',static::$dev);
    }
}
