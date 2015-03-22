<?php
require_once('../commons/base.inc.php');
try
{
	$index = 0;
	foreach($FOGCore->getClass('GreenFogManager')->find() AS $gf)
	{
		$Datatosend .= ($_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#task$index=".$gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action') : base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action')))."\n";
		$index++;
	}
	$FOGCore->sendData($Datatosend);
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
