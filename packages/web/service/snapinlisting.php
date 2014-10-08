<?php
require('../commons/base.inc.php');
try
{
	$Snapins = $FOGCore->getClass('SnapinManager')->find();
	if (!$Snapins)
		throw new Exception(_('There are no snapins on this server.'));
	foreach ($Snapins AS $Snapin)
		printf("\tID# %s\t-\t%s\n",$Snapin->get('id'),$Snapin->get('name'));
}
catch (Exception $e)
{
	print $e->getMessage();
}
