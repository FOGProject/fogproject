<?php
abstract class FOGService extends FOGBase {
    protected $dev = '';
    protected $logpath = '';
    protected $log = '';
    protected $zzz = '';
    protected $ips = array();
    public $service = true;
    private $transferLog = array();
    public $procRef = array();
    public $procPipes = array();
    private static function files_are_equal($size_a,$size_b,$file_a,$file_b) {
        if ($size_a !== $size_b) return false;
        $res = true;
        $fp_a = fopen($file_a,'r');
        $fp_b = fopen($file_b,'r');
        $a = fgets($fp_a,10240);
        $a_hex = bin2hex($a);
        $b = fgets($fp_b,10240);
        $b_hex = bin2hex($b);
        if ($a_hex !== $b_hex) $res = false;
        fclose($fp_a);
        fclose($fp_b);
        return $res;
    }
    public function __construct() {
        parent::__construct();
        $this->logpath = sprintf('/%s/',trim($this->getSetting('SERVICE_LOG_PATH'),'/'));
    }
    protected function getIPAddress() {
        $output = array();
        exec("/sbin/ip addr | awk -F'[ /]+' '/global/ {print $3}'",$IPs,$retVal);
        if (!count($IPs)) exec("/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d':' -f2",$IPs,$retVal);
        if (@fsockopen('ipinfo.io',80)) {
            $res = $this->FOGURLRequests->process('http://ipinfo.io/ip','GET');
            $IPs[] = $res[0];
        }
        @natcasesort($IPs);
        foreach ($IPs AS $i => &$IP) {
            $IP = trim($IP);
            if (filter_var($IP,FILTER_VALIDATE_IP)) $output[] = $IP;
            $output[] = gethostbyaddr($IP);
        }
        unset($IP);
        @natcasesort($output);
        $this->ips = array_values(array_filter(array_unique((array)$output)));
        return $this->ips;
    }
    protected function checkIfNodeMaster() {
        $this->getIPAddress();
        foreach ((array)self::getClass('StorageNodeManager')->find(array('isMaster'=>1,'isEnabled'=>1)) AS $i => &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            if (!in_array(self::$FOGCore->resolveHostname($StorageNode->get('ip')),$this->ips)) continue;
            return $StorageNode;
        }
        throw new Exception(_(' | This is not the master node'));
    }
    public function wait_interface_ready() {
        $this->getIPAddress();
        if (!count($this->ips)) {
            $this->outall('Interface not ready, waiting.',$this->dev);
            sleep(10);
            $this->wait_interface_ready();
        }
        foreach ($this->ips AS $i => &$ip) $this->outall(_("Interface Ready with IP Address: $ip"),$this->dev);
        unset($ip);
    }
    public function wait_db_ready() {
        while (self::$DB->link()->connect_errno) {
            $this->outall(sprintf('FOGService: %s - %s',get_class($this),_('Waiting for mysql to be available')),$this->dev);
            sleep(10);
        }
    }
    public function getBanner() {
        ob_start();
        echo "\n";
        echo "        ___           ___           ___      \n";
        echo "       /\  \         /\  \         /\  \     \n";
        echo "      /::\  \       /::\  \       /::\  \    \n";
        echo "     /:/\:\  \     /:/\:\  \     /:/\:\  \   \n";
        echo "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  \n";
        echo "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ \n";
        echo "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ \n";
        echo "        \:\__\    \:\  /:/  /   \:\ \:\__\   \n";
        echo "         \/__/     \:\/:/  /     \:\/:/  /   \n";
        echo "                    \::/  /       \::/  /    \n";
        echo "                     \/__/         \/__/     \n";
        echo "\n";
        echo "  ###########################################\n";
        echo "  #     Free Computer Imaging Solution      #\n";
        echo "  #     Credits:                            #\n";
        echo "  #     http://fogproject.org/credits       #\n";
        echo "  #     GNU GPL Version 3                   #\n";
        echo "  ###########################################\n";
        $this->outall(ob_get_clean());
    }
    public function outall($string) {
        $this->out("$string\n",$this->dev);
        $this->wlog("$string\n",$this->log);
        return;
    }
    protected function out($string,$device) {
        if (!$hdl = fopen($device,'w')) return;
        if (fwrite($hdl,"$string\n") === false) return;
        fclose($hdl);
    }
    protected function getDateTime() {
        return $this->nice_date()->format('m-d-y g:i:s a');
    }
    protected function wlog($string, $path) {
        if (file_exists($path) && filesize($path) >= $this->getSetting('SERVICE_LOG_SIZE')) unlink($path);
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
        $tmpTime = (int)$this->getSetting($this->sleeptime);
        if ($this->zzz != $tmpTime) {
            $this->zzz = $tmpTime;
            $this->outall(sprintf(" | Sleep time has changed to %s seconds",$this->zzz));
        }
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
        $itemType = $master ? 'group' : 'node';
        $findWhere = array(
            'isEnabled' => 1,
            'storageGroupID' => $master ? $Obj->get('storageGroups') : $myStorageGroupID,
        );
        if ($master) $findWhere['isMaster'] = 1;
        $StorageNode = self::getClass('StorageNode',$myStorageNodeID);
        if (!$StorageNode->isValid() || !$StorageNode->get('isMaster')) throw new Exception(_(' * I am not the master'));
        $objType = get_class($Obj);
        $groupOrNodeCount = self::getClass('StorageNodeManager')->count($findWhere);
        $countTest = ($master ? 1 : 0);
        if ($groupOrNodeCount <= 1) {
            $this->outall(_(" * Not syncing $objType between $itemType(s)"));
            $this->outall(_(" | $objType Name: {$Obj->get(name)}"));
            $this->outall(_(' | I am the only member'));
        } else {
            $this->outall(sprintf(" * Found $objType to transfer to %s %s(s)",$groupOrNodeCount,$itemType));
            $this->outall(sprintf(" | $objType name: %s",$Obj->get('name')));
            $getPathOfItemField = $objType == 'Snapin' ? 'snapinpath' : 'ftppath';
            $getFileOfItemField = $objType == 'Snapin' ? 'file' : 'path';
            $PotentialStorageNodes = array_diff((array)$this->getSubObjectIDs('StorageNode',$findWhere,'id'),(array)$myStorageNodeID);
            $myDir = sprintf('/%s/',trim($StorageNode->get($getPathOfItemField),'/'));
            $myFile = basename($Obj->get($getFileOfItemField));
            $myAdd = "$myDir$myFile";
            $myAddItem = false;
            foreach ((array)self::getClass('StorageNodeManager')->find(array('id'=>$PotentialStorageNodes)) AS $i => &$PotentialStorageNode) {
                if (!$PotentialStorageNode->isValid()) continue;
                if ($master && $PotentialStorageNode->get('storageGroupID') == $myStorageGroupID) continue;
                if ($this->isRunning($this->procRef[$itemType][$Obj->get('name')][$i])) {
                    $this->outall(_(' | Replication not complete'));
                    $this->outall(sprintf(_(' | PID: %d'),$this->getPID($this->procRef[$itemType][$Obj->get('name')][$i])));
                    continue;
                }
                if (!file_exists("$myAdd")) {
                    $this->outall(_(" * Not syncing $objType between $itemType(s)"));
                    $this->outall(_(" | $objType Name: {$Obj->get(name)}"));
                    $this->outall(_(" | File or path cannot be reached"));
                    continue;
                }
                self::$FOGFTP
                    ->set('username',$PotentialStorageNode->get('user'))
                    ->set('password',$PotentialStorageNode->get('pass'))
                    ->set('host',$PotentialStorageNode->get('ip'));
                if (!self::$FOGFTP->connect()) {
                    $this->outall(_(" * Cannot connect to {$PotentialStorageNode->get(name)}"));
                    continue;
                }
                $nodename = $PotentialStorageNode->get('name');
                $username = self::$FOGFTP->get('username');
                $password = self::$FOGFTP->get('password');
                $encpassword = urlencode($password);
                $ip = self::$FOGFTP->get('host');
                $removeDir = sprintf('/%s/',trim($PotentialStorageNode->get($getPathOfItemField),'/'));
                $removeFile = $myFile;
                $limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
                $limitsend = $this->byteconvert($PotentialStorageNode->get('bandwidth'));
                if ($limitmain > 0) $limitset = "set net:limit-total-rate 0:$limitmain;";
                if ($limitsend > 0) $limitset .= "set net:limit-rate 0:$limitsend;";
                $limit = $limitset;
                $ftpstart = "ftp://$username:$encpassword@$ip";
                if (is_file($myAdd)) {
                    $remItem = dirname("$removeDir$removeFile");
                    $includeFile = sprintf('-R -i %s',$myFile);
                    if (!$myAddItem) $myAddItem = dirname($myAdd);
                    $localfilescheck[0] = $myAdd;
                    $remotefilescheck[0] = $remItem;
                } else if (is_dir($myAdd)) {
                    $remItem = "$removeDir$removeFile";
                    $localfilescheck = glob("$myAdd/*");
                    $remotefilescheck = self::$FOGFTP->nlist($remItem);
                    $includeFile = '-R';
                    if (!$myAddItem) $myAddItem = $myAdd;
                }
                sort($localfilescheck);
                sort($remotefilescheck);
                foreach ($localfilescheck AS $j => &$localfile) {
                    if (($index = array_search($localfile,$remotefilescheck)) === false) continue;
                    $this->outall(" | Local File: $localfile");
                    $this->outall(" | Remote File: {$remotefilescheck[$index]}");
                    $res = 'true';
                    $filesize_main = filesize($localfile);
                    $filesize_rem = self::$FOGFTP->size($remotefilescheck[$index]);
                    $this->outall(" | Local File size: $filesize_main");
                    $this->outall(" | Remote File size: $filesize_rem");
                    if (!self::files_are_equal($filesize_main,$filesize_rem,$localfile,$ftpstart.$remotefilescheck[$index])) {
                        $this->outall(" | Files do not match");
                        $this->outall(" * Deleting remote file: {$remotefilescheck[$index]}");
                        self::$FOGFTP->delete($remotefilescheck[$index]);
                    } else $this->outall(" | Files match");
                    unset($localfile);
                }
                self::$FOGFTP->close();
                $logname = "$this->log.transfer.$nodename.log";
                if (!$i) $this->outall(_(' * Starting Sync Actions'));
                $this->killTasking($i,$itemType,$Obj->get('name'));
                $cmd = "lftp -e 'set ftp:list-options -a;set net:max-retries 10;set net:timeout 30; $limit mirror -c $includeFile --ignore-time -vvv --exclude 'dev/' --exclude 'ssl/' --exclude 'CA/' --delete-first $myAddItem $remItem; exit' -u $username,$password $ip";
                if ($this->getSetting('FOG_SERVICE_DEBUG')) $this->outall(" | CMD:\n\t\t\t$cmd");
                $this->startTasking($cmd,$logname,$i,$itemType,$Obj->get('name'));
                $this->outall(sprintf(' * %s %s %s',_('Started sync for'),$objType,$Obj->get('name')));
                unset($PotentialStorageNode);
            }
        }
    }
    public function startTasking($cmd,$logname,$index = 0,$itemType = false,$filename = false) {
        $descriptor = array(0=>array('pipe','r'),1=>array('file',$logname,'a'),2=>array('file',$this->log,'a'));
        if ($itemType === false) {
            $this->procRef[$index] = @proc_open($cmd,$descriptor,$pipes);
            $this->procPipes[$index] = $pipes;
        } else {
            $this->procRef[$itemType][$filename][$index] = @proc_open($cmd,$descriptor,$pipes);
            $this->procPipes[$itemType][$filename][$index] = $pipes;
        }
    }
    public function killAll($pid,$sig) {
        exec("ps -ef|awk '\$3 == '$pid' {print \$2}'",$output,$ret);
        if ($ret) return false;
        while (list(,$t) = each($output)) {
            if ($t != $pid) $this->killAll($t,$sig);
        }
        @posix_kill($pid,$sig);
    }
    public function killTasking($index = 0,$itemType = false,$filename = false) {
        if ($itemType === false) {
            foreach ((array)$this->procPipes[$index] AS $i => &$close) {
                @fclose($close);
                unset($close);
            }
            unset($this->procPipes[$index]);
            if ($this->isRunning($this->procRef[$index])) {
                $pid = $this->getPID($this->procRef[$index]);
                if ($pid) $this->killAll($pid,SIGTERM);
                @proc_terminate($this->procRef[$index],SIGTERM);
            }
            @proc_close($this->procRef[$index]);
            unset($this->procRef[$index]);
            return (bool)$this->isRunning($this->procRef[$index]);
        } else {
            foreach ((array)$this->procPipes[$itemType][$filename][$index] AS $i => &$close) {
                @fclose($close);
                unset($close);
            }
            unset($this->procPipes[$itemType][$filename][$index]);
            if ($this->isRunning($this->procRef[$itemType][$filename][$index])) {
                $pid = $this->getPID($this->procRef[$itemType][$filename][$index]);
                if ($pid) $this->killAll($pid,SIGTERM);
                @proc_terminate($this->procRef[$itemType][$filename][$index],SIGTERM);
            }
            @proc_close($this->procRef[$itemType][$filename][$index]);
            unset($this->procRef[$itemType][$filename][$index]);
            return (bool)$this->isRunning($this->procRef[$itemType][$filename][$index]);
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
