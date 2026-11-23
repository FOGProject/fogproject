<?php
/**
 * Creates the timer item so we know when
 * something is supposed to occur.
 *
 * PHP version 7.4+
 *
 * @category Timer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Util;

/**
 * Creates the timer item so we know when
 * something is supposed to occur.
 *
 * @category Timer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Timer extends FOGCron
{
    /**
     * Is single?
     *
     * @var bool
     */
    private $_blSingle;
    /**
     * Cron time
     *
     * @var string
     */
    private $_cron;
    /**
     * Line single run.
     *
     * @var mixed
     */
    private $_lngSingle;
    /**
     * Initializes the Timer class.
     *
     * @param mixed $minute The minute field.
     * @param mixed $hour   The hour field.
     * @param mixed $dom    The dom field.
     * @param mixed $month  The motnh field.
     * @param mixed $dow    The dow field.
     *
     * @return void
     */
    public function __construct(
        $minute,
        $hour = null,
        $dom = null,
        $month = null,
        $dow = null
    ) {
        parent::__construct();
        if ($minute != null
            && $hour == null
            && $dom == null
            && $month == null
            && $dow == null
        ) {
            // Single task based on timestamp
            $this->_lngSingle = $minute;
            $this->_blSingle = true;
        } else {
            $this->_cron = sprintf(
                '%s %s %s %s %s',
                $minute,
                $hour,
                $dom,
                $month,
                $dow
            );
            $this->_lngSingle = self::parse($this->_cron);
            $this->_blSingle = false;
        }
    }
    /**
     * Is this a single run or cron?
     *
     * @return bool
     */
    public function isSingleRun()
    {
        return $this->_blSingle;
    }
    /**
     * The time to run single.
     *
     * @return string
     */
    public function getSingleRunTime()
    {
        return $this->_lngSingle;
    }
    /**
     * Send the time to string.
     *
     * @return string
     */
    public function toString()
    {
        $runTime = self::niceDate()->setTimestamp($this->_lngSingle);
        return $runTime->format('r');
    }
    /**
     * Should single run now?
     *
     * @return bool
     */
    private function _shouldSingleRun()
    {
        $CurrTime = self::niceDate();
        $Time = self::niceDate()->setTimestamp($this->_lngSingle);
        return (bool) ($Time <= $CurrTime);
    }
    /**
     * Is a cron task due: does a matching minute fall after $since and at
     * or before now?
     *
     * The scheduler passes the time of its previous pass, so each pass
     * covers the minutes since the last one, and every matching minute is
     * seen exactly once. The old test asked only "does THIS minute match".
     * A pass that came late, or a sleep time over 60 seconds, missed the
     * minute and the task did not run. A sleep time under 60 seconds saw
     * the same minute twice and ran the task twice.
     *
     * With no $since, the window is the current minute, which is the old
     * answer.
     *
     * $until closes the window. The scheduler passes the start of its pass,
     * which is also the next pass's $since. With the live clock instead, a
     * pass that crossed a minute boundary saw the new minute, and the next
     * pass saw it again.
     *
     * @param int|null $since unix timestamp of the previous pass
     * @param int|null $until unix timestamp of this pass; null is now
     *
     * @return bool
     */
    private function _cronDue($since, $until)
    {
        $now = null === $until ? time() : (int)$until;
        if (null === $since) {
            $since = $now - $now % 60 - 1;
        }
        return self::parse($this->_cron, false, $since) <= $now;
    }
    /**
     * Should run now checking for logging.
     *
     * @param int|null $since unix timestamp of the previous pass
     * @param int|null $until unix timestamp of this pass; null is now
     *
     * @return string
     */
    public function shouldRunNowCheck($since = null, $until = null)
    {
        if ($this->_blSingle) {
            if ($this->_shouldSingleRun()) {
                return _('This is a single run task that should run now.');
            }
            return _('This is a single run task that should not run now.');
        }
        if ($this->_cronDue($since, $until)) {
            return _('This is a cron style task that should run now.');
        }
        return _('This is a cron style task that should not run now.');
    }
    /**
     * Should run common.
     *
     * @param int|null $since unix timestamp of the previous pass
     * @param int|null $until unix timestamp of this pass; null is now
     *
     * @return bool
     */
    public function shouldRunNow($since = null, $until = null)
    {
        return (bool) (
            $this->_blSingle ?
            $this->_shouldSingleRun() :
            $this->_cronDue($since, $until)
        );
    }
}
