<?php
require_once('../commons/base.inc.php');
$data = $FOGCore->getHWInfo();
foreach($data AS $d => &$val) print $val."\n";
