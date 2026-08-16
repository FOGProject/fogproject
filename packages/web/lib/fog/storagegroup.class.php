<?php
/**
 * Storage Group object
 *
 * PHP version 5
 *
 * @category StorageGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLV3
 * @link     https://fogproject.org
 */
/**
 * Storage Group object
 *
 * @category StorageGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLV3
 * @link     https://fogproject.org
 */
class StorageGroup extends FOGController
{
    /**
     * The table for the group info.
     *
     * @var string
     */
    protected $databaseTable = 'nfsGroups';
    /**
     * The database fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ngID',
        'name' => 'ngName',
        'description' => 'ngDesc',
        'trustedcidrs' => 'ngTrustedCIDRs'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'allnodes',
        'enablednodes',
        'usedtasks',
        'images',
        'snapins'
    ];

    protected $sqlQueryStr = "SELECT `%s`,SUM(`nfsGroupMembers`.`ngmMaxClients`)
        AS `totalclients`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'
        %s
        GROUP BY `nfsGroups`.`ngName`
        %s
        %s";
    /**
     * No GROUP BY, and COUNT(DISTINCT), unlike the row query above.
     *
     * The row query needs the GROUP BY so SUM(ngmMaxClients) totals per group.
     * Carrying it into the COUNT queries made them return one row PER GROUP,
     * each holding that group's own member count -- and
     * FOGManagerController::complex() reads $resFilterLength[0][0], the first
     * row. So a server with three storage groups reported recordsTotal=1
     * (the first group's member count), and DataTables' Scroller sizes its
     * virtual scroll extent as recordsDisplay * rowHeight: the grid rendered
     * every row but collapsed to a single row's height, with the rest reachable
     * only by scrolling inside it. Reported on the forum, topic 18217.
     *
     * DISTINCT because the LEFT JOIN multiplies a group by its member count,
     * so a plain COUNT would over-report a group holding several nodes. Counted
     * on the primary key rather than ngName only because the template's first
     * placeholder is the primary key; nfsGroups.ngName carries a UNIQUE index,
     * so the two agree with the row query's GROUP BY either way.
     *
     * The JOIN stays: %s below is the caller's WHERE, which may reference
     * nfsGroupMembers columns when searching.
     */
    protected $sqlFilterStr = "SELECT COUNT(DISTINCT `%s`)
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'
        %s";
    protected $sqlTotalStr = "SELECT COUNT(DISTINCT `%s`)
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'";
    /**
     * Load used tasks
     *
     * @return void
     */
    protected function loadUsedtasks()
    {
        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        if (count($used) < 1) {
            $used = [
                1,
                15,
                17
            ];
        }
        $this->set('usedtasks', $used);
    }
    /**
     * Loads all the images in the group.
     *
     * @return void
     */
    protected function loadImages()
    {
        $find = ['storagegroupID' => $this->get('id')];
        $imageIDs = Route::getIds(
            'imageassociation',
            $find,
            'imageID'
        );
        $this->set('images', (array)$imageIDs);
    }
    /**
     * Loads all the snapins in the group.
     *
     * @return void
     */
    protected function loadSnapins()
    {
        $find = ['storagegroupID' => $this->get('id')];
        $snapinIDs = Route::getIds(
            'snapingroupassociation',
            $find,
            'snapinID'
        );
        $this->set('snapins', (array)$snapinIDs);
    }
    /**
     * Loads all the nodes in the group
     *
     * @return void
     */
    protected function loadAllnodes()
    {
        $find = ['storagegroupID' => $this->get('id')];
        $allnodes = Route::getIds(
            'storagenode',
            $find
        );
        $this->set('allnodes', (array)$allnodes);
    }
    /**
     * Loads the enabled nodes in the group
     *
     * @return void
     */
    protected function loadEnablednodes()
    {
        $find = [
            'storagegroupID' => $this->get('id'),
            'id' => $this->get('allnodes'),
            'isEnabled' => 1
        ];
        $nodeids = [];
        $testurls = [];
        $StorageNodes = Route::getList(
            'storagenode',
            $find
        );
        foreach ($StorageNodes as &$StorageNode) {
            if ($StorageNode->maxClients < 1) {
                continue;
            }
            $nodeids[] = $StorageNode->id;
            unset($node);
        }
        $this->set('enablednodes', $nodeids);
    }
    /**
     * Returns total available slots
     *
     * @return int
     */
    public function getTotalAvailableSlots()
    {
        $tot = (
            $this->getTotalSupportedClients()
            - $this->getUsedSlots()
            - $this->getQueuedSlots()
        );
        if ($tot < 1) {
            return 0;
        }
        return $tot;
    }
    /**
     * Returns total used / in tasking slots
     *
     * @return int
     */
    public function getUsedSlots()
    {
        return Route::getCount(
            'task',
            [
                'stateID' => self::getProgressState(),
                'storagenodeID' => $this->get('enablednodes'),
                'typeID' => $this->get('usedtasks')
            ]
        );
    }
    /**
     * Returns total queued slots
     *
     * @return int
     */
    public function getQueuedSlots()
    {
        return Route::getCount(
            'task',
            [
                'stateID' => self::getQueuedStates(),
                'storagenodeID' => $this->get('enablednodes'),
                'typeID' => $this->get('usedtasks')
            ]
        );
    }
    /**
     * Returns total supported clients
     *
     * @return int
     */
    public function getTotalSupportedClients()
    {
        $find = ['id' => $this->get('enablednodes')];
        $maxClients = Route::getIds(
            'storagenode',
            $find,
            'maxClients'
        );
        return array_sum($maxClients);
    }
    /**
     * Get's the groups master storage node
     *
     * @return object
     */
    public function getMasterStorageNode()
    {
        $getter = 'enablednodes';
        if (count($this->get('enablednodes')) < 1) {
            $getter = 'allnodes';
        }
        $masternode = null;
        Route::listem(
            'storagenode',
            [
                'id' => $this->get($getter),
                'isEnabled' => 1,
                'isMaster' => 1
            ]
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        // $StorageNodes stays the paginated envelope on purpose: it is handed
        // to MASTER_STORAGE_NODE below by reference, so its shape is surface a
        // third-party plugin may already read. Only the per-row fetch moves --
        // getItem() answers null for a node deleted between the list and the
        // fetch, where indiv() ended the response outright. Refs ADR 0011.
        foreach ($StorageNodes->data as $StorageNode) {
            $StorageNode = Route::getItem('storagenode', $StorageNode->id);
            if (!$StorageNode || !$StorageNode->online) {
                continue;
            }
            if ($masternode == null) {
                $masternode = $StorageNode;
                break;
            }
            unset($StorageNode);
        }
        $StorageNode = empty($masternode) ? null : new StorageNode($masternode->id);
        self::$HookManager->processEvent(
            'MASTER_STORAGE_NODE',
            [
                'StorageGroup' => &$this,
                'StorageNodes' => &$StorageNodes,
                'StorageNode' => &$StorageNode
            ]
        );
        if (empty($StorageNode) || !$StorageNode->isValid()) {
            throw new \Exception(_('No master nodes available'));
        }
        return $StorageNode;
    }
    /**
     * Get's the optimal storage node
     *
     * @return object
     */
    public function getOptimalStorageNode()
    {
        $getter = 'enablednodes';
        if (count($this->get('enablednodes')) < 1) {
            $getter = 'allnodes';
        }
        $winner = null;
        Route::listem(
            'storagenode',
            ['id' => $this->get($getter)]
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        // Envelope kept, per the note in getMasterStorageNode(): this one is
        // handed to OPTIMAL_STORAGE_NODE by reference.
        foreach ($StorageNodes->data as &$StorageNode) {
            $StorageNode = Route::getItem('storagenode', $StorageNode->id);
            if (!$StorageNode || !$StorageNode->online) {
                continue;
            }
            if (!$StorageNode->isEnabled) {
                continue;
            }
            if ($StorageNode->maxClients < 1) {
                continue;
            }
            if ($winner == null
                || $StorageNode->clientload < $winner->clientload
            ) {
                $winner = $StorageNode;
            }
            unset($StorageNode);
        }
        unset($StorageNode);
        $StorageNode = empty($winner) ? null : new StorageNode($winner->id);
        self::$HookManager->processEvent(
            'OPTIMAL_STORAGE_NODE',
            [
                'StorageGroup' => &$this,
                'StorageNodes' => &$StorageNodes,
                'StorageNode' => &$StorageNode
            ]
        );
        if (empty($StorageNode) || !$StorageNode->isValid()) {
            throw new \Exception(_('No nodes available'));
        }
        return $StorageNode;
    }
    /**
     * Adds nodes to this storage group
     *
     * @param array $addArray the nodes to add
     *
     * @return object
     */
    public function addNode($addArray)
    {
        self::getClass('StorageNodeManager')
            ->update(
                ['id' => $addArray],
                '',
                ['storagegroupID' => $this->get('id')]
            );
        $this->loadAllnodes();
        $this->loadEnabledNodes();
        $this->loadUsedtasks();
        return $this;
    }
    /**
     * Removes nodes from this storage group
     *
     * @param array $removeArray the nodes to remove
     *
     * @return object
     */
    public function removeNode($removeArray)
    {
        self::getClass('StorageNodeManager')
            ->update(
                [
                    'id' => $removeArray,
                    'storagegroupID' => $this->get('id')
                ],
                '',
                [
                    'storagegroupID' => 0
                ]
            );
        $this->loadAllnodes();
        $this->loadEnabledNodes();
        $this->loadUsedtasks();
        return $this;
    }
    /**
     * Adds images to this object
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addImage($addArray)
    {
        return $this->addRemItem(
            'images',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes images from this object
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeImage($removeArray)
    {
        return $this->addRemItem(
            'images',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds snapins to this object
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addSnapin($addArray)
    {
        return $this->addRemItem(
            'snapins',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes snapins from this object
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeSnapin($removeArray)
    {
        return $this->addRemItem(
            'snapins',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Saves the storage group elements.
     *
     * @return object
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        // No assocSetter('StorageGroup', 'storagenode') here: it derived the
        // plural 'storagenodes', which is not in $additionalFields and has no
        // loader, so the key could never hold data and the call was dead.
        // Node membership is managed by addNode()/removeNode(), which write
        // StorageNodeManager directly and never touch that key.
        return $this
            ->assocSetter('Image', 'image')
            ->assocSetter('SnapinGroup', 'snapin')
            ->load();
    }
}
