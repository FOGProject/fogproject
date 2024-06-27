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
        $imagesizekeys = [
            'IMAGESIZEDEVICEOUTPUT',
            'IMAGESIZELOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($imagesizekeys);
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
            self::$_sizeOn = self::getSetting('IMAGESIZEGLOBALENABLED');
            if (self::$_sizeOn < 1) {
                throw new Exception(_(' * Image size is globally disabled'));
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
                        _('Starting Image Size Service')
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
                        _('Finding any images associated'),
                        _('with this group'),
                        _('as its primary group')
                    )
                );
                $find = [
                    'primary' => 1,
                    'storagegroupID' => $myStorageGroupID
                ];
                Route::ids(
                    'imageassociation',
                    $find,
                    'imageID'
                );
                $imageIDs = json_decode(Route::getData(), true);
                $find = [
                    'id' => $imageIDs,
                    'isEnabled' => 1
                ];
                Route::ids(
                    'image',
                    $find
                );
                $imageIDs = json_decode(Route::getData(), true);
                $ImageCount = count($imageIDs ?: []);
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
                Route::listem(
                    'image',
                    ['id' => $imageIDs]
                );
                $Images = json_decode(
                    Route::getData()
                );
                foreach ($Images->data as $Image) {
                    self::outall(
                        sprintf(
                            ' * %s: %s, %s: %d',
                            _('Trying image size for'),
                            $Image->name,
                            _('ID'),
                            $Image->id
                        )
                    );
                    $path = sprintf(
                        '/%s',
                        trim($StorageNode->path, '/')
                    );
                    $file = basename($Image->path);
                    $filepath = sprintf(
                        '%s/%s',
                        $path,
                        $file
                    );
                    if (!file_exists($filepath) || !is_readable($filepath)) {
                        self::outall(
                            sprintf(
                                '| %s: %s',
                                $Image->name,
                                _('Path is unavailable')
                            )
                        );
                        self::getClass('Image', $Image->id)
                            ->set('srvsize', $size)
                            ->save();
                        continue;
                    }
                    self::outall(
                        sprintf(
                            ' * %s: %s.',
                            _('Getting image size for'),
                            $Image->name
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
                    self::getClass('Image', $Image->id)
                        ->set('srvsize', $size)
                        ->save();
                    unset($url, $response, $size);
                }
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
