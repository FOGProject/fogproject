<?php
class LoginFailure_Slack extends Event {
    // Class variables
    var $name = 'LoginFailure_Slack';
    var $description = 'Triggers when a an invalid login occurs';
    var $author = 'Tom Elliott';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)$this->getClass('SlackManager')->find() AS &$Token) {
            if (!$Token->isValid()) continue;
            $channel_user = preg_match('/^#/',$Token->get('name')) ? $Token->get('name') : "@{$Token->get(name)}";
            $this->getClass('SlackHandler',$Token->get('token'))->call('chat.postMessage',array('channel'=>$channel_user,'text'=>$data['Failure'].' failed to login.'));
            unset($Token);
        }
    }
}
$EventManager->register('LoginFail', new LoginFailure_Slack());
