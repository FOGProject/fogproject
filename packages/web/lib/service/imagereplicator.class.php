<?php
/**
 * Replication service for images
 *
 * PHP version 5
 *
 * @category ImageReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Replication service for images
 *
 * @category ImageReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageReplicator extends FOGService
{
    /**
     * Is the service globally enabled.
     *
     * @var int
     */
    private static $_repOn = 0;
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'IMAGEREPSLEEPTIME';
    /**
     * Initializes the ImageReplicator Class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $imagereplicatorkeys = [
            'IMAGEREPLICATORDEVICEOUTPUT',
            'IMAGEREPLICATORLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($imagereplicatorkeys);
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
                'fogreplicator.log'
            )
        );
        // GH-497: the log used to be deleted here on every start, which threw
        // away the run that led up to a restart -- exactly the one worth
        // reading -- and made `tail -f` useless across a service restart. The
        // file is now appended to, and wlog() rotates it on size instead.
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty1'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            600
        );
    }
    /**
     * This is what almost all services have available
     * but is specific to this service
     *
     * @return void
     */
    private function _commonOutput()
    {
        try {
            // Check of status changed.
            self::$_repOn = self::getSetting('IMAGEREPLICATORGLOBALENABLED');
            if (self::$_repOn < 1) {
                throw new \Exception(_(' * Image replication is globally disabled'));
            }
            foreach ($this->checkIfNodeMaster() as $StorageNode) {
                $skip = false;
                self::wlog(
                    sprintf(
                        '* %s',
                        _('I am the group manager')
                    ),
                    FOG_LOG_DIR . DS . 'groupmanager.log'
                );
                $myStorageGroupID = $StorageNode->storagegroupID;
                $myStorageNodeID = $StorageNode->id;
                Route::indiv(
                    'storagegroup',
                    $myStorageGroupID
                );
                $StorageGroup = json_decode(
                    Route::getData()
                );
                self::outall(
                    sprintf(
                        ' * %s.',
                        _('Starting Image Replication')
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
                /**
                 * More implicit defining of type of sync
                 * currently happening.
                 */
                self::outall(
                    sprintf(
                        ' * %s %s -> %s %s.',
                        _('Attempting to perform'),
                        _('Group'),
                        _('Group'),
                        _('image replication')
                    )
                );
                /**
                 * Get the image ids that are valid.
                 */
                $find = [
                    'isEnabled' => [1],
                    'toReplicate' => [1]
                ];
                $imageIDs = Route::getIds(
                    'image',
                    $find
                );
                $ImageAssocCount = Route::getCount(
                    'imageassociation',
                    [
                        'storagegroupID' => $myStorageGroupID,
                        'imageID' => $imageIDs
                    ]
                );
                $ImageCount = count($imageIDs ?: []);
                if ($ImageCount <= 0) {
                    self::outall(
                        sprintf(
                            ' | %s.',
                            _('There are no images available!')
                        )
                    );
                    $skip = true;
                }
                if ($ImageAssocCount <= 0) {
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
                            _('images to a storage group')
                        )
                    );
                    $skip = true;
                }
                unset($ImageAssocCount, $ImageCount);
                if ($skip) {
                    continue;
                }
                $find = [
                    'storagegroupID' => $myStorageGroupID,
                    'imageID' => $imageIDs
                ];
                $imageIDs = Route::getIds(
                    'imageassociation',
                    $find,
                    'imageID'
                );
                Route::listem(
                    'image',
                    ['id' => $imageIDs]
                );
                $Images = json_decode(
                    Route::getData()
                );
                /**
                 * Handles replicating of our dev/postinitscripts
                 * and postdownload scripts
                 */
                $Postdown = 'postdownloadscripts';
                $Postinit = sprintf(
                    '%s/%s',
                    'dev',
                    'postinitscripts'
                );
                $extrascripts = [
                    $Postdown,
                    $Postinit
                ];
                foreach ($extrascripts as $scripts) {
                    self::outall(
                        sprintf(
                            ' | %s %s',
                            _('Replicating'),
                            basename($scripts)
                        )
                    );
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        new Image(),
                        false,
                        $scripts
                    );
                }
                foreach ($Images->data as $Image) {
                    if (!Image::getPrimaryGroup($myStorageGroupID, $Image->id)) {
                        self::outall(
                            sprintf(
                                ' | %s: %s',
                                _('Not syncing Image'),
                                $Image->name
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
                    $I = new Image($Image->id);
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        $I,
                        true
                    );
                }
                /**
                 * More implicit defining of type of sync
                 * currently happening.
                 */
                self::outall(
                    sprintf(
                        ' * %s %s -> %s %s.',
                        _('Attempting to perform'),
                        _('Group'),
                        _('Nodes'),
                        _('image replication')
                    )
                );
                foreach ($Images->data as $Image) {
                    $I = new Image($Image->id);
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        $I,
                        false
                    );
                }
                unset($Images);
            }
        } catch (\Exception $e) {
            self::outall(
                sprintf(
                    ' * %s',
                    _($e->getMessage())
                )
            );
        }
    }
    /**
     * This is runs the service
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
