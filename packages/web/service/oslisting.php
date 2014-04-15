<?php
require('../commons/base.inc.php');
try
{
	// Just prints the OS listing like Images does.
	$OSs = $FOGCore->getClass('OSManager')->find(null,null,'id');
	foreach ($OSs AS $OS)
		printf("\tID# %s\t-\t%s\n",$OS->get('id'),$OS->get('name'));
}
catch (Exception $e)
{
	print $e->getMessage();
}
