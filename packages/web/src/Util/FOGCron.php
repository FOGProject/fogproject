<?php
/**
 * The cron validation
 *
 * PHP version 7.4+
 *
 * @category FOGCron
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Util;

use FOG\Base\FOGBase;

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
     * Validates a single cron field value.
     * Only digits, *, /, -, and commas are permitted.
     *
     * @param string $value the cron field value
     *
     * @throws Exception if the value contains invalid characters
     *
     * @return string the trimmed value
     */
    public static function _sanitizeCronField($value)
    {
        $value = trim((string)$value);
        if (!preg_match('/^[0-9\*\/\-\,]+$/', $value)) {
            throw new \Exception(
                _('Invalid cron field value. Only digits, *, /, -, and commas are allowed.')
            );
        }
        return $value;
    }
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
        if (strpos($str, ',') !== false) {
            $arr = explode(',', $str);
            $test = [];
            foreach ((array)$arr as &$ar) {
                $test[] = (bool)self::_fit($ar, $num);
                unset($ar);
            }
            return in_array(true, $test, true);
        }
        // A step is tested before a range: `0-30/10` is a range WITH a step,
        // and splitting it on `-` first read it as 0 to (int)"30/10", which
        // is every value from 0 to 30.
        if (strpos($str, '/') !== false) {
            list($pre, $pos) = explode('/', $str, 2);
            $step = (int)$pos;
            // A step below 1 matches nothing; `% 0` is DivisionByZeroError.
            if ($step < 1) {
                return false;
            }
            if ($pre == '*') {
                return ((int)$num % $step == 0);
            }
            // The step counts from the range's low end, and the high end
            // bounds the match. A bare start (`5/15`) has no high end.
            $low = $pre;
            if (strpos($pre, '-') !== false) {
                list($low, $high) = explode('-', $pre, 2);
                if ((int)$num > (int)$high) {
                    return false;
                }
            }
            return ((int)$num >= (int)$low
                && ((int)$num - (int)$low) % $step === 0);
        }
        if (strpos($str, '-') !== false) {
            list($low, $high) = explode('-', $str);
            return (bool)((int)$num >= (int)$low) && ((int)$num <= (int)$high);
        }
        return (bool)($str == $num);
    }
    /**
     * Does the weekday field match this day?
     *
     * $isoDow is format('N'): 1 (Monday) to 7 (Sunday). The field is tested
     * twice, so that Sunday matches in every form a field can take: once as
     * standard cron numbering (0-6, Sunday = 0), and once as is, which
     * accepts 7 for Sunday. Monday to Saturday are the same number both
     * times. So `0,6` and `0-6` include Sunday, and `6-7` does too.
     *
     * A single negative value is Sunday. The FOG client already reads it
     * that way (Assign\Resolver::cronExpression() maps it to 7); without the
     * same rule here, _fit() splits `-1` on `-` into the range 0 to 1, and
     * the server's WOL path ran the schedule on Monday.
     *
     * @param string $field  the weekday field, never `*`
     * @param int    $isoDow the day to test, as format('N')
     *
     * @return bool
     */
    private static function _dowFits($field, $isoDow)
    {
        $field = trim((string)$field);
        if (is_numeric($field) && (int)$field < 0) {
            $field = '7';
        }
        return self::_fit($field, $isoDow % 7)
            || self::_fit($field, $isoDow);
    }
    /**
     * Returns the next run time
     *
     * @param string $cron    the cron to parse
     * @param bool   $lastrun show the last run time
     *
     * @return int unix timestamp -- every caller feeds it to setTimestamp()
     */
    public static function parse($cron, $lastrun = false)
    {
        $parts = array_values(
            array_filter(
                array_map('trim', preg_split('/\s+/', trim($cron))),
                'strlen'
            )
        );
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException(
                sprintf(
                    _('Invalid cron expression (expected 5 fields, got %d): "%s"'),
                    count($parts),
                    $cron
                )
            );
        }
        list(
            $min,
            $hour,
            $dom,
            $month,
            $dow
        ) = $parts;
        // An out-of-range field (a minute of 99, which the power-management
        // pages can store) would otherwise be found only after millions of
        // one-minute steps. The weekday is not checked: `-1` is a valid
        // stored weekday (see _dowFits()), and checkDOWField() refuses it.
        $fields = [
            'minute' => [$min, self::checkMinutesField($min)],
            'hour' => [$hour, self::checkHoursField($hour)],
            'day of month' => [$dom, self::checkDOMField($dom)],
            'month' => [$month, self::checkMonthField($month)],
        ];
        foreach ($fields as $name => list($value, $valid)) {
            if (!$valid) {
                throw new \InvalidArgumentException(
                    sprintf(
                        _('Invalid cron %s field "%s": "%s"'),
                        $name,
                        $value,
                        $cron
                    )
                );
            }
        }
        $Start = self::niceDate();
        // A schedule with no matching date (`0 0 30 2 *`) must not loop
        // forever. Five years covers the leap-day schedule `0 0 29 2 *`.
        // The pass limit is the hard stop: where a DST change makes
        // modify('-1 hour') land on the same wall time, the date limit
        // alone is never reached.
        $limit = self::niceDate()->modify(
            sprintf('%s5 years', $lastrun ? '-' : '+')
        );
        $passes = 0;
        do {
            if (++$passes > 1000000
                || ($lastrun ? $Start < $limit : $Start > $limit)
            ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        _('No run time within five years for cron expression: "%s"'),
                        $cron
                    )
                );
            }
            list(
                $nmin,
                $nhour,
                $ndom,
                $nmonth,
                $ndow
            ) = array_map(
                function ($val) {
                    return intval(trim($val));
                },
                preg_split('/\s+/', $Start->format('i H j n N'))
            );
            // Fairly straight forward, check our minutes field.
            if ($min != '*') {
                if (!self::_fit($min, $nmin)) {
                    $Start->modify(sprintf('%s1 minute', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            // Check our hours field.
            if ($hour != '*') {
                if (!self::_fit($hour, $nhour)) {
                    $Start->modify(sprintf('%s1 hour', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            // Here comes tricky part. DOM and DOW checks.
            // If dow AND dom are set to some value other than * the
            // typical action is to run if the days are true OR the dow is true.
            //
            // For example:
            // our crontab is:
            // * * 2-5 * 5 it should run on the 2nd through 5th of the month
            // AND every friday. If you only have the DOW, it will only run on
            // that day of the week. (* * * * 5) would only run on fridays.
            if ($dom != '*' && $dow != '*') {
                if (!self::_fit($dom, $ndom) && !self::_dowFits($dow, $ndow)) {
                    $Start->modify(sprintf('%s1 day', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            if ($dow == '*' && $dom != '*') {
                if (!self::_fit($dom, $ndom)) {
                    $Start->modify(sprintf('%s1 day', $lastrun ? '-' : '+'));
                    continue;
                }
            }
            if ($dow != '*' && $dom == '*') {
                if (!self::_dowFits($dow, $ndow)) {
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
            return $Start->setTime(
                (int) $Start->format('H'),
                (int) $Start->format('i'),
                0
            )->getTimestamp();
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
        $res = true;
        foreach ($v as &$vv) {
            $vvv = explode('/', $vv);
            $step = count($vvv ?: []) <= 1 ? 1 : $vvv[1];
            // A step below 1 is refused: `*/0` divides by zero in _fit().
            if ((int)$step < 1) {
                $res = false;
                break;
            }
            $vvvv = explode('-', $vvv[0]);
            $_min = (
                count($vvvv ?: []) == 2 ?
                $vvvv[0] :
                (
                    $vvv[0] == '*' ?
                    $min :
                    $vvv[0]
                )
            );
            $_max = (
                count($vvvv ?: []) == 2 ?
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
            if (!$res) {
                break;
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
     * @param string $minutes the field to check
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
     * @param string $hours the field to check
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
     * @param string $dom the field to check
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
     * @param string $month the field to check
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
     * @param string $dow the field to check
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
     * @param int $time unix timestamp to test -- this is what parse()
     *                  returns and what Timer passes in, not a DateTime
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
