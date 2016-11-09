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
        'pass',
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
            'webroot',
        );
        if (in_array($key, $pathvars)) {
            return rtrim(parent::get($key), '/');
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
        return  new StorageGroup($this->get('storagegroupID'));
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
        $Fails = self::getClass('NodeFailureManager')
            ->find(
                array(
                    'storagenodeID' => $this->get('id'),
                    'hostID' => $Host,
                )
            );
        foreach ((array) $Fails as &$Failed) {
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
        $url = sprintf(
            'http://%s/fog/status/getfiles.php?path=%s',
            $this->get('ip'),
            '%s'
        );
        $test = self::$FOGURLRequests->isAvailable($url);
        $test = array_shift($test);
        if (false === $test) {
            return;
        }
        $paths = array(
            '/var/log/nginx',
            '/var/log/httpd',
            '/var/log/apache2',
            '/var/log/fog',
            '/var/log/php7.0-fpm',
            '/var/log/php-fpm',
            '/var/log/php5-fpm',
            '/var/log/php5.6-fpm',
        );
        $urls = array();
        foreach ($paths as &$path) {
            $urls[] = sprintf(
                $url,
                urlencode($path)
            );
            unset($path);
        }
        unset($paths);
        $paths = self::$FOGURLRequests->process($urls);
        foreach ((array) $paths as $index => &$response) {
            $tmppath = array_merge(
                (array) $tmppath,
                (array) json_decode($response, true)
            );
            unset($response);
        }
        $paths = array_filter($tmppath);
        $paths = array_values($paths);
        natcasesort($paths);
        $this->set('logfiles', $paths);
    }
    /**
     * Loads the snapins available on this node.
     *
     * @return void
     */
    public function getSnapinfiles()
    {
        $url = sprintf(
            'http://%s/fog/status/getfiles.php?path=%s',
            $this->get('ip'),
            urlencode($this->get('snapinpath'))
        );
        $test = self::$FOGURLRequests->isAvailable($url);
        $test = array_shift($test);
        if (false === $test) {
            return;
        }
        $paths = self::$FOGURLRequests->process($url);
        $paths = array_shift($paths);
        $paths = json_decode($paths, true);
        $paths = array_map('basename', (array) $paths);
        $paths = preg_replace(
            '#dev|postdownloadscripts|ssl#',
            '',
            $paths
        );
        $paths = array_unique(
            (array) $paths
        );
        $paths = array_filter(
            (array) $paths
        );
        $this->set('snapinfiles', array_values($paths));
    }
    /**
     * Loads the snapins available on this node.
     *
     * @return void
     */
    public function getImages()
    {
        $url = sprintf(
            'http://%s/fog/status/getfiles.php?path=%s',
            $this->get('ip'),
            urlencode($this->get('path'))
        );
        $test = self::$FOGURLRequests->isAvailable($url);
        $test = array_shift($test);
        if (false === $test) {
            return;
        }
        $paths = self::$FOGURLRequests->process($url);
        $paths = array_shift($paths);
        $paths = json_decode($paths);
        $paths = array_map('basename', (array) $paths);
        $paths = preg_replace(
            '#dev|postdownloadscripts|ssl#',
            '',
            $paths
        );
        $paths = array_unique(
            (array) $paths
        );
        $paths = array_filter(
            (array) $paths
        );
        $ids = self::getSubObjectIDs(
            'Image',
            array('path' => $paths)
        );
        $this->set('images', $ids);
    }
    /**
     * Gets this node's load of clients.
     *
     * @return float
     */
    public function getClientLoad()
    {
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
            $used = array(
                1,
                15,
                17,
            );
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
        $findTasks = array(
            'stateID' => $this->getProgressState(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks,
        );
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $MulticastCount = self::getSubObjectIDs(
            'MulticastSessionsAssociation',
            array(
                'taskID' => self::getSubObjectIDs(
                    'Task',
                    array(
                        'stateID' => $this->getProgressState(),
                        'typeID' => 8,
                    )
                ),
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
            'stateID' => $this->getQueuedStates(),
            'storagenodeID' => $this->get('id'),
            'typeID' => $usedtasks,
        );
        $countTasks = self::getClass('TaskManager')->count($findTasks);
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $MulticastCount = self::getSubObjectIDs(
            'MulticastSessionsAssociation',
            array(
                'taskID' => self::getSubObjectIDs(
                    'Task',
                    array(
                        'stateID' => $this->getQueuedStates(),
                        'typeID' => 8,
                    )
                ),
            ),
            'msID'
        );
        $countTasks += count($MulticastCount);

        return $countTasks;
    }
}
