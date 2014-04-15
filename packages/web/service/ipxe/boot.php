<?php
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
$MACAddress = new MACAddress($_REQUEST['mac']);
$Host = $FOGCore->getClass('HostManager')->getHostByMacAddress($MACAddress);
new BootMenu($Host);
