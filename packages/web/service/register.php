<?php
/**
 * Passes the legacy and new client
 * host register information.  Particularly
 * useful for adding additional mac addresses.
 *
 * PHP version 7.4+
 *
 * @category Register
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Client\RegisterClient;

/**
 * Passes the legacy and new client
 * host register information.  Particularly
 * useful for adding additional mac addresses.
 *
 * @category Register
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
new RegisterClient(
    true,
    false,
    isset($_REQUEST['newService']),
    false,
    isset($_REQUEST['newService'])
);
