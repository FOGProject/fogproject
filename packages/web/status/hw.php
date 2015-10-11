<?php
require_once('../commons/base.inc.php');
$data = $FOGCore->getHWInfo();
foreach($data AS $i => &$val) echo $val."\n";
unset($val);
