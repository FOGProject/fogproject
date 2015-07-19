<?php
class PXEMenuOptionsManager extends FOGManagerController {
    public function regText() {
        $regVals = array(
            0 => $this->foglang['NotRegHost'],
            1 => $this->foglang['RegHost'],
            2 => $this->foglang['AllHosts'],
            3 => $this->foglang['DebugOpts'],
            4 => $this->foglang['AdvancedOpts'],
            5 => $this->foglang['AdvancedLogOpts'],
            6 => $this->foglang['PendRegHost'],
            7 => $this->foglang['DoNotList'],
        );
        return $regVals;
    }
    public function regSelect($request = '') {
        $regMenuItems = '';
        foreach($this->regText() AS $num => &$val) $regMenuItems .= '<option value="'.$num.'"'.($request == $num ? ' selected="selected"' : '').'>'.$val.'</option>';
        unset($val);
        return '<select name="menu_regmenu">'.$regMenuItems.'</select>';
    }
}
