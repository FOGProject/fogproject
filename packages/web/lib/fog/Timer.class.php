<?php
class Timer extends FOGBase
{
	private $blSingle;
	private $cron;
	private $lngSingle;
	public function __construct($minute,$hour=null,$dom=null,$month=null,$dow=null)
	{
		parent::__construct();
		if ( $minute != null && $hour == null && $dom == null && $month == null && $dow == null )
		{
			// Single task based on timestamp
			$this->lngSingle = $minute;
			$this->debug = false;
			$this->blSingle = true;
		}
		else
		{
			$this->cron = $minute.' '.$hour.' '.$dom.' '.$month.' '.$dow;
			$this->lngSingle = FOGCron::parse($this->cron);
			$this->debug = false;
			$this->blSingle = false;
		}
	}
	
	public function isSingleRun() {return $this->blSingle;}
	public function getSingleRunTime() {return $this->lngSingle;}
	public function toString()
	{
		$runTime = $this->nice_date()->setTimestamp($this->lngSingle);
		return $runTime->format('r');
	}
	public function setDebug($blDebug)
	{
		$this->debug = $blDebug;
	}
	private function shouldSingleRun() {return ($this->nice_date() >= $this->nice_date()->setTimestamp($this->lngSingle));}
	public function shouldRunNow()
	{
		if ($this->blSingle)
			return $this->shouldSingleRun();
		else
		{
			$runTime = $this->nice_date()->setTimestamp($this->lngSingle);
			return FOGCron::shouldRunCron($runTime);
		}
		return false;
	}
	
	private function d($s)
	{
		if ($this->debug)
			echo ($s."\n");
	}
}
