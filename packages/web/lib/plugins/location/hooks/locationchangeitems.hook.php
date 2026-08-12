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
/**
 * Named for its plugin, like every other hook here, because the class name is
 * derived from the filename: startClassFromFiles() strips .hook.php and skips
 * any name already declared. This and the OU plugin's hook were both
 * changeitems.hook.php, so whichever the autoloader's file walk reached first
 * -- readdir order -- was the only one that ever loaded, and the other
 * plugin's registrations silently never happened. It did not even need both
 * plugins installed: registerInstalled() bails when $node is not an installed
 * plugin, so an uninstalled location plugin winning the walk left the OU
 * plugin's AD hook unregistered with nothing to show for it.
 */
class LocationChangeItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'LocationChangeItems';
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
        $this->registerInstalled([
            ['SNAPIN_NODE', 'storageNodeSetting'],
            ['SNAPIN_GROUP', 'storageGroupSetting'],
            ['BOOT_ITEM_NEW_SETTINGS', 'bootItemSettings'],
            ['BOOT_TASK_NEW_SETTINGS', 'storageNodeSetting'],
            ['BOOT_TASK_NEW_SETTINGS', 'storageGroupSetting'],
            ['HOST_NEW_SETTINGS', 'storageNodeSetting'],
            ['HOST_NEW_SETTINGS', 'storageGroupSetting'],
            ['BOOT_TASK_NEW_SETTINGS', 'storageNodeSetting'],
            ['CHECK_NODE_MASTERS', 'alterMasters'],
            ['CHECK_NODE_MASTER', 'makeMaster'],
        ]);
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
            // Inverted since it was written: this skipped the groups it
            // should have answered with and offered up only invalid ones,
            // so every caller fell through to the image's or snapin's own
            // group and a host's location never influenced the group at all.
            // storageNodeSetting() overrode the node regardless, which is
            // why the two could disagree.
            if (!$StorageGroup->isValid()) {
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
        $storagenodes = Route::getIds(
            'location',
            [],
            'storagenodeID'
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
            if (!self::isLocalNode($StorageNode)) {
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
        // CHECK_NODE_MASTER hands over the class *name*, not an instance.
        // This read `(!$x) != 'MulticastTask'` -- the negation binds to $x, so
        // the comparison is bool-vs-string and always true, and the promotion
        // below has never run once since the hook was written. (dev-branch is
        // broken differently to the same end: it tests `instanceof` against
        // that same string.) With it dead, a location's node stayed a
        // non-master, MulticastTask::getAllMulticastTasks() returned early on
        // it, and only the group master ever ran a udp-sender -- so clients at
        // every other site sat waiting on a stream that could not reach them,
        // which is #815.
        if ('MulticastTask' !== $arguments['FOGServiceClass']) {
            return;
        }
        // Promote only a node this machine actually hosts. checkIfNodeMaster()
        // narrows to local IPs deliberately: a promoted node's image path is
        // read off local disk and its sender is spawned as a local process, so
        // claiming a node that lives on another server would have this daemon
        // stream on that node's behalf and would let
        // _reconcileOrphanedSenders() clear -- or SIGKILL on a pid collision --
        // a sender it does not own.
        if (!self::isLocalNode($arguments['StorageNode'])) {
            return;
        }
        $arguments['StorageNode']->isMaster = 1;
    }
    /**
     * Whether a storage node lives on the machine running this code.
     *
     * The multicast promotion above is only ever safe for a node this server
     * hosts, and both callers need the same test, so it lives here rather
     * than being written out twice.
     *
     * @param object $StorageNode The node to check.
     *
     * @return bool
     */
    protected static function isLocalNode($StorageNode)
    {
        self::getIPAddress();

        return in_array(
            self::resolveHostname($StorageNode->ip),
            (array)self::$ips
        );
    }
}
