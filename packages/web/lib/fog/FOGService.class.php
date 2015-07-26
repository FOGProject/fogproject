<?php
abstract class FOGService extends FOGBase {
    /** @var $dev string the device output for console */
    public $dev;
    /** @var $log string the log file to write to */
    public $log;
    /** @var $zzz int the sleep time for the service */
    public $zzz;
    /** getIPAddress()
     * Gets the service server's IP address.
     */
    public function getIPAddress() {
        $output = array();
        exec("/sbin/ip addr | awk -F'[ /]+' '/global/ {print $3}'|grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs, $retVal);
        if (!count($IPs)) exec("/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d':' -f2' | grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'", $IPs,$retVal);
        foreach ($IPs AS $i => &$IP) {
            $IP = trim($IP);
            if (($bIp = ip2long($IP)) !== false) $output[] = $IP;
            $output[] = gethostbyaddr($IP);
        }
        unset($IP);
        return array_values(array_unique((array)$output));
    }
    /** wait_interface_ready() wait for interface to be ready
     * @return void
     */
    public function wait_interface_ready() {
        $ipaddresses = $this->getIPAddress();
        $ipcount = count($ipaddresses);
        if (!$ipcount) {
            $this->out('Interface not ready, waiting.');
            sleep(10);
            $this->wait_interface_ready();
        }
        foreach ($ipaddresses AS $i => &$ip) $this->out("Interface Ready with IP Address: $ip",$this->log);
        unset($ip);
    }
    /** wait_db_ready() wait for db to be ready
     * @return void
     */
    public function wait_db_ready() {
        while ($this->DB->link()->connect_errno) {
            $this->out('FOGService: '.get_class($this).' - Waiting for mysql to be available',$this->dev);
            sleep(10);
        }
    }
    /** getBanner() Prints the FOG banner
     * @return $str the string as is
     */
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
    /** @function outall() outputs to log file
     * @param $string string the data to write
     * @return null
     */
    public function outall($string) {
        $this->out($string."\n",$this->dev);
        $this->wlog($string."\n",$this->log);
        return;
    }
    /** out()
     * @param $string the string to send
     * @param $device the file/device to output to
     * @return void
     */
    public function out($string,$device) {
        $strOut = $string."\n";
        if (!$hdl = fopen($device,'w')) return;
        if (fwrite($hdl,$strOut) === FALSE) return;
        fclose($hdl);
    }
    /** getDateTime()
     * Returns the date format used at the start of each line in the service lines.
     */
    public function getDateTime() {
        return $this->nice_date()->format('m-d-y g:i:s a');
    }
    /** wlog($string, $path)
     * Writes to the log file and clears if needed.
     */
    public function wlog($string, $path) {
        if (filesize($path) > LOGMAXSIZE) unlink($path);
        if (!$hdl = fopen($path,'a')) $this->out("\n * Error: Unable to open file: $path\n");
        if (fwrite($hdl,sprintf('[%s] %s',$this->getDateTime(),$string)) === FALSE) $this->out("\n * Error: Unable to write to file: $path\n");
    }
    /** @function serviceStart() starts the service
     * @return null
     */
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
            }
            unset($StorageNodeToSend);
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
