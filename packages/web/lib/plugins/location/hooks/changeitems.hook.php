<?php
/**
 * Changes the elements we need.
 *
 * PHP version 5
 *
 * @category ChangeItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Changes the elements we need.
 *
 * @category ChangeItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ChangeItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'ChangeItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Location to Active Tasks';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'location';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'SNAPIN_NODE',
            [$this, 'storageNodeSetting']
        )->register(
            'SNAPIN_GROUP',
            [$this, 'storageGroupSetting']
        )->register(
            'BOOT_ITEM_NEW_SETTINGS',
            [$this, 'bootItemSettings']
        )->register(
            'BOOT_TASK_NEW_SETTINGS',
            [$this, 'storageGroupSetting']
        )->register(
            'HOST_NEW_SETTINGS',
            [$this, 'storageNodeSetting']
        )->register(
            'HOST_NEW_SETTINGS',
            [$this, 'storageGroupSetting']
        )->register(
            'BOOT_TASK_NEW_SETTINGS',
            [$this, 'storageNodeSetting']
        )->register(
            'CHECK_NODE_MASTERS',
            [$this, 'alterMasters']
        )->register(
            'CHECK_NODE_MASTER',
            [$this, 'makeMaster']
        );
    }
    /**
     * Sets up storage node.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function storageNodeSetting($arguments)
    {
        if (!$arguments['Host']->isValid()) {
            return;
        }
        $Locations = self::getClass('LocationAssociationManager')->find(
            ['hostID' => $arguments['Host']->get('id')]
        );
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            ['hostID' => $arguments['Host']->get('id')],
            'locationID'
        );
        $method = false;
        foreach ((array)self::getClass('LocationManager')
            ->find(['id' => $Locations]) as $Location
        ) {
            $Host =& $arguments['Host'];
            $Task = $Host->get('task');
            $TaskType =& $arguments['TaskType'];
            if ($Task->isValid()
                && ($Task->isCapture()
                || $Task->isMulticast())
            ) {
                $method = 'getMasterStorageNode';
            } elseif ($TaskType instanceof TaskType
                && $TaskType->isValid()
                && ($TaskType->isCapture()
                || $TaskType->isMulticast())
            ) {
                $method = 'getMasterStorageNode';
            }
            $StorageGroup = $Location
                ->getStorageGroup();
            if ($StorageGroup->isValid()) {
                if (!isset($arguments['snapin'])
                    || ($arguments['snapin'] === true
                    && self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') > 0)
                ) {
                    $arguments['StorageNode'] = $Location
                        ->getStorageNode();
                }
                if (!$method) {
                    continue;
                }
                $arguments['StorageNode'] = $Location
                    ->getStorageGroup()
                    ->{$method}();
            }
            unset($Location);
        }
    }
    /**
     * Sets up storage group.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function storageGroupSetting($arguments)
    {
        if (!$arguments['Host']->isValid()) {
            return;
        }
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            ['hostID' => $arguments['Host']->get('id')],
            'locationID'
        );
        foreach ((array)self::getClass('LocationManager')
            ->find(['id' => $Locations]) as &$Location
        ) {
            $StorageGroup = $Location
                ->getStorageGroup();
            if (!$StorageGroup->isValid()) {
                continue;
            }
            $arguments['StorageGroup'] = $StorageGroup;
            unset($Location);
        }
    }
    /**
     * Sets up boot item information.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function bootItemSettings($arguments)
    {
        if (!$arguments['Host']->isValid()) {
            return;
        }
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            ['hostID' => $arguments['Host']->get('id')],
            'locationID'
        );
        $Locations = self::getSubObjectIDs(
            'Location',
            ['id' => $Locations]
        );
        $find = [
            'hostID' => $arguments['Host']->get('id'),
            'locationID' => $Locations
        ];
        foreach ((array)self::getClass('LocationAssociationManager')
            ->find($find, 'AND', 'id') as $Location
        ) {
            if (!$Location->isTFTP()) {
                continue;
            }
            $StorageNode = $Location
                ->getLocation()
                ->getStorageNode();
            if (!$StorageNode->isValid()) {
                continue;
            }
            $ip = $StorageNode->get('ip');
            if (!isset($memtest)) {
                $memtest = $arguments['memtest'];
            }
            if (!isset($memdisk)) {
                $memdisk = $arguments['memdisk'];
            }
            if (!isset($bzImage)) {
                $bzImage = $arguments['bzImage'];
            }
            if (!isset($initrd)) {
                $initrd = $arguments['initrd'];
            }
            $arguments['webserver'] = $ip;
            $arguments['memdisk'] = "http://${ip}/fog/service/ipxe/$memdisk";
            $arguments['memtest'] = "http://${ip}/fog/service/ipxe/$memtest";
            $arguments['bzImage'] = "http://${ip}/fog/service/ipxe/$bzImage";
            $arguments['imagefile'] = "http://${ip}/fog/service/ipxe/$initrd";
            unset($Location);
        }
    }
    /**
     * Alters master nodes.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function alterMasters($arguments)
    {
        if (!$arguments['FOGServiceClass'] instanceof MulticastManager) {
            return;
        }
        $storagenodeIDs = self::getSubObjectIDs(
            'Location',
            '',
            'storagenodeID'
        );
        $storagenodeIDs = self::fastmerge(
            (array) $storagenodeIDs,
            (array) $arguments['MasterIDs']
        );
        $storagenodeIDs = array_filter($storagenodeIDs);
        $storagenodeIDs = array_unique($storagenodeIDs);
        $arguments['StorageNodes'] = self::getClass('StorageNodeManager')
            ->find(['id' => $storagenodeIDs]);
        foreach ($arguments['StorageNodes'] as &$StorageNode) {
            if (!$StorageNode->isValid()) {
                continue;
            }
            if (!$StorageNode->get('isMaster')) {
                $StorageNode->set('isMaster', 1);
            }
            unset($StorageNode);
        }
    }
    /**
     * Makes master nodes.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function makeMaster($arguments)
    {
        if (!$arguments['FOGServiceClass'] instanceof MulticastTask) {
            return;
        }
        $arguments['StorageNode']->set('isMaster', 1);
    }
}
