<?php
require_once('../commons/base.inc.php');
try
{
	$GF = $FOGCore->getClass('GreenFogManager')->find();
	foreach($GF AS $gf)
		print base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action'))."\n";
}
catch (Exception $e)
{
	print $e->getMessage();
}
