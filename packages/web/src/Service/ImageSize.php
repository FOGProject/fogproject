<?php
/**
 * Image size service for images.
 *
 * PHP version 7.4+
 *
 * @category ImageSize
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Service;

/**
 * Image size service for images.
 *
 * The walk lives in FOGItemScanner, shared with SnapinHash. All that is here
 * is what genuinely differs: the table, and what to record about the file.
 *
 * The messages are literal _() calls rather than something the base builds
 * from a noun, and that is deliberate: gettext extracts msgids from the
 * source text, so _("Trying $noun size for") would never translate and would
 * never appear in the .pot -- silently, forever.
 *
 * @category ImageSize
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageSize extends FOGItemScanner
{
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'IMAGESIZESLEEPTIME';
    /**
     * Fallback sleep when the globalSetting above is unset.
     *
     * @var int
     */
    public static $sleepdefault = 3600;
    /**
     * Everything that differs from the other scanner.
     *
     * @return array
     */
    protected function descriptor()
    {
        return [
            'prefix' => 'IMAGESIZE',
            'log' => 'fogimagesize.log',
            'dev' => '/dev/tty3',
            'zzz' => 3600,
            'route' => 'image',
            'assocRoute' => 'imageassociation',
            'assocField' => 'imageID',
            'model' => 'Image',
            'nodePathField' => 'path',
            'itemFileField' => 'path',
            'msg' => [
                'disabled' => _(' * Image size is globally disabled'),
                'starting' => _('Starting Image Size Service'),
                'finding' => _('Finding any images associated'),
                'none' => _('No images associated with this group as master'),
                'plural' => _('images'),
                'singular' => _('image'),
                'tail' => _('to update size values as needed'),
                'trying' => _('Trying image size for'),
                'getting' => _('Getting image size for')
            ]
        ];
    }
    /**
     * Records the size of the image file.
     *
     * @param object $item     The image row.
     * @param string $filepath The file.
     *
     * @return void
     */
    protected function updateItem($item, $filepath)
    {
        $size = self::getFilesize($filepath);
        self::outall(
            sprintf(
                ' | %s: %s',
                _('Size'),
                $size
            )
        );
        self::getClass($this->modelClass(), $item->id)
            ->set('srvsize', $size)
            ->save();
    }
    /**
     * Records that the image file is not there.
     *
     * @param object $item The image row.
     *
     * @return void
     */
    protected function clearItem($item)
    {
        self::getClass($this->modelClass(), $item->id)
            ->set('srvsize', 0)
            ->save();
    }
}
