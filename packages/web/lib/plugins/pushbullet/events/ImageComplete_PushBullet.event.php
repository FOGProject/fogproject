<?php

/****************************************************
 *  Called when imaging is completed
 *	Author:		Jbob
 ***/
require_once(BASEPATH.'/lib/plugins/pushbullet/libs/PushbulletHandler.php');


class ImageComplete_PushBullet extends Event {
	// Class variables
	var $name = 'ImageComplete_PushBullet';
	var $description = 'Triggers when a host finishes imaging';
	var $author = 'Jbob';
	var $active = true;
	
	public function onEvent($event, $data) {

		foreach ((array)$this->getClass('PushbulletManager')->find() AS $Token)
        {
            $bulletHandler = new PushbulletHandler($Token->get('token'));
            $bulletHandler->pushNote('', $data['HostName'].' Complete', 'This host has finished imaging');

        }		

	}
}
$EventManager->register('HOST_IMAGE_COMPLETE', new ImageComplete_PushBullet());
$EventManager->register('HOST_IMAGEUP_COMPLETE', new ImageComplete_PushBullet());
