<?php
abstract class FOGService extends FOGBase {
    protected $dev = '';
    protected $log = '';
    protected $zzz = '';
    protected $ips = array();
    public $service = true;
    private $transferLog = array();
    public $procRefs = array();
    public $procPipes = array();
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
        $countTest = ($master ? 1 : 0);
        if ($groupOrNodeCount <= $countTest) {
            $this->outall(_(" * Not syncing $objType between $itemType(s)"));
            $this->outall(_(" | $objType Name: ".$Obj->get('name')));
            $this->outall(_(" | I am the only member"));
            $onlyone = true;
        }
        unset($countTest);
        if (!$onlyone) {
            $this->outall(sprintf(" * Found $objType to transfer to %s %s(s)",$groupOrNodeCount,$itemType));
            $this->outall(sprintf(" | $objType name: %s",$Obj->get('name')));
            $getPathOfItemField = $objType == 'Snapin' ? 'snapinpath' : 'ftppath';
            $getFileOfItemField = $objType == 'Snapin' ? 'file' : 'path';
            $PotentialStorageNodes = array_diff((array)$this->getSubObjectIDs('StorageNode',$findWhere,'id'),(array)$myStorageNodeID);
            $myDir = sprintf('/%s/',trim($StorageNode->get($getPathOfItemField),'/'));
            $myFile = ($master ? basename($Obj->get($getFileOfItemField)) : '');
            $myAddItem = $myDir;
            if (is_dir("$myDir$myFile")) $myAddItem = "$myDir$myFile";
            foreach ((array)$this->getClass('StorageNodeManager')->find(array('id'=>$PotentialStorageNodes)) AS $i => &$PotentialStorageNode) {
                if (!$PotentialStorageNode->isValid()) continue;
                if ($master && $PotentialStorageNode->get('storageGroupID') == $myStorageGroupID) continue;
                if (!file_exists("$myDir$myFile")) {
                    $this->outall(_(" * Not syncing $objType between $itemType(s)"));
                    $this->outall(_(" | $objType Name: {$Obj->get(name)}"));
                    $this->outall(_(" | File or path cannot be reached"));
                    continue;
                }
                $this->FOGFTP
                    ->set('username',$PotentialStorageNode->get('user'))
                    ->set('password',$PotentialStorageNode->get('pass'))
                    ->set('host',$PotentialStorageNode->get('ip'));
                if (!$this->FOGFTP->connect()) {
                    $this->outall(_(" * Cannot connect to {$StorageNodeToSend->get(name)}"));
                    continue;
                }
                $nodename = $PotentialStorageNode->get('name');
                $username = $this->FOGFTP->get('username');
                $password = $this->FOGFTP->get('password');
                $ip = $this->FOGFTP->get('host');
                $this->FOGFTP->close();
                $removeDir = sprintf('/%s/',trim($PotentialStorageNode->get($getPathOfItemField),'/'));
                $removeFile = $myFile;
                $limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
                $limitsend = $this->byteconvert($PotentialStorageNode->get('bandwidth'));
                if ($limitmain > 0) $limitset = "set net:limit-total-rate 0:$limitmain;";
                if ($limitsend > 0) $limitset .= "set net:limit-rate 0:$limitsend;";
                $limit = $limitset;
                if (is_file("$myDir$myFile")) {
                    $remItem = "$removeDir";
                    $includeFile = sprintf('-i %s',$myFile);
                } else if (is_dir("$myDir$myFile")) {
                    $remItem = "$removeDir$myFile";
                    $includeFile = null;
                } else {
                    $remItem = $removeDir;
                    $includeFile = null;
                }
                $date = $this->formatTime('','Ymd_His');
                $logname = "$this->log.transfer.$nodename.log";
                if (!$i) $this->outall(_(' * Starting Sync Actions'));
                if ($this->isRunning($this->procRef[$itemType][$i])) {
                    $this->outall(_(' | Replication not complete'));
                    $this->outall(sprintf(_(' | PID: %d'),$this->getPID($this->procRef[$itemType][$i])));
                } else {
                    $this->killTasking($index,$itemType);
                    $this->startTasking("lftp -e 'set ftp:list-options -a;set net:max-retries 10;set net:timeout 30; $limit mirror -c -R --ignore-time $includeFile -vvv --exclude 'dev/' --exclude 'ssl/' --exclude 'CA/' --delete-first $myAddItem $remItem; exit' -u $username,$password $ip",$logname,$i,$itemType);
                }
                unset($PotentialStorageNode);
            }
        }
    }
    public function startTasking($cmd,$logname,$index = 0,$itemType = false) {
        $descriptor = array(0=>array('pipe','r'),1=>array('file',$logname,'a'),2=>array('file',$this->log,'a'));
        if ($itemType === false) {
            $this->procRef[$index] = @proc_open($cmd,$descriptor,$pipes);
            $this->procPipes[$index] = $pipes;
        } else {
            $this->procRef[$itemType][$index] = @proc_open($cmd,$descriptor,$pipes);
            $this->procPipes[$itemType][$index] = $pipes;
        }
    }
    public function killAll($pid,$sig) {
        exec("ps -ef|awk '\$3 == '$pid' {print \$2}'",$output,$ret);
        if ($ret) return false;
        while (list(,$t) = each($output)) {
            if ($t != $pid) $this->killAll($t,$sig);
        }
        posix_kill($pid,$sig);
    }
    public function killTasking($index = 0,$itemType = false) {
        if ($itemType === false) {
            @fclose($this->procPipe[$index]);
            unset($this->procPipe[$index]);
            $running = 4;
            if ($this->isRunning($this->procRef[$index])) {
                $running = 5;
                $pid = $this->getPID($this->procRef[$index]);
                if ($pid) $this->killAll($pid,SIGTERM);
                @proc_terminate($this->procRef[$index],SIGTERM);
            }
            @proc_close($this->procRef[$index]);
            unset($this->procRef[$index]);
            return (bool)$this->isRunning($this->procRef[$index]);
        } else {
            @fclose($this->procPipe[$itemType][$index]);
            unset($this->procPipe[$itemType][$index]);
            $running = 4;
            if ($this->isRunning($this->procRef[$itemType][$index])) {
                $running = 5;
                $pid = $this->getPID($this->procRef[$itemType][$index]);
                if ($pid) $this->killAll($pid,SIGTERM);
                @proc_terminate($this->procRef[$itemType][$index],SIGTERM);
            }
            @proc_close($this->procRef[$itemType][$index]);
            unset($this->procRef[$itemType][$index]);
            return (bool)$this->isRunning($this->procRef[$itemType][$index]);
        }
    }
    public function getPID($procRef) {
        if (!$procRef) return false;
        $ar = @proc_get_status($procRef);
        return $ar['pid'];
    }
    public function isRunning($procRef) {
        if (!$procRef) return false;
        $ar = @proc_get_status($procRef);
        return $ar['running'];
    }
}
