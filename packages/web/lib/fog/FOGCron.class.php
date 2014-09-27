<?php
class FOGCron extends FOGGetSet
{
	public static function parse($Cron,$TimeStamp = null)
	{
		try{
			if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i',trim($Cron)))
				throw new Exception("Invalid cron string: ".$Cron);
			if ($TimeStampe && !is_numeric($TimeStamp))
				throw new Exception("Invalid timestamp passed: ".$TimeStamp);
			$Cron = preg_split("/[\s]+/i",trim($Cron));
			$Start = empty($TimeStamp) ? time() : $TimeStamp;
			$date = array(
				'minutes' => self::_parseCronNumbers($Cron[0],0,59),
				'hours' => self::_parseCronNumbers($Cron[1],0,23),
				'dom' => self::_parseCronNumbers($Cron[2],1,31),
				'month' => self::_parseCronNumbers($Cron[3],1,12),
				'dow' => self::_parseCronNumbers($Cron[4],1,7),
			);
			for ($i = 0; $i <= (60*60*24*366); $i += 60)
			{
				$dom = in_array(intval(date('j',$Start+$i)),$date['dom']);
				$month = in_array(intval(date('n',$Start+$i)),$date['month']);
				$dow = in_array(intval(date('w',$Start+$i)),$date['dow']);
				$hours = in_array(intval(date('G',$Start+$i)),$date['hours']);
				$minutes = in_array(intval(date('i',$Start+$i)),$date['minutes']);
				if ($dom && $month && $dow && $hours && $minutes)
					return $Start+$i;
			}
		}
		catch (Exception $e)
		{
		}
		return false;
	}

	protected static function _parseCronNumbers($s,$min,$max)
	{
		$result = array();
		$v = explode(',',$s);
		foreach($v AS $vv)
		{
			$vvv = explode('/',$vv);
			$step = empty($vvv[1]) ? 1 : $vvv[1];
			$vvvv = explode('-',$vvv[0]);
			$_min = count($vvvv) == 2 ? $vvvv[0] : ($vvv[0] == '*' ? $min : $vvv[0]);
			$_max = count($vvvv) == 2 ? $vvvv[1] : ($vvv[0] == '*' ? $max : $vvv[1]);
			for ($i = $_min; $i <= $_max; $i += $step)
			{
				$result[$i] = intval($i);
			}
		}
		ksort($result);
		return $result;
	}

	public function shouldRunCron($Time)
	{
		$CurrTime = $this->nice_date();
		if ($Time <= $CurrTime)
			return true;
		return false;
	}
}
