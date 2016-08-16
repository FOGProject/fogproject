<?php
class LoginFailure_Slack extends Event
{
    // Class variables
    public $name = 'LoginFailure_Slack';
    public $description = 'Triggers when a an invalid login occurs';
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
                'text' => "{$data[Failure]} failed to login.",
            );
            $Token->call('chat.postMessage', $args);
            unset($Token);
        }
    }
}
$EventManager->register('LoginFail', new LoginFailure_Slack());
