<?php
/**
 * Replication service for snapins
 *
 * PHP version 5
 *
 * @category SnapinReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Replication service for snapins
 *
 * @category SnapinReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinReplicator extends FOGService
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
    public static $sleeptime = 'SNAPINREPSLEEPTIME';
    /**
     * Initializes the SnapinReplicator Class
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
     * @return void
     */
    private function _commonOutput()
    {
        try {
            self::$_repOn = self::getSetting('SNAPINREPLICATORGLOBALENABLED');
            if (self::$_repOn < 1) {
                throw new Exception(_(' * Snapin replication is globally disabled'));
            }
            foreach ((array)$this->checkIfNodeMaster() as &$StorageNode) {
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
                        _('Starting Snapin Replication')
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
                        _('snapin replication')
                    )
                );
                /**
                 * Get the snapin ids that are valid.
                 */
                $SnapinIDs = self::getSubObjectIDs('Snapin');
                /**
                 * Find any snapins that are no longer valid within
                 * fog, but still existing in the group assoc.
                 */
                $SnapinAssocs = self::getSubObjectIDs(
                    'SnapinGroupAssociation',
                    array('snapinID' => $SnapinIDs),
                    'snapinID',
                    true
                );
                /**
                 * If any assocs exist from prior, remove.
                 */
                if (count($SnapinAssocs)) {
                    self::getClass('SnapinGroupAssociationManager')
                        ->destroy(array('snapinID' => $SnapinAssocs));
                }
                unset($SnapinAssocs);
                /**
                 * Get the snapin ids that are to be replicated.
                 * NOTE: Must be enabled and have Replication enabled.
                 */
                $SnapinIDs = self::getSubObjectIDs(
                    'Snapin',
                    array(
                        'isEnabled' => 1,
                        'toReplicate' => 1
                    )
                );
                $SnapinAssocCount = self::getClass('SnapinGroupAssociationManager')
                    ->count(
                        array(
                            'storagegroupID' => $myStorageGroupID,
                            'snapinID' => $SnapinIDs
                        )
                    );
                $SnapinCount = self::getClass('SnapinManager')->count();
                if ($SnapinAssocCount < 1
                    || $SnapinCount < 1
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
                $Snapins = (array)self::getClass('SnapinManager')
                    ->find(array('id' => $snapinIDs));
                /**
                 * Handles replicating of our ssl folder and contents.
                 */
                $ssls = array(
                    'ssl/fog.csr',
                    'ssl/CA'
                );
                self::outall(
                    sprintf(
                        ' | %s',
                        _('Replicating ssl less private key')
                    )
                );
                foreach ($ssls as $ssl) {
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        new Snapin(),
                        false,
                        $ssl
                    );
                }
                foreach ($Snapins as &$Snapin) {
                    if (!$Snapin->getPrimaryGroup($myStorageGroupID)) {
                        self::outall(
                            sprintf(
                                ' | %s: %s',
                                _('Not syncing Snapin'),
                                $Snapin->get('name')
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
                        _('snapin replication')
                    )
                );
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
