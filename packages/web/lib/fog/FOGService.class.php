<?php
abstract class FOGService extends FOGBase {
    /** @var $dev string the device output for console */
    public $dev;
    /** @var $log string the log file to write to */
    public $log;
    /** @var $zzz int the sleep time for the service */
    public $zzz;
    /** @function outall() outputs to log file
     * @param $string string the data to write
     * @return null
     */
    public function outall($string) {
        $this->FOGCore->out($string, $this->dev);
        $this->FOGCore->wlog($string, $this->log);
        return;
    }
    /** @function serviceStart() starts the service
     * @return null
     */
    public function serviceStart() {
        $this->FOGCore->out($this->FOGCore->getBanner(), $this->log);
        $this->outall(sprintf(' * Starting %s Service',get_class($this)));
        $this->outall(sprintf(' * Checking for new items every %s seconds',$this->zzz));
        $this->outall(' * Starting service loop');
        return;
    }
    public function serviceRun() {
        $this->FOGCore->out(' ', $this->dev);
        $this->FOGCore->out(' +---------------------------------------------------------', $this->dev);
    }
    /** replicate_items() replicates data without having to keep repeating
     * @param $myStorageGroupID int this servers groupid
     * @param $Obj object that is trying to send data, e.g. images, snapins
     * @param $master bool set if sending to master->master or master->nodes
     *     auto sets to false
     * @return the process
     */
    public function replicate_items($myStorageGroupID,$Obj,StorageNode $StorageNode,$master = false) {
        $objType = get_class($Obj);
        $StorageNodeToSend = $master ? current($this->getClass(StorageNodeManager)->find(array('storageGroupID' => $Obj->get(storageGroups),'isMaster' => 1))) : current($this->getClass(StorageNodeManager)->find(array('storageGroupID' => $myStorageGroupID,'isMaster' => 0,'isEnabled','isEnabled' => 1),'','','','','',true));
        $StorageGroups = ($master ? $this->getClass(StorageGroupManager)->find(array('id' => $Obj->get(storageGroups))) : $this->getClass(StorageNodeManager)->find(array('storageGroupID' => $myStorageGroupID)));
        if (!$master) $myStorageGroupID = $this->getClass(StorageGroup,$myStorageGroupID)->getMasterStorageNode()->get(id);
        $getPathOfItemField = $objType == 'Snapin' ? 'snapinpath' : 'ftppath';
        foreach ($StorageGroups AS $i => &$GroupToSend) {
            if ($StorageNodeToSend && $StorageNodeToSend->isValid() && $GroupToSend->isValid() && $GroupToSend->get(id) != $myStorageGroupID) {
                $username = $StorageNodeToSend->get(user);
                $password = $StorageNodeToSend->get(pass);
                $ip = $StorageNodeToSend->get(id);
                $remItem = rtrim($StorageNodeToSend->get($getPathOfItemField),'/');
                $myItem = rtrim($StorageNode->get($getPathOfItemField),'/');
                if ($objType == 'Image') {
                    $remItem .= $Obj->get(path);
                    $myItem .= $Obj->get(path);
                }
                $limitmain = $this->byteconvert($StorageNode->get(bandwidth));
                $limitsend = $this->byteconvert($StorageNodeToSend->get(bandwidth));
                if ($limitmain > 0) $limit = "set net:limit-total-rate 0:$limitmain;";
                if ($limitsend > 0) $limit .= "set net:limit-rate 0:$limitsend;";
                $groupOrNodeCount = $master ? $this->getClass(StorageGroupManager)->count(array('id' => $Obj->get(storageGroups))) -1 : $this->getClass(StorageNodeManager)->count(array('storageGroupID' => $myStorageGroupID)) -1;
                if ($groupOrNodeCount > 1) {
                    $this->outall(sprintf(" * Found $itemType to transfer to %s %s",$itemType,$groupOrNodeCount));
                    $this->outall(sprintf(" | $objType name: %s",$Obj->get(name)));
                } else throw new Exception(_('This is the only member, not syncing'));
                $process[$StorageNodeToSend->get(name)] = popen("lftp -e 'set ftp:list-options -a;set net:max-retries 10;set net:timeout 30; $limit mirror -R --ignore-time -i $mySnapFile -vvv --exclude 'dev/' --exclude 'ssl/' --exclude 'CA/' --delete-first $myItem $remItem; exit' -u $username,$password $ip 2>&1","r");
                stream_set_blocking($process[$StorageNodeToSend->get(name)]);
            }
        }
        unset($GroupToSend);
        foreach((array)$process AS $nodename => &$proc) {
            while (!feof($proc) && $proc != null) {
                $output = fgets($proc,256);
                if ($output) $this->outall(sprintf(' * %s - SubProcess -> %s',$nodename,$output));
            }
            pclose($proc);
            $this->outall(sprintf(' * %s - SubProcess -> Complete',$nodename));
        }
        unset($process,$proc);
    }
}
