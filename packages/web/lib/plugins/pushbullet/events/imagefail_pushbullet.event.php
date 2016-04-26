<?php
/****************************************************
 *  Called when imaging fails
 *	Author:		Jbob
 ***/
class ImageFail_PushBullet extends PushbulletExtends {
    // Class variables
    protected $name = 'ImageFail_PushBullet';
    protected $description = 'Triggers when a host fails imaging';
    protected $author = 'Jbob';
    public $active = true;
    public function onEvent($event, $data) {
        static::$message = 'This host has failed to image';
        static::$shortdesc = 'Failed';
        parent::onEvent($event,$data);
    }
}
$EventManager->register('HOST_IMAGE_FAIL', new ImageFail_PushBullet());
