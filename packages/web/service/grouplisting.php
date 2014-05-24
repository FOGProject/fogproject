<?php
require('../commons/base.inc.php');
try
{
	// Just list all the images available.
	$Groups = $FOGCore->getClass('GroupManager')->find();
	if (!$Groups)
		throw new Exception(_('There are no images on this server.'));
	foreach ($Groups AS $Group)
		printf("\tID# %s\t-\t%s\n",$Group->get('id'),$Group->get('name'));
}
catch (Exception $e)
{
	print $e->getMessage();
}
