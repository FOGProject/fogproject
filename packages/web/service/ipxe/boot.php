<?php
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
if ($_REQUEST['mac0'] && !$_REQUEST['mac1'] && !$_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'];
else if ($_REQUEST['mac0'] && $_REQUEST['mac1'] && !$_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac1'];
else if ($_REQUEST['mac0'] && !$_REQUEST['mac1'] && $_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac2'];
else if ($_REQUEST['mac0'] && $_REQUEST['mac1'] && $_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac2'].'|'.$_REQUEST['mac3'];
$MACs = HostManager::parseMacList($_REQUEST['mac']);
$Host = $FOGCore->getClass('HostManager')->getHostByMacAddresses($MACs);
new BootMenu($Host);
