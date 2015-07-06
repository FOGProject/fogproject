<?php
class FOGCron extends FOGBase {
    public static function parse($Cron,$TimeStamp = null) {
        global $FOGCore;
        if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i',trim($Cron))) throw new Exception("Invalid cron string: ".$Cron);
        if ($TimeStamp && !is_numeric($TimeStamp)) throw new Exception("Invalid timestamp passed: ".$TimeStamp);
        $Cron = preg_split("/[\s]+/",trim($Cron));
        $Start = empty($TimeStamp) ? $FOGCore->nice_date()->getTimestamp() : $TimeStamp;
        $date = array(
            'minutes' => self::_parseCronNumbers($Cron[0],0,59),
            'hours' => self::_parseCronNumbers($Cron[1],0,23),
            'dom' => self::_parseCronNumbers($Cron[2],1,31),
            'month' => self::_parseCronNumbers($Cron[3],1,12),
            'dow' => self::_parseCronNumbers($Cron[4],0,6),
        );
        for ($i = 0; $i <= (60*60*24*366); $i += 60) {
            $minutes = in_array(intval($FOGCore->nice_date('',true)->setTimestamp($Start+$i)->format('i')),$date['minutes']);
            $hours = in_array(intval($FOGCore->nice_date('',true)->setTimestamp($Start+$i)->format('H')),$date['hours']);
            $dom = in_array(intval($FOGCore->nice_date('',true)->setTimestamp($Start+$i)->format('j')),$date['dom']);
            $month = in_array(intval($FOGCore->nice_date('',true)->setTimestamp($Start+$i)->format('n')),$date['month']);
            $dow = in_array(intval($FOGCore->nice_date('',true)->setTimestamp($Start+$i)->format('N')),$date['dow']);
            if ($dom && $month && $dow && $hours && $minutes) return $Start+$i;
        }
        return false;
    }
    protected static function _parseCronNumbers($s,$min,$max) {
        $result = null;
        $v = explode(',',$s);
        foreach($v AS $vv) {
            $vvv = explode('/',$vv);
            $step = empty($vvv[1]) ? 1 : $vvv[1];
            $vvvv = explode('-',$vvv[0]);
            $_min = count($vvvv) == 2 ? $vvvv[0] : ($vvv[0] == '*' ? $min : $vvv[0]);
            $_max = count($vvvv) == 2 ? $vvvv[1] : ($vvv[0] == '*' ? $max : $vvv[0]);
            for ($i = $_min; $i <= $_max; $i += $step) $result[] = intval($i);
        }
        ksort($result);
        return $result;
    }
    public function shouldRunCron($Time) {
        $CurrTime = $this->nice_date();
        return ($Time <= $CurrTime);
    }
}
