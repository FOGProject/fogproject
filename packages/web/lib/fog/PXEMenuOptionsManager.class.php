<?php
class PXEMenuOptionsManager extends FOGManagerController
{
	public function regText()
	{
		$regVals = array(
			0 => $this->foglang['NotRegHost'],
			1 => $this->foglang['RegHost'],
			2 => $this->foglang['AllHosts'],
			3 => $this->foglang['DebugOpts'],
			4 => $this->foglang['AdvancedOpts'],
			5 => $this->foglang['AdvancedLogOpts'],
			6 => $this->foglang['PendRegHost'],
		);
		return $regVals;
	}
	public function regSelect($request = '')
	{
		$regVals = '<select name="menu_regmenu">';
		foreach($this->regText() AS $num => $val)
			$regMenuItems[] = '<option value="'.$num.'"'.($request == $num ? ' selected="selected"' : '').'>'.$val.'</option>';
		$regVals .= implode("\n\t\t\t\t\t",$regMenuItems)."\n"."</select>";
		return $regVals;
	}
}
