<?php
class ImageFail_Slack extends Event
{
    // Class variables
    public $name = 'ImageFail_Slack';
    public $description = 'Triggers when a host fails imaging';
    public $author = 'Tom Elliott';
    public $active = true;
    public function onEvent($event, $data)
    {
        foreach ((array)self::getClass('SlackManager')->find() as &$Token) {
            if (!$Token->isValid()) {
                continue;
            }
            $args = array(
                'channel' => $Token->get('name'),
                'text' => "{$data[HostName]} Failed imaging",
            );
            $Token->call('chat.postMessage', $args);
            unset($Token);
        }
    }
}
$EventManager->register('HOST_IMAGE_FAIL', new ImageFail_Slack());
