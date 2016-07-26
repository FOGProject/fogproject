<?php
class Timer extends FOGCron {
    private $blSingle;
    private $cron;
    private $lngSingle;
    public function __construct($minute,$hour=null,$dom=null,$month=null,$dow=null) {
        parent::__construct();
        if ( $minute != null && $hour == null && $dom == null && $month == null && $dow == null ) {
            // Single task based on timestamp
            $this->lngSingle = $minute;
            $this->blSingle = true;
        } else {
            $this->cron = sprintf('%s %s %s %s %s',$minute,$hour,$dom,$month,$dow);
            $this->lngSingle = self::parse($this->cron);
            $this->blSingle = false;
        }
    }
    public function isSingleRun() {
        return $this->blSingle;
    }
    public function getSingleRunTime() {
        return $this->lngSingle;
    }
    public function toString() {
        $runTime = self::nice_date()->setTimestamp($this->lngSingle);
        return $runTime->format('r');
    }
    public function setDebug($blDebug) {
        self::$debug = $blDebug;
    }
    private function shouldSingleRun() {
        $CurrTime = self::nice_date();
        $Time = self::nice_date()->setTimestamp($this->lngSingle);
        return (bool)($Time <= $CurrTime);
    }
    public function shouldRunNow() {
        return (bool)($this->blSingle ? $this->shouldSingleRun() : self::shouldRunCron($this->lngSingle));
    }
    private function d($s) {
        if (self::$debug) echo ($s."\n");
    }
}
