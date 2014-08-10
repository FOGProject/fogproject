<?php
require((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
// Allow AJAX check
if (!$_SESSION['AllowAJAXTasks'])
	die('FOG Session Invalid');
// Variables
$data = array(
		'key' => $FOGCore->randomString(32),
);
if ($FOGCore->isAJAXRequest())
	print json_encode($data);
