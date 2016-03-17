<?php
require('../commons/base.inc.php');
try {
    if ($FOGCore::getClass('PrinterManager')->count()) throw new Exception("#!np\n");
    echo "#!ok\n";
    foreach ((array)$FOGCore->getSubObjectIDs('Printer','','name') AS $i => &$name) {
        echo "#printer$i=$name\n";
        unset($name);
    }
    exit;
} catch (Exception $e) {
    echo $e->getMessage();
}
