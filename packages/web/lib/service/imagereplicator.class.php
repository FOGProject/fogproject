<?php
class ImageReplicator extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'IMAGEREPSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',static::$logpath,self::getSetting('IMAGEREPLICATORLOGFILENAME'));
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = self::getSetting('IMAGEREPLICATORDEVICEOUTPUT');
        static::$zzz = (int)self::getSetting(static::$sleeptime);
    }
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            static::out(' * I am the group manager',static::$dev);
            static::wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            static::outall(" * Starting Image Replication.");
            static::outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            static::outall(sprintf(" | We are group name: %s",self::getClass('StorageGroup',$myStorageGroupID)->get('name')));
            static::outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            static::outall(sprintf(" | We are node name: %s",self::getClass('StorageNode',$myStorageNodeID)->get('name')));
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
                    static::outall(_(" | Not syncing Image: {$Image->get(name)}"));
                    static::outall(_(' | This is not the primary group'));
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
            static::outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun() {
        static::out(' ',static::$dev);
        static::out(' +---------------------------------------------------------',static::$dev);
        static::out(' * Checking if I am the group manager.',static::$dev);
        static::wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
        $this->commonOutput();
        static::out(' +---------------------------------------------------------',static::$dev);
    }
}
