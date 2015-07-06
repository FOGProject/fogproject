<?php
class MulticastTask extends FOGBase {
    // Updated to only care about tasks in its group
    public static function getAllMulticastTasks($root) {
        global $FOGCore;
        $Tasks = array();
        foreach($FOGCore->getClass('MulticastSessionsManager')->find(array('stateID' => array(0,1,2,3))) AS $MultiSess) {
            $Image = $FOGCore->getClass(Image,$MultiSess->get(image));
            if (in_array($FOGCore->resolveHostname($Image->getStorageGroup()->getMasterStorageNode()->get('ip')),$FOGCore->getIPAddress())) {
                $count = $FOGCore->getClass('MulticastSessionsAssociationManager')->count(array('msID' => $MultiSess->get('id')));
                $Tasks[] = new self(
                    $MultiSess->get('id'),
                    $MultiSess->get('name'),
                    $MultiSess->get('port'),
                    $root.'/'.$MultiSess->get('logpath'),
                    $Image->getStorageGroup()->getMasterStorageNode()->get('interface') ? $Image->getStorageGroup()->getMasterStorageNode()->get('interface') : $FOGCore->getSetting('FOG_UDPCAST_INTERFACE'),
                    ($count > 0 ? $count : ($MultiSess->get('sessclients') > 0 ? $MultiSess->get('sessclients') : $FOGCore->getClass('HostManager')->count())),
                    $MultiSess->get('isDD'),
                    $Image->get('osID')
                );
            }
        }
        return array_filter($Tasks);
    }
    private $intID, $strName, $intPort, $strImage, $strEth, $intClients;
    private $intImageType, $intOSID;
    private $procRef, $arPipes;
    private $deathTime;
    public function __construct($id, $name, $port, $image, $eth, $clients, $imagetype, $osid) {
        parent::__construct();
        $this->intID = $id;
        $this->strName = $name;
        $this->intPort = $this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE') ? $this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE') : $port;
        $this->strImage = $image;
        $this->strEth = $eth;
        $this->intClients = $clients;
        $this->intImageType = $imagetype;
        $this->deathTime = null;
        $this->intOSID = $osid;
        $this->dubPercent = null;
    }
    public function getID() {return $this->intID;}
        public function getName() {return $this->strName;}
        public function getImagePath() {return $this->strImage;}
        public function getImageType() {return $this->intImageType;}
        public function getClientCount() {return $this->intClients;}
        public function getPortBase() {return $this->intPort;}
        public function getInterface() {return $this->strEth;}
        public function getOSID() {return $this->intOSID;}
        public function getUDPCastLogFile() {return MULTICASTLOGPATH.".udpcast.".$this->getID();}
        public function getBitrate() {
            return $this->getClass(Image,$this->getClass(MulticastSessions,$this->getID())->get(image))->getStorageGroup()->getMasterStorageNode()->get(bitrate);
        }
        public function getCMD() {
            unset($filelist,$buildcmd,$cmd);
            $buildcmd = array(
                UDPSENDERPATH,
                $this->getBitrate() ? sprintf(' --max-bitrate %s',$this->getBitrate()) : null,
                $this->getInterface() ? sprintf(' --interface %s',$this->getInterface()) : null,
                sprintf(' --min-receivers %d',($this->getClientCount() ? $this->getClientCount() : $this->getClass('HostManager')->count())),
                sprintf(' --max-wait %d', $this->FOGCore->getSetting('FOG_UDPCAST_MAXWAIT') ? $this->FOGCore->getSetting('FOG_UDPCAST_MAXWAIT') * 60 : UDPSENDER_MAXWAIT),
                $this->FOGCore->getSetting('FOG_MULTICAST_ADDRESS') ? sprintf(' --mcast-data-address %s',$this->FOGCore->getSetting('FOG_MULTICAST_ADDRESS')) : null,
                sprintf(' --portbase %s',$this->getPortBase()),
                sprintf(' %s',$this->FOGCore->getSetting('FOG_MULTICAST_DUPLEX')),
                ' --ttl 32',
                ' --nokbd',
                ' --nopointopoint;',
            );
            $buildcmd = array_values(array_filter($buildcmd));
            if ($this->getImageType() == 4) {
                if (is_dir($this->getImagePath())) {
                    if($handle = opendir($this->getImagePath())) {
                        while (false !== ($file = readdir($handle))) {
                            if ($file != '.' && $file != '..') $filelist[] = $file;
                        }
                        closedir($handle);
                    }
                }
            } else if ($this->getImageType() == 1 && in_array($this->getOSID(),array(1,2))) {
                if (is_dir($this->getImagePath())) {
                    if ($handle = opendir($this->getImagePath())) {
                        while (false !== ($file = readdir($handle))) {
                            if ($file != '.' && $file != '..') $filelist[] = $file;
                        }
                        closedir($handle);
                    }
                } else if (is_file($this->getImagePath())) $filelist[] = $this->getImagePath();
            } else {
                $device = 1;
                $part = 0;
                if (in_array($this->getImageType(),array(1,2))) $filename = 'd1p%d.%s';
                if ($this->getImageType() == 3) $filename = 'd%dp%d.%s';
                if (is_dir($this->getImagePath())) {
                    if ($handle = opendir($this->getImagePath())) {
                        while (false !== ($file = readdir($handle))) {
                            if ($file != '.' && $file != '..') {
                                $ext = '';
                                if ($this->getImageType() == 3) sscanf($file,$filename,$device,$part,$ext);
                                else sscanf($file,$filename,$part,$ext);
                                if ($ext == 'img') $filelist[] = $file;
                            }
                        }
                        closedir($handle);
                    }
                }
            }
            if (in_array($this->getOSID(),array(5,6,7)) && $this->getImageType() == 1) {
                if (is_dir($this->getImagePath())) {
                    if (file_exists(rtrim($this->getImagePath(),'/').'/rec.img.000') || file_exists(rtrim($this->getImagePath(),'/').'/sys.img.000')) {
                        unset($filelist);
                        if (file_exists(rtrim($this->getImagePath(),'/').'/rec.img.000')) $filelist[] = 'rec.img.*';
                        if (file_exists(rtrim($this->getImagePath(),'/').'/sys.img.000')) $filelist[] = 'sys.img.*';
                    }
                }
            }
            natsort($filelist);
            foreach ($filelist AS $file) $cmd[] = sprintf('cat %s | %s',rtrim($this->getImagePath(),'/').'/'.$file,implode($buildcmd));
            return implode($cmd);
        }
    public function startTask() {
        @unlink($this->getUDPCastLogFile());
        $descriptor = array(0 => array('pipe','r'), 1 => array('file',$this->getUDPCastLogFile(),'w'), 2 => array('file',$this->getUDPCastLogFile(),'w'));
        $this->procRef = @proc_open($this->getCMD(),$descriptor,$pipes);
        $this->arPipes = $pipes;
        $MultiSess = new MulticastSessions($this->intID);
        $MultiSess->set('stateID','1')->save();
        return $this->isRunning();
    }
    public function flagAsDead() {
        if($this->deathTime == null) $this->deathTime = time();
    }
    private static function killAll($pid,$sig) {
        exec("ps -ef|awk '\$3 == '$pid' {print \$2}'",$output,$ret);
        if ($ret) return false;
        while (list(,$t) = each($output)) {
            if  ($t != $pid) self::killAll($t,$sig);
        }
        @posix_kill($pid,$sig);
    }
    public function killTask() {
        foreach($this->arPipes AS $closeme) @fclose($closeme);
        if ($this->isRunning()) {
            $pid = $this->getPID();
            if ($pid) self::killAll($pid, SIGTERM);
            @proc_terminate($this->procRef, SIGTERM);
        }
        @proc_close($this->procRef);
        $this->procRef=null;
        @unlink($this->getUDPCastLogFile());
        foreach($this->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $this->intID)) AS $MultiSessAssoc) {
            $Task = new Task($MultiSessAssoc->get('taskID'));
            $Task->set('stateID','5')->save();
        }
        $MultiSess = new MulticastSessions($this->intID);
        $MultiSess->set('name',null)->set('name','')->set('stateID','5')->save();
        return true;
    }
    public function updateStats() {
        foreach($this->getClass('MulticastSessionsAssociationManager')->find(array('msid' => $this->intID)) AS $MultiSessAssoc) {
            $Task = new Task($MultiSessAssoc->get('taskID'));
            if ($Task && $Task->isValid()) $TaskPercent[] = $Task->get('percent');
        }
        $TaskPercent = array_unique((array)$TaskPercent);
        $MultiSess = new MulticastSessions($this->intID);
        $MultiSess->set('percent',max((array)$TaskPercent))->save();
    }
    public function isRunning() {
        if ($this->procRef) {
            $ar = proc_get_status($this->procRef);
            return $ar['running'];
        }
        return false;
    }
    public function getPID() {
        if ($this->procRef) {
            $ar = proc_get_status($this->procRef);
            return $ar['pid'];
        }
        return -1;
    }
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
