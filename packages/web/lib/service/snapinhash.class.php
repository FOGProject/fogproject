<?php
/**
 * Hashing service for snapins
 *
 * PHP version 5
 *
 * @category SnapinHash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hashing service for snapins
 *
 * @category SnapinHash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinHash extends FOGService
{
    /**
     * Is the service globally enabled.
     *
     * @var int
     */
    private static $_hashOn = 0;
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'SNAPINHASHSLEEPTIME';
    /**
     * Initializes the SnapinReplicator Class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $snapinhashkeys = [
            'SNAPINHASHDEVICEOUTPUT',
            'SNAPINHASHLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($snapinhashkeys);
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
                'fogsnapinhash.log'
            )
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty6'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            1800
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
            self::$_hashOn = self::getSetting('SNAPINHASHGLOBALENABLED');
            if (self::$_hashOn < 1) {
                throw new Exception(_(' * Snapin hash is globally disabled'));
            }
            foreach ($this->checkIfNodeMaster() as $StorageNode) {
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
                        _('Starting Snapin Hashing Service')
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
                        _('Finding any snapins associated'),
                        _('with this group'),
                        _('as its primary group')
                    )
                );
                $find = [
                    'primary' => 1,
                    'storagegroupID' => $myStorageGroupID
                ];
                Route::ids(
                    'snapingroupassociation',
                    $find,
                    'snapinID'
                );
                $snapinIDs = json_decode(Route::getData(), true);
                $find = [
                    'id' => $snapinIDs,
                    'isEnabled' => 1
                ];
                Route::ids(
                    'snapin',
                    $find
                );
                $snapinIDs = json_decode(Route::getData(), true);
                $SnapinCount = count($snapinIDs ?: []);
                if ($SnapinCount < 1) {
                    self::outall(
                        sprintf(
                            ' * %s.',
                            _('No snapins associated with this group as master')
                        )
                    );
                    continue;
                }
                self::outall(
                    sprintf(
                        ' * %s %d %s %s.',
                        _('Found'),
                        $SnapinCount,
                        (
                            $SnapinCount != 1 ?
                            _('snapins') :
                            _('snapin')
                        ),
                        _('to update hash values as needed')
                    )
                );
                Route::listem(
                    'snapin',
                    ['id' => $snapinIDs]
                );
                $Snapins = json_decode(
                    Route::getData()
                );
                foreach ($Snapins->data as $Snapin) {
                    self::outall(
                        sprintf(
                            ' * %s: %s, %s: %d',
                            _('Trying Snapin hash for'),
                            $Snapin->name,
                            _('ID'),
                            $Snapin->id
                        )
                    );
                    $path = sprintf(
                        '/%s',
                        trim($StorageNode->snapinpath, '/')
                    );
                    $file = basename($Snapin->file);
                    $filepath = sprintf(
                        '%s/%s',
                        $path,
                        $file
                    );
                    self::outall(
                        sprintf(
                            ' * %s: %s.',
                            _('Getting snapin hash and size for'),
                            $Snapin->name
                        )
                    );
                    $hash = hash_file('sha512', $filepath);
                    $size = self::getFilesize($filepath);
                    unset($path, $file);
                    self::outall(
                        sprintf(
                            ' | %s: %s',
                            _('Hash'),
                            $hash
                        )
                    );
                    self::getClass('Snapin', $Snapin->id)
                        ->set('hash', $hash)
                        ->set('size', $size)
                        ->save();
                    unset($url, $response, $hash, $size);
                }
            }
            self::outall(
                sprintf(
                    ' * %s.',
                    _('Completed')
                )
            );
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
        $this->_commonOutput();
        parent::serviceRun();
    }
}
