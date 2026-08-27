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

use FOG\Router\Route;

/**
 * Returns a listing of all printers in the system.
 *
 * @category Printerlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a booting NIC, FOS, the fog-client
 * or a storage node, none of which can present a credential. Declared per
 * file rather than inferred from the absence of one -- see
 * Authorization::_hasNoPrincipal() for what it licenses and why the
 * distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../commons/base.inc.php';
try {
    // getNames(): names() answers with its rows under a `data` envelope, and
    // this wants the rows. A router failure still raises into the catch below
    // and the client gets "#!np" -- rather than reaching breakHead()'s exit,
    // which sends a non-2xx the client reads as a transport failure. See
    // ADR 0011.
    $printernames = Route::getNames('printer');
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
