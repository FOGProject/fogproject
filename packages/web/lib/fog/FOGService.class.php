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
     * @param $myStorageNodeID int this servers nodeid
     * @param $Obj object that is trying to send data, e.g. images, snapins
     * @param $master bool set if sending to master->master or master->nodes
     *     auto sets to false
     * @return the process
     */
    public function replicate_items($myStorageGroupID,$myStorageNodeID,$Obj,$master = false) {
        // Ensure clean variables
        unset($username,$password,$ip,$remItem,$myItem,$limitmain,$limitsend,$limit,$includeFile);
        $itemType = $master ? 'group' : 'node';
        // Get count of items to transfer to
        if ($master) $groupOrNodeCount = $this->getClass(StorageGroupManager)->count(array('id' => $Obj->get(storageGroups))) - 1;
        else $groupOrNodeCount = $this->getClass(StorageGroupManager)->count(array('id' => $myStorageGroupID)) - 1;
        // No need to try doing anything as nothing exists for it to do
        if (!$groupOrNodeCount) throw new Exception(_('This is the only member, not syncing'));
        // Get the Object Type
        $objType = get_class($Obj);
        $this->outall(sprintf(" * Found $itemType to transfer to %s %s(s)",$itemType,$groupOrNodeCount));
        $this->outall(sprintf(" | $objType name: %s",$Obj->get(name)));
        // Define the way to search for items
        $findWhere[isEnabled] = 1;
        $findWhere[isMaster] = (int)$master;
        $findWhere[storageGroupID] = $master ? $Obj->get(storageGroups) : $myStorageGroupID;
        // Get the path based off the object
        $getPathOfItemField = $objType == 'Snapin' ? 'snapinpath' : 'ftppath';
        // Get the file itself
        $getFileOfItemField = $objType == 'Snapin' ? 'file' : 'path';
        // Get all the potential nodes of this group
        $PotentialStorageNodes = $this->getClass(StorageNodeManager)->find($findWhere);
        // Group to group is item specific
        if ($master) {
            foreach ($PotentialStorageNodes AS $i => &$StorageNodeToSend) {
                if ($StorageNodeToSend->get(storageGroupID) != $myStorageGroupID) {
                    $nodename[] = $StorageNodeToSend->get(name);
                    $username[] = $StorageNodeToSend->get(user);
                    $password[] = $StorageNodeToSend->get(pass);
                    $ip[] = $StorageNodeToSend->get(ip);
                    $removeItem = rtrim($StorageNodeToSend->get($getPathOfItemField),'/').'/'.$Obj->get($getFileOfItemField);
                    $myAddItem = rtrim($StorageNode->get($getPathOfItemField),'/').'/'.$Obj->get($getFileOfItemField);
                    if (is_file($removeItem)) {
                        $remItem[] = dirname($removeItem).'/';
                        $myItem[] = dirname($myAddItem).'/';
                        $includeFile[] = '-i '.basename($removeItem);
                    } else {
                        $remItem[] = $removeItem;
                        $myItem[] = $myAddItem;
                        $includeFile[] = null;
                    }
                    $limitmain = $this->byteconvert($StorageNode->get(bandwidth));
                    $limitsend = $this->byteconvert($StorageNodeToSend->get(bandwidth));
                    if ($limitmain > 0) $limitset = "set net:limit-total-rate 0:$limitmain;";
                    if ($limitsend > 0) $limitset .= "set net:limit-rate 0:$limitsend;";
                    $limit[] = $limitset;
                }
            }
            unset($StorageNodeToSend);
        // Group to nodes is everything
        } else {
            foreach ($PotentialStorageNodes AS $i => &$StorageNodeToSend) {
                if ($StorageNodeToSend->get(storageGroupID) == $myStorageGroupID && $StorageNodeToSend->get(id) != $myStorageNodeID) {
                    $nodename[] = $StorageNodeToSend->get(name);
                    $username[] = $StorageNodeToSend->get(user);
                    $password[] = $StorageNodeToSend->get(pass);
                    $ip[] = $StorageNodeToSend->get(ip);
                    $remItem[] = rtrim($StorageNodeToSend->get($getPathOfItemField),'/');
                    $myItem[] = rtrim($StorageNode->get($getPathOfItemField),'/');
                    $limitmain = $this->byteconvert($StorageNode->get(bandwidth));
                    $limitsend = $this->byteconvert($StorageNodeToSend->get(bandwidth));
                    if ($limitmain > 0) $limitset = "set net:limit-total-rate 0:$limitmain;";
                    if ($limitsend > 0) $limitset .= "set net:limit-rate 0:$limitsend;";
                    $limit[] = $limitset;
                    $includeFile[] = null;
                }
            }
            unset($StorageNodeToSend);
        }
        foreach ($nodename AS $i => &$name) {
            $process[$name] = popen("lftp -e 'set ftp:list-options -a;set net:max-retries 10;set net:timeout 30; ".$limit[$i]." mirror -R --ignore-time ".$includeFile[$i]." -vvv --exclude 'dev/' --exclude 'ssl/' --exclude 'CA/' --delete-first ".$myItem[$i].' '.$remItem[$i]."; exit' -u ".$username[$i].','.$password[$i].' '.$ip[$i]." 2>&1","r");
            stream_set_blocking($process[$name]);
        }
        unset($name);
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
