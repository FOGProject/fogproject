<?php
/**
 * Sends notification when snapin task completes.
 *
 * PHP version 5
 *
 * @category SnapinTaskComplete_Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sends notification when snapin task completes.
 *
 * @category SnapinTaskComplete_Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinTaskComplete_Slack extends Event
{
    /**
     * The name of the event
     *
     * @var string
     */
    public $name = 'SnapinTaskComplete_Slack';
    /**
     * The description of the event
     *
     * @var string
     */
    public $description = 'Triggers when a host completes snapin task';
    /**
     * The event is active
     *
     * @var bool
     */
    public $active = true;
    /**
     * Initialize item.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$EventManager->register(
            'HOST_SNAPINTASK_COMPLETE',
            $this
        );
    }
    /**
     * Perform action
     *
     * @param string $event the event to enact
     * @param mixed  $data  the data
     *
     * @return void
     */
    public function onEvent($event, $data)
    {
        foreach ((array)self::getClass('SlackManager')
            ->find() as &$Token
        ) {
            $args = array(
                'channel' => $Token->get('name'),
                'text' => sprintf(
                    'The %s snapin has completed installation on %s with status code: %s',
                    $data['Snapin']->get('name'),
                    $data['Host']->get('name'),
                    $data['SnapinTask']->get('return')
                )
            );
            $Token->call('chat.postMessage', $args);
            unset($Token);
        }
    }
}
