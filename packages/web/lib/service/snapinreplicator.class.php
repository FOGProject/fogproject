<?php
class SnapinReplicator extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'SNAPINREPSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',static::$logpath,static::getSetting('SNAPINREPLICATORLOGFILENAME'));
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = static::getSetting('SNAPINREPLICATORDEVICEOUTPUT');
        static::$zzz = (int)static::getSetting(static::$sleeptime);
    }
    private function commonOutput() {
        try {
            $StorageNode = $this->checkIfNodeMaster();
            static::out(' * I am the group manager',static::$dev);
            static::wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get('storageGroupID');
            $myStorageNodeID = $StorageNode->get('id');
            static::outall(" * Starting Snapin Replication.");
            static::outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            static::outall(sprintf(" | We are group name: %s",static::getClass('StorageGroup',$myStorageGroupID)->get('name')));
            static::outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            static::outall(sprintf(" | We are node name: %s",static::getClass('StorageNode',$myStorageNodeID)->get('name')));
            $SnapinIDs = static::getSubObjectIDs('Snapin',array('isEnabled'=>1,'toReplicate'=>1));
            $SnapinAssocs = static::getSubObjectIDs('SnapinGroupAssociation',array('snapinID'=>$SnapinIDs),'snapinID',true);
            if (count($SnapinAssocs)) static::getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$SnapinAssocs));
            unset($SnapinAssocs);
            $SnapinAssocCount = static::getClass('SnapinGroupAssociationManager')->count(array('storageGroupID'=>$myStorageGroupID,'snapinID'=>$SnapinIDs));
            $SnapinCount = static::getClass('SnapinManager')->count();
            if ($SnapinAssocCount <= 0 || $SnapinCount <= 0) throw new Exception(_('There is nothing to replicate'));
            unset($SnapinAssocCount,$SnapinCount);
            $Snapins = static::getClass('SnapinManager')->find(array('id'=>static::getSubObjectIDs('SnapinGroupAssociation',array('storageGroupID'=>$myStorageGroupID,'snapinID'=>$SnapinIDs),'snapinID')));
            unset($SnapinIDs);
            foreach ((array)$Snapins AS $i => &$Snapin) {
                if (!$Snapin->isValid()) continue;
                if (!$Snapin->getPrimaryGroup($myStorageGroupID)) {
                    static::outall(_(" | Not syncing Snapin: {$Snapin->get(name)}"));
                    static::outall(_(' | This is not the primary group'));
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
