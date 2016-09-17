<?php
/**
 * The cron validation
 *
 * PHP version 5
 *
 * @category FOGCron
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */
/**
 * The cron validation
 *
 * @category FOGCron
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */
class FOGCron extends FOGBase
{
    /**
     * Verifies the fit of the string
     *
     * @param string $str the string to check
     * @param int    $num the num to check
     *
     * @return bool
     */
    private static function _fit($str, $num)
    {
        if (strpos($str, ',')) {
            $arr = explode(',', $str);
            $test = array();
            foreach ((array)$arr as &$ar) {
                $test[] = (bool)self::_fit($ar, $num);
                unset($ar);
            }
            return in_array(true, $test, true);
        }
        if (strpos($str, '-')) {
            list($low, $high) = explode('-', $str);
            return (bool)($num = $low);
        }
        if (strpos($str, '/')) {
            list($pre, $pos) = explode('/', $str);
            if ($pre == '*') {
                return ($num % $pos == 0);
            }
            return ($num % $pos == $pre);
        }
        return (bool)($str == $num);
    }
    /**
     * Returns the next run time
     *
     * @param string $cron    the cron to parse
     * @param bool   $lastrun show the last run time
     *
     * @return string
     */
    public static function parse($cron, $lastrun = false)
    {
        list(
            $min,
            $hour,
            $dom,
            $month,
            $dow
        ) = array_map('trim', preg_split('/ +/', $cron));
        if (is_numeric($dow) && $dow == 0) {
            $dow = 7;
        }
        $Start = self::niceDate();
        do {
            list(
                $nmin,
                $nhour,
                $ndom,
                $nmonth,
                $ndow) = array_map(
                    'trim',
                    preg_split('/ +/', $Start->format('i H d n N'))
                );
            if ($min != '*') {
                if (!self::_fit($min, $nmin)) {
                    $Start->modify(sprintf('%s1 minute', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            if ($hour != '*') {
                if (!self::_fit($hour, $nhour)) {
                    $Start->modify(sprintf('%s1 hour', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            if ($dom != '*') {
                if (!self::_fit($dom, $ndom)) {
                    $Start->modify(sprintf('%s1 day', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            if ($month != '*') {
                if (!self::_fit($month, $nmonth)) {
                    $Start->modify(sprintf('%s1 month', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            if ($dow != '*') {
                if (!self::_fit($dow, $ndow)) {
                    $Start->modify(sprintf('%s1 day', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            return $Start->getTimestamp();
        } while (true);
    }
    /**
     * Check the fields
     *
     * @param string $field the field to test
     * @param int    $min   the minimum the field can have
     * @param int    $max   the maximum the field can have
     *
     * @return bool
     */
    private static function _checkField($field, $min, $max)
    {
        $field = trim($field);
        if ($field != 0 && empty($field)) {
            return false;
        }
        $v = explode(',', $field);
        foreach ($v as &$vv) {
            $vvv = explode('/', $vv);
            $step = !$vvv[1] ? 1 : $vvv[1];
            $vvvv = explode('-', $vvv[0]);
            $_min = (
                count($vvvv) == 2 ?
                $vvvv[0] :
                (
                    $vvv[0] == '*' ?
                    $min :
                    $vvv[0]
                )
            );
            $_max = (
                count($vvvv) == 2 ?
                $vvvv[1] :
                (
                    $vvv[0] == '*' ?
                    $max :
                    $vvv[0]
                )
            );
            $res = self::_checkIntValue($step, $min, $max, true);
            if ($res) {
                $res = self::_checkIntValue($_min, $min, $max, true);
            }
            if ($res) {
                $res = self::_checkIntValue($_max, $min, $max, true);
            }
            unset($vv);
        }
        return $res;
    }
    /**
     * The integer value to test
     *
     * @param mixed $value     The value to check
     * @param int   $min       The minimum the value can be
     * @param int   $max       The maximum the value can be
     * @param bool  $extremity Implicitly test extremeties
     *
     * @return bool
     */
    private static function _checkIntValue($value, $min, $max, $extremity)
    {
        $val = intval($value, 10);
        if ($value != $val) {
            return false;
        }
        if (!$extremity) {
            return true;
        }
        if ($val >= $min && $val <= $max) {
            return true;
        }
        return false;
    }
    /**
     * Check the minutes field
     *
     * @param int $minutes the value to check
     *
     * @return bool
     */
    public static function checkMinutesField($minutes)
    {
        return self::_checkField($minutes, 0, 59);
    }
    /**
     * Check the hours field
     *
     * @param int $hours the value to check
     *
     * @return bool
     */
    public static function checkHoursField($hours)
    {
        return self::_checkField($hours, 0, 23);
    }
    /**
     * Check the day of month field
     *
     * @param int $dom the value to check
     *
     * @return bool
     */
    public static function checkDOMField($dom)
    {
        return self::_checkField($dom, 1, 31);
    }
    /**
     * Check the month field
     *
     * @param int $month the value to check
     *
     * @return bool
     */
    public static function checkMonthField($month)
    {
        return self::_checkField($month, 1, 12);
    }
    /**
     * Check the day of week field
     *
     * @param int $dow the value to check
     *
     * @return bool
     */
    public static function checkDOWField($dow)
    {
        return self::_checkField($dow, 0, 7);
    }
    /**
     * Check the time to see if it should be run now
     *
     * @param DateTime $time the datetime to test
     *
     * @return bool
     */
    public static function shouldRunCron($time)
    {
        $time = self::niceDate()->setTimestamp($time);
        $currTime = self::niceDate();
        return (bool)($time <= $currTime);
    }
}
