<?php
require_once('../commons/base.inc.php');
try {
    if (!$FOGCore->getClass(PrinterManager)->count()) throw new Exception("#!np\n");
    $printerNames = $FOGCore->getClass(PrinterManager)->find('','','','','','','','name');
    echo "#!ok\n";
    foreach ($printerNames AS $i => &$name) echo "#printer$i=$name\n";
    unset($name);
    exit;
} catch (Exception $e) {
    echo $e->getMessage();
}
