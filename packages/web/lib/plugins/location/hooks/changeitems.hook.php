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
            [$this, 'storageNodeSetting']
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
        Route::listem(
            'locationassociation',
            ['hostID' => $arguments['Host']->get('id')]
        );
        $LocationAssocs = json_decode(
            Route::getData()
        );
        $Task = $arguments['Host']->get('task');
        $TaskType = $arguments['TaskType'] ?? null;
        $method = false;
        foreach ($LocationAssocs->data as &$LocationAssoc) {
            $Location = self::getClass('Location', $LocationAssoc->locationID);
            if (!$Location->isValid()) {
                continue;
            }
            if ($Task->isValid()
                && ($Task->isCapture() || $Task->isMulticast())
            ) {
                $method = 'getMasterStorageNode';
            } elseif ($TaskType instanceof TaskType
                && $TaskType->isValid()
                && ($TaskType->isCapture() || $TaskType->isMulticast())
            ) {
                $method = 'getMasterStorageNode';
            }
            $StorageGroup = $Location->getStorageGroup();
            if ($StorageGroup->isValid()) {
                if (!isset($arguments['snapin'])
                    || ($arguments['snapin'] === true
                    && self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') > 0)
                ) {
                    $arguments['StorageNode'] = $Location
                        ->getStorageNode();
                    $arguments['StorageNode']->{"location_url"} = sprintf(
                        '%s://%s/%s',
                        $Location->get('protocol') ?: self::$httpproto,
                        $arguments['StorageNode']->get('ip'),
                        $arguments['StorageNode']->get('webroot')
                    );
                }
                if (!$method) {
                    continue;
                }
                $arguments['StorageNode'] = $Location
                    ->getStorageGroup()
                    ->{$method}();
            }
            unset($Location);
            unset($LocationAssoc);
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
        Route::listem(
            'locationassociation',
            ['hostID' => $arguments['Host']->get('id')]
        );
        $LocationAssocs = json_decode(
            Route::getData()
        );
        foreach ($LocationAssocs->data as &$LocationAssoc) {
            $StorageGroup = self::getClass('Location', $LocationAssoc->locationID)
                ->getStorageGroup();
            if ($StorageGroup->isValid()) {
                continue;
            }
            $arguments['StorageGroup'] = $StorageGroup;
            unset($LocationAssoc);
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
        Route::listem(
            'locationassociation',
            ['hostID' => $arguments['Host']->get('id')]
        );
        $LocationAssocs = json_decode(
            Route::getData()
        );
        foreach ($LocationAssocs->data as &$LocationAssoc) {
            $Location = self::getClass('Location', $LocationAssoc->locationID);
            if (!$Location->get('tftp')) {
                continue;
            }
            $StorageNode = $Location->getStorageNode();
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
            unset($LocationAssoc);
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
        Route::ids(
            'location',
            [],
            'storagenodeID'
        );
        $storagenodes = json_decode(
            Route::getData(),
            true
        );
        $storagenodeIDs = array_unique(
            array_filter(
                self::fastmerge(
                    $storagenodes,
                    $arguments['MasterIDs']
                )
            )
        );
        Route::listem(
            'storagenode',
            ['id' => $storagenodeIDs]
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        $arguments['StorageNodes'] = [];
        foreach ($StorageNodes->data as $ind => $StorageNode) {
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
            if (!$StorageNode->online) {
                continue;
            }
            if (!$StorageNode->isMaster) {
                $StorageNode->isMaster = 1;
            }
            $arguments['StorageNodes'][] = $StorageNode;
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
        if (!$arguments['FOGServiceClass'] != 'MulticastTask') {
            return;
        }
        $arguments['StorageNode']->isMaster = 1;
    }
}
