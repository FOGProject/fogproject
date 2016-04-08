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
        static::$eventloop = function(&$Pushbullet) {
            static::getClass('PushbulletHandler',$Pushbullet->get('token'))->pushNote('',sprintf('%s %s',static::$elements['HostName'],_(static::$shortdesc)),_(static::$message));
        };
    }
    public function onEvent($event,$data) {
        static::$elements = $data;
        array_map(static::$eventloop,(array)static::getClass('PushbulletManager')->find());
    }
}
