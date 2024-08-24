<?php
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
    protected $name = 'SnapinTaskComplete_Slack';
    /**
     * The description of the event
     *
     * @var string
     */
    protected $description = 'Triggers when a host completes snapin task';
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
        $hostname = $data['Host']->get('name');
        $snapinname = $data['Snapin']->get('name');
        $statuscode = $data['SnapinTask']->get('return');
        Route::listem('slack');
        $Slacks = json_decode(
            Route::getData()
        );
        foreach ($Slacks->data as $Slack) {
            $args = [
                'channel' => $Slack->name,
                'text' => sprintf(
                    '%s: %s %s %s: %s %s: %s',
                    _('Snapin'),
                    $snapinname,
                    _('has completed on'),
                    _('Host'),
                    $hostname,
                    _('with status code'),
                    $statuscode
                )
            ];
            self::getClass('Slack', $Slack->id)->call('chat.postMessage', $args);
        }
    }
}
