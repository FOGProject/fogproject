<?php
require('../commons/base.inc.php');
try
{
	// Just list all the images available.
	$Images = $FOGCore->getClass('ImageManager')->find();
	if (!$Images)
		throw new Exception(_('There are no images on this server.'));
	foreach ($Images AS $Image)
		printf("\tID# %s\t-\t%s\n",$Image->get('id'),$Image->get('name'));
}
catch (Exception $e)
{
	print $e->getMessage();
}
