<?php
class ImageFail_Slack extends Event {
    // Class variables
    var $name = 'ImageFail_Slack';
    var $description = 'Triggers when a host fails imaging';
    var $author = 'Tom Elliott';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)self::getClass('SlackManager')->find() AS &$Token) {
            if (!$Token->isValid()) continue;
            $args = array(
                'channel' => $Token->get('name'),
                'text' => "{$data[HostName]} Failed imaging",
            );
            $Token->call('chat.postMessage',$args);
            unset($Token);
        }
    }
}
$EventManager->register('HOST_IMAGE_FAIL', new ImageFail_Slack());
