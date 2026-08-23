<?php
/**
 * Hashing service for snapins
 *
 * PHP version 7.4+
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
        // By name, not by position -- see the note in MulticastManager's
        // constructor (issue #728). getSubObjectIDs() returns only the rows
        // that exist, so one missing Service row shifted every later value
        // left and left the last undefined.
        $dev = self::getSetting('SNAPINHASHDEVICEOUTPUT');
        $log = self::getSetting('SNAPINHASHLOGFILENAME');
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
                'fogsnapinhash.log'
            )
        );
        // GH-497: the log used to be deleted here on every start, which threw
        // away the run that led up to a restart -- exactly the one worth
        // reading -- and made `tail -f` useless across a service restart. The
        // file is now appended to, and wlog() rotates it on size instead.
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
            foreach ((array)$this->checkIfNodeMaster() as &$StorageNode) {
                $myStorageGroupID = $StorageNode->get('storagegroupID');
                $myStorageNodeID = $StorageNode->get('id');
                $StorageGroup = $StorageNode->getStorageGroup();
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
                self::outall(
                    sprintf(
                        ' * %s %s %s',
                        _('Finding any snapins associated'),
                        _('with this group'),
                        _('as its primary group')
                    )
                );
                $snapinIDs = self::getSubObjectIDs(
                    'SnapinGroupAssociation',
                    array(
                        'primary' => 1,
                        'storagegroupID' => $myStorageGroupID
                    ),
                    'snapinID'
                );
                $SnapinCount = self::getClass('SnapinManager')->count(
                    array(
                        'id' => $snapinIDs,
                        'isEnabled' => 1
                    )
                );
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
                foreach ((array)self::getClass('SnapinManager')
                    ->find(
                        array(
                            'id' => $snapinIDs,
                            'isEnabled' => 1
                        )
                    ) as &$Snapin
                ) {
                    self::outall(
                        sprintf(
                            ' * %s: %s, %s: %d',
                            _('Trying Snapin hash for'),
                            $Snapin->get('name'),
                            _('ID'),
                            $Snapin->get('id')
                        )
                    );
                    $path = sprintf(
                        '/%s',
                        trim($StorageNode->get('snapinpath'), '/')
                    );
                    $file = basename($Snapin->get('file'));
                    $filepath = sprintf(
                        '%s/%s',
                        $path,
                        $file
                    );
                    // ImageSize has had this guard for years; SnapinHash
                    // never grew it. Without it hash_file() on a file that
                    // is not there -- deleted, or storage unmounted when
                    // the pass ran -- returns FALSE with a warning, and
                    // false was then SAVED as the hash. A client comparing
                    // against an empty hash fails the snapin every time,
                    // with nothing in the log to say why. Blanking both
                    // columns says the file is not currently there, which
                    // is true, and the next pass repairs it.
                    if (!file_exists($filepath) || !is_readable($filepath)) {
                        self::outall(
                            sprintf(
                                '| %s: %s',
                                $Snapin->get('name'),
                                _('Path is unavailable')
                            )
                        );
                        $Snapin
                            ->set('hash', '')
                            ->set('size', 0)
                            ->save();
                        unset($path, $file);
                        continue;
                    }
                    self::outall(
                        sprintf(
                            ' * %s: %s.',
                            _('Getting snapin hash and size for'),
                            $Snapin->get('name')
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
                    $Snapin
                        ->set('hash', $hash)
                        ->set('size', $size)
                        ->save();
                    unset($url, $response, $hash, $size);
                    unset($Snapin);
                }
                unset($StorageNode);
            }
            self::outall(
                sprintf(
                    ' * %s.',
                    _('Completed')
                )
            );
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
        $this->_commonOutput();
        parent::serviceRun();
    }
}
