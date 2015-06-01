<?php
abstract class FOGService extends FOGBase {
	/** @var $dev string the device output for console */
	public $dev;
	/** @var $log string the log file to write to */
	public $log;
	/** @var $zzz int the sleep time for the service */
	public $zzz;
	/** @function outall() outputs to log file
	  * @param $string string the data to write
	  * @return null
	  */
	public function outall($string) {
		$this->FOGCore->out($string, $this->dev);
		$this->FOGCore->wlog($string, $this->log);
		return;
	}
	/** @function serviceStart() starts the service
	  * @return null
	  */
	public function serviceStart() {
		$this->FOGCore->out($this->FOGCore->getBanner(), $this->log);
		$this->outall(sprintf(' * Starting %s Service',get_class($this)));
		$this->outall(sprintf(' * Checking for new items every %s seconds',$this->zzz));
		$this->outall(' * Starting service loop');
		return;
	}
	public function serviceRun() {
		$this->FOGCore->out(' ', $this->dev);
		$this->FOGCore->out(' +---------------------------------------------------------', $this->dev);
	}
}
