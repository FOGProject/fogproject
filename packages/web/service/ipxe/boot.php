<?php
require_once('../../commons/base.inc.php');
header("Content-type: text/plain");
if ($_REQUEST['mac0'] && !$_REQUEST['mac1'] && !$_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'];
else if ($_REQUEST['mac0'] && $_REQUEST['mac1'] && !$_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac1'];
else if ($_REQUEST['mac0'] && !$_REQUEST['mac1'] && $_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac2'];
else if ($_REQUEST['mac0'] && $_REQUEST['mac1'] && $_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac1'].'|'.$_REQUEST['mac2'];
$Host = $FOGCore->getHostItem(false,false,true);
new BootMenu($Host);
