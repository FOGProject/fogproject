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
     * @param string $str   the string to check
     * @param int    $num   the num to check
     * @param int    $start the field's lowest value: 1 for the day of
     *                      month and the month, 0 for the rest
     *
     * @return bool
     */
    private static function _fit($str, $num, $start = 0)
    {
        if ('*' === $str) {
            return true;
        }
        if (strpos($str, ',') !== false) {
            $arr = explode(',', $str);
            $test = [];
            foreach ((array)$arr as &$ar) {
                $test[] = (bool)self::_fit($ar, $num, $start);
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
            // `*/n` counts from the field's first value, as cron does. So a
            // day of month of `*/2` is 1, 3, 5 and a month of `*/3` is 1, 4,
            // 7, 10. Counting from 0 gave even days and March, June,
            // September, December.
            if ($pre == '*') {
                return (((int)$num - $start) % $step == 0);
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
     * Does this day match the day-of-month and weekday fields together?
     *
     * Cron's rule: when both fields are restricted, a day matches if EITHER
     * one does. `0 0 1 * 1` runs on the 1st and on every Monday. A field
     * that starts with `*` is not restricted, so then BOTH must match:
     * `0 22 *\/2 * 1-5` runs on odd days that are weekdays. FOG used to
     * read `*\/2` as restricted and ran that on odd days OR weekdays.
     *
     * @param string    $dom  the day of month field
     * @param string    $dow  the day of week field
     * @param \DateTime $date the day to test
     *
     * @return bool
     */
    private static function _dayFits($dom, $dow, $date)
    {
        $domFits = self::_fit($dom, (int)$date->format('j'), 1);
        $dowFits = self::_dowFits($dow, (int)$date->format('N'));
        if ('*' === substr($dom, 0, 1) || '*' === substr($dow, 0, 1)) {
            return $domFits && $dowFits;
        }
        return $domFits || $dowFits;
    }
    /**
     * Returns the next run time, or the last one.
     *
     * The next run is the first matching minute AFTER the minute that $from
     * falls in. The last run is the latest matching minute at or before
     * $from. So with $from at 10:00:30, a `0 10 * * *` schedule's next run is
     * tomorrow at 10:00, and its last run is today at 10:00.
     *
     * The search walks the calendar from the largest field down, the way
     * cron's own matching does: a month that does not match jumps to the
     * start of the next month, a day to the next midnight, an hour to the
     * next top of the hour. The old walk kept the smaller fields on each
     * jump, so `0 * * * 1` from a Sunday 14:00 gave Monday 14:00, not
     * Monday 00:00.
     *
     * The walk is on wall-clock time, because the fields are wall-clock
     * intent. Each match is then placed in the install's zone. On the
     * spring-forward day, a time in the skipped hour runs one hour later
     * (02:30 runs at 03:30). On the fall-back day, a time in the repeated
     * hour runs once, at its first occurrence. That is how cron treats a
     * fixed time too.
     *
     * @param string   $cron    the cron to parse
     * @param bool     $lastrun show the last run time
     * @param int|null $from    unix timestamp to search from; null is now
     *
     * @return int unix timestamp -- every caller feeds it to setTimestamp()
     */
    public static function parse($cron, $lastrun = false, $from = null)
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
        $now = self::niceDate();
        if (null !== $from) {
            $now->setTimestamp((int)$from);
        }
        // The fields are wall-clock intent in the install's zone
        // (FOG_TZ_INFO). niceDate() is the STORAGE zone, which is UTC once
        // schema step 396 has recorded the UTC boundary. Evaluated there,
        // `0 23 * * *` on a Chicago install ran at 17:00 local. Not the
        // viewer's zone either: a schedule must not move with who reads it.
        $now->setTimezone(self::defaultDisplayTimeZone());
        $fromTs = $now->getTimestamp();
        $zone = $now->getTimezone();
        // The walk runs on a copy of the wall clock in UTC. UTC has no DST,
        // so each step moves the clock in one direction only, and the
        // five-year limit always ends the loop. Five years covers the
        // leap-day schedule `0 0 29 2 *`; a schedule with no date at all
        // (`0 0 30 2 *`) throws.
        $utc = new \DateTimeZone('UTC');
        $wall = new \DateTime($now->format('Y-m-d H:i'), $utc);
        if (!$lastrun) {
            $wall->modify('+1 minute');
        }
        $sign = $lastrun ? '-' : '+';
        $limit = (clone $wall)->modify($sign . '5 years');
        while ($lastrun ? $wall >= $limit : $wall <= $limit) {
            if (!self::_fit($month, (int)$wall->format('n'), 1)) {
                // Forward: the first minute of the next month. Back: the
                // last minute of the month before.
                $wall->modify('first day of this month')->setTime(0, 0);
                $wall->modify($lastrun ? '-1 minute' : '+1 month');
                continue;
            }
            if (!self::_dayFits($dom, $dow, $wall)) {
                $wall->setTime(0, 0);
                $wall->modify($lastrun ? '-1 minute' : '+1 day');
                continue;
            }
            if (!self::_fit($hour, (int)$wall->format('G'))) {
                $wall->setTime((int)$wall->format('G'), 0);
                $wall->modify($lastrun ? '-1 minute' : '+1 hour');
                continue;
            }
            if (!self::_fit($min, (int)$wall->format('i'))) {
                $wall->modify($sign . '1 minute');
                continue;
            }
            // Place the wall-clock match in the install's zone. A time in
            // the spring-forward gap comes back one hour later. A time in
            // the fall-back hour comes back as its first occurrence, which
            // can be before $from: then the walk goes on.
            $ts = (new \DateTime($wall->format('Y-m-d H:i'), $zone))
                ->getTimestamp();
            if ($lastrun ? $ts <= $fromTs : $ts > $fromTs) {
                return $ts;
            }
            $wall->modify($sign . '1 minute');
        }
        throw new \InvalidArgumentException(
            sprintf(
                _('No run time within five years for cron expression: "%s"'),
                $cron
            )
        );
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
            // Cron refuses a reversed range. `30-10` matches no value, so the
            // schedule would be saved and then never run.
            if ($res && (int)$_min > (int)$_max) {
                $res = false;
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
     * Refuses a schedule that cron would refuse, or that never runs.
     *
     * One check for every form that saves a schedule: the scheduled task
     * form and the host and group power-management forms. The
     * power-management forms checked only the characters, so a minute of
     * 99 or a 30th of February was saved and then skipped on every
     * scheduler pass.
     *
     * @param string $min   the minute field
     * @param string $hour  the hour field
     * @param string $dom   the day of month field
     * @param string $month the month field
     * @param string $dow   the day of week field
     *
     * @throws \Exception naming the field that is wrong
     *
     * @return void
     */
    public static function validate($min, $hour, $dom, $month, $dow)
    {
        $checks = [
            [self::checkMinutesField($min), _('Minutes field is invalid')],
            [self::checkHoursField($hour), _('Hours field is invalid')],
            [self::checkDOMField($dom), _('Day of Month field is invalid')],
            [self::checkMonthField($month), _('Month field is invalid')],
            [self::checkDOWField($dow), _('Day of Week field is invalid')],
        ];
        foreach ($checks as list($valid, $message)) {
            if (!$valid) {
                throw new \Exception($message);
            }
        }
        try {
            self::parse(implode(' ', [$min, $hour, $dom, $month, $dow]));
        } catch (\InvalidArgumentException $e) {
            throw new \Exception(
                _('This schedule has no run time within five years')
            );
        }
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
