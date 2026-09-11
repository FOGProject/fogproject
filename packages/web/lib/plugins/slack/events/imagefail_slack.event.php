<?php
/**
 * The event to call when imaging task fails
 *
 * PHP version 7.4+
 *
 * @category ImageFail_Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        // See the pushbullet listener: the reason is the actionable half of
        // this notification, and every added key is read defensively so the
        // plugin keeps working against a web tree that still sends HostName
        // alone.
        $image = (string) ($data['ImageName'] ?? '');
        if ('' === $image) {
            $image = _('an unnamed image');
        }
        $reason = (string) ($data['Reason'] ?? '');
        if ('' === $reason) {
            $reason = _('no reason was reported');
        }
        foreach ((array)self::getClass('SlackManager')
            ->find() as &$Token
        ) {
            $args = array(
                'channel' => $Token->get('name'),
                // Whole sentence inside _() with positional specifiers: the
                // old form put 'Host: %s ' outside the call and translated
                // the tail on its own, which is not a sentence a translator
                // can work with.
                'text' => sprintf(
                    _('Host %1$s failed imaging %2$s: %3$s'),
                    $data['HostName'],
                    $image,
                    $reason
                )
            );
            $Token->call('chat.postMessage', $args);
            unset($Token);
        }
    }
}
