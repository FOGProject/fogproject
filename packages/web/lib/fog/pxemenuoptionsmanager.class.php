<?php
class PXEMenuOptionsManager extends FOGManagerController {
    private static $regVals;
    public static function regText() {
        return self::$regVals = array(
            0 => self::$foglang['NotRegHost'],
            1 => self::$foglang['RegHost'],
            2 => self::$foglang['AllHosts'],
            3 => self::$foglang['DebugOpts'],
            4 => self::$foglang['AdvancedOpts'],
            5 => self::$foglang['AdvancedLogOpts'],
            6 => self::$foglang['PendRegHost'],
            7 => self::$foglang['DoNotList'],
        );
    }
    public function regSelect($request = '') {
        self::$selected = (int)$request;
        ob_start();
        $sender = self::regText();
        array_walk($sender,self::$buildSelectBox);
        return sprintf('<select name="menu_regmenu">%s</select>',ob_get_clean());
    }
}
