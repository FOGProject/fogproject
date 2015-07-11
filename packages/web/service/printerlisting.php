<?php
require_once('../commons/base.inc.php');
try {
    if (!$FOGCore->getClass(PrinterManager)->count()) throw new Exception("#!np\n");
    $printerNames = $FOGCore->getClass(PrinterManager)->find('','','','','','','','name');
    print "#!ok\n";
    foreach ($printerNames AS $i => &$name) print "#printer$i=$name\n";
    unset($name);
    exit;
} catch (Exception $e) {
    print $e->getMessage();
}
