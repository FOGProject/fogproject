<?php
/**
 * Boot page for U-Boot (extlinux)
 *
 * PHP version 7.4+
 *
 * @category Boot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGCore;
use FOG\Boot\UbootBootMenu;

/**
 * Boot page for U-Boot (extlinux)
 *
 * The board's own U-Boot fetches this over HTTP with its MAC in the query
 * string and feeds the answer to `sysboot`:
 *
 *   wget ${pxefile_addr_r} http://<fog>/fog/service/uboot/boot.php?mac=${ethaddr}
 *   pxe boot ${pxefile_addr_r}
 *
 * `pxe boot`, not `sysboot`: the former interprets a config already in memory,
 * the latter reads one off a filesystem on a block device. See
 * docs/UBOOT_ARM_BOOT.md.
 *
 * GET, not POST, because that is all U-Boot's `wget` can issue -- which is
 * also why there is no mac0..mac7 union as service/ipxe/boot.php has. A board
 * knows one MAC: the one it is booting from.
 *
 * @category Boot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a booting board with no way to present
 * a credential. Declared per file rather than inferred from the absence of
 * one -- see Authorization::_hasNoPrincipal() for what it licenses and why
 * the distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../../commons/base.inc.php';
header("Content-type: text/plain");
/*
 * No explicit mac argument: getHostItem() reads POST then GET on its own, so
 * a board that can only GET is served, and the normalisation (separators,
 * URL-encoding) stays in the one place that already does it.
 */
FOGCore::getHostItem(
    false,
    false,
    true,
    false,
    false
);
new UbootBootMenu();
