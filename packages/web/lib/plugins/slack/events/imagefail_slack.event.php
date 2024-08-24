<?php
/**
 * The event to call when imaging task fails
 *
 * @category ImageFail_Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageFail_Slack extends Event
{
    /**
     * The name of this event
     *
     * @var string
     */
    public $name = 'ImageFail_Slack';
    /**
     * The description of this event
     *
     * @var string
     */
    public $description = 'Triggers when a host fails imaging';
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
            'HOST_IMAGE_FAIL',
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
        foreach ($Slacks->data as $Slack) {
            $args = [
                'channel' => $Slack->name,
                'text' => sprintf(
                    '%s: %s %s.',
                    _('Host'),
                    $data['HostName'],
                    _('imaging failed')
                )
            ];
            self::getClass('Slack', $Slack->id)->call('chat.postMessage', $args);
        }
    }
}
