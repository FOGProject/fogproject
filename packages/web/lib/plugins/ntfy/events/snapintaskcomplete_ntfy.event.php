<?php
/**
 * Pushes notification on snapin task completion.
 *
 * PHP version 5
 *
 * @category SnapinTaskComplete_Ntfy
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pushes notification on snapin task completion.
 *
 * @category SnapinTaskComplete_Ntfy
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinTaskComplete_Ntfy extends NtfyExtends
{
    /**
     * Name of the event.
     *
     * @var string
     */
    protected $name = 'SnapinTaskComplete_Ntfy';
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
