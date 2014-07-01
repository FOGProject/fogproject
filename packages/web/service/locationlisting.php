<?php
require('../commons/base.inc.php');
try
{
	// Just list all the locations available.
	$Locations = $FOGCore->getClass('LocationManager')->find();
	if (!$Locations)
		throw new Exception(_('There are no locations on this server.'));
	foreach ($Locations AS $Location)
		printf("\tID# %s\t-\t%s\n",$Location->get('id'),$Location->get('name'));
}
catch (Exception $e)
{
	print $e->getMessage();
}
