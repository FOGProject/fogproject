<?php
/**
 * Pushes notification on image completion.
 *
 * PHP version 5
 *
 * @category ImageComplete_PushBullet
 * @package  FOGProject
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pushes notification on image completion.
 *
 * @category ImageComplete_PushBullet
 * @package  FOGProject
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageComplete_PushBullet extends PushbulletExtends
{
    /**
     * Name of this event.
     *
     * @var string
     */
    protected $name = 'ImageComplete_PushBullet';
    /**
     * Description of this event.
     *
     * @var string
     */
    protected $description = 'Triggers when a host finishes imaging';
    /**
     * Active flag.
     *
     * @var bool
     */
    public $active = true;
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
        self::$message = 'This host has finished imaging.';
        self::$shortdesc = 'Imaging Complete';
        parent::onEvent($event, $data);
    }
}
$ImageComplete = new ImageComplete_PushBullet();
$EventManager
    ->register(
        'HOST_IMAGE_COMPLETE',
        $ImageComplete
    );
$EventManager
    ->register(
        'HOST_IMAGEUP_COMPLETE',
        $ImageComplete
    );
