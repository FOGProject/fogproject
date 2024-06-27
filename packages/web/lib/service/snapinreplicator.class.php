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
        $snapinreplicatorkeys = [
            'SNAPINREPLICATORDEVICEOUTPUT',
            'SNAPINREPLICATORLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($snapinreplicatorkeys);
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
            foreach ($this->checkIfNodeMaster() as $StorageNode) {
                $skip = false;
                self::wlog(
                    sprintf(
                        ' * %s',
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
                        _('Starting Snapin Replication')
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
                        _('snapin replication')
                    )
                );
                /**
                 * Get the snapin ids that are valid.
                 */
                $find = [
                    'isEnabled' => [1],
                    'toReplicate' => [1]
                ];
                Route::ids(
                    'snapin',
                    $find
                );
                $snapinIDs = json_decode(Route::getData(), true);
                Route::count(
                    'snapingroupassociation',
                    [
                        'storagegroupID' => $myStorageGroupID,
                        'snapinID' => $snapinIDs
                    ]
                );
                $SnapinAssocCount = json_decode(Route::getData());
                $SnapinAssocCount = $SnapinAssocCount->total;
                $SnapinCount = count($snapinIDs ?: []);
                if ($SnapinCount <= 0) {
                    $this->outall(
                        sprintf(
                            ' | %s',
                            _('There are no snapins available!')
                        )
                    );
                    $skip = true;
                } elseif ($SnapinAssocCount < 1) {
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
                    $skip = true;
                }
                unset($SnapinAssocCount, $SnapinCount);
                if ($skip) {
                    continue;
                }
                $find = [
                    'storagegroupID' => $myStorageGroupID,
                    'snapinID' => $snapinIDs
                ];
                Route::ids(
                    'snapingroupassociation',
                    $find,
                    'snapinID'
                );
                $snapinIDs = json_decode(Route::getData(), true);
                Route::listem(
                    'snapin',
                    ['id' => $snapinIDs]
                );
                $Snapins = json_decode(
                    Route::getData()
                );
                /**
                 * Handles replicating of our ssl folder and contents.
                 */
                $ssls = [
                    'ssl/fog.csr',
                    'ssl/CA'
                ];
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
                foreach ($Snapins->data as $Snapin) {
                    if (!Snapin::getPrimaryGroup($myStorageGroupID, $Snapin->id)) {
                        self::outall(
                            sprintf(
                                ' | %s: %s',
                                _('Not syncing Snapin'),
                                $Snapin->name
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
                    $S = new Snapin($Snapin->id);
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        $S,
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
                        _('snapin replication')
                    )
                );
                foreach ($Snapins->data as $Snapin) {
                    $S = new Snapin($Snapin->id);
                    $this->replicateItems(
                        $myStorageGroupID,
                        $myStorageNodeID,
                        $S,
                        false
                    );
                }
                unset($Snapins);
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
