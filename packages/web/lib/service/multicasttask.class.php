<?php
class MulticastTask extends MulticastManager {
    public function getSession($method = 'find') {
        if (!in_array($method,array('find','count'))) $method = 'find';
        return $this->getClass('MulticastSessionsManager')->$method(array('stateID'=>array(0,1,2,3)));
    }
    public function getAllMulticastTasks($root) {
        $Tasks = array();
        if (self::getSession('count')) {
            $this->outall(sprintf(' | Sleeping for %s seconds to ensure tasks are properly submitted',$this->zzz));
            sleep($this->zzz);
        }
        $MulticastSessions = self::getSession('find');
        foreach($MulticastSessions AS $i => &$MultiSess) {
            $Image = $this->getClass('Image',$MultiSess->get('image'));
            if (!$Image->isValid()) continue;
            if (in_array($this->FOGCore->resolveHostname($Image->getStorageGroup()->getMasterStorageNode()->get('ip')),$this->getIPAddress())) {
                $count = $this->getClass('MulticastSessionsAssociationManager')->count(array(msID=>$MultiSess->get('id')));
                $Tasks[] = new self(
                    $MultiSess->get('id'),
                    $MultiSess->get('name'),
                    $MultiSess->get('port'),
                    $root.'/'.$MultiSess->get('logpath'),
                    $Image->getStorageGroup()->getMasterStorageNode()->get('interface')? $Image->getStorageGroup()->getMasterStorageNode()->get('interface'):$this->getSetting('FOG_UDPCAST_INTERFACE'),
                    ($count>0?$count:($MultiSess->get('sessclients')>0?$MultiSess->get('sessclients'):$this->getClass('HostManager')->count())),
                    $MultiSess->get('isDD'),
                    $Image->get('osID')
                );
            }
            unset($MultiSess);
        }
        unset($MulticastSessions);
        return array_filter($Tasks);
    }
    private $intID, $strName, $intPort, $strImage, $strEth, $intClients;
    private $intImageType, $intOSID;
    public $procRef;
    public $procPipes;
    public function __construct($id,$name,$port,$image,$eth,$clients,$imagetype,$osid) {
        parent::__construct();
        $this->intID = $id;
        $this->strName = $name;
        $this->intPort = $this->getSetting('FOG_MULTICAST_PORT_OVERRIDE')?$this->getSetting('FOG_MULTICAST_PORT_OVERRIDE'):$port;
        $this->strImage = $image;
        $this->strEth = $eth;
        $this->intClients = $clients;
        $this->intImageType = $imagetype;
        $this->intOSID = $osid;
    }
    public function getID() {
        return $this->intID;
    }
    public function getName() {
        return $this->strName;
    }
    public function getImagePath() {
        return $this->strImage;
    }
    public function getImageType() {
        return $this->intImageType;
    }
    public function getClientCount() {
        return $this->intClients;
    }
    public function getPortBase() {
        return $this->intPort;
    }
    public function getInterface() {
        return $this->strEth;
    }
    public function getOSID() {
        return $this->intOSID;
    }
    public function getUDPCastLogFile() {
        return MULTICASTLOGPATH.".udpcast.".$this->getID();
    }
    public function getBitrate() {
        return $this->getClass('Image',$this->getClass('MulticastSessions',$this->getID())->get('image'))->getStorageGroup()->getMasterStorageNode()->get('bitrate');
    }
    public function getCMD() {
        unset($filelist,$buildcmd,$cmd);
        $buildcmd = array(
            UDPSENDERPATH,
            $this->getBitrate() ? sprintf(' --max-bitrate %s',$this->getBitrate()) : null,
            $this->getInterface() ? sprintf(' --interface %s',$this->getInterface()) : null,
            sprintf(' --min-receivers %d',($this->getClientCount()?$this->getClientCount():$this->getClass(HostManager)->count())),
            sprintf(' --max-wait %d',$this->getSetting('FOG_UDPCAST_MAXWAIT')?$this->getSetting('FOG_UDPCAST_MAXWAIT')*60:UDPSENDER_MAXWAIT),
            $this->getSetting('FOG_MULTICAST_ADDRESS')?sprintf(' --mcast-data-address %s',$this->getSetting('FOG_MULTICAST_ADDRESS')):null,
            sprintf(' --portbase %s',$this->getPortBase()),
            sprintf(' %s',$this->getSetting('FOG_MULTICAST_DUPLEX')),
            ' --ttl 32',
            ' --nokbd',
            ' --nopointopoint;',
        );
        $buildcmd = array_values(array_filter($buildcmd));
        switch ((int)$this->getImageType()) {
        case 1:
            switch ((int)$this->getOSID()) {
            case 1:
            case 2:
                if (is_file($this->getImagePath())) $filelist[] = $this->getImagePath();
                else {
                    $iterator = $this->getClass('DirectoryIterator',$this->getImagePath());
                    foreach ($iterator AS $i => $fileInfo) {
                        if ($fileInfo->isDot()) continue;
                        $filelist[] = $fileInfo->getFilename();
                    }
                    unset($iterator);
                }
                break;
            case 5:
            case 6:
            case 7:
                $files = scandir($this->getImagePath());
                $sys = preg_grep('#(sys\.img\..*$)#i',$files);
                $rec = preg_grep('#(rec\.img\..*$)#i',$files);
                if (count($sys) || count($rec)) {
                    if (count($sys)) $filelist[] = 'sys.img.*';
                    if (count($rec)) $filelist[] = 'rec.img.*';
                } else {
                    $filename = 'd1p%d.%s';
                    $iterator = $this->getClass('DirectoryIterator',$this->getImagePath());
                    foreach ($iterator AS $i => $fileInfo) {
                        if ($fileInfo->isDot()) continue;
                        sscanf($fileInfo->getFilename(),$filename,$part,$ext);
                        if ($ext == 'img') $filelist[] = $fileInfo->getFilename();
                        unset($part,$ext);
                    }
                }
                unset($files,$sys,$rec);
                break;
            default:
                $filename = 'd1p%d.%s';
                $iterator = $this->getClass('DirectoryIterator',$this->getImagePath());
                foreach ($iterator AS $i => $fileInfo) {
                    if ($fileInfo->isDot()) continue;
                    sscanf($fileInfo->getFilename(),$filename,$part,$ext);
                    if ($ext == 'img') $filelist[] = $fileInfo->getFilename();
                    unset($part,$ext);
                }
                break;
            }
            break;
        case 2:
            $filename = 'd1p%d.%s';
            $iterator = $this->getClass('DirectoryIterator',$this->getImagePath());
            foreach ($iterator AS $i => $fileInfo) {
                if ($fileInfo->isDot()) continue;
                sscanf($fileInfo->getFilename(),$filename,$part,$ext);
                if ($ext == 'img') $filelist[] = $fileInfo->getFilename();
                unset($part,$ext);
            }
            break;
        case 3:
            $filename = 'd%dp%d.%s';
            $iterator = $this->getClass('DirectoryIterator',$this->getImagePath());
            foreach ($iterator AS $i => $fileInfo) {
                if ($fileInfo->isDot()) continue;
                sscanf($fileInfo->getFilename(),$filename,$device,$part,$ext);
                if ($ext == 'img') $filelist[] = $fileInfo->getFilename();
                unset($device,$part,$ext);
            }
            break;
        case 4:
            $iterator = $this->getClass('DirectoryIterator',$this->getImagePath());
            foreach ($iterator AS $i => $fileInfo) {
                if ($fileInfo->isDot()) continue;
                $filelist[] = $fileInfo->getFilename();
            }
            unset($iterator);
            break;
        }
        natsort($filelist);
        $cmd = '';
        foreach ($filelist AS $i => &$file) {
            $cmd .= sprintf('cat %s | %s',rtrim($this->getImagePath(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file,implode($buildcmd));
            unset($file);
        }
        unset($filelist);
        return $cmd;
    }
    public function startTask() {
        @unlink($this->getUDPCastLogFile());
        $this->startTasking($this->getCMD(),$this->getUDPCastLogFile());
        $this->procRef = array_shift($this->procRef);
        $this->getClass('MulticastSessions',$this->intID)
            ->set(stateID,1)
            ->save();
        return $this->isRunning($this->procRef);
    }
    public function killTask() {
        $this->killTasking();
        @unlink($this->getUDPCastLogFile());
        $Tasks = $this->getClass('TaskManager')->find(array('id'=>$this->getSubObjectIDs('MulticastSessionsAssociation',array('msID'=>$RMTask->getID()),'taskID')));
        foreach((array)$Tasks AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Task
                ->set('stateID',$running)
                ->save();
            unset($Task);
        }
        unset($Tasks);
        $this->getClass('MulticastSessions',$this->intID)
            ->set('name',null)
            ->set('stateID',$running)
            ->save();
        return true;
    }
    public function updateStats() {
        $Tasks = $this->getClass('TaskManager')->find(array('id'=>$this->getSubObjectIDs('MulticastSessionsAssociation',array('msID'=>$this->intID),'taskID')));
        foreach($Tasks AS $i => &$Task) {
            $TaskPercent[] = $Task->get('percent');
            unset($Task);
        }
        unset($Tasks);
        $TaskPercent = array_unique((array)$TaskPercent);
        $this->getClass('MulticastSessions',$this->intID)->set('percent',@max((array)$TaskPercent))->save();
    }
}
