<?php
class FOGCron extends FOGBase {
    public static function parse(&$FOGCore,$Cron,$TimeStamp = null) {
        $Cron = preg_split("/[\s]+/",trim($Cron));
        if ($Cron[4] == 0) $Cron[4] = 7;
        $Start = empty($TimeStamp) ? $FOGCore->nice_date() : $FOGCore->nice_date($TimeStamp);
        $date = array(
            'minutes' => self::_parseCronNumbers($Cron[0],0,59),
            'hours' => self::_parseCronNumbers($Cron[1],0,23),
            'dom' => self::_parseCronNumbers($Cron[2],1,31),
            'month' => self::_parseCronNumbers($Cron[3],1,12),
            'dow' => self::_parseCronNumbers($Cron[4],1,7),
        );
        $increment = '+1 minute';
        while (true) {
            if ($dom && $month && $dow && $hours && $minutes) return $Start->getTimestamp();
            $Start->modify($increment);
            if (!$minutes) $minutes = in_array((int)$Start->format('i'),$date['minutes']);
            if ($minutes) $increment = '+1 hour';
            if (!$hours) $hours = in_array((int)$Start->format('H'),$date['hours']);
            if ($hours) $increment = '+1 day';
            $dom = in_array((int)$Start->format('j'),$date['dom']);
            $month = in_array((int)$Start->format('n'),$date['month']);
            $dow = in_array((int)$Start->format('N'),$date['dow']);
        }
    }
    private static function checkField($field, $min, $max) {
        $field = trim($field);
        if ($field != 0 && empty($field)) return false;
        $v = explode(',',$field);
        foreach ($v AS &$vv) {
            $vvv = explode('/',$vv);
            $step = !$vvv[1] ? 1 : $vvv[1];
            $vvvv = explode('-',$vvv[0]);
            $_min = count($vvvv) == 2 ? $vvvv[0] : ($vvv[0] == '*' ? $min : $vvv[0]);
            $_max = count($vvvv) == 2 ? $vvvv[1] : ($vvv[0] == '*' ? $max : $vvv[0]);
            $res = self::checkIntValue($step,$min,$max,true);
            if ($res) $res = self::checkIntValue($_min,$min,$max,true);
            if ($res) $res = self::checkIntValue($_max,$min,$max,true);
        }
        return $res;
    }
    private static function checkIntValue($value,$min,$max,$extremity) {
        $val = intval($value,10);
        if ($value != $val) return false;
        if (!$extremity) return true;
        if ($val >= $min && $val <= $max) return true;
        return false;
    }
    public static function checkMinutesField($minutes) {
        return self::checkField($minutes,0,59);
    }
    public static function checkHoursField($hours) {
        return self::checkField($hours,0,23);
    }
    public static function checkDOMField($dom) {
        return self::checkField($dom,1,31);
    }
    public static function checkMonthField($month) {
        return self::checkField($month,1,12);
    }
    public static function checkDOWField($dow) {
        return self::checkField($dow,0,7);
    }
    protected static function _parseCronNumbers($s,$min,$max) {
        $result = null;
        $v = explode(',',$s);
        foreach($v AS $i => &$vv) {
            $vvv = explode('/',$vv);
            $step = empty($vvv[1]) ? 1 : $vvv[1];
            $vvvv = explode('-',$vvv[0]);
            $_min = count($vvvv) == 2 ? $vvvv[0] : ($vvv[0] == '*' ? $min : $vvv[0]);
            $_max = count($vvvv) == 2 ? $vvvv[1] : ($vvv[0] == '*' ? $max : $vvv[0]);
            for ($i = $_min; $i <= $_max; $i += $step) $result[] = (int)$i;
        }
        unset($vv);
        ksort($result);
        return $result;
    }
    public function shouldRunCron($Time) {
        $CurrTime = $this->nice_date();
        return ($Time <= $CurrTime);
    }
}
