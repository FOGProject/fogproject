<?php
class PXEMenuOptionsManager extends FOGManagerController {
    private static $regVals;
    public static function regText($foglang) {
        return self::$regVals = array(
            0 => $foglang['NotRegHost'],
            1 => $foglang['RegHost'],
            2 => $foglang['AllHosts'],
            3 => $foglang['DebugOpts'],
            4 => $foglang['AdvancedOpts'],
            5 => $foglang['AdvancedLogOpts'],
            6 => $foglang['PendRegHost'],
            7 => $foglang['DoNotList'],
        );
    }
    public function regSelect($request = '') {
        ob_start();
        foreach (self::regText($this->foglang) AS $num => &$val) {
            printf('<option value="%s"%s>%s</option>',$num,($request === $num ? ' selected' : ''),$val);
            unset($val);
        }
        return sprintf('<select name="menu_regmenu">%s</select>',ob_get_clean());
    }
}
