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
        $this->set('usedtasks', (array)$used);
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
            (array)self::getSubObjectIDs(
                'StorageNode',
                array('storagegroupID' => $this->get('id')),
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
        $this->set('enablednodes', (array)$nodeids);
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
        $count = 0;
        $find = array(
            'storagegroupID' => $this->get('id'),
            'id' => $this->get('allnodes'),
            'isEnabled' => 1
        );
        foreach ((array)self::getClass('StorageNodeManager')
            ->find($find) as &$node
        ) {
            $count += $node->getUsedSlotCount();
        }
        return $count;
    }
    /**
     * Returns total queued slots
     *
     * @return int
     */
    public function getQueuedSlots()
    {
        $count = 0;
        $find = array(
            'storagegroupID' => $this->get('id'),
            'id' => $this->get('allnodes'),
            'isEnabled' => 1
        );
        foreach ((array)self::getClass('StorageNodeManager')
            ->find($find) as &$node
        ) {
            $count += $node->getQueuedSlotCount();
        }
        return $count;
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
        $getter = 'enablednodes';
        if (count($this->get('enablednodes')) < 1) {
            $getter = 'allnodes';
        }
        $masternode = null;
        $find = [
            'id' => $this->get($getter),
            'isEnabled' => 1,
            'isMaster' => 1
        ];
        Route::listem(
            'storagenode',
            'name',
            false,
            $find
        );
        $Nodes = json_decode(
            Route::getData()
        );
        foreach ($Nodes->storagenodes as &$Node) {
            if (!$Node->online) {
                continue;
            }
            if ($masternode == null) {
                $masternode = $Node->id;
                break;
            }
            unset($Node);
        }
        if (empty($masternode)) {
            $masternode = @min($this->get($getter));
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
        $find = [
            'storagegroupID' => $this->get('id'),
            'id' => $this->get($getter),
            'isEnabled' => 1
        ];
        Route::listem(
            'storagenode',
            'name',
            false,
            $find
        );
        $Nodes = json_decode(
            Route::getData()
        );
        $Nodes = $Nodes->storagenodes;
        foreach ($Nodes as &$Node) {
            if (!$Node->online) {
                continue;
            }
            if ($Node->maxClients < 1) {
                continue;
            }
            if ($winner == null
                || $Node->clientload < $winner->clientload
            ) {
                $winner = $Node;
            }
            unset($Node);
        }
        if (empty($winner)) {
            $winner = @min($this->get($getter));
        } else {
            $winner = $winner->id;
        }
        return new StorageNode($winner);
    }
}
