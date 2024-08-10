<?php
/**
 * Storage node handler class.
 *
 * PHP version 5
 *
 * @category StorageNode
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Storage node handler class.
 *
 * @category StorageNode
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageNode extends FOGController
{
    /**
     * The storage node table.
     *
     * @var string
     */
    protected $databaseTable = 'nfsGroupMembers';
    /**
     * The storage node fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ngmID',
        'name' => 'ngmMemberName',
        'description' => 'ngmMemberDescription',
        'isMaster' => 'ngmIsMasterNode',
        'storagegroupID' => 'ngmGroupID',
        'isEnabled' => 'ngmIsEnabled',
        'isGraphEnabled' => 'ngmGraphEnabled',
        'path' => 'ngmRootPath',
        'ftppath' => 'ngmFTPPath',
        'bitrate' => 'ngmMaxBitrate',
        'helloInterval' => 'ngmHelloInterval',
        'snapinpath' => 'ngmSnapinPath',
        'sslpath' => 'ngmSSLPath',
        'ip' => 'ngmHostname',
        'maxClients' => 'ngmMaxClients',
        'user' => 'ngmUser',
        'pass' => 'ngmPass',
        'key' => 'ngmKey',
        'interface' => 'ngmInterface',
        'bandwidth' => 'ngmBandwidthLimit',
        'webroot' => 'ngmWebroot',
        'graphcolor' => 'ngmGraphColor'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'ip',
        'path',
        'ftppath',
        'user',
        'pass'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'images',
        'snapinfiles',
        'logfiles',
        'usedtasks',
        'storagegroup',
        'online'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'StorageGroup' => [
            'id',
            'storagegroupID',
            'storagegroup'
        ]
    ];
    protected $sqlQueryStr = "SELECT `%s`,`ngID`,`ngName`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`
        %s
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`),`ngID`,`ngName`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`),`ngID`,`ngName`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`";
    /**
     * Gets an item from the key sent, if no key all object data is returned.
     *
     * @param mixed $key the key to get
     *
     * @return object
     */
    public function get($key = '')
    {
        $pathvars = [
            'path',
            'ftppath',
            'snapinpath',
            'sslpath',
            'webroot'
        ];
        if (in_array($key, $pathvars)) {
            if (trim(parent::get($key)) === '/') {
                return parent::get($key);
            }
            return rtrim(parent::get($key), '/');
        }
        if ($key === 'pass') {
            return parent::get($key);
        }
        $loaders = [
            'snapinfiles' => 'getSnapinfiles',
            'images' => 'getImages',
            'logfiles' => 'getLogfiles'
        ];
        if (in_array($key, array_keys($loaders))
            && !array_key_exists($key, $this->data)
        ) {
            if (!$this->get('online')) {
                return parent::get($key);
            }
            $func = $loaders[$key];
            $this->{$func}();
        }

        return parent::get($key);
    }
    /**
     * Loads the log files available on this node.
     *
     * @return void
     */
    public function getLogfiles()
    {
        $paths = array_values(
            array_filter(
                $this->_getData('logfiles')
            )
        );
        @natcasesort($paths);
        $this->set('logfiles', (array)$paths);
    }
    /**
     * Get the storage group of this node.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
    /**
     * Loads the online status for us.
     *
     * @return void
     */
    public function loadOnline()
    {
        $test = self::$FOGURLRequests->isAvailable($this->get('ip'), '0.1', 21, 'tcp');
        $this->set('online', array_shift($test));
    }
    /**
     * Loads the storage group for this node.
     *
     * @return void;
     */
    public function loadStoragegroup()
    {
        $this->set('storagegroup', new StorageGroup($this->get('storagegroupID')));
    }
    /**
     * Get the node failure.
     *
     * @param int $Host the host id
     *
     * @return object
     */
    public function getNodeFailure($Host)
    {
        Route::listem(
            'nodefailure',
            [
                'hostID' => $Host,
                'storagenodeID' => $this->get('id')
            ]
        );
        $Failures = json_decode(
            Route::getData()
        );
        foreach ($Failures->data as &$Failed) {
            $curr = self::niceDate();
            $prev = self::niceDate($Failed->failureTime);
            if ($curr < $prev) {
                return true;
            }
            unset($Failed);
        }
        return false;
    }
    /**
     * Get's the storage node snapins, logfiles, and images
     * in a single multi call rather than three individual calls.
     *
     * @param string $item The item to get.
     *
     * @return void
     */
    private function _getData($item)
    {
        if (!$this->get('online')) {
            return;
        }
        $logPaths = [
            '/var/log/apache2',
            '/var/log/fog',
            '/var/log/httpd',
            '/var/log/nginx',
            '/var/log/php*',
        ];
        $items = [
            'images' => urlencode($this->get('path')),
            'snapinfiles' => urlencode($this->get('snapinpath')),
            'logfiles' => urlencode(implode(':', $logPaths))
        ];
        if (!array_key_exists($item, $items)) {
            return;
        }
        $imagePaths = [$this->get('path')];
        $snapinPaths = [$this->get('snapinpath')];
        $validPaths = array_merge(
            $imagePaths,
            $snapinPaths,
            $logPaths
        );
        $pathTest = preg_grep(
            '#'
            . str_replace(':', '|', urldecode($items[$item]))
            . '#',
            $validPaths
        );
        if (count($pathTest ?: []) < 1) {
            return [];
        }
        $url = sprintf(
            '%s://%s/fog/status/getfiles.php?path=%s',
            self::$httpproto,
            $this->get('ip'),
            rtrim($items[$item], DS)
        );
        $response = self::$FOGURLRequests->process(
            $url,
            'GET',
            null,
            false,
            false,
            false,
            false,
            false,
            ['X-Requested-With: XMLHttpRequest']
        );
        return preg_grep(
            '#dev|postdownloadscripts|ssl#',
            json_decode($response[0], true),
            PREG_GREP_INVERT
        );
    }
    /**
     * Loads the snapins available on this node.
     *
     * @return void
     */
    public function getSnapinfiles()
    {
        $response = $this->_getData('snapinfiles');
        $values = array_map('basename', (array)$response);
        $this->set('snapinfiles', $values);
    }
    /**
     * Loads the images available on this node.
     *
     * @return void
     */
    public function getImages()
    {
        $response = $this->_getData('images');
        $values = array_map('basename', (array)$response);
        Route::ids(
            'storagenode',
            ['path' => $values]
        );
        $values = json_decode(Route::getData(), true);
        $this->set('images', $values);
    }
    /**
     * Gets this node's load of clients.
     *
     * @return float
     */
    public function getClientLoad()
    {
        if ($this->getUsedSlotCount() + $this->getQueuedSlotCount() < 0) {
            return 0;
        }
        if ($this->get('maxClients') < 1) {
            return 0;
        }
        return (float) (
            $this->getUsedSlotCount() + $this->getQueuedSlotCount()
        ) / $this->get('maxClients');
    }
    /**
     * Load used tasks.
     *
     * @return void
     */
    protected function loadUsedtasks()
    {
        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        if (count($used) < 1) {
            $used = [
                TaskType::DEPLOY,
                TaskType::DEPLOY_CAPTURE,
                TaskType::DEPLOY_NO_SNAPINS
            ];
        }
        $this->set('usedtasks', $used);
    }
    /**
     * Gets this node's used count.
     *
     * @return int
     */
    public function getUsedSlotCount()
    {
        $countTasks = 0;
        $usedtasks = $this->get('usedtasks');
        $findTasks = [
            'stateID' => self::getProgressState(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks,
        ];
        Route::count(
            'task',
            $findTasks
        );
        $countTasks = json_decode(Route::getData());
        $countTasks = $countTasks->total;
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $find = [
            'stateID' => self::getProgressState(),
            'typeID' => TaskType::MULTICAST
        ];
        Route::ids(
            'task',
            $find
        );
        $taskids = json_decode(Route::getData(), true);
        $find = ['taskID' => $taskids];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'msID'
        );
        $msids = json_decode(Route::getData(), true);
        $countTasks += count($msids);

        return $countTasks;
    }
    /**
     * Gets the queued hosts on this node.
     *
     * @return int
     */
    public function getQueuedSlotCount()
    {
        $countTasks = 0;
        $usedtasks = $this->get('usedtasks');
        $findTasks = [
            'stateID' => self::getQueuedStates(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks
        ];
        Route::count(
            'task',
            $findTasks
        );
        $countTasks = json_encode(Route::getData());
        $countTasks = isset($countTasks->total) ? $countTasks->total : 0;
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $find = [
            'stateID' => self::getQueuedStates(),
            'typeID' => TaskType::MULTICAST
        ];
        Route::ids(
            'task',
            $find
        );
        $taskids = json_decode(Route::getData(), true);
        $find = ['taskID' => $taskids];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'msID'
        );
        $msids = json_decode(Route::getData(), true);
        $countTasks += count($msids);

        return $countTasks;
    }
}
