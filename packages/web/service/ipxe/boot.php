<?php
ob_start();
require_once('../../commons/base.inc.php');
header("Content-type: text/plain");
header('Connection: close');
if ($_REQUEST['mac0'] && !$_REQUEST['mac1'] && !$_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'];
else if ($_REQUEST['mac0'] && $_REQUEST['mac1'] && !$_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac1'];
else if ($_REQUEST['mac0'] && !$_REQUEST['mac1'] && $_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac2'];
else if ($_REQUEST['mac0'] && $_REQUEST['mac1'] && $_REQUEST['mac2'])
	$_REQUEST['mac'] = $_REQUEST['mac0'].'|'.$_REQUEST['mac1'].'|'.$_REQUEST['mac2'];
$Host = $FOGCore->getHostItem(false,false,true);
FOGCore::getClass('BootMenu',$Host);
flush();
ob_flush();
ob_end_flush();
exit;
