<?php
/**
 * Pushes notification on image completion.
 *
 * PHP version 5
 *
 * @category ImageComplete_PushBullet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pushes notification on image completion.
 *
 * @category ImageComplete_PushBullet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinTaskComplete_PushBullet extends PushbulletExtends
{
    /**
     * Name of the event.
     *
     * @var string
     */
    protected $name = 'SnapinTaskComplete_PushBullet';
    /**
     * Description of the event.
     *
     * @var string
     */
    protected $description = 'Triggers when a host completes snapin task';
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
                'HOST_SNAPINTASK_COMPLETE',
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
        self::$message = sprintf(
            'The snapin has completed installation on %s with status code: %s',
            $data['Host']->get('name'),
            $data['SnapinTask']->get('return')
        );
        self::$shortdesc = sprintf(
            '%s completed',
            $data['Snapin']->get('name')
        );
        parent::onEvent($event, $data);
    }
}
