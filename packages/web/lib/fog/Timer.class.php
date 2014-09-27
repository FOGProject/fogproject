<?php
class Timer extends FOGBase
{
	private $blSingle;
	private $strMin, $strHour, $strDOM, $strMonth, $strDOW;
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
		$runTime = DateTime::createFromFormat('U',$this->lngSingle);
		return ($this->blSingle ? $runTime->format('r') : 'Crontab: '.$this->strMin.' '.$this->strHour.' '.$this->strDOM.' '.$this->strMonth.' '.$this->strDOW);
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
			try
			{
				$curdate = $this->nice_date();
				if (!$this->passesTime($this->strMin,$curdate->format('i'),60))
					throw new Exception("Failed Minute");
				if (!$this->passesTime($this->strHour,$curdate->format('H'),24))
					throw new Exception("Failed Hour");
				if (!$this->passesTime($this->strDOM,$curdate->format('j'),32))
					throw new Exception("Failed DOM");
				if (!$this->passesTime($this->strMonth,$curdate->format('n'),12))
					throw new Exception("Failed Month");
				if (!$this->passesTime($this->strDOW,$curdate->format('N'),8))
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
	
	private function splitOnCommas($s)
	{
		return explode(",",$s);
	}
	
	private function splitOnDash($s)
	{
		return explode("-",$s);
	}
	
	private function splitOnSlash($s)
	{
		return explode("/",$s);
	}

	private function containsDash($s)
	{
		return strpos($s,"-") !== false;
	}
	
	private function containsSlash($s)
	{
		return strpos($s,"/") !== false;
	}
	
	private function passesTime($time,$curTime,$routine)
	{
		if (trim($time) == "*") return true;
		$arValues = array();
		$arCommas = $this->splitOnCommas($time);
		foreach ($arCommas AS $arComma)
		{
			$curPiece = trim($arComma);
			if ($this->containsDash($curPiece))
				$arDashes = $this->splitOnDash($curPiece);
			if ($this->containsSlash($curPiece))
				$arSlashes = $this->splitOnSlash($curPiece);
			if (count($arDashes) == 2)
			{
				if (is_numeric(trim($arDashes[0])) && is_numeric(trim($arDashes[1])))
				{
					for ($t = trim($arDashes[0]); $t <= trim($arDashes[1]); $t++)
						$arValues[] = $t;
				}
			}
			if (count($arSlash) == 2)
			{
				// Min: 00 - 59, Hour: 00 - 24, DOM: 1 - 31, Month: 1 - 12, DOW: 1-7 (1 = Monday ..... 7 = Sunday)
				if (trim($arSlash[0]) == "*" && is_numeric(trim($arSlash[1])))
				{
					$divisor = trim($arSlash[1]);
					for ($i = 0;$i < $routine;$i++)
					{
						if ($i % $divisor == 0)
							$arValues[] = $i;
					}
				}
			}
			else
			{
				if (is_numeric($curPiece))
					$arValues[] = $curPiece;
			}
		}
		foreach((array)$arValues AS $Value)
		{
			if (trim($curTime) == $Value)
				return true;
		}
		if ($this->debug)
			print_r($arValues);
		return false;
	}
	
	private function d($s)
	{
		if ($this->debug)
			echo ($s."\n");
	}
}
