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
    protected $databaseFields = array(
        'id' => 'ngID',
        'name' => 'ngName',
        'description' => 'ngDesc',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'allnodes',
        'enablednodes',
        'usedtasks',
    );

    protected $sqlQueryStr = "SELECT `%s`,SUM(`nfsGroupMembers`.`ngmMaxClients`) AS `totalclients`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'
        %s
        GROUP BY `nfsGroups`.`ngName`
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`),SUM(`nfsGroupMembers`.`ngmMaxClients`) AS `totalclients`
        FROM `%s`
        LEFT OUTER JOIN `nfsGroupMembers`
        ON `nfsGroups`.`ngID` = `nfsGroupMembers`.`ngmGroupID`
        AND `nfsGroupMembers`.`ngmIsEnabled` = '1'
        %s
        GROUP BY `nfsGroups`.`ngName`";
    protected $sqlTotalStr = "SELECT COUNT(`%s`),SUM(`nfsGroupMembers`.`ngmMaxClients`) AS `totalclients`
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
            $used = array(
                1,
                15,
                17
            );
        }
        $this->set('usedtasks', $used);
    }
    /**
     * Loads all the nodes in the group
     *
     * @return void
     */
    protected function loadAllnodes()
    {
        $this->set(
            'allnodes',
            self::getSubObjectIDs(
                'StorageNode',
                array(
                    'storagegroupID' => $this->get('id'),
                ),
                'id'
            )
        );
    }
    /**
     * Loads the enabled nodes in the group
     *
     * @return void
     */
    protected function loadEnablednodes()
    {
        $find = array(
            'storagegroupID' => $this->get('id'),
            'id' => $this->get('allnodes'),
            'isEnabled' => 1
        );
        $nodeids = array();
        $testurls = array();
        foreach ((array)self::getClass('StorageNodeManager')
            ->find($find) as &$node
        ) {
            if ($node->get('maxClients') < 1) {
                continue;
            }
            $nodeids[] = $node->get('id');
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
                array(
                    'stateID' => self::getProgressState(),
                    'storagenodeID' => $this->get('enablednodes'),
                    'typeID' => $this->get('usedtasks'),
                )
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
                array(
                    'stateID' => self::getQueuedStates(),
                    'storagenodeID' => $this->get('enablednodes'),
                    'typeID' => $this->get('usedtasks'),
                )
            );
    }
    /**
     * Returns total supported clients
     *
     * @return int
     */
    public function getTotalSupportedClients()
    {
        return self::getSubObjectIDs(
            'StorageNode',
            array('id' => $this->get('enablednodes')),
            'maxClients',
            false,
            'AND',
            'name',
            false,
            'array_sum'
        );
    }
    /**
     * Get's the groups master storage node
     *
     * @return object
     */
    public function getMasterStorageNode()
    {
        $masternode = self::getSubObjectIDs(
            'StorageNode',
            array(
                'id' => $this->get('enablednodes'),
                'isMaster' => 1,
            )
        );
        $masternode = array_shift($masternode);
        if (!($masternode
            && is_numeric($masternode)
            && $masternode > 0)
        ) {
            $masternode = @min($this->get('enablednodes'));
        }
        if (!$masternode > 0) {
            $nodeids = self::getSubObjectIDs(
                'StorageNode',
                array(
                    'id' => $this->get('allnodes'),
                    'isEnabled' => 1,
                    'isMaster' => 1
                )
            );
            if (count($nodeids) < 1) {
                $nodeids = self::getSubObjectIDs(
                    'StorageNode',
                    array(
                        'id' => $this->get('allnodes'),
                        'isEnabled' => 1
                    )
                );
            }
            $masternode = @min($nodeids);
        }
        return new StorageNode($masternode);
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
        foreach ((array)self::getClass('StorageNodeManager')
            ->find(
                array('id' => $this->get($getter))
            ) as &$Node
        ) {
            if ($Node->get('maxClients') < 1) {
                continue;
            }
            if ($winner == null
                || $Node->getClientLoad() < $winner->getClientLoad()
            ) {
                $winner = $Node;
            }
            unset($Node);
        }
        if (empty($winner)) {
            $winner = new StorageNode(@min($this->get('enablednodes')));
        }
        return $winner;
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
                [
                    'id' => $addArray
                ],
                '',
                [
                    'storagegroupID' => $this->get('id')
                ]
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
}
