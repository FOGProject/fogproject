<?php
class ImageComplete_Slack extends Event {
    // Class variables
    var $name = 'ImageComplete_Slack';
    var $description = 'Triggers when a host finishes imaging';
    var $author = 'Tom Elliott';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)$this->getClass('SlackManager')->find() AS &$Token) {
            $this->getClass('SlackHandler',$Token->get('token'))->call('chat.postMessage',array('channel'=>"@{$Token->get(name)}",'text'=>$data['HostName'].' Completed imaging'));
        }
    }
}
$EventManager->register('HOST_IMAGE_COMPLETE', new ImageComplete_Slack());
$EventManager->register('HOST_IMAGEUP_COMPLETE', new ImageComplete_Slack());
