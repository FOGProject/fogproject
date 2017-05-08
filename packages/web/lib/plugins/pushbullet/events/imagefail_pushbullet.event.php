<?php
/**
 * Pushes notification on imaging failure.
 *
 * PHP version 5
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
        self::$message = 'This host has failed to image';
        self::$shortdesc = 'Failed';
        parent::onEvent($event, $data);
    }
}
