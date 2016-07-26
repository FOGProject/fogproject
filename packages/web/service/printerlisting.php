<?php
require('../commons/base.inc.php');
try {
    if (FOGCore::getClass('PrinterManager')->count() < 1) throw new Exception("#!np\n");
    echo "#!ok\n";
    $printers = (array)FOGCore::getClass('PrinterManager')->find('','AND','name','ASC','=',false,false,'name');
    array_walk($printers,function(&$name,$index) {
        echo "#printer$index=$name\n";
        unset($name,$index);
    });
} catch (Exception $e) {
    echo $e->getMessage();
}
