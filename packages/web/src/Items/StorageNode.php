<?php
/**
 * Storage node handler class.
 *
 * PHP version 7.4+
 *
 * @category StorageNode
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Router\Route;
use FOG\Util\FOGLogPaths;

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
     * stdClass property of Location URL
     *
     * @var string
     */
    public $location_url = '';
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
        'location_url',
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
    /**
     * ngID/ngName are deliberately NOT selected here, unlike the row query.
     *
     * These are pure aggregates with no GROUP BY, so selecting bare columns
     * alongside COUNT() is only legal while ONLY_FULL_GROUP_BY is off -- and it
     * is on by default in MySQL 5.7+ and 8. Where it is on, both queries error
     * outright, complex() reads a missing row 0 and the grid ends up with a
     * record count of zero: the same collapsed-to-one-row rendering the storage
     * GROUP count bug produced (see storagegroup.class.php), reached a
     * different way. Nothing consumed the two columns in any case -- only
     * [0][0], the count, is ever read.
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `nfsGroups`
        ON `nfsGroupMembers`.`ngmGroupID` = `nfsGroups`.`ngID`
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
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
        // FOG reaches a node two ways and both count as "up": ssh for the
        // transfers, and http(s) for everything _getData() asks for -- the
        // image, snapin and log file listings all come from
        // <proto>://<ip>/fog/status/getfiles.php. Probing only ssh reported a
        // node offline that FOG was in the middle of talking to over http,
        // which is what a NAS with ssh switched off looks like (forums
        // 18217). Try ssh first because it is the transport tasking needs,
        // then fall back rather than declaring the node dead on one port.
        //
        // -1 lets isAvailable resolve FOG_SSH_PORT; hardcoding 22 reported
        // every node offline whenever ssh was moved (forums 18210).
        //
        // One second, not 0.1. A tenth of a second is inside the noise for a
        // switched network and a NAS that has to wake a disk, so the probe
        // was answering "offline" for hosts that were merely unhurried. 1.5
        // used a second here and additionally floored anything below one.
        $ip = $this->get('ip');
        $test = self::$FOGURLRequests->isAvailable($ip, 1, -1);
        $online = array_shift($test);
        if (!$online) {
            $webPort = 'https' === self::$httpproto ? 443 : 80;
            $test = self::$FOGURLRequests->isAvailable($ip, 1, $webPort);
            $online = array_shift($test);
        }
        $this->set('online', $online);
    }
    /**
     * Load the location url for us.
     *
     * @return void
     */
    public function loadLocation_url()
    {
        $this->location_url = sprintf('%s://%s/%s', self::$httpproto, $this->get('ip'), $this->get('webroot'));
        $this->set('location_url', $this->location_url);
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
        $Failures = Route::getList(
            'nodefailure',
            [
                'hostID' => $Host,
                'storagenodeID' => $this->get('id')
            ]
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
        // Owned by FOGLogPaths, which getfiles.php reads too -- the two used
        // to be separate copies that had to agree, with nothing checking.
        $logPaths = FOGLogPaths::directories();
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
            false
        );
        // array_values, because preg_grep PRESERVES KEYS. Every entry it
        // filters out leaves a gap, and json_encode renders an array with
        // gaps as an OBJECT -- so `snapinfiles` reached API clients as
        // {"0":"a","2":"b"} whenever the node held anything matching the
        // filter, while `images` looked fine only because it is mapped
        // through Route::getIds() and rebuilt on the way out.
        return array_values(
            preg_grep(
                '#dev|postdownloadscripts|ssl#',
                json_decode($response[0], true) ?? [],
                PREG_GREP_INVERT
            )
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
        // 'image', not 'storagenode'. These are image DIRECTORY basenames
        // read off the node; a storagenode's own `path` is the share root
        // (/images) on every node FOG has ever installed, so the lookup
        // could never match and this field was always []. dev-branch still
        // carries the original getSubObjectIDs('Image', ...) -- the class
        // name was lost when this call moved to Route::getIds().
        $values = Route::getIds(
            'image',
            ['path' => $values]
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
        $countTasks = Route::getCount(
            'task',
            $findTasks
        );
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $find = [
            'stateID' => self::getProgressState(),
            'typeID' => TaskType::MULTICAST
        ];
        $taskids = Route::getIds(
            'task',
            $find
        );
        $find = ['taskID' => $taskids];
        $msids = Route::getIds(
            'multicastsessionassociation',
            $find,
            'msID'
        );
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
        $countTasks = Route::getCount(
            'task',
            $findTasks
        );
        $index = array_search(8, $usedtasks);
        if ($index === false) {
            return $countTasks;
        }
        $find = [
            'stateID' => self::getQueuedStates(),
            'typeID' => TaskType::MULTICAST
        ];
        $taskids = Route::getIds(
            'task',
            $find
        );
        $find = ['taskID' => $taskids];
        $msids = Route::getIds(
            'multicastsessionassociation',
            $find,
            'msID'
        );
        $countTasks += count($msids);

        return $countTasks;
    }
}
