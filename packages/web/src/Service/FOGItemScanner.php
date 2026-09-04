<?php
/**
 * Shared per-item file scanning for images and snapins.
 *
 * PHP version 7.4+
 *
 * @category FOGItemScanner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Service;

use FOG\Router\Route;

/**
 * Shared per-item file scanning for images and snapins.
 *
 * ImageSize and SnapinHash do the same walk: find everything this node is
 * the primary group for, look at the file on disk, and write what it finds
 * back onto the record. Only the last step genuinely differs -- one records
 * a size, the other a sha512 and a size.
 *
 * Sharing them is not tidiness. The duplication had already cost something
 * real: ImageSize checks file_exists()/is_readable() before touching the
 * file and records a zero when it is gone, and SnapinHash never grew that
 * guard. So a snapin whose file had been deleted or whose storage was
 * unmounted went straight into hash_file(), which returns false with a
 * warning, and false was then SAVED -- an empty hash on the record, which
 * the client compares against and fails. One body means the guard exists
 * once and covers both.
 *
 * ImageSize also read its own GLOBALENABLED setting twice in a row, on
 * consecutive lines. That is what duplicated code looks like after a few
 * years of edits.
 *
 * WHAT A SUBCLASS SUPPLIES. The data is a table; see descriptor(). The
 * messages stay as literal _() calls in the subclass, because gettext
 * extracts msgids from the source text -- a string built at runtime is
 * never translated and never reaches the .pot. The per-item work is a
 * method, because it is the one part that is genuinely different code.
 *
 * @category FOGItemScanner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGItemScanner extends FOGService
{
    /**
     * Is the service globally enabled.
     *
     * @var int
     */
    private static $_scanOn = 0;
    /**
     * Everything that differs between the scanners, as one table.
     *
     * Required keys:
     *   prefix        settings-key prefix, e.g. 'IMAGESIZE'
     *   log           default log filename
     *   dev           default console device
     *   zzz           default sleep, seconds
     *   route         route class for the item, e.g. 'image'
     *   assocRoute    route class for the group association
     *   assocField    association field holding the item id
     *   model         model class name, unqualified
     *   nodePathField storage node column holding the directory
     *   itemFileField item column holding the filename
     *   msg           disabled, starting, finding, none, found, plural,
     *                 singular, tail, trying, getting
     *
     * @return array
     */
    abstract protected function descriptor();
    /**
     * Records what the file on disk says.
     *
     * Called only once the file is known to exist and be readable.
     *
     * @param object $item     The item row.
     * @param string $filepath The file.
     *
     * @return void
     */
    abstract protected function updateItem($item, $filepath);
    /**
     * Records that the file is not there.
     *
     * @param object $item The item row.
     *
     * @return void
     */
    abstract protected function clearItem($item);
    /**
     * Reads one descriptor key, or dies saying which one is missing.
     *
     * A typo in the table is otherwise a silent null: the wrong settings
     * key reads as "unset" and the daemon falls back to a default, and an
     * empty route name asks the API for a class called "". Neither looks
     * like a bug in a log.
     *
     * @param string $key The key.
     * @param string $sub Optional key inside msg.
     *
     * @return mixed
     */
    protected function d($key, $sub = '')
    {
        $desc = $this->descriptor();
        if (!array_key_exists($key, $desc)) {
            throw new \Exception(
                sprintf(
                    'Scanner descriptor for %s has no "%s"',
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
                    'Scanner descriptor for %s has no "%s.%s"',
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
    protected function modelClass()
    {
        return 'FOG\\Items\\' . $this->d('model');
    }
    /**
     * Initializes the scanner.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $prefix = $this->d('prefix');
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
                $this->d('log')
            )
        );
        // GH-497: the log is not deleted on start. Rotation is handled by
        // FOGService, so a restart no longer throws away the evidence of
        // whatever made the admin restart the service.
        static::$dev = (
            $dev ?
            $dev :
            $this->d('dev')
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            $this->d('zzz')
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
            self::$_scanOn = (int) self::getSetting(
                $this->d('prefix') . 'GLOBALENABLED'
            );
            if (self::$_scanOn < 1) {
                throw new \Exception($this->d('msg', 'disabled'));
            }
            foreach ($this->checkIfNodeMaster() as $StorageNode) {
                $this->_scanGroup($StorageNode);
            }
            self::outall(
                sprintf(
                    ' * %s.',
                    _('Completed')
                )
            );
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
     * Scans everything one storage group is primary for.
     *
     * @param object $StorageNode The node we are master of.
     *
     * @return void
     */
    private function _scanGroup($StorageNode)
    {
        $myStorageGroupID = $StorageNode->storagegroupID;
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
                $this->d('msg', 'starting')
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
        self::outall(
            sprintf(
                ' * %s %s %s',
                $this->d('msg', 'finding'),
                _('with this group'),
                _('as its primary group')
            )
        );
        $itemIDs = Route::getIds(
            $this->d('assocRoute'),
            [
                'primary' => 1,
                'storagegroupID' => $myStorageGroupID
            ],
            $this->d('assocField')
        );
        $itemIDs = Route::getIds(
            $this->d('route'),
            [
                'id' => $itemIDs,
                'isEnabled' => 1
            ]
        );
        $count = count($itemIDs ?: []);
        if ($count < 1) {
            self::outall(
                sprintf(
                    ' * %s.',
                    $this->d('msg', 'none')
                )
            );
            return;
        }
        self::outall(
            sprintf(
                ' * %s %d %s %s.',
                _('Found'),
                $count,
                (
                    $count != 1 ?
                    $this->d('msg', 'plural') :
                    $this->d('msg', 'singular')
                ),
                $this->d('msg', 'tail')
            )
        );
        $Items = Route::getList(
            $this->d('route'),
            ['id' => $itemIDs]
        );
        foreach ($Items as $Item) {
            $this->_scanItem($StorageNode, $Item);
        }
    }
    /**
     * Scans one item's file.
     *
     * @param object $StorageNode The node holding the file.
     * @param object $Item        The item row.
     *
     * @return void
     */
    private function _scanItem($StorageNode, $Item)
    {
        $field = $this->d('itemFileField');
        self::outall(
            sprintf(
                ' * %s: %s, %s: %d',
                $this->d('msg', 'trying'),
                $Item->name,
                _('ID'),
                $Item->id
            )
        );
        $filepath = sprintf(
            '/%s/%s',
            trim($StorageNode->{$this->d('nodePathField')}, '/'),
            basename($Item->{$field})
        );
        // The guard SnapinHash never had. Without it hash_file() on a
        // missing file returns false, and false was saved -- an empty hash
        // on the record, which the client then compares against.
        if (!file_exists($filepath) || !is_readable($filepath)) {
            self::outall(
                sprintf(
                    '| %s: %s',
                    $Item->name,
                    _('Path is unavailable')
                )
            );
            $this->clearItem($Item);
            return;
        }
        self::outall(
            sprintf(
                ' * %s: %s.',
                $this->d('msg', 'getting'),
                $Item->name
            )
        );
        $this->updateItem($Item, $filepath);
    }
    /**
     * Runs the service.
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->_commonOutput();
        parent::serviceRun();
    }
}
