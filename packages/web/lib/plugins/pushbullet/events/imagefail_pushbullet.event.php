<?php
/**
 * Pushes notification on imaging failure.
 *
 * PHP version 7.4+
 *
 * @category ImageFail_PushBullet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pushes notification on imaging failure.
 *
 * @category ImageFail_PushBullet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageFail_PushBullet extends PushbulletExtends
{
    /**
     * The name of the event.
     *
     * @var string
     */
    protected $name = 'ImageFail_PushBullet';
    /**
     * The description of the event.
     *
     * @var string
     */
    protected $description = 'Triggers when a host fails imaging';
    /**
     * Active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * Initialize object
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$EventManager
            ->register(
                'HOST_IMAGE_FAIL',
                $this
            );
    }
    /**
     * Perform action when event met.
     *
     * @param string $event The event to perform from.
     * @param mixed  $data  The data to send.
     *
     * @return void
     */
    public function onEvent($event, $data)
    {
        // TaskError sends the image and the reason FOS gave, and the reason is
        // the only part of this an admin can act on -- "failed to image" says
        // nothing the task list did not already show. Read defensively: a
        // server whose web tree predates that change sends HostName alone, and
        // reading a missing key would turn the notification into a PHP warning
        // in an event that only fires when something has already gone wrong.
        $image = (string) ($data['ImageName'] ?? '');
        if ('' === $image) {
            $image = _('an unnamed image');
        }
        $reason = (string) ($data['Reason'] ?? '');
        if ('' === $reason) {
            $reason = _('no reason was reported');
        }
        self::$shortdesc = _('Imaging Failed');
        // Composed here rather than handed to the base class as a bare
        // literal, because the substitution has to happen AFTER translation:
        // a msgid built at runtime matches no catalog entry and never
        // translates. The base's _() then finds nothing and passes this
        // through unchanged. Positional specifiers so a translator can
        // reorder the sentence.
        self::$message = sprintf(
            _('This host failed imaging %1$s: %2$s'),
            $image,
            $reason
        );
        parent::onEvent($event, $data);
    }
}
