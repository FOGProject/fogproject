<?php
/****************************************************
 *  Called when Login failed
 *	Author:		Tom Elliott
 ***/
class LoginFailure_PushBullet extends Event {
	// Class variables
	var $name = 'LoginFailure_PushBullet';
	var $description = 'Triggers when a an invalid login occurs';
	var $author = 'Tom Elliott';
	var $active = true;
	public function onEvent($event, $data) {
		foreach ((array)$this->getClass('PushbulletManager')->find() AS $Token) {
			$this->getClass('PushbulletHandler',$Token->get('token'))->pushNote('', $data['Failure'].' failed to login', 'If you see repeatedly, please check your security');
		}
	}
}
$EventManager->register('LoginFail', new LoginFailure_PushBullet());
