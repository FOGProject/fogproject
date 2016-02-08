<?php
class ImageFail_Slack extends Event {
    // Class variables
    var $name = 'ImageFail_Slack';
    var $description = 'Triggers when a host fails imaging';
    var $author = 'Tom Elliott';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)$this->getClass('SlackManager')->find() AS &$Token) {
            if (!$Token->isValid()) continue;
            $channel_user = preg_match('/^#/',$Token->get('name')) ? $Token->get('name') : "@{$Token->get(name)}";
            $this->getClass('SlackHandler',$Token->get('token'))->call('chat.postMessage',array('channel'=>$channel_user,'text'=>$data['HostName'].' Failed to complete imaging'));
            unset($Token);
        }
    }
}
$EventManager->register('HOST_IMAGE_FAIL', new ImageFail_Slack());
