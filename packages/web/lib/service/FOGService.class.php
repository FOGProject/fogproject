<?php
abstract class FOGService extends FOGBase {
    protected $dev = '';
    protected $log = '';
    protected $zzz = '';
    protected $ips = array();
    protected function getIPAddress() {
        $output = array();
        exec("/sbin/ip addr | awk -F'[ /]+' '/global/ {print $3}'",$IPs,$retVal);
        if (!count($IPs)) exec("/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d':' -f2",$IPs,$retVal);
        foreach ($IPs AS $i => &$IP) {
            $IP = trim($IP);
            if (filter_var($IP,FILTER_VALIDATE_IP)) $output[] = $IP;
            $output[] = gethostbyaddr($IP);
        }
        unset($IP);
        $this->ips = array_values(array_unique((array)$output));
        return $this->ips;
    }
    protected function checkIfNodeMaster() {
		$this->getIPAddress();
        $StorageNodes = $this->getClass('StorageNodeManager')->find(array('isMaster'=>1,'isEnabled'=>1));
        foreach ($StorageNodes AS $i => &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            if (!in_array($this->FOGCore->resolveHostname($StorageNode->get('ip')),$this->ips)) continue;
            return $StorageNode;
		}
        throw new Exception(' | '._('This is not the master node'));
    }
    public function wait_interface_ready() {
        $this->getIPAddress();
        if (!count($this->ips)) {
            $this->out('Interface not ready, waiting.',$this->dev);
            sleep(10);
            $this->wait_interface_ready();
        }
        foreach ($this->ips AS $i => &$ip) $this->out("Interface Ready with IP Address: $ip",$this->dev);
        unset($ip);
    }
    public function wait_db_ready() {
        while ($this->DB->link()->connect_errno) {
            $this->out('FOGService: '.get_class($this).' - Waiting for mysql to be available',$this->dev);
            sleep(10);
        }
    }
    public function getBanner() {
        $str = "\n";
        $str .= "        ___           ___           ___      \n";
        $str .= "       /\  \         /\  \         /\  \     \n";
        $str .= "      /::\  \       /::\  \       /::\  \    \n";
        $str .= "     /:/\:\  \     /:/\:\  \     /:/\:\  \   \n";
        $str .= "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  \n";
        $str .= "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ \n";
        $str .= "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ \n";
        $str .= "        \:\__\    \:\  /:/  /   \:\ \:\__\   \n";
        $str .= "         \/__/     \:\/:/  /     \:\/:/  /   \n";
        $str .= "                    \::/  /       \::/  /    \n";
        $str .= "                     \/__/         \/__/     \n";
        $str .= "\n";
        $str .= "  ###########################################\n";
        $str .= "  #     Free Computer Imaging Solution      #\n";
        $str .= "  #     Credits:                            #\n";
        $str .= "  #     http://fogproject.org/credits       #\n";
        $str .= "  #     GNU GPL Version 3                   #\n";
        $str .= "  ###########################################\n";
        $this->outall($str);
    }
    public function outall($string) {
        $this->out($string."\n",$this->dev);
        $this->wlog($string."\n",$this->log);
        return;
    }
    protected function out($string,$device) {
        $strOut = $string."\n";
        if (!$hdl = fopen($device,'w')) return;
        if (fwrite($hdl,$strOut) === false) return;
        fclose($hdl);
    }
    protected function getDateTime() {
        return $this->nice_date()->format('m-d-y g:i:s a');
    }
    protected function wlog($string, $path) {
        if (file_exists($path) && filesize($path) >= LOGMAXSIZE) unlink($path);
        if (!$hdl = fopen($path,'a')) $this->out("\n * Error: Unable to open file: $path\n",$this->dev);
        if (fwrite($hdl,sprintf('[%s] %s',$this->getDateTime(),$string)) === FALSE) $this->out("\n * Error: Unable to write to file: $path\n",$this->dev);
    }
    public function serviceStart() {
        $this->outall(sprintf(' * Starting %s Service',get_class($this)));
        $this->outall(sprintf(' * Checking for new items every %s seconds',$this->zzz));
        $this->outall(' * Starting service loop');
        return;
    }
    public function serviceRun() {
        $this->out('',$this->dev);
        $this->out('+---------------------------------------------------------',$this->dev);
    }
    /** replicate_items() replicates data without having to keep repeating
     * @param $myStorageGroupID int this servers groupid
     * @param $myStorageNodeID int this servers nodeid
     * @param $Obj object that is trying to send data, e.g. images, snapins
     * @param $master bool set if sending to master->master or master->nodes
     * auto sets to false
     */
    protected function replicate_items($myStorageGroupID,$myStorageNodeID,$Obj,$master = false) {
        unset($username,$password,$ip,$remItem,$myItem,$limitmain,$limitsend,$limit,$includeFile);
        $message = $onlyone = false;
        $itemType = $master ? 'group' : 'node';
        $findWhere['isEnabled'] = 1;
        $findWhere['isMaster'] = (int)$master;
        $findWhere['storageGroupID'] = $master ? $Obj->get('storageGroups') : $myStorageGroupID;
        $StorageNode = $this->getClass('StorageNode',$myStorageNodeID);
        if (!($StorageNode->isValid() && $StorageNode->get('isMaster'))) throw new Exception(_('I am not the master'));
        $objType = get_class($Obj);
        $groupOrNodeCount = $this->getClass('StorageNodeManager')->count($findWhere);
        if ((!$master && $groupOrNodeCount <= 0) || ($master && $groupOrNodeCount <= 1)) {
            $this->outall(_(" * Not syncing $objType between $itemType(s)"));
            $this->outall(_(" | $objType Name: ".$Obj->get('name')));
            $this->outall(_(" | I am the only member"));
            $onlyone = true;
        }
        if (!$onlyone) {
            $this->outall(sprintf(" * Found $objType to transfer to %s %s(s)",$groupOrNodeCount,$itemType));
            $this->outall(sprintf(" | $objType name: %s",$Obj->get('name')));
            $getPathOfItemField = $objType == 'Snapin' ? 'snapinpath' : 'ftppath';
            $getFileOfItemField = $objType == 'Snapin' ? 'file' : 'path';
            $PotentialStorageNodes = array_diff((array)$this->getSubObjectIDs('StorageNode',$findWhere,'id'),(array)$myStorageNodeID);
            foreach ($PotentialStorageNodes AS $i => &$PotentialStorageNode) {
                $StorageNodeToSend = $this->getClass('StorageNode',$PotentialStorageNode);
                if (($master && $StorageNodeToSend->get('storageGroupID') != $myStorageGroupID) || !$master) {
                    $this->FOGFTP
                        ->set('username',$StorageNodeToSend->get('user'))
                        ->set('password',$StorageNodeToSend->get('pass'))
                        ->set('host',$StorageNodeToSend->get('ip'));
                    if (!$this->FOGFTP->connect()) {
                        $this->outall(_(' * Cannot connect to '.$StorageNodeToSend->get('name')));
                        break;
                    }
                    $this->FOGFTP->close();
                    $removeItem = '/'.trim($StorageNodeToSend->get($getPathOfItemField),'/').($master ? '/'.$Obj->get($getFileOfItemField) : '');
                    $myAddItem = '/'.trim($StorageNode->get($getPathOfItemField),'/').($master ? '/'.$Obj->get($getFileOfItemField) : '');
                    if (!file_exists($myAddItem)) {
                        $this->outall(_(" * Not syncing $objType between $itemType(s)"));
                        $this->outall(_(" | $objType Name: ".$Obj->get('name')));
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
                    $nodename[] = $StorageNodeToSend->get('name');
                    $username[] = $StorageNodeToSend->get('user');
                    $password[] = $StorageNodeToSend->get('pass');
                    $ip[] = $StorageNodeToSend->get('ip');
                    $limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
                    $limitsend = $this->byteconvert($StorageNodeToSend->get('bandwidth'));
                    if ($limitmain > 0) $limitset = "set net:limit-total-rate 0:$limitmain;";
                    if ($limitsend > 0) $limitset .= "set net:limit-rate 0:$limitsend;";
                    $limit[] = $limitset;
                }
            }
            unset($StorageNodeToSend);
            $this->outall(_(' * Starting Sync Actions'));
            foreach ((array)$nodename AS $i => &$name) {
                $process[$name] = popen("lftp -e 'set ftp:list-options -a;set net:max-retries 10;set net:timeout 30; ".$limit[$i]." mirror -c -R --ignore-time ".$includeFile[$i]." -vvv --exclude 'dev/' --exclude 'ssl/' --exclude 'CA/' --delete-first ".$myItem[$i].' '.$remItem[$i]."; exit' -u ".$username[$i].','.$password[$i].' '.$ip[$i]." 2>&1","r");
                stream_set_blocking($process[$name],false);
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
