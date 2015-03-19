<?php
require_once('../commons/base.inc.php');
if (isset($_REQUEST['clientver']))
	print FOG_CLIENT_VERSION;
else
	print FOG_VERSION;
