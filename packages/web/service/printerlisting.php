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
    Route::names('printer');
    $printernames = json_decode(
        Route::getData()
    );
    if (count($printernames ?: []) < 1) {
        throw new Exception("#!np\n");
    }
    echo "#!ok\n";
    foreach ((array)$printernames as $index => $printer) {
        echo "#printer{$index}={$printer->name}\n";
        unset($printer);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
