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
    protected $databaseFields = array(
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
        'webroot' => 'ngmWebroot'
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'ip',
        'path',
        'ftppath',
        'user',
        'pass'
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'images',
        'snapinfiles',
        'logfiles',
        'usedtasks',
        'storagegroup',
        'online'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'StorageGroup' => array(
            'id',
            'storagegroupID',
            'storagegroup'
        )
    );
    /**
     * Gets an item from the key sent, if no key all object data is returned.
     *
     * @param mixed $key the key to get
     *
     * @return object
     */
    public function get($key = '')
    {
        $pathvars = array(
            'path',
            'ftppath',
            'snapinpath',
            'sslpath',
            'webroot'
        );
        if (in_array($key, $pathvars)) {
            if (trim(parent::get($key)) === '/') {
                return parent::get($key);
            }
            return rtrim(parent::get($key), '/');
        }
        if ($key === 'pass') {
            return htmlspecialchars_decode(parent::get($key), ENT_QUOTES | ENT_HTML401);
        }
        $loaders = array(
            'snapinfiles' => 'getSnapinfiles',
            'images' => 'getImages',
            'logfiles' => 'getLogfiles'
        );
        if (in_array($key, array_keys($loaders))
            && !$this->isLoaded($key)
        ) {
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
        foreach ((array)self::getClass('NodeFailureManager')
            ->find(
                array(
                    'storagenodeID' => $this->get('id'),
                    'hostID' => $Host,
                )
            ) as &$Failed
        ) {
            $curr = self::niceDate();
            $prev = $Failed->get('failureTime');
            $prev = self::niceDate($prev);
            if ($curr < $prev) {
                return $Failed;
            }
            unset($Failed);
        }

        return $Failed;
    }
    /**
     * Loads the logfiles available on this node.
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
        natcasesort($paths);
        $this->set('logfiles', (array)$paths);
    }
    /**
     * Get's the storage node snapins, logfiles, and images
     * in a single multi call rather than three individual calls.
     *
     * @return array
     */
    private function _getData($item)
    {
        if (!$this->get('online')) {
            return;
        }
        $logpaths = array(
            '/var/log/nginx',
            '/var/log/httpd',
            '/var/log/apache2',
            '/var/log/fog',
            '/var/log/php7.0-fpm',
            '/var/log/php-fpm',
            '/var/log/php5-fpm',
            '/var/log/php5.6-fpm',
        );
        $items = array(
            'images' => urlencode($this->get('path')),
            'snapinfiles' => urlencode($this->get('snapinpath')),
            'logfiles' => urlencode(implode(':', $logpaths))
        );
        if (!array_key_exists($item, $items)) {
            return;
        }
        $url = sprintf(
            '%s://%s/fog/status/getfiles.php?path=%s',
            self::$httpproto,
            $this->get('ip'),
            $items[$item]
        );
        $response = self::$FOGURLRequests->process($url);
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
        $values = self::getSubObjectIDs(
            'Image',
            array('path' => $values)
        );
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
        return (float)(
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
            $used = array(
                1,
                15,
                17,
            );
        }
        $this->set('usedtasks', (array)$used);
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
        $findTasks = array(
            'stateID' => self::getProgressState(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks,
        );
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $MulticastCount = self::getSubObjectIDs(
            'MulticastSession',
            array(
                        'stateID' => self::getProgressState()
                ),
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
        $findTasks = array(
            'stateID' => self::getQueuedStates(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks,
        );
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $MulticastCount = self::getSubObjectIDs(
            'MulticastSession',
            array('stateID' => self::getQueuedStates()),
            'msID'
        );
        $countTasks += count($MulticastCount);
        return $countTasks;
    }
}
