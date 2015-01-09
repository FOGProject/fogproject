<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/commons/base.inc.php');
$data = $FOGCore->getHWInfo();
foreach($data AS $d => $val)
	print $val."\n";
