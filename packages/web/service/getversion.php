<?php
require_once('../commons/base.inc.php');
if (isset($_REQUEST['client']))
	print FOG_CLIENT_VERSION;
else if (isset($_REQUEST['clientver']))
	exit;
else
	print FOG_VERSION;
exit;
