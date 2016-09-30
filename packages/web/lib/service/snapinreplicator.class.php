<?php
class SnapinReplicator extends FOGService
{
    public static $sleeptime = 'SNAPINREPSLEEPTIME';
    public function __construct()
    {
        parent::__construct();
        list($dev, $log, $zzz) = self::getSubObjectIDs('Service', array('name'=>array('SNAPINREPLICATORDEVICEOUTPUT', 'SNAPINREPLICATORLOGFILENAME', self::$sleeptime)), 'value', false, 'AND', 'name', false, '');
        static::$log = sprintf('%s%s', self::$logpath ? self::$logpath : '/opt/fog/log/', $log ? $log : 'fogsnapinrep.log');
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = $dev ? $dev : '/dev/tty4';
        static::$zzz = ($zzz ? $zzz : 600);
    }
    private function _commonOutput()
    {
        try {
            $StorageNodes = $this->checkIfNodeMaster();
            foreach ((array)$StorageNodes as &$StorageNode) {
                self::out(' * I am the group manager', static::$dev);
                self::wlog(' * I am the group manager', '/opt/fog/log/groupmanager.log');
                $myStorageGroupID = $StorageNode->get('storagegroupID');
                $myStorageNodeID = $StorageNode->get('id');
                self::outall(" * Starting Snapin Replication.");
                self::outall(sprintf(" * We are group ID: #%s", $myStorageGroupID));
                self::outall(sprintf(" | We are group name: %s", self::getClass('StorageGroup', $myStorageGroupID)->get('name')));
                self::outall(sprintf(" * We have node ID: #%s", $myStorageNodeID));
                self::outall(sprintf(" | We are node name: %s", self::getClass('StorageNode', $myStorageNodeID)->get('name')));
                $SnapinIDs = self::getSubObjectIDs('Snapin', array('isEnabled'=>1, 'toReplicate'=>1));
                $SnapinAssocs = self::getSubObjectIDs('SnapinGroupAssociation', array('snapinID'=>$SnapinIDs), 'snapinID', true);
                if (count($SnapinAssocs)) {
                    self::getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$SnapinAssocs));
                }
                unset($SnapinAssocs);
                $SnapinAssocCount = self::getClass('SnapinGroupAssociationManager')->count(array('storagegroupID'=>$myStorageGroupID, 'snapinID'=>$SnapinIDs));
                $SnapinCount = self::getClass('SnapinManager')->count();
                if ($SnapinAssocCount <= 0 || $SnapinCount <= 0) {
                    $this->outall(_(' | There is nothing to replicate'));
                    $this->outall(_(' | Please physically associate snapins'));
                    $this->outall(_(' |    to a storage group'));
                    continue;
                }
                unset($SnapinAssocCount, $SnapinCount);
                $Snapins = self::getClass('SnapinManager')->find(array('id'=>self::getSubObjectIDs('SnapinGroupAssociation', array('storagegroupID'=>$myStorageGroupID, 'snapinID'=>$SnapinIDs), 'snapinID')));
                unset($SnapinIDs);
                foreach ((array)$Snapins as $i => &$Snapin) {
                    if (!$Snapin->isValid()) {
                        continue;
                    }
                    if (!$Snapin->getPrimaryGroup($myStorageGroupID)) {
                        self::outall(_(" | Not syncing Snapin: {$Snapin->get(name)}"));
                        self::outall(_(' | This is not the primary group'));
                        continue;
                    }
                    $this->replicateItems($myStorageGroupID, $myStorageNodeID, $Snapin, true);
                    unset($Snapin);
                }
                foreach ($Snapins as $i => &$Snapin) {
                    $this->replicateItems($myStorageGroupID, $myStorageNodeID, $Snapin, false);
                    unset($Snapin);
                }
                unset($Snapins, $StorageNode);
            }
            unset($StorageNodes);
        } catch (Exception $e) {
            self::outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun()
    {
        self::out(' ', static::$dev);
        self::out(' +---------------------------------------------------------', static::$dev);
        self::out(' * Checking if I am the group manager.', static::$dev);
        self::wlog(' * Checking if I am the group manager.', '/opt/fog/log/groupmanager.log');
        $this->_commonOutput();
        self::out(' +---------------------------------------------------------', static::$dev);
        parent::serviceRun();
    }
}
