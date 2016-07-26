<?php
abstract class PushbulletExtends extends Event {
    protected $name;
    protected $description;
    protected $author;
    public $active;
    protected static $eventloop;
    protected static $elements;
    protected static $shortdesc;
    protected static $message;
    public function __construct() {
        parent::__construct();
        self::$eventloop = function(&$Pushbullet) {
            self::getClass('PushbulletHandler',$Pushbullet->get('token'))->pushNote('',sprintf('%s %s',self::$elements['HostName'],_(self::$shortdesc)),_(self::$message));
        };
    }
    public function onEvent($event,$data) {
        self::$elements = $data;
        array_map(self::$eventloop,(array)self::getClass('PushbulletManager')->find());
    }
}
