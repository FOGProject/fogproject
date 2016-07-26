<?php
/****************************************************
 *  Called when snapin tasking is complete
 *	Author:		Tom Elliott
 ***/
class SnapinTaskComplete_Slack extends PushbulletExtends {
    // Class variables
    protected $name = 'SnapinTaskComplete_Slack';
    protected $description = 'Triggers when a host completes snapin task';
    protected $author = 'Tom Elliott';
    public $active = true;
    public function onEvent($event, $data) {
        self::$message = sprintf('The snapin has completed installation on %s with status code: %s',$data['Host']->get('name'),$data['SnapinTask']->get('return'));
        self::$shortdesc = sprintf('%s completed',$data['Snapin']->get('name'));
        parent::onEvent($event,$data);
    }
}
$EventManager->register('HOST_SNAPINTASK_COMPLETE', new SnapinTaskComplete_Slack());
