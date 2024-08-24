<?php
/**
 * The event to call when snapin completes
 *
 * @category SnapinComplete_Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinComplete_Slack extends Event
{
    /**
     * The name of this event
     *
     * @var string
     */
    protected $name = 'SnapinComplete_Slack';
    /**
     * The description of this event
     *
     * @var string
     */
    protected $description = 'Triggers when a host completes snapin taskings';
    /**
     * The event is active
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
        self::$EventManager->register(
            'HOST_SNAPIN_COMPLETE',
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
        Route::listem('slack');
        $Slacks = json_decode(
            Route::getData()
        );
        $hostname = $data['Host']->get('name');
        foreach ($Slacks->data as $Slack) {
            $args = [
                'channel' => $Slack->name,
                'text' => sprintf(
                    '%s %s.',
                    $hostname,
                    _('has completed snapin tasking')
                )
            ];
            self::getClass('Slack', $Slack->id)->call('chat.postMessage', $args);
        }
    }
}
