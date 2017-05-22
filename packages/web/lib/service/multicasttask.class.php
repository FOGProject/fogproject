<?php
/**
 * Multicast task generator/finder
 *
 * PHP version 5
 *
 * @category MulticastTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Multicast task generator/finder
 *
 * @category MulticastTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastTask extends FOGService
{
    /**
     * Gets all the multicast tasks
     *
     * @param string $root            root to look for items
     * @param int    $myStorageNodeID this services storage id
     *
     * @return array
     */
    public function getAllMulticastTasks($root, $myStorageNodeID)
    {
        $StorageNode = self::getClass('StorageNode', $myStorageNodeID);
        self::$HookManager->processEvent(
            'CHECK_NODE_MASTER',
            array(
                'StorageNode' => &$StorageNode,
                'FOGServiceClass' => &$this
            )
        );
        if (!$StorageNode->get('isMaster')) {
            return;
        }
        $Interface = self::getMasterInterface(
            self::resolveHostname(
                $StorageNode->get('ip')
            )
        );
        unset($StorageNode);
        $Tasks = array();
        $find = array(
            'stateID' =>
            self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            )
        );
        foreach ((array)self::getClass('MulticastSessionManager')
            ->find($find) as $index => &$MultiSess
        ) {
            $taskIDs = self::getSubObjectIDs(
                'MulticastSessionAssociation',
                array(
                    'msID' => $MultiSess->get('id')
                ),
                'taskID'
            );
            $count = self::getClass('MulticastSessionAssociationManager')
                ->count(
                    array(
                        'msID' => $MultiSess->get('id')
                    )
                );
            if ($count < 1) {
                $count = $MultiSess->get('sessclients');
            }
            if ($count < 1) {
                $MultiSess->set('stateID', self::getCancelledState())->save();
                self::outall(
                    _('Task not created as there are no associated Tasks')
                );
                self::outall(
                    _('Or there was no number defined for joining session')
                );
                continue;
            }
            $Image = $MultiSess->getImage();
            $fullPath = sprintf('%s/%s', $root, $MultiSess->get('logpath'));
            if (!file_exists($fullPath)) {
                continue;
            }
            $Tasks[] = new self(
                $MultiSess->get('id'),
                $MultiSess->get('name'),
                $MultiSess->get('port'),
                $fullPath,
                $Interface,
                $count,
                $MultiSess->get('isDD'),
                $Image->get('osID'),
                $MultiSess->get('clients') == -2 ? 1 : 0,
                $taskIDs
            );
            unset($MultiSess, $index);
        }
        return array_filter($Tasks);
    }
    /**
     * Session ID
     *
     * @var int
     */
    private $_intID;
    /**
     * The session name
     *
     * @var string
     */
    private $_strName;
    /**
     * The session port
     *
     * @var int
     */
    private $_intPort;
    /**
     * The session image
     *
     * @var string
     */
    private $_strImage;
    /**
     * The session interface to use
     *
     * @var string
     */
    private $_strEth;
    /**
     * The number of clients
     *
     * @var int
     */
    private $_intClients;
    /**
     * The sessions task ids
     *
     * @var array
     */
    private $_taskIDs;
    /**
     * The sessions image type
     *
     * @var int
     */
    private $_intImageType;
    /**
     * The sessions osid
     *
     * @var int
     */
    private $_intOSID;
    /**
     * Is this session a joined session
     *
     * @var bool
     */
    private $_isNameSess;
    /**
     * The multicast session class
     *
     * @var object
     */
    private $_MultiSess;
    /**
     * This tasks process reference
     *
     * @var resource
     */
    public $procRef;
    /**
     * This tasks process piped info
     *
     * @var resource
     */
    public $procPipes;
    /**
     * Initializes the task so multicast man can process
     *
     * @param int    $id        the id
     * @param string $name      the name
     * @param int    $port      the port
     * @param string $image     the image
     * @param string $eth       the interface
     * @param int    $clients   the number of clients
     * @param int    $imagetype the image type
     * @param int    $osid      the os id
     * @param bool   $nameSess  the named session
     * @param array  $taskIDs   the task ids
     *
     * @return void
     */
    public function __construct(
        $id = '',
        $name = '',
        $port = '',
        $image = '',
        $eth = '',
        $clients = '',
        $imagetype = '',
        $osid = '',
        $nameSess = '',
        $taskIDs = ''
    ) {
        parent::__construct();
        $overridePort = self::getSetting('FOG_MULTICAST_PORT_OVERRIDE');
        $this->_intID = $id;
        $this->_strName = $name;
        if ($overridePort) {
            $this->_intPort = $overridePort;
        } else {
            $this->_intPort = $port;
        }
        $this->_strImage = $image;
        $this->_strEth = $eth;
        $this->_intClients = $clients;
        $this->_intImageType = $imagetype;
        $this->_intOSID = $osid;
        $this->_isNameSess = $nameSess;
        $this->_taskIDs = $taskIDs;
        $this->_MultiSess = new MulticastSession($this->getID());
    }
    /**
     * Get session clients
     *
     * @return object
     */
    public function getSessClients()
    {
        return $this->_MultiSess->get('clients') == 0;
    }
    /**
     * Is this a named session
     *
     * @return bool
     */
    public function isNamedSession()
    {
        return (bool)$this->_isNameSess;
    }
    /**
     * Returns the task ids
     *
     * @return array
     */
    public function getTaskIDs()
    {
        return $this->_taskIDs;
    }
    /**
     * Returns the id
     *
     * @return int
     */
    public function getID()
    {
        return $this->_intID;
    }
    /**
     * Returns the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_strName;
    }
    /**
     * Returns the image path
     *
     * @return string
     */
    public function getImagePath()
    {
        return $this->_strImage;
    }
    /**
     * Returns the image type
     *
     * @return int
     */
    public function getImageType()
    {
        return $this->_intImageType;
    }
    /**
     * Returns the client count
     *
     * @return int
     */
    public function getClientCount()
    {
        return $this->_intClients;
    }
    /**
     * Returns the port
     *
     * @return int
     */
    public function getPortBase()
    {
        return $this->_intPort;
    }
    /**
     * Returns the interface
     *
     * @return string
     */
    public function getInterface()
    {
        return $this->_strEth;
    }
    /**
     * Returns the os id
     *
     * @return int
     */
    public function getOSID()
    {
        return $this->_intOSID;
    }
    /**
     * Returns the udpcast log file
     *
     * @return string
     */
    public function getUDPCastLogFile()
    {
        list(
            $filenam,
            $logpath
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'MULTICASTLOGFILENAME',
                    'SERVICE_LOG_PATH',
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        return $this->altLog = sprintf(
            '/%s/%s.udpcast.%s',
            trim($logpath, '/'),
            $filenam,
            $this->getID()
        );
    }
    /**
     * Returns the bitrate max
     *
     * @return string
     */
    public function getBitrate()
    {
        return self::getClass(
            'Image',
            $this->_MultiSess->get('image')
        )->getStorageGroup()
        ->getMasterStorageNode()
        ->get('bitrate');
    }
    /**
     * Returns the session class
     *
     * @return object
     */
    public function getSess()
    {
        return $this->_MultiSess;
    }
    /**
     * Sets/Gets the command needed to start the tasking
     *
     * @return string
     */
    public function getCMD()
    {
        unset(
            $filelist,
            $buildcmd,
            $cmd
        );
        list(
            $address,
            $duplex,
            $multicastrdv,
            $maxwait
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_MULTICAST_ADDRESS',
                    'FOG_MULTICAST_DUPLEX',
                    'FOG_MULTICAST_RENDEZVOUS',
                    'FOG_UDPCAST_MAXWAIT'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $buildcmd = array(
            UDPSENDERPATH,
            (
                $this->getBitrate() ?
                sprintf(' --max-bitrate %s', $this->getBitrate()) :
                null
            ),
            (
                $this->getInterface() ?
                sprintf(' --interface %s', $this->getInterface()) :
                null
            ),
            sprintf(
                ' --min-receivers %d',
                (
                    $this->getClientCount() ?
                    $this->getClientCount():
                    self::getClass('HostManager')->count()
                )
            ),
            sprintf(' --max-wait %s', '%d'),
            (
                $address ?
                sprintf(' --mcast-data-address %s', $address) :
                null
            ),
            (
                $multicastrdv ?
                sprintf(' --mcast-rdv-address %s', $multicastrdv) :
                null
            ),
            sprintf(' --portbase %s', $this->getPortBase()),
            sprintf(' %s', $duplex),
            ' --ttl 32',
            ' --nokbd',
            ' --nopointopoint',
        );
        $buildcmd = array_values(array_filter($buildcmd));
        switch ($this->getImageType()) {
        case 1:
            switch ($this->getOSID()) {
            case 1:
            case 2:
                if (is_file($this->getImagePath())) {
                    $filelist[] = $this->getImagePath();
                    break;
                }
            case 5:
            case 6:
            case 7:
                $files = scandir($this->getImagePath());
                $sys = preg_grep('#(sys\.img\..*$)#i', $files);
                $rec = preg_grep('#(rec\.img\..*$)#i', $files);
                if (count($sys) || count($rec)) {
                    if (count($sys)) {
                        $filelist[] = 'sys.img.*';
                    }
                    if (count($rec)) {
                        $filelist[] = 'rec.img.*';
                    }
                } else {
                    $filename = 'd1p%d.%s';
                    $iterator = new DirectoryIterator(
                        $this->getImagePath()
                    );
                    foreach ($iterator as $fileInfo) {
                        if ($fileInfo->isDot()) {
                            continue;
                        }
                        sscanf(
                            $fileInfo->getFilename(),
                            $filename,
                            $part,
                            $ext
                        );
                        if ($ext == 'img') {
                            $filelist[] = $fileInfo->getFilename();
                        }
                        unset($part, $ext);
                    }
                    unset($iterator);
                }
                unset($files, $sys, $rec);
                break;
            default:
                $filename = 'd1p%d.%s';
                $iterator = new DirectoryIterator(
                    $this->getImagePath()
                );
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }
                    sscanf(
                        $fileInfo->getFilename(),
                        $filename,
                        $part,
                        $ext
                    );
                    if ($ext == 'img') {
                        $filelist[] = $fileInfo->getFilename();
                    }
                    unset($part, $ext);
                }
                unset($iterator);
                break;
            }
            break;
        case 2:
            $filename = 'd1p%d.%s';
            $iterator = new DirectoryIterator(
                $this->getImagePath()
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                sscanf(
                    $fileInfo->getFilename(),
                    $filename,
                    $part,
                    $ext
                );
                if ($ext == 'img') {
                    $filelist[] = $fileInfo->getFilename();
                }
                unset($part, $ext);
            }
            unset($iterator);
            break;
        case 3:
            $filename = 'd%dp%d.%s';
            $iterator = new DirectoryIterator(
                $this->getImagePath()
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                sscanf(
                    $fileInfo->getFilename(),
                    $filename,
                    $device,
                    $part,
                    $ext
                );
                if ($ext == 'img') {
                    $filelist[] = $fileInfo->getFilename();
                }
                unset($device, $part, $ext);
            }
            unset($iterator);
            break;
        case 4:
            $iterator = new DirectoryIterator(
                $this->getImagePath()
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                $filelist[] = $fileInfo->getFilename();
            }
            unset($iterator);
            break;
        }
        natcasesort($filelist);
        $filelist = array_values((array)$filelist);
        ob_start();
        foreach ($filelist as $i => &$file) {
            printf(
                '%s --file %s%s%s;',
                sprintf(
                    implode($buildcmd),
                    (
                        $i == 0 ?
                        $maxwait * 60 :
                        10
                    )
                ),
                rtrim(
                    $this->getImagePath(),
                    DS
                ),
                DS,
                $file
            );
            unset($file);
        }
        unset($filelist, $buildcmd);
        return ob_get_clean();
    }
    /**
     * Starts our tasking as needed
     *
     * @return bool
     */
    public function startTask()
    {
        if (file_exists($this->getUDPCastLogFile())) {
            unlink($this->getUDPCastLogFile());
        }
        $this->startTasking($this->getCMD(), $this->getUDPCastLogFile());
        $this->procRef = array_shift($this->procRef);
        $this->_MultiSess
            ->set('stateID', self::getQueuedState())
            ->save();
        return $this->isRunning($this->procRef);
    }
    /**
     * Kills the tasking as needed
     *
     * @return bool
     */
    public function killTask()
    {
        $this->killTasking();
        unlink($this->getUDPCastLogFile());
        return true;
    }
    /**
     * Updates the stats of the tasking
     *
     * @return void
     */
    public function updateStats()
    {
        $find = array(
            'id' => self::getSubObjectIDs(
                'MulticastSessionAssociation',
                array('msID' => $this->_intID),
                'taskID'
            )
        );
        foreach ((array)self::getClass('TaskManager')
            ->find($find) as &$Task
        ) {
            $TaskPercent[] = $Task->get('percent');
            unset($Task);
        }
        unset($Tasks);
        $TaskPercent = array_unique((array)$TaskPercent);
        $this->_MultiSess
            ->set('percent', @max($TaskPercent))
            ->save();
    }
}
