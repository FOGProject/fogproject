<?php
/**
 * Replication service for images
 *
 * PHP version 5
 *
 * @category ImageReplicator
 * @package  FOGPackage
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Replication service for images
 *
 * @category ImageReplicator
 * @package  FOGPackage
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageReplicator extends FOGService
{
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
            array(
                'name' => array(
                    'IMAGEREPLICATORDEVICEOUTPUT',
                    'IMAGEREPLICATORLOGFILENAME',
                    self::$sleeptime
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        static::$log = sprintf(
            '%s%s',
            self::$logpath ?
            self::$logpath :
            '/opt/fog/log/',
            $log ?
            $log :
            'fogreplicator.log'
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = $dev ? $dev : '/dev/tty1';
        static::$zzz = ($zzz ? $zzz : 600);
    }
    /**
     * This is what almost all services have available
     * but is specific to this service
     *
     * @throws Exception
     * @return void
     */
    private function _commonOutput()
    {
        try {
            $StorageNodes = $this->checkIfNodeMaster();
            foreach ((array)$StorageNodes as &$StorageNode) {
                $myStorageGroupID = $StorageNode->get('storagegroupID');
                self::out(
                    ' * I am the group manager',
                    static::$dev
                );
                self::wlog(
                    ' * I am the group manager',
                    '/opt/fog/log/groupmanager.log'
                );
                $myStorageNodeID = $StorageNode->get('id');
                self::outall(' * Starting Image Replication.');
                self::outall(
                    sprintf(
                        " * We are group ID: #%s",
                        $myStorageGroupID
                    )
                );
                self::outall(
                    sprintf(
                        " | We are group name: %s",
                        self::getClass(
                            'StorageGroup',
                            $myStorageGroupID
                        )->get('name')
                    )
                );
                self::outall(
                    sprintf(
                        " * We have node ID: #%s",
                        $myStorageNodeID
                    )
                );
                self::outall(
                    sprintf(
                        " | We are node name: %s",
                        self::getClass(
                            'StorageNode',
                            $myStorageNodeID
                        )->get('name')
                    )
                );
                $ImageIDs = self::getSubObjectIDs(
                    'Image',
                    array(
                        'isEnabled'=>1,
                        'toReplicate'=>1
                    )
                );
                $ImageAssocs = self::getSubObjectIDs(
                    'ImageAssociation',
                    array('imageID' => $ImageIDs),
                    'imageID',
                    true
                );
                if (count($ImageAssocs)) {
                    self::getClass('ImageAssociationManager')
                        ->destroy(array('imageID' => $ImageAssocs));
                }
                unset($ImageAssocs);
                $ImageAssocCount = self::getClass('ImageAssociationManager')
                    ->count(
                        array(
                            'storagegroupID' => $myStorageGroupID,
                            'imageID' => $ImageIDs
                        )
                    );
                $ImageCount = self::getClass('ImageManager')->count();
                if ($ImageAssocCount <= 0 || $ImageCount <= 0) {
                    $this->outall(_(' | There is nothing to replicate'));
                    $this->outall(_(' | Please physically associate images'));
                    $this->outall(_(' |    to a storage group'));
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
                $Images = self::getClass('ImageManager')
                    ->find(array('id' => $imageIDs));
                unset($imageIDs);
                foreach ((array)$Images as &$Image) {
                    if (!$Image->isValid()) {
                        continue;
                    }
                    if (!$Image->getPrimaryGroup($myStorageGroupID)) {
                        self::outall(_(" | Not syncing Image: {$Image->get(name)}"));
                        self::outall(_(' | This is not the primary group'));
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
                    $e->getMessage()
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
        self::out(
            ' ',
            static::$dev
        );
        $str = str_pad('+', 75, '-');
        self::out($str, static::$dev);
        self::out(
            ' * Checking if I am the group manager.',
            static::$dev
        );
        self::wlog(
            ' * Checking if I am the group manager.',
            '/opt/fog/log/groupmanager.log'
        );
        $this->_commonOutput();
        self::out($str, static::$dev);
        parent::serviceRun();
    }
}
