<?php
class ImageReplicator extends FOGService {
    public $dev = REPLICATORDEVICEOUTPUT;
    public $log = REPLICATORLOGPATH;
    public $zzz = REPLICATORSLEEPTIME;
    private function commonOutput() {
        $StorageNode = current($this->getClass(StorageNodeManager)->find(array('isMaster' => 1,'isEnabled' => 1,'ip' => $this->FOGCore->getIPAddress())));
        try {
            if (!$StorageNode || !$StorageNode->isValid()) {
                $this->FOGCore->wlog(" * I don't appear to be the group manager, I will check back later.",'/opt/fog/log/groupmanager.log');
                throw new Exception("I don't appear to be the group manager, I will check back later.");
            }
            $this->FOGCore->out(' * I am the group manager.',$this->dev);
            $this->FOGCore->wlog(' * I am the group manager.','/opt/fog/log/groupmanager.log');
            $this->outall(" * Starting Image Replication.");

            $this->outall(sprintf(" * We are group ID: #%s",$StorageNode->get('storageGroupID')));
            $this->outall(sprintf(" * We have node ID: #%s",$StorageNode->get('id')));
            $StorageNodes = $this->getClass('StorageNodeManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
            foreach($StorageNodes AS $i => &$OtherNode) {
                if ($OtherNode->get('id') != $StorageNode->get('id') && $OtherNode->get('isEnabled')) $StorageNodeCount[] = $OtherNode;
            }
            unset($OtherNode);
            unset($limit);
            // Try to get the images based on this group
            $ImageIDs = array_unique($this->getClass(ImageAssociationManager)->find(array('storageGroupID' => $StorageNode->get(storageGroupID)),'','','','','','','imageID'));
            foreach($ImageIDs AS $i => &$imgID) {
                $Image = $this->getClass(Image,$imgID);
                $ImageGroups = $this->getClass(StorageGroupManager)->find(array('id' => $Image->get(storageGroups)));
                foreach($ImageGroups AS $i => &$GroupToSend) {
                    if ($GroupToSend->isValid() && $GroupToSend->get(id) != $StorageNode->get(storageGroupID)) {
                        $StorageNodeToSend = $GroupToSend->getMasterStorageNode();
                        if ($StorageNodeToSend && $StorageNodeToSend->isValid()) {
                            $username = $StorageNodeToSend->get(user);
                            $password = $StorageNodeToSend->get(pass);
                            $ip = $this->FOGCore->resolveHostname($StorageNodeToSend->get(ip));
                            $remImage = rtrim($StorageNodeToSend->get(ftppath),'/').'/'.$Image->get(path);
                            $myImage = rtrim($StorageNode->get(ftppath),'/').'/'.$Image->get(path);
                            $limitmain = $this->byteconvert($StorageNode->get(bandwidth));
                            $limitsend = $this->byteconvert($StorageNodeToSend->get(bandwidth));
                            if ($limitmain > 0) $limit = "set net:limit-total-rate 0:$limitmain;";
                            if ($limitsend > 0) $limit .= "set net:limit-rate 0:$limitsend;";
                            $this->outall(sprintf(" * Found image to transfer to %s group(s)",count($Image->get(storageGroups)) - 1));
                            $this->outall(sprintf(" | Image name: %s",$Image->get('name')));
                            $process[$StorageNodeToSend->get('name')] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 10;set net:timeout 30;".$limit." mirror -n --ignore-time -R -vvv --exclude 'dev/' --delete $myImage $remImage; exit\" -u $username,$password $ip 2>&1","r");
                        }
                    }
                }
                unset($GroupToSend);
                foreach ((array)$process AS $nodename => &$proc) {
                    stream_set_blocking($proc,false);
                    while (!feof($proc) && $proc != null) {
                        $output = fgets($proc,256);
                        if ($output) $this->outall(sprintf(" * %s - SubProcess -> %s",$nodename,$output));
                    }
                    pclose($proc);
                    $this->outall(sprintf(" * %s - SubProcess -> Complete",$nodename));
                }
                unset($process,$proc);
            }
            unset($limit,$imgID);
            $this->outall(sprintf(" * Checking nodes within my group."));
            if (!count($StorageNodeCount)) throw new Exception(sprintf("I am the only member, no need to copy anything!."));
            $this->outall(sprintf(" * Found: %s other member(s).",count($StorageNodeCount)));
            $this->outall(sprintf(''));
            $myRoot = rtrim($StorageNode->get('ftppath'),'/');
            $this->outall(sprintf(" * My root: %s",$myRoot));
            $this->outall(sprintf(" * Starting Sync."));
            foreach($StorageNodeCount AS $i => &$StorageNodeFTP) {
                if ($StorageNodeFTP->get(isEnabled)) {
                    $username = $StorageNodeFTP->get(user);
                    $password = $StorageNodeFTP->get(pass);
                    $ip = $StorageNodeFTP->get(ip);
                    $remRoot = rtrim($StorageNodeFTP->get(ftppath),'/');
                    $limitmain = $this->byteconvert($StorageNode->get(bandwidth));
                    $limitsend = $this->byteconvert($StorageNodeFTP->get(bandwidth));
                    if ($limitmain > 0) $limit = "set net:limit-total-rate 0:$limitmain;";
                    if ($limitsend > 0) $limit .= "set net:limit-rate 0:$limitsend;";
                    $process[$StorageNodeFTP->get(name)] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 10;set net:timeout 30;".$limit." mirror -n --ignore-time -R -vvv --exclude 'dev/' --delete $myRoot $remRoot; exit\" -u $username,$password $ip 2>&1","r");
                }
            }
            unset($StorageNodeFTP);
            foreach ((array)$process AS $nodename => &$proc) {
                stream_set_blocking($proc,false);
                while(!feof($proc) && $proc != null) {
                    $output = fgets($proc,256);
                    if ($output) $this->outall(sprintf(" * %s - SubProcess -> %s",$nodename,$output));
                }
                pclose($proc);
                $this->outall(sprintf(" * %s - SubProcess -> Complete",$nodename));
            }
            unset($process,$proc);
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
