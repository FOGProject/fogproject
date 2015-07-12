<?php
class ImageReplicator extends FOGService {
    public $dev = REPLICATORDEVICEOUTPUT;
    public $log = REPLICATORLOGPATH;
    public $zzz = REPLICATORSLEEPTIME;
    private function commonOutput() {
        $StorageNodes = $this->getClass(StorageNodeManager)->find(array('isMaster' => 1,'isEnabled' => 1));
        foreach ($StorageNodes AS $i => &$SN) {
            if (in_array($this->FOGCore->resolveHostname($SN->get(ip)),$this->FOGCore->getIPAddress())) {
                $StorageNode = $SN;
                break;
            }
        }
        unset($SN);
        try {
            if (!$StorageNode || !$StorageNode->isValid()) {
                $message = _('I do not appear to be the group manager');
                $this->FOGCore->wlog(' * '.$message,'/opt/fog/log/groupmanager.log');
                throw new Exception($message);
            }
            $this->FOGCore->out(' * I am the group manager',$this->dev);
            $this->FOGCore->wlog(' * I am the group manager','/opt/fog/log/groupmanager.log');
            $myStorageGroupID = $StorageNode->get(storageGroupID);
            $myStorageNodeID = $StorageNode->get(id);
            $this->outall(" * Starting Image Replication.");

            $this->outall(sprintf(" * We are group ID: #%s",$myStorageGroupID));
            $this->outall(sprintf(" | We are group name: %s",$this->getClass(StorageGroup,$myStorageGroupID)->get(name)));
            $this->outall(sprintf(" * We have node ID: #%s",$myStorageNodeID));
            $this->outall(sprintf(" | We are node name: %s",$this->getClass(StorageNode,$myStorageNodeID)->get(name)));
            $ImageAssocCount = $this->getClass(ImageAssociationManager)->count(array('storageGroupID' => $myStorageGroupID));
            $ImageCount = $this->getClass(ImageManager)->count();
            if ($ImageAssocCount <= 0 || $ImageCount <= 0) throw new Exception(_('There is nothing to replicate'));
            $Images = $this->getClass(ImageManager)->find(array('id' => $this->getClass(ImageAssociationManager)->find(array('storageGroupID' => $myStorageGroupID),'','','','','','','imageID')));
            foreach ($Images AS $i => &$Image) $this->replicate_items($myStorageGroupID,$myStorageNodeID,$Image,true);
            unset($Image);
            foreach ($Images AS $i => &$Image) $this->replicate_items($myStorageGroupID,$myStorageNodeID,$Image,false);
            unset($Image);
        } catch (Exception $e) {
            $this->outall(' * '.$e->getMessage());
        }
    }
    public function serviceRun() {
        $this->FOGCore->out(' ',$this->dev);
        $this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
        $this->FOGCore->out(' * Checking if I am the group manager.',$this->dev);
        $this->FOGCore->wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
        $this->commonOutput();
        $this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
    }
}
