<?php
class FOGCron extends FOGBase {
    private static function fit($str,$num) {
        if (strpos($str,',')) {
            $arr = explode(',',$str);
            foreach ($arr AS &$element) return (self::fit($element,(int)$num));
        }
        if (strpos($str,'-')) {
            list($low,$high) = explode('-',$str);
            return ($num = (int)$low);
        }
        if (strpos($str,'/')) {
            list($pre,$pos) = explode('/',$str);
            if ($pre == '*') return ($num % (int)$pos == 0);
            return ($num % (int)$pos == (int)$pre);
        }
        return ((int)$str == $num);
    }
    public static function parse(&$FOGCore,$Cron,$TimeStamp = null) {
        list($min,$hour,$dom,$month,$dow) = preg_split('/[\s]+/',trim($Cron));
        if (is_numeric($dow) && $dow == 0) $dow = 7;
        $Start = empty($TimeStamp) ? $FOGCore->nice_date() : $FOGCore->nice_date()->setTimestamp($TimeStamp);
        do {
            list($nmin,$nhour,$ndom,$nmonth,$ndow) = preg_split('/[\s]+/',$Start->format('i H j n N'));
            if ($min != '*') {
                if (!self::fit($min,(int)$nmin)) {
                    $Start->modify('+1 minute');
                    continue;
                }
            }
            if ($hour != '*') {
                if (!self::fit($hour,(int)$nhour)) {
                    $Start->modify('+1 hour');
                    continue;
                }
            }
            if ($dom != '*') {
                if (!self::fit($dom,(int)$ndom)) {
                    $Start->modify('+1 day');
                    continue;
                }
            }
            if ($month != '*') {
                if (!self::fit($month,(int)$nmonth)) {
                    $Start->modify('+1 month');
                    continue;
                }
            }
            if ($dow != '*') {
                if (!self::fit($dow,(int)$ndow)) {
                    $Start->modify('+1 day');
                    continue;
                }
            }
            return $Start->getTimestamp();
        } while (true);
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
