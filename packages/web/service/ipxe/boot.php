<?php
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
$MACs = HostManager::parseMacList($_REQUEST['mac']);
$Host = $FOGCore->getClass('HostManager')->getHostByMacAddresses($MACs);
new BootMenu($Host);
