<?php
/****************************************************
 *  Called when imaging is completed
 *	Author:		Jbob
 ***/
class ImageComplete_PushBullet extends Event {
    // Class variables
    var $name = 'ImageComplete_PushBullet';
    var $description = 'Triggers when a host finishes imaging';
    var $author = 'Jbob';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)self::getClass('PushbulletManager')->find() AS $Token) {
            self::getClass('PushbulletHandler',$Token->get('token'))->pushNote('', $data['HostName'].' Complete', 'This host has finished imaging');
        }
    }
}
$EventManager->register('HOST_IMAGE_COMPLETE', new ImageComplete_PushBullet());
$EventManager->register('HOST_IMAGEUP_COMPLETE', new ImageComplete_PushBullet());
