<?php
/**
 * Returns a listing of all printers in the system.
 *
 * PHP version 5
 *
 * @category Printerlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Returns a listing of all printers in the system.
 *
 * @category Printerlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $printerCount = FOGCore::getClass('PrinterManager')
        ->count();
    if ($printerCount < 1) {
        throw new Exception("#!np\n");
    }
    echo "#!ok\n";
    $printerids = FOGCore::getSubObjectIDs('Printer');
    $printernames = FOGCore::getSubObjectIDs(
        'Printer',
        array('id' => $printerids),
        'name'
    );
    foreach ((array)$printerids as $index => $printerid) {
        $name = $printernames[$index];
        echo "#printer$index=$name\n";
        unset(
            $name,
            $index,
            $printerids[$index],
            $printerid,
            $printernames[$index]
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
