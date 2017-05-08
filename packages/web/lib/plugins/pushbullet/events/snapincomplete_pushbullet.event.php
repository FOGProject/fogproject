<?php
/**
 * Pushes notification on snapin completion.
 *
 * PHP version 5
 *
 * @category SnapinComplete_PushBullet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pushes notification on snapin completion.
 *
 * @category SnapinComplete_PushBullet
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinComplete_PushBullet extends PushbulletExtends
{
    /**
     * The name of the event.
     *
     * @var string
     */
    protected $name = 'SnapinComplete_PushBullet';
    /**
     * The description of the event.
     *
     * @var string
     */
    protected $description = 'Triggers when a host completes snapin taskings';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$EventManager
            ->register(
                'HOST_SNAPIN_COMPLETE',
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
            'Host %s has completed snapin tasking.',
            $data['Host']->get('name')
        );
        self::$shortdesc = 'Snapin(s) Complete';
        parent::onEvent($event, $data);
    }
}
