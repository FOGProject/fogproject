<?php
require_once('../commons/base.inc.php');
try
{
	$Plugin = current($FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
	if ($Plugin)
		print '##';
}
catch (Exception $e){}
