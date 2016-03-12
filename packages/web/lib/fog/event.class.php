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
    public function log($txt, $level = 1) {
        if ($this->ajax) return;
        $txt = trim(preg_replace(array("#\r#","#\n#",'#\s+#','# ,#'),array('',' ',' ',','),$txt));
        if (empty($txt)) return;
        $txt = sprintf('[%s] %s',$this->nice_date()->format('Y-m-d H:i:s'),$txt);
        if ($this->logToBrowser && $this->logLevel >= $level && !$this->post) printf('%s<div class="debug-hook">%s</div>%s',"\n",$txt,"\n");
        if ($this->logToFile) file_put_contents(sprintf('%s/lib/%s/%s.log',BASEPATH,$this instanceof Hook ? 'hooks' : 'events',get_class($this)),sprintf("[%s] %s\r\n", $this->nice_date()->format('d-m-Y H:i:s'),$log), FILE_APPEND | LOCK_EX);
    }
    public function run($arguments) {
    }
    public function onEvent($event, $data) {
        printf('%s %s',$event,_('Registered'));
    }
}
