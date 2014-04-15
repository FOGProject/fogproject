<?php
require((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
// Allow AJAX check
if (!$_SESSION['AllowAJAXTasks'])
	die('FOG Session Invalid');
if ( $_GET["prefix"] != null && strlen($_GET["prefix"]) >= 8 )
{
	if ( $FOGCore->getMACLookupCount() > 0 )
	{
		$mac = new MACAddress( $_GET["prefix"] );
		if ( $mac != null )
		{
			$mac = $FOGCore->getMACManufacturer($mac->getMACPrefix());
			echo ($mac == 'n/a' ? _('Unknown') : $mac);
		}
	}
	else
		echo "<a href='?node=about&sub=mac-list'>"._("Load MAC Vendors")."</a>";
}
else
	echo _('Unknown');
