<?php
require('../commons/base.inc.php');
foreach ((array)$FOGCore->getHWInfo() AS $i => &$val) {
    echo "$val\n";
    unset($val);
}
