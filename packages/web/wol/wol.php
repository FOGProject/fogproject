<?php
require_once('../commons/base.inc.php');
try {
	$FOGCore->getClass(WakeOnLan,$FOGCore->getHostItem(false,false,false,true))->send();
} catch (Exception $e) {
	print $e->getMessage();
}
