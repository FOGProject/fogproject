<?php
/**
 * Replication service for snapins
 *
 * PHP version 5
 *
 * @category SnapinReplicator
 * @package  FOGPackage
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Replication service for snapins
 *
 * @category SnapinReplicator
 * @package  FOGPackage
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinReplicator extends FOGService
{
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'SNAPINREPSLEEPTIME';
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
                    'SNAPINREPLICATORDEVICEOUTPUT',
                    'SNAPINREPLICATORLOGFILENAME',
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
            (
                self::$logpath ?
                self::$logpath :
                '/opt/fog/log/'
            ),
            (
                $log ?
                $log :
                'fogsnapinrep.log'
            )
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty4'
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
     * @throws Exception
     * @return void
     */
    private function _commonOutput()
    {
        try {
            $StorageNodes = $this->checkIfNodeMaster();
            foreach ((array)$StorageNodes as &$StorageNode) {
                self::out(
                    sprintf(
                        ' * %s',
                        _('I am the group manager')
                    ),
                    static::$dev
                );
                self::wlog(
                    sprintf(
                        ' * %s',
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
                $SnapinIDs = self::getSubObjectIDs(
                    'Snapin',
                    array(
                        'isEnabled' => 1,
                        'toReplicate' => 1
                    )
                );
                $SnapinAssocs = self::getSubObjectIDs(
                    'SnapinGroupAssociation',
                    array('snapinID' => $SnapinIDs),
                    'snapinID',
                    true
                );
                if (count($SnapinAssocs)) {
                    self::getClass('SnapinGroupAssociationManager')
                        ->destroy(array('snapinID' => $SnapinAssocs));
                }
                unset($SnapinAssocs);
                $SnapinAssocCount = self::getClass('SnapinGroupAssociationManager')
                    ->count(
                        array(
                            'storagegroupID' => $myStorageGroupID,
                            'snapinID' => $SnapinIDs
                        )
                    );
                $SnapinCount = self::getClass('SnapinManager')->count();
                if ($SnapinAssocCount <= 0
                    || $SnapinCount <= 0
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
                            _('snapins to a storage group')
                        )
                    );
                    continue;
                }
                unset($SnapinAssocCount, $SnapinCount);
                $snapinIDs = self::getSubObjectIDs(
                    'SnapinGroupAssociation',
                    array(
                        'storagegroupID' => $myStorageGroupID,
                        'snapinID' => $SnapinIDs
                    ),
                    'snapinID'
                );
                $Snapins = self::getClass('SnapinManager')
                    ->find(array('id' => $snapinIDs));
                unset($snapinIDs);
                foreach ((array)$Snapins as &$Snapin) {
                    if (!$Snapin->isValid()) {
                        continue;
                    }
                    if (!$Snapin->getPrimaryGroup($myStorageGroupID)) {
                        self::outall(
                            sprintf(
                                ' | %s: %s',
                                _('Not syncing Snapin'),
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
                        $Snapin,
                        true
                    );
                    unset($Snapin);
                }
                foreach ($Snapins as &$Snapin) {
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        $Snapin,
                        false
                    );
                    unset($Snapin);
                }
                unset($Snapins);
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
        self::out(
            ' ',
            static::$dev
        );
        $str = str_pad('+', 75, '-');
        self::out($str, static::$dev);
        self::out(
            sprintf(
                ' * %s.',
                _('Checking if I am the group manager')
            ),
            static::$dev
        );
        self::wlog(
            sprintf(
                ' * %s.',
                _('Checking if I am the group manager')
            ),
            '/opt/fog/log/groupmanager.log'
        );
        $this->_commonOutput();
        self::out($str, static::$dev);
        parent::serviceRun();
    }
}
