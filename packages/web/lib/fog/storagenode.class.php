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
        'snapinpath' => 'ngmSnapinPath',
        'sslpath' => 'ngmSSLPath',
        'ip' => 'ngmHostname',
        'maxClients' => 'ngmMaxClients',
        'user' => 'ngmUser',
        'pass' => 'ngmPass',
        'key' => 'ngmKey',
        'interface' => 'ngmInterface',
        'bandwidth' => 'ngmBandwidthLimit',
        'webroot' => 'ngmWebroot'
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
        $loaders = [
            'snapinfiles' => 'getSnapinfiles',
            'images' => 'getImages',
            'logfiles' => 'getLogfiles'
        ];
        if (in_array($key, array_keys($loaders))
            && !$this->isLoaded($key)
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
        $test = self::$FOGURLRequests->isAvailable($this->get('ip'), 1);
        $this->set('online', array_shift($test));
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
                'nfHostID' => $Host,
                'nfNodeID' => $this->get('id')
            ]
        );
        $Failures = json_decode(
            Route::getData()
        );
        foreach ($Failures as &$Failed) {
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
     * Loads the logfiles available on this node.
     *
     * @return void
     */
    public function getLogfiles()
    {
        $url = sprintf(
            '%s://%s/fog/status/getfiles.php?path=%s',
            self::$httpproto,
            $this->get('ip'),
            '%s'
        );
        $paths = [
            '/var/log/nginx',
            '/var/log/httpd',
            '/var/log/apache2',
            '/var/log/fog',
            '/var/log/php7.0-fpm',
            '/var/log/php-fpm',
            '/var/log/php5-fpm',
            '/var/log/php5.6-fpm',
        ];
        $url = sprintf(
            $url,
            urlencode(implode(':', $paths))
        );
        $paths = self::$FOGURLRequests->process($url);
        foreach ((array)$paths as $index => &$response) {
            $tmppath = self::fastmerge(
                (array)$tmppath,
                (array)json_decode($response, true)
            );
            unset($response);
        }
        $paths = array_filter($tmppath);
        $paths = array_values($paths);
        natcasesort($paths);
        $this->set('logfiles', $paths);
    }
    /**
     * Get's the storage node snapins, logfiles, and images
     * in a single multi call rather than three individual calls.
     *
     * @return void
     */
    private function _getData()
    {
        $url = sprintf(
            '%s://%s/fog/status/getfiles.php',
            self::$httpproto,
            $this->get('ip')
        );
        $keys = [
            'images' => urlencode($this->get('path')),
            'snapinfiles' => urlencode($this->get('snapinpath'))
        ];
        $urls = [];
        foreach ((array)$keys as $key => &$data) {
            $urls[] = sprintf(
                '%s?path=%s',
                $url,
                $data
            );
            unset($data);
        }
        $paths = self::$FOGURLRequests->process($urls);
        $pat = '#dev|postdownloadscripts|ssl#';
        $values = [];
        $index = 0;
        foreach ((array)$keys as $key => &$data) {
            $values = $paths[$index];
            unset($paths[$index]);
            $values = json_decode($values, true);
            $values = array_map('basename', (array)$values);
            $values = preg_replace(
                $pat,
                '',
                $values
            );
            $values = array_unique(
                (array)$values
            );
            $values = array_filter(
                (array)$values
            );
            if ($key === 'images') {
                $values = self::getSubObjectIDs(
                    'Image',
                    ['path' => $values]
                );
            }
            $this->set($key, $values);
            $index++;
            unset($data);
        }
        unset($values, $paths);
    }
    /**
     * Loads the snapins available on this node.
     *
     * @return void
     */
    public function getSnapinfiles()
    {
        $this->_getData();
    }
    /**
     * Loads the images available on this node.
     *
     * @return void
     */
    public function getImages()
    {
        $this->_getData();
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
        return (float) (
            $this->getUsedSlotCount()
            +
            $this->getQueuedSlotCount()
        )
        /
        $this->get('maxClients');
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
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $MulticastCount = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            [
                'taskID' => self::getSubObjectIDs(
                    'Task',
                    [
                        'stateID' => self::getProgressState(),
                        'typeID' => 8,
                    ]
                ),
            ],
            'msID'
        );
        $countTasks += count($MulticastCount);

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
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $MulticastCount = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            [
                'taskID' => self::getSubObjectIDs(
                    'Task',
                    [
                        'stateID' => self::getQueuedStates(),
                        'typeID' => 8
                    ]
                ),
            ],
            'msID'
        );
        $countTasks += count($MulticastCount);

        return $countTasks;
    }
}
