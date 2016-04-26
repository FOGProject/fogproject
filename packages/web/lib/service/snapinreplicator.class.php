<?php
class SnapinReplicator extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'SNAPINREPSLEEPTIME';
    public function __construct() {
        parent::__construct();
        self::$log = sprintf('%s%s',self::$logpath,self::getSetting('SNAPINREPLICATORLOGFILENAME'));
        if (file_exists(self::$log)) @unlink(self::$log);
        self::$dev = self::getSetting('SNAPINREPLICATORDEVICEOUTPUT');
        self::$zzz = (int)self::getSetting(self::$sleeptime);
    }
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            self::out(' * I am the group manager',self::$dev);
            self::wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            self::outall(" * Starting Snapin Replication.");
            self::outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            self::outall(sprintf(" | We are group name: %s",self::getClass('StorageGroup',$myStorageGroupID)->get('name')));
            self::outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            self::outall(sprintf(" | We are node name: %s",self::getClass('StorageNode',$myStorageNodeID)->get('name')));
            $SnapinIDs = self::getSubObjectIDs('Snapin',array('isEnabled'=>1,'toReplicate'=>1));
            $SnapinAssocs = self::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$SnapinIDs),'snapinID',true);
            if (count($SnapinAssocs)) self::getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$SnapinAssocs));
            unset($SnapinAssocs);
            $SnapinAssocCount = self::getClass('SnapinGroupAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID,'snapinID'=>$SnapinIDs));
            $SnapinCount = self::getClass('SnapinManager')->count();
            if ($SnapinAssocCount <= 0 || $SnapinCount <= 0) throw new Exception(_('There is nothing to replicate'));
            unset($SnapinAssocCount,$SnapinCount);
            $Snapins = self::getClass('SnapinManager')->find(array('id'=>self::getSubObjectIDs('SnapinGroupAssociation',array('storageGroupID'=>$myStorageGroupID,'snapinID'=>$SnapinIDs),'snapinID')));
            unset($SnapinIDs);
            foreach ((array)$Snapins AS $i => &$Snapin) {
                if (!$Snapin->isValid()) continue;
                if (!$Snapin->getPrimaryGroup($myStorageGroupID)) {
                    self::outall(_(" | Not syncing Snapin: {$Snapin->get(name)}"));
                    self::outall(_(' | This is not the primary group'));
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
            self::outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun() {
        self::out(' ',self::$dev);
        self::out(' +---------------------------------------------------------',self::$dev);
        self::out(' * Checking if I am the group manager.',self::$dev);
        self::wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
        $this->commonOutput();
        self::out(' +---------------------------------------------------------',self::$dev);
    }
}
