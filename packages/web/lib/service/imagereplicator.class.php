<?php
/**
 * Replication service for images
 *
 * PHP version 7.4+
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
        // By name, not by position -- see the note in MulticastManager's
        // constructor (issue #728). getSubObjectIDs() returns only the rows
        // that exist, so one missing Service row shifted every later value
        // left and left the last undefined.
        $dev = self::getSetting('IMAGEREPLICATORDEVICEOUTPUT');
        $log = self::getSetting('IMAGEREPLICATORLOGFILENAME');
        $zzz = self::getSetting(self::$sleeptime);
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
                throw new Exception(_(' * Image replication is globally disabled'));
            }
            foreach ((array)$this->checkIfNodeMaster() as &$StorageNode) {
                self::wlog(
                    sprintf(
                        " * %s - %s.\n",
                        get_class($this),
                        _('I am the group manager')
                    ),
                    '/opt/fog/log/groupmanager.log'
                );
                $myStorageGroupID = $StorageNode->get('storagegroupID');
                $myStorageNodeID = $StorageNode->get('id');
                $StorageGroup = $StorageNode->getStorageGroup();
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
                        $StorageGroup->get('id'),
                        _('We are group name'),
                        $StorageGroup->get('name')
                    )
                );
                self::outall(
                    sprintf(
                        ' * %s: %d. %s: %s',
                        _('We are node ID'),
                        $StorageNode->get('id'),
                        _('We are node name'),
                        $StorageNode->get('name')
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
                $ImageIDs = self::getSubObjectIDs('Image');
                /**
                 * Find any images that are no longer valid within
                 * fog, but still existing in the group assoc.
                 */
                $ImageAssocs = self::getSubObjectIDs(
                    'ImageAssociation',
                    array('imageID' => $ImageIDs),
                    'imageID',
                    true
                );
                /**
                 * If any assocs exist from prior, remove
                 */
                if (count($ImageAssocs)) {
                    self::getClass('ImageAssociationManager')
                        ->destroy(array('imageID' => $ImageAssocs));
                }
                unset($ImageAssocs);
                /**
                 * Get the image ids that are to be replicated.
                 * NOTE: Must be enabled and have Replication enabled.
                 */
                $ImageIDs = self::getSubObjectIDs(
                    'Image',
                    array(
                        'isEnabled'=>1,
                        'toReplicate'=>1
                    )
                );
                $ImageAssocCount = self::getClass('ImageAssociationManager')
                    ->count(
                        array(
                            'storagegroupID' => $myStorageGroupID,
                            'imageID' => $ImageIDs
                        )
                    );
                $ImageCount = self::getClass('ImageManager')->count();
                if ($ImageAssocCount <= 0
                    || $ImageCount <= 0
                ) {
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
                    continue;
                }
                unset($ImageAssocCount, $ImageCount);
                $imageIDs = self::getSubObjectIDs(
                    'ImageAssociation',
                    array(
                        'storagegroupID' => $myStorageGroupID,
                        'imageID' => $ImageIDs
                    ),
                    'imageID'
                );
                $Images = (array)self::getClass('ImageManager')
                    ->find(array('id' => $imageIDs));
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
                $extrascripts = array(
                    $Postdown,
                    $Postinit
                );
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
                foreach ($Images as &$Image) {
                    if (!$Image->getPrimaryGroup($myStorageGroupID)) {
                        self::outall(
                            sprintf(
                                ' | %s: %s',
                                _('Not syncing Image'),
                                $Image->get('name')
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
                        $Image,
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
                foreach ($Images as &$Image) {
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        $Image,
                        false
                    );
                    unset($Image);
                }
                unset($Images);
                unset($StorageNode);
            }
            unset($StorageNodes);
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
                " * %s - %s.\n",
                get_class($this),
                _('Checking if I am the group manager')
            ),
            '/opt/fog/log/groupmanager.log'
        );
        $this->_commonOutput();
        parent::serviceRun();
    }
    /**
     * Do some housekeeping jobs in between the replication.
     *
     * @return void
     */
    public function doHousekeeping()
    {
        parent::cleanupProcList();
    }
}
