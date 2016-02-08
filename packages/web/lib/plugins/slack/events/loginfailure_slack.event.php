<?php
class LoginFailure_Slack extends Event {
    // Class variables
    var $name = 'LoginFailure_Slack';
    var $description = 'Triggers when a an invalid login occurs';
    var $author = 'Tom Elliott';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)$this->getClass('SlackManager')->find() AS $Token) {
            $this->getClass('SlackHandler',$Token->get('token'))->call('chat.postMessage',array('channel'=>"@{$Token->get(name)}",'text'=>$data['Failure'].' failed to login.'));
        }
    }
}
$EventManager->register('LoginFail', new LoginFailure_Slack());
