<?php
/**
 * Image size service for images.
 *
 * PHP version 5
 *
 * @category ImageSize
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Image size service for images.
 *
 * @category ImageSize
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageSize extends FOGService
{
    /**
     * Is the service globally enabled.
     *
     * @var int
     */
    private static $_sizeOn = 0;
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'IMAGESIZESLEEPTIME';
    /**
     * Initializes the ImageSize Class
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
                    'IMAGESIZEDEVICEOUTPUT',
                    'IMAGESIZELOGFILENAME',
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
                'fogimagesize.log'
            )
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty3'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            3600
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
            self::$_sizeOn = self::getSetting('IMAGESIZEGLOBALENABLED');
            if (self::$_sizeOn < 1) {
                throw new Exception(_(' * Image size is globally disabled'));
            }
            foreach ((array)$this->checkIfNodeMaster() as &$StorageNode) {
                $myStorageGroupID = $StorageNode->get('storagegroupID');
                $myStorageNodeID = $StorageNode->get('id');
                $StorageGroup = $StorageNode->getStorageGroup();
                self::outall(
                    sprintf(
                        ' * %s.',
                        _('Starting Image Size Service')
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
                        _('Finding any images associated'),
                        _('with this group'),
                        _('as its primary group')
                    )
                );
                $imageIDs = self::getSubObjectIDs(
                    'ImageAssociation',
                    array(
                        'primary' => 1,
                        'storagegroupID' => $myStorageGroupID
                    ),
                    'imageID'
                );
                $ImageCount = self::getClass('ImageManager')->count(
                    array(
                        'id' => $imageIDs,
                        'isEnabled' => 1
                    )
                );
                if ($ImageCount < 1) {
                    self::outall(
                        sprintf(
                            ' * %s.',
                            _('No images associated with this group as master')
                        )
                    );
                    continue;
                }
                self::outall(
                    sprintf(
                        ' * %s %d %s %s.',
                        _('Found'),
                        $ImageCount,
                        (
                            $ImageCount != 1 ?
                            _('images') :
                            _('image')
                        ),
                        _('to update size values as needed')
                    )
                );
                foreach ((array)self::getClass('ImageManager')
                    ->find(
                        array(
                            'id' => $imageIDs,
                            'isEnabled' => 1
                        )
                    ) as &$Image
                ) {
                    self::outall(
                        sprintf(
                            ' * %s: %s, %s: %d',
                            _('Trying image size for'),
                            $Image->get('name'),
                            _('ID'),
                            $Image->get('id')
                        )
                    );
                    $path = sprintf(
                        '/%s',
                        trim($StorageNode->get('path'), '/')
                    );
                    $file = basename($Image->get('path'));
                    $filepath = sprintf(
                        '%s/%s',
                        $path,
                        $file
                    );
                    if (!file_exists($filepath) || !is_readable($filepath)) {
                        self::outall(
                            sprintf(
                                '| %s: %s',
                                $Image->get('name'),
                                _('Path is unavailable')
                            )
                        );
                        continue;
                    }
                    self::outall(
                        sprintf(
                            ' * %s: %s.',
                            _('Getting image size for'),
                            $Image->get('name')
                        )
                    );
                    $size = self::getFilesize($filepath);
                    unset($path, $file);
                    self::outall(
                        sprintf(
                            ' | %s: %s',
                            _('Size'),
                            $size
                        )
                    );
                    $Image
                        ->set('srvsize', $size)
                        ->save();
                    unset($url, $response, $size);
                    unset($Image);
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
