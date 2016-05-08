<?php
require_once('../commons/base.inc.php');
$clientUpdate = (bool)$FOGCore->getSetting('FOG_CLIENT_AUTOUPDATE');
if (isset($_REQUEST['client'])) echo $clientUpdate ? '9.9.99' : '0.0.0';
else if (isset($_REQUEST['clientver'])) echo $clientUpdate ? FOG_CLIENT_VERSION : '0.0.0';
else echo FOG_VERSION;
exit;
