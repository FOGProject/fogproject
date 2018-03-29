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
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSubObjectIDs(
            'Service',
            [
                'name' => [
                    'IMAGEREPLICATORDEVICEOUTPUT',
                    'IMAGEREPLICATORLOGFILENAME',
                    self::$sleeptime
                ]
            ],
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        static::$log = sprintf(
            '%s%s',
            (
                self::$logpath ?
                self::$logpath :
                '/opt/fog/log/'
            ),
            (
                $log ?
                $log :
                'fogreplicator.log'
            )
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
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
                throw new Exception(_(' * Image replication is globally disabled'));
            }
            foreach ($this->checkIfNodeMaster() as &$StorageNode) {
                self::wlog(
                    sprintf(
                        '* %s',
                        _('I am the group manager')
                    ),
                    '/opt/fog/log/groupmanager.log'
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
                $imageIDs = self::getSubObjectIDs('Image');
                /**
                 * Find any images that are no longer valid within
                 * fog, but still existing in the group assoc.
                 */
                $ImageAssocs = self::getSubObjectIDs(
                    'ImageAssociation',
                    ['imageID' => $imageIDs],
                    'imageID',
                    true
                );
                /**
                 * If any assocs exist from prior, remove
                 */
                if (count($ImageAssocs)) {
                    self::getClass('ImageAssociationManager')
                        ->destroy(['imageID' => $ImageAssocs]);
                }
                unset($ImageAssocs);
                /**
                 * Get the image ids that are to be replicated.
                 * NOTE: Must be enabled and have Replication enabled.
                 */
                $imageIDs = self::getSubObjectIDs(
                    'Image',
                    [
                        'id' => $imageIDs,
                        'isEnabled'=>1,
                        'toReplicate'=>1
                    ]
                );
                $ImageAssocCount = self::getClass('ImageAssociationManager')
                    ->count(
                        [
                            'storagegroupID' => $myStorageGroupID,
                            'imageID' => $imageIDs
                        ]
                    );
                $ImageCount = count($imageIDs ?: []);
                if ($ImageAssocCount < 1
                    || $ImageCount < 1
                ) {
                    $this->outall(
                        sprintf(
                            ' | %s.',
                            _('There is nothing to replicate')
                        )
                    );
                    $this->outall(
                        sprintf(
                            ' | %s %s.',
                            _('Please physically associate'),
                            _('images to a storage group')
                        )
                    );
                    continue;
                }
                unset($ImageAssocCount, $ImageCount);
                $imageIDs = self::getSubObjectIDs(
                    'ImageAssociation',
                    [
                        'storagegroupID' => $myStorageGroupID,
                        'imageID' => $imageIDs
                    ],
                    'imageID'
                );
                Route::listem(
                    'image',
                    ['imageID' => $imageIDs]
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
                foreach ($Images->data as &$Image) {
                    if (!Image::getPrimaryGroup($myStorageGroupID, $imageID)) {
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
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        new Image($Image->id),
                        true
                    );
                    unset($Image);
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
                foreach ($Images->data as &$Image) {
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        new Image($Image->id),
                        false
                    );
                    unset($Image);
                }
                unset($Images);
                unset($StorageNode);
            }
        } catch (Exception $e) {
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
            '/opt/fog/log/groupmanager.log'
        );
        $this->_commonOutput();
        parent::serviceRun();
    }
}
