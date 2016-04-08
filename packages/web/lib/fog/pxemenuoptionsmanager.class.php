<?php
class PXEMenuOptionsManager extends FOGManagerController {
    private static $regVals;
    private static function regText() {
        return static::$regVals = array(
            0 => static::$foglang['NotRegHost'],
            1 => static::$foglang['RegHost'],
            2 => static::$foglang['AllHosts'],
            3 => static::$foglang['DebugOpts'],
            4 => static::$foglang['AdvancedOpts'],
            5 => static::$foglang['AdvancedLogOpts'],
            6 => static::$foglang['PendRegHost'],
            7 => static::$foglang['DoNotList'],
        );
    }
    public function regSelect($request = '') {
        static::$selected = (int)$request;
        ob_start();
        $sender = static::regText();
        array_walk($sender,static::$buildSelectBox);
        return sprintf('<select name="menu_regmenu">%s</select>',ob_get_clean());
    }
}
