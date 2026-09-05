<?php
/**
 * Shared group-to-group and group-to-node replication.
 *
 * PHP version 7.4+
 *
 * @category FOGReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Service;

use FOG\Router\Route;

/**
 * Shared group-to-group and group-to-node replication.
 *
 * ImageReplicator and SnapinReplicator were ~90% the same file. The sequence
 * they share is the whole daemon: prove we are the group manager, resolve the
 * group, count what is enabled and associated, bail out early if either is
 * zero, push the extra paths, then replicate the items twice -- once
 * group-to-group for the items this group is primary for, once group-to-node
 * for all of them.
 *
 * That duplication was not harmless. It had already produced drift in the log
 * formats ('* %s' against ' * %s'), a doubled "nothing to replicate" message
 * on the image side where the snapin side used elseif, and -- in the sibling
 * pair, ImageSize and SnapinHash -- a missing-file guard that exists in one
 * copy and not the other. One body means a fix lands once.
 *
 * WHAT A SUBCLASS SUPPLIES, and why it is shaped this way.
 *
 * The data (route names, association field, model class, extra paths) is
 * ordinary configuration. The MESSAGES are not, and this is the part worth
 * reading before editing: gettext extracts msgids from the source text, so a
 * string built at runtime -- _("There are no $noun available!") -- is never
 * translated and never appears in the .pot, silently, forever. Every
 * noun-bearing message therefore stays a literal _() call in the subclass
 * that owns it, and the base asks for the finished string. That is why there
 * are six message hooks rather than one noun.
 *
 * @category FOGReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGReplicator extends FOGService
{
    /**
     * Is the service globally enabled.
     *
     * @var int
     */
    private static $_repOn = 0;
    /**
     * Everything that differs between the replicators, as one table.
     *
     * Required keys:
     *   prefix      settings-key prefix, e.g. 'IMAGEREPLICATOR'
     *   log         default log filename
     *   dev         default console device
     *   route       route class for the item, e.g. 'image'
     *   assocRoute  route class for the group association
     *   assocField  association field holding the item id
     *   model       model class name, unqualified
     *   extraPaths  paths replicated alongside the items
     *   msg         disabled, starting, kind, none, associate, notSyncing
     *
     * @return array
     */
    abstract protected function descriptor();
    /**
     * Reads one descriptor key, or dies saying which one is missing.
     *
     * A typo in the table is otherwise a silent null: the wrong settings
     * key reads as "unset" and the daemon quietly falls back to a default,
     * or an empty route name asks the API for a class called "". Neither
     * looks like a bug in a log.
     *
     * @param string $key  The key.
     * @param string $sub  Optional key inside msg.
     *
     * @return mixed
     */
    private function _d($key, $sub = '')
    {
        $desc = $this->descriptor();
        if (!array_key_exists($key, $desc)) {
            throw new \Exception(
                sprintf(
                    'Replicator descriptor for %s has no "%s"',
                    static::class,
                    $key
                )
            );
        }
        if ('' === $sub) {
            return $desc[$key];
        }
        if (!array_key_exists($sub, (array)$desc[$key])) {
            throw new \Exception(
                sprintf(
                    'Replicator descriptor for %s has no "%s.%s"',
                    static::class,
                    $key,
                    $sub
                )
            );
        }
        return $desc[$key][$sub];
    }
    /**
     * The fully-qualified model class.
     *
     * Qualified rather than relying on the global compatibility alias,
     * which only exists once the class has been loaded -- and nothing
     * here guarantees that it has been.
     *
     * The bucket is named rather than taken from __NAMESPACE__: the
     * models live in FOG\Items and this class lives in FOG\Service, so
     * before Move 2 the two happened to coincide in a flat tree and no
     * longer do. Building a COLLABORATOR's name from the CALLER's
     * namespace is the mistake; it produced FOG\Service\Image.
     *
     * @return string
     */
    private function _modelClass()
    {
        return 'FOG\\Items\\' . $this->_d('model');
    }
    /**
     * A model instance for this item type.
     *
     * @param int $id The item to load, 0 for an empty one.
     *
     * @return FOGController
     */
    private function _newItem($id = 0)
    {
        return $id
            ? self::getClass($this->_modelClass(), $id)
            : self::getClass($this->_modelClass());
    }
    /**
     * Initializes the replicator.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $prefix = $this->_d('prefix');
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting(
            [
                $prefix . 'DEVICEOUTPUT',
                $prefix . 'LOGFILENAME',
                static::$sleeptime
            ]
        );
        static::$log = sprintf(
            '%s%s',
            (
                self::$logpath ?
                self::$logpath :
                FOG_LOG_DIR . DS
            ),
            (
                $log ?
                $log :
                $this->_d('log')
            )
        );
        // GH-497: the log is not deleted on start. Rotation is handled by
        // FOGService, so a restart no longer throws away the evidence of
        // whatever made the admin restart the service.
        static::$dev = (
            $dev ?
            $dev :
            $this->_d('dev')
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            600
        );
    }
    /**
     * The one pass over every group this node masters.
     *
     * @return void
     */
    private function _commonOutput()
    {
        try {
            // Re-read every pass: a daemon must notice the setting being
            // turned off without needing a restart.
            self::$_repOn = (int) self::getSetting(
                $this->_d('prefix') . 'GLOBALENABLED'
            );
            if (self::$_repOn < 1) {
                throw new \Exception($this->_d('msg', 'disabled'));
            }
            foreach ($this->checkIfNodeMaster() as $StorageNode) {
                $this->_replicateGroup($StorageNode);
            }
        } catch (\Exception $e) {
            self::outall(
                sprintf(
                    ' * %s',
                    $e->getMessage()
                )
            );
        }
    }
    /**
     * Replicates everything one storage group owns.
     *
     * @param object $StorageNode The node we are master of.
     *
     * @return void
     */
    private function _replicateGroup($StorageNode)
    {
        self::wlog(
            sprintf(
                ' * %s',
                _('I am the group manager')
            ),
            FOG_LOG_DIR . DS . 'groupmanager.log'
        );
        $myStorageGroupID = $StorageNode->storagegroupID;
        $myStorageNodeID = $StorageNode->id;
        // getItem(), not indiv(): a miss answers with null here rather than
        // exiting the daemon child outright. Refs #907.
        $StorageGroup = Route::getItem(
            'storagegroup',
            $myStorageGroupID
        );
        if (!$StorageGroup) {
            self::outall(
                sprintf(
                    ' * %s: %d',
                    _('Skipping, no such storage group'),
                    $myStorageGroupID
                )
            );
            return;
        }
        self::outall(
            sprintf(
                ' * %s.',
                $this->_d('msg', 'starting')
            )
        );
        self::outall(
            sprintf(
                ' * %s: %d. %s: %s',
                _('We are group ID'),
                $StorageGroup->id,
                _('We are group name'),
                $StorageGroup->name
            )
        );
        self::outall(
            sprintf(
                ' * %s: %d. %s: %s',
                _('We are node ID'),
                $StorageNode->id,
                _('We are node name'),
                $StorageNode->name
            )
        );
        $itemIDs = $this->_itemsToReplicate($myStorageGroupID);
        if (!$itemIDs) {
            return;
        }
        $Items = Route::getList(
            $this->_d('route'),
            ['id' => $itemIDs]
        );
        foreach ($this->_d('extraPaths') as $path) {
            self::outall(
                sprintf(
                    ' | %s %s',
                    _('Replicating'),
                    basename($path)
                )
            );
            $this->replicateItems(
                $myStorageGroupID,
                $myStorageNodeID,
                $this->_newItem(),
                false,
                $path
            );
        }
        foreach ($Items as $Item) {
            $model = $this->_modelClass();
            if (!$model::getPrimaryGroup($myStorageGroupID, $Item->id)) {
                self::outall(
                    sprintf(
                        ' | %s: %s',
                        $this->_d('msg', 'notSyncing'),
                        $Item->name
                    )
                );
                self::outall(
                    sprintf(
                        ' | %s.',
                        _('This is not the primary group')
                    )
                );
                continue;
            }
            $this->replicateItems(
                $myStorageGroupID,
                $myStorageNodeID,
                $this->_newItem($Item->id),
                true
            );
        }
        $this->_banner(_('Nodes'));
        foreach ($Items as $Item) {
            $this->replicateItems(
                $myStorageGroupID,
                $myStorageNodeID,
                $this->_newItem($Item->id),
                false
            );
        }
    }
    /**
     * Announces which half of the replication is starting.
     *
     * @param string $target _('Group') or _('Nodes'), already translated.
     *
     * @return void
     */
    private function _banner($target)
    {
        self::outall(
            sprintf(
                ' * %s %s -> %s %s.',
                _('Attempting to perform'),
                _('Group'),
                $target,
                $this->_d('msg', 'kind')
            )
        );
    }
    /**
     * Resolves the item ids this group should replicate.
     *
     * @param int $myStorageGroupID The group.
     *
     * @return array Empty when there is nothing to do, which is not an error.
     */
    private function _itemsToReplicate($myStorageGroupID)
    {
        $this->_banner(_('Group'));
        $itemIDs = Route::getIds(
            $this->_d('route'),
            [
                'isEnabled' => [1],
                'toReplicate' => [1]
            ]
        );
        if (count($itemIDs ?: []) < 1) {
            // Short-circuit before the association count. Asking for
            // `IN ()` is not a thing: an empty array filter falls through
            // to `field = ''` in the manager, which happens to answer zero
            // for an integer column and so gave the right result for the
            // wrong reason.
            self::outall(
                sprintf(
                    ' | %s.',
                    $this->_d('msg', 'none')
                )
            );
            return [];
        }
        $assocCount = Route::getCount(
            $this->_d('assocRoute'),
            [
                'storagegroupID' => $myStorageGroupID,
                $this->_d('assocField') => $itemIDs
            ]
        );
        if ($assocCount < 1) {
            self::outall(
                sprintf(
                    ' | %s.',
                    _('There is nothing to replicate')
                )
            );
            self::outall(
                sprintf(
                    ' | %s %s.',
                    _('Please physically associate'),
                    $this->_d('msg', 'associate')
                )
            );
            return [];
        }
        return Route::getIds(
            $this->_d('assocRoute'),
            [
                'storagegroupID' => $myStorageGroupID,
                $this->_d('assocField') => $itemIDs
            ],
            $this->_d('assocField')
        );
    }
    /**
     * Runs the service.
     *
     * @return void
     */
    public function serviceRun()
    {
        self::wlog(
            sprintf(
                ' * %s.',
                _('Checking if I am the group manager')
            ),
            FOG_LOG_DIR . DS . 'groupmanager.log'
        );
        $this->_commonOutput();
        parent::serviceRun();
    }
}
