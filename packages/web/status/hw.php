<?php
require_once('../commons/base.inc.php');
array_map(function(&$val) {
    echo "$val\n";
    unset($val);
},(array)$FOGCore->getHWInfo());
