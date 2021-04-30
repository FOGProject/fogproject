<?php
/**
 * Creates the timer item so we know when
 * something is supposed to occur.
 *
 * PHP version 5
 *
 * @category Timer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     * Should run now checking for logging.
     *
     * @return string
     */
    public function shouldRunNowCheck()
    {
        if ($this->_blSingle) {
            if ($this->_shouldSingleRun()) {
                return _('This is a single run task that should run now.');
            }
            return _('This is a single run task that should not run now.');
        }
        if (self::shouldRunCron($this->_lngSingle)) {
            return _('This is a cron style task that should run now.');
        }
        return _('This is a cron style task that should not run now.');
    }
    /**
     * Should run common.
     *
     * @return bool
     */
    public function shouldRunNow()
    {
        return (bool) (
            $this->_blSingle ?
            $this->_shouldSingleRun() :
            self::shouldRunCron($this->_lngSingle)
        );
    }
}
