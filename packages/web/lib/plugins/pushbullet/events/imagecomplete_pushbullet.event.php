<?php
/****************************************************
 *  Called when imaging is completed
 *	Author:		Jbob
 ***/
class ImageComplete_PushBullet extends PushbulletExtends {
    // Class variables
    protected $name = 'ImageComplete_PushBullet';
    protected $description = 'Triggers when a host finishes imaging';
    protected $author = 'Jbob';
    public $active = true;
    public function onEvent($event, $data) {
        self::$message = 'This host has finished imaging.';
        self::$shortdesc = 'Imaging Complete';
        parent::onEvent($event,$data);
    }
}
$EventManager->register('HOST_IMAGE_COMPLETE', new ImageComplete_PushBullet());
$EventManager->register('HOST_IMAGEUP_COMPLETE', new ImageComplete_PushBullet());
