<?php
/****************************************************
 *  Called when Login failed
 *	Author:		Tom Elliott
 ***/
class LoginFailure_PushBullet extends PushbulletExtends {
    // Class variables
    protected $name = 'LoginFailure_PushBullet';
    protected $description = 'Triggers when a an invalid login occurs';
    protected $author = 'Tom Elliott';
    public $active = true;
    public function onEvent($event, $data) {
        static::$message = 'If you see repeatedly, please check your security';
        static::$shortdesc = sprintf('%s %s',$data['Failure'], _('failed to login'));
        parent::onEvent($event,$data);
    }
}
$EventManager->register('LoginFail', new LoginFailure_PushBullet());
