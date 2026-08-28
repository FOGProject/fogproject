<?php
/**
 * Storage Group object
 *
 * PHP version 7.4+
 *
 * @category StorageGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLV3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Router\Route;

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
        // Same unreachable guard as StorageNode::loadUsedtasks() -- see the
        // comment there. 1/15/17 are DEPLOY, DEPLOY_DEBUG and
        // DEPLOY_NO_SNAPINS, matching the setting's shipped default.
        $used = array_values(
            array_filter(
                explode(',', (string) self::getSetting('FOG_USED_TASKS')),
                static function ($taskTypeId) {
                    return trim($taskTypeId) !== '';
                }
            )
        );
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
     * Picks a node without consulting the availability probe.
     *
     * The probe is a TCP connect to the node's ssh or web port. Neither says
     * anything about whether the image share works, so it is useful for
     * choosing BETWEEN nodes and must not be what decides an admin may not
     * create a task at all. 1.5 fell back to min($nodes) here for exactly
     * that reason; 1.6 threw instead, which turned an unprobeable node -- a
     * NAS with ssh switched off is the common one -- into "No master nodes
     * available" and no capture task (forums 18217).
     *
     * Unlike 1.5 this is not silent. A node picked here is a node FOG could
     * not reach on either port, so the task may well fail later; saying so
     * once, with the group and node named, is the difference between a
     * puzzling failure and an obvious one.
     *
     * @param array  $ids    candidate node ids
     * @param string $reason what the probe failed to find
     *
     * @return int|null the chosen node id
     */
    private function _fallbackNode($ids, $reason)
    {
        $ids = array_values(array_filter(array_map('intval', (array)$ids)));
        if (count($ids) < 1) {
            return null;
        }
        $chosen = min($ids);
        // error_log, not self::error(). The latter writes only when its
        // logging setting is switched on, and returns without printing
        // anything at all on an ajax request -- which is how tasking runs.
        // A warning nobody sees is the silence this exists to end, so it goes
        // to the web server's log unconditionally.
        error_log(
            sprintf(
                'FOG storage node availability: %s. Group %s (id %d); '
                . 'node id %d chosen anyway. The probe is a reachability '
                . 'test, not a test of the image share, so tasking '
                . 'continues -- if the task fails, check that this node is '
                . 'reachable.',
                $reason,
                $this->get('name'),
                (int)$this->get('id'),
                $chosen
            )
        );

        return $chosen;
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
        $masterIds = [];
        foreach ($StorageNodes->data as $StorageNode) {
            $masterIds[] = (int)$StorageNode->id;
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
        $masterId = empty($masternode) ? null : (int)$masternode->id;
        if ($masterId === null) {
            // Enabled masters first: an unreachable master is still the node
            // this group is configured to capture to, so preferring it over
            // an arbitrary member keeps the degraded answer the same shape as
            // the healthy one.
            $masterId = $this->_fallbackNode(
                count($masterIds) > 0 ? $masterIds : $this->get($getter),
                count($masterIds) > 0
                    ? _('no enabled master node answered the probe')
                    : _('the group has no enabled master node')
            );
        }
        $StorageNode = empty($masterId) ? null : new StorageNode($masterId);
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
        $winnerId = empty($winner) ? null : (int)$winner->id;
        if ($winnerId === null) {
            $winnerId = $this->_fallbackNode(
                $this->get($getter),
                _('no node in the group answered the probe')
            );
        }
        $StorageNode = empty($winnerId) ? null : new StorageNode($winnerId);
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
