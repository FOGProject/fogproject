<?php
class ImageReplicator extends FOGService {
    public static $sleeptime = 'IMAGEREPSLEEPTIME';
    public function __construct() {
        parent::__construct();
        list($dev,$log,$zzz) = self::getSubObjectIDs('Service',array('name'=>array('IMAGEREPLICATORDEVICEOUTPUT','IMAGEREPLICATORLOGFILENAME',$sleeptime)),'value',false,'AND','name',false,'');
        static::$log = sprintf('%s%s',self::$logpath ? self::$logpath : '/opt/fog/log/',$log ? $log : 'fogreplicator.log');
        if (file_exists(static::$log)) unlink(static::$log);
        static::$dev = $dev ? $dev : '/dev/tty1';
        static::$zzz = ($zzz ? $zzz : 600);
    }
    private function commonOutput() {
        try {
            $StorageNodes = $this->checkIfNodeMaster();
            foreach ((array)$StorageNodes AS $StorageNode) {
                $myStorageGroupID = $StorageNode->get('storageGroupID');
                self::out(' * I am the group manager',static::$dev);
                self::wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
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
                unset($StorageNode);
            }
            unset($StorageNodes);
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
