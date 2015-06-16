<?php
abstract class Event extends FOGBase {
	/** @var $name the name of the event/hook */
	protected $name;
	/** @var $description the description of the event/hook */
	protected $description;
	/** @var $author the author of the event/hook */
	protected $author;
	/** @var $active if the event/hook is active */
	public $active = true;
	/** @var $logLevel the logging level of the event/hook */
	public $logLevel = 0;
	/** @var $logToFile send log to file for the event/hook */
	public $logToFile = false;
	/** @var $logToBrowser send log to the browser for the event/hook */
	public $logToBrowser = true;
	/** @var $delformat the link format for deleting from event/hook */
	public $delformat;
	/** @function __construct() constructs the base elements
	  * @return void
	  */
	public function __construct() {
		parent::__construct();
		if (!$this->FOGUser)
			$this->FOGUser = unserialize($_SESSION['FOG_USER']);
	}
	/** @function run() what to run if anything
	  * @param $arguments the event/hookevent to enact upon
	  * @return void
	  */
	public function run($arguments) {}
	/** @function log() logs the Event
	  * @param $txt the text to log
	  * @param $level the level of logging defaults to 1
	  * @return void
	  */
	public function log($txt, $level = 1) {
		$log = trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt));
		if ($this->logToBrowser && $this->logLevel >= $level && !$this->isAJAXRequest())
			printf('%s<div class="debug-event">%s</div>%s', "\n", $log, "\n");
		if ($this->logToFile)
			file_put_contents(BASEPATH . '/lib/events/' . get_class($this) . '.log', sprintf("[%s] %s\r\n", $this->nice_date()->format("d-m-Y H:i:s"), $log), FILE_APPEND | LOCK_EX);
	}
	/** @function onEvent() when the event occurs have it register the event as well
	  *     can be used for hooks as well
	  * @param $event the event to register
	  * @param $data the data to work with
	  * @return void
	  */
	public function onEvent($event, $data) {
		print $event.' Registered';
	}
}
