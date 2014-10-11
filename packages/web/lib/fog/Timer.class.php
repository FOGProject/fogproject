<<<<<<< HEAD
<?php

class Timer
{
	public $debug;
	private $blSingle;
	private $strMin, $strHour, $strDOM, $strMonth, $strDOW;
	private $lngSingle;

	public function __construct( $minute, $hour=null, $dom=null, $month=null, $dow=null )
	{
		if ( $minute != null && $hour == null && $dom == null && $month == null && $dow == null )
		{
			// Single task based on timestamp
			$this->lngSingle = $minute;
			$this->debug = false;
			$this->blSingle = true;
		}
		else
		{
			$this->strMin = $minute;
			$this->strHour = $hour;
			$this->strDOM = $dom;
			$this->strMonth = $month;
			$this->strDOW = $dow;
			$this->debug = false;
			$this->blSingle = false;
		}
	}
	
	public function isSingleRun() {return $this->blSingle;}
	public function getSingleRunTime() {return $this->lngSingle;}
	public function toString()
	{
		return ($this->blSingle ? date("r",$this->lngSingle) : 'Crontab: '.$this->strMin.' '.$this->strHour.' '.$this->strDOM.' '.$this->strMonth.' '.$this->strDOW);
	}
	public function setDebug( $blDebug )
	{
		$this->debug = $blDebug;
	}
	private function shouldSingleRun() {return (time() >= $this->lngSingle ? true : false);}
	public function shouldRunNow()
	{
		if ($this->blSingle)
			return $this->shouldSingleRun();
		else
		{ 
			try
			{
				if (!$this->passesTime($this->strMin,date('i'),60))
					throw new Exception("Failed Minute");
				if (!$this->passesTime($this->strHour,date('H'),24))
					throw new Exception("Failed Hour");
				if (!$this->passesTime($this->strDOM,date('j'),32))
					throw new Exception("Failed DOM");
				if (!$this->passesTime($this->strMonth,date('n'),12))
					throw new Exception("Failed Month");
				if (!$this->passesTime($this->strDOW,date('N'),8))
					throw new Exception("Failed DOW");
				$this->d("All Times Pass\nTask should run.");
				return true;
			}
			catch (Exception $e)
			{
				$this->d($e->getMessage());
			}
		}
		return false;
	}
	
	private function splitOnCommas( $s )
	{
		return explode(  ",", $s);
	}
	
	private function splitOnDash( $s )
	{
		return explode( "-", $s );
	}
	
	private function splitOnSlash( $s )
	{
		return explode( "/", $s );
	}
	
	private function containsDash( $s )
	{
		return strpos( $s  ,  "-" ) !== false;
	}
	
	private function containsSlash( $s )
	{
		return strpos( $s, "/" ) !== false;
	}
	
	private function passesTime($time,$curTime,$routine)
	{
		if (trim($time) == "*") return true;
		$arValues = array();
		$arCommas = $this->splitOnCommas($time);
		foreach ($arCommas AS $arComma)
		{
			$curPiece = trim($arComma);
			($this->containsDash($curPiece) ? $arDashes = $this->splitOnDash($curPiece) : ($this->containsSlash($curPiece) ? $arSlash = $this->splitOnSlash($curPiece) : null));
			if (count($arDashes) == 2 && is_numeric(trim($arDashes[0])) && is_numeric(trim($arDashes[1])))
			{
				for( $t = trim($arDashes[0]); $t <= trim($arDashes[1]); $t++ )
					$arValues[] = $t;
			}
			if (count($arSlash) == 2 && trim($arSlash[0]) == "*" && is_numeric(trim($arSlash[1])))
			{
				$divisor = trim($arSlash[1]);
				// Min: 00 - 59, Hour: 00 - 24, DOM: 1 - 31, Month: 1 - 12, DOW: 1-7 (1 = Monday ..... 7 = Sunday)
				for ($i = 0;$i < $routine;$i++)
				{
					if ($i % $divisor == 0)
						$arValues[] = $i;
				}
			}
			else
			{
				if (is_numeric($curPiece))
					$arValues[] = $curPiece;
			}
		}
		for($i = 0;$i < count($arValues);$i++)
			if (trim($curTime) == $arValues[$i]) return true;
		if ( $this->debug )
			print_r( $arValues );
		return false;
	}
	
	private function d( $s )
	{
		if ( $this->debug )
			echo ( $s . "\n" );
	}
}
=======
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
>>>>>>> dev-branch
