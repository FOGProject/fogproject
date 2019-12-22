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
        'description' => 'ngDesc'
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
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'
        %s
        GROUP BY `nfsGroups`.`ngName`";
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'
        GROUP BY `nfsGroups`.`ngName`";
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
        Route::ids(
            'imageassociation',
            $find,
            'imageID'
        );
        $imageIDs = json_decode(Route::getData(), true);
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
        Route::ids(
            'snapingroupassociation',
            $find,
            'snapinID'
        );
        $snapinIDs = json_decode(Route::getData(), true);
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
        Route::ids(
            'storagenode',
            $find
        );
        $allnodes = json_decode(Route::getData(), true);
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
        Route::listem(
            'storagenode',
            $find
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        foreach ($StorageNodes->data as &$StorageNode) {
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
        return self::getClass('TaskManager')
            ->count(
                [
                    'stateID' => self::getProgressState(),
                    'storagenodeID' => $this->get('enablednodes'),
                    'typeID' => $this->get('usedtasks'),
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
        return self::getClass('TaskManager')
            ->count(
                [
                    'stateID' => self::getQueuedStates(),
                    'storagenodeID' => $this->get('enablednodes'),
                    'typeID' => $this->get('usedtasks'),
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
        Route::ids(
            'storagenode',
            $find,
            'maxClients'
        );
        $maxClients = json_decode(Route::getData(), true);
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
        foreach ($StorageNodes->data as $StorageNode) {
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
            if (!$StorageNode->online) {
                continue;
            }
            if ($masternode == null) {
                $masternode = $StorageNode;
                break;
            }
            unset($StorageNode);
        }
        if (empty($masternode)) {
            throw new Exception(_('No master nodes available'));
        }
        return new StorageNode($masternode->id);
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
        foreach ($StorageNodes->data as &$StorageNode) {
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
            if (!$StorageNode->online) {
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
        if (empty($winner)) {
            throw new Exception(_('No nodes available'));
        }
        return new StorageNode($winner->id);
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
        parent::save();
        return $this
            ->assocSetter('StorageGroup', 'storagenode')
            ->assocSetter('Image', 'image')
            ->assocSetter('SnapinGroup', 'snapin')
            ->load();
    }
}
