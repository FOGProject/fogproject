<?php
abstract class Event extends FOGBase {
    protected $name;
    protected $description;
    protected $author;
    public $active = true;
    public $logLevel = 0;
    public $logToFile = false;
    public $logToBrowser = true;
    public $delformat;
    public function __construct() {
        parent::__construct();
        $this->FOGUser = ($_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : $this->getClass('User'));
    }
    public function run($arguments) {
    }
    public function log($txt, $level = 1) {
        $log = trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt));
        if ($this->logToBrowser && $this->logLevel >= $level && !$this->ajax) printf('%s<div class="debug-event">%s</div>%s', "\n", $log, "\n");
        if ($this->logToFile) file_put_contents(BASEPATH.'/lib/events/'.get_class($this).'.log',sprintf("[%s] %s\r\n",$this->nice_date()->format("d-m-Y H:i:s"),$log),FILE_APPEND|LOCK_EX);
    }
    public function onEvent($event, $data) {
        echo $event.' Registered';
    }
}
