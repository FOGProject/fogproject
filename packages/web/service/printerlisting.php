<?php
/**
 * Returns a listing of all printers in the system.
 *
 * PHP version 7.4+
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
    // asValue(): names() has no wrapper, and its payload is a bare list with
    // no envelope to unwrap. What this buys is that a router failure raises
    // into the catch below and the client gets "#!np" -- rather than reaching
    // breakHead()'s exit, which sends a non-2xx the client reads as a
    // transport failure. See ADR 0011.
    $printernames = Route::asValue(
        function () {
            Route::names('printer');
        }
    );
    if (count((array)$printernames) < 1) {
        throw new \Exception("#!np\n");
    }
    echo "#!ok\n";
    foreach ((array)$printernames as $index => $printer) {
        echo "#printer{$index}={$printer->name}\n";
        unset($printer);
    }
} catch (\Exception $e) {
    echo $e->getMessage();
}
exit;
