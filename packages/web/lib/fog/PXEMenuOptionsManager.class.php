<?php
class PXEMenuOptionsManager extends FOGManagerController
{
	public function regText()
	{
		$regVals = array(
			0 => _('Not Registered Hosts'),
			1 => _('Registered Hosts'),
			2 => _('All Hosts'),
			3 => _('Debug Options'),
			4 => _('Advanced Options'),
			5 => _('Advanced Login Required'),
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
