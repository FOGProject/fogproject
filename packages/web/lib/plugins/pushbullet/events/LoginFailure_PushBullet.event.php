<?php
/****************************************************
 *  Called when Login failed
 *	Author:		Tom Elliott
 ***/
require_once(BASEPATH.'/lib/plugins/pushbullet/libs/PushbulletHandler.php');
class LoginFailure_PushBullet extends Event {
	// Class variables
	var $name = 'LoginFailure_PushBullet';
	var $description = 'Triggers when a an invalid login occurs';
	var $author = 'Tom Elliott';
	var $active = true;
	
	public function onEvent($event, $data) {
		foreach ((array)$this->getClass('PushbulletManager')->find() AS $Token) {
            $bulletHandler = new PushbulletHandler($Token->get('token'));
            $bulletHandler->pushNote('', $data['Failure'].' failed to login', 'If you see repeatedly, please check your security');

        }		

	}
}
$EventManager->register('LoginFail', new LoginFailure_PushBullet());
