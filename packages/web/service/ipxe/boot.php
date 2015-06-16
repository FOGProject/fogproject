<?php
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: deny');
header('Cache-Control: no-cache');
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
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
