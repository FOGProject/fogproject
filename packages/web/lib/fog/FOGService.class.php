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
     */
    public function replicate_items($myStorageGroupID,$myStorageNodeID,$Obj,$master = false) {
        // Ensure clean variables
        unset($username,$password,$ip,$remItem,$myItem,$limitmain,$limitsend,$limit,$includeFile);
        $message = $onlyone = false;
        $itemType = $master ? 'group' : 'node';
        // Define the way to search for items
        $findWhere[isEnabled] = 1;
        $findWhere[isMaster] = (int)$master;
        $findWhere[storageGroupID] = $master ? $Obj->get(storageGroups) : $myStorageGroupID;
        // Storage Node
        $StorageNode = $this->getClass(StorageNode,$myStorageNodeID);
        if (!$StorageNode->isValid() || !$StorageNode->get(isMaster)) throw new Exception(_('I am not the master'));
        // Get the Object Type
        $objType = get_class($Obj);
        // Get count of items to transfer to
        $groupOrNodeCount = $this->getClass(StorageNodeManager)->count($findWhere);
        // No need to try doing anything as nothing exists for it to do
        if ((!$master && $groupOrNodeCount <= 0) || ($master && $groupOrNodeCount <= 1)) {
            $this->outall(_(" * Not syncing $objType between $itemType(s)"));
            $this->outall(_(" | $objType Name: ".$Obj->get(name)));
            $this->outall(_(" | I am the only member"));
            $onlyone = true;
        }
        if (!$onlyone) {
            $this->outall(sprintf(" * Found $objType to transfer to %s %s(s)",$groupOrNodeCount,$itemType));
            $this->outall(sprintf(" | $objType name: %s",$Obj->get(name)));
            // Get the path based off the object
            $getPathOfItemField = $objType == 'Snapin' ? 'snapinpath' : 'ftppath';
            // Get the file itself
            $getFileOfItemField = $objType == 'Snapin' ? 'file' : 'path';
            // Get all the potential nodes of this group
            $PotentialStorageNodes = $this->getClass(StorageNodeManager)->find($findWhere);
            // Group to group is item specific
            foreach ($PotentialStorageNodes AS $i => &$StorageNodeToSend) {
                if (($master && $StorageNodeToSend->get(storageGroupID) != $myStorageGroupID) || !$master) {
                    $this->FOGFTP
                        ->set(username,$StorageNodeToSend->get(user))
                        ->set(password,$StorageNodeToSend->get(pass))
                        ->set(host,$StorageNodeToSend->get(ip));
                    // Test if we can even talk with the remote end
                    if (!$this->FOGFTP->connect()) {
                        $this->outall(_(' * Cannot connect to '.$StorageNodeToSend->get(name)));
                        $this->FOGFTP->close();
                        break;
                    }
                    $this->FOGFTP->close();
                    $removeItem = '/'.trim($StorageNodeToSend->get($getPathOfItemField),'/').($master ? '/'.$Obj->get($getFileOfItemField) : '');
                    $myAddItem = '/'.trim($StorageNode->get($getPathOfItemField),'/').($master ? '/'.$Obj->get($getFileOfItemField) : '');
                    if (!file_exists($myAddItem)) {
                        $this->outall(_(" * Not syncing $objType between $itemType(s)"));
                        $this->outall(_(" | $objType Name: ".$Obj->get(name)));
                        $this->outall(_(" | File or path cannot be reached"));
                        continue;
                    }
                    if (is_file($myAddItem)) {
                        $remItem[] = dirname($removeItem).'/';
                        $myItem[] = dirname($myAddItem).'/';
                        $includeFile[] = '-i '.basename($removeItem);
                    } else {
                        $remItem[] = $removeItem;
                        $myItem[] = $myAddItem;
                        $includeFile[] = null;
                    }
                    $nodename[] = $StorageNodeToSend->get(name);
                    $username[] = $StorageNodeToSend->get(user);
                    $password[] = $StorageNodeToSend->get(pass);
                    $ip[] = $StorageNodeToSend->get(ip);
                    $limitmain = $this->byteconvert($StorageNode->get(bandwidth));
                    $limitsend = $this->byteconvert($StorageNodeToSend->get(bandwidth));
                    if ($limitmain > 0) $limitset = "set net:limit-total-rate 0:$limitmain;";
                    if ($limitsend > 0) $limitset .= "set net:limit-rate 0:$limitsend;";
                    $limit[] = $limitset;
                }
                unset($StorageNodeToSend);
            }
            $this->outall(_(' * Starting Sync Actions'));
            foreach ((array)$nodename AS $i => &$name) {
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
            $this->outall(_(' * Sync Actions all complete'));
        }
    }
}
