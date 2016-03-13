<?php
require('../commons/base.inc.php');
foreach ($FOGCore->getHWInfo() AS &$val) {
    echo "$val\n";
    unset($val);
}
