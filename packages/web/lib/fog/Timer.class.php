<?php

class Timer
{
	public $debug;
	private $blSingle;
	private $strMin, $strHour, $strDOM, $strMonth, $strDOW;
	private $lngSingle;

	function __construct( $minute, $hour=null, $dom=null, $month=null, $dow=null )
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
	
	function isSingleRun() { return $this->blSingle; }
	
	function getSingleRunTime()
	{
		return $this->lngSingle;
	}
	
	function toString()
	{
		return ($this->blSingle ? date("r",$this->lngSingle) : 'Crontab: '.$this->strMin.' '.$this->strHour.' '.$this->strDOM.' '.$this->strMonth.' '.$this->strDOW);
	}
	
	public function setDebug( $blDebug )
	{
		$this->debug = $blDebug;
	}
	
	private function shouldSingleRun()
	{
		if(time() >= $this->lngSingle)
			return true;
		else
			return false;
	}
	
	public function shouldRunNow()
	{
		if ($this->blSingle)
			return $this->shouldSingleRun();
		else
		{ 
			if ($this->passesTime($this->strMin,date('i'),60))
			{
				$this->d( "passed minute" );
				if ($this->passesTime($this->strHour,date('H'),24))
				{
					$this->d( "passed hour" );
					if ( $this->passesTime($this->strDOM,date('j'),32))
					{
						$this->d( "passed DOM" );
						if ( $this->passesTime($this->strMonth,date('n'),13))
						{
							$this->d( "passed Month" );
							if ( $this->passesTime($this->strDOW,date('N'),8) )
							{
								$this->d( "passed DOW" );
								$this->d( "task should run." );
								return true;
							}
							else
								$this->d( "Failed DOW" );
						}
						else
							$this->d( "Failed Month" );
					}
					else
						$this->d( "Failed DOM" );
				}
				else
					$this->d( "Failed hour" );
			}
			else
				$this->d( "Failed minute" );
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
			
			if ($this->containsDash($curPiece))
			{
				$arDashes = $this->splitOnDash($curPiece);
				if (count($arDashes) == 2)
				{
					if ( is_numeric( trim($arDashes[0]) ) && is_numeric( trim($arDashes[1]) ) )
					{
						for( $t = trim($arDashes[0]); $t <= trim($arDashes[1]); $t++ )
						{
							$arValues[] = $t;
						}
					}
				}
			}
			else if  ( $this->containsSlash( $curPiece ) )
			{
				$arSlash = $this->splitOnSlash( $curPiece );
				if ( count( $arSlash ) == 2 )
				{
					if ( trim( $arSlash[0] ) == "*" && is_numeric( trim( $arSlash[1] ) ) )
					{
						$divisor = trim($arSlash[1]);
						// Min: 00 - 59, Hour: 00 - 24, DOM: 1 - 31, Month: 1 - 12, DOW: 1-7 (1 = Monday ..... 7 = Sunday)
						for ( $i = 0; $i < $routine; $i++ )
						{
							if ( $i % $divisor == 0 )
								$arValues[] = $i;
						}
					}
				}
			}
			else
			{
				if ( is_numeric( $time ) )
				{
					$arValues[] = $time;
				}
			}
		}
		
		for( $i = 0; $i < count( $arValues ); $i++ )
		{
			if ( trim($curTime) == $arValues[$i] ) return true;
		}
		
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
