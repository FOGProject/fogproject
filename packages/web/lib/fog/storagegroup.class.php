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
     * Stores the total count and returns if it is already
     * set.
     *
     * @var array
     */
    private static $_tot = array();
    /**
     * Stores the queued count and returns if it is already
     * set.
     *
     * @var array
     */
    private static $_queued = array();
    /**
     * Stores the used count and returns if it is already
     * set.
     *
     * @var array
     */
    private static $_used = array();
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
        if (isset(self::$_used['tot'])) {
            return (int)self::$_used['tot'];
        }
        return (int)self::$_used['tot'] = self::getClass('TaskManager')
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
        if (isset(self::$_queued['tot'])) {
            return (int)self::$_queued['tot'];
        }
        return (int)self::$_queued['tot'] = self::getClass('TaskManager')
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
        if (isset(self::$_tot['tot'])) {
            return (int)self::$_tot['tot'];
        }
        return (int)self::$_tot['tot'] = self::getSubObjectIDs(
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
}
