<?php
/**
 * Gets version information
 *
 * PHP version 7.4+
 *
 * @category Mainversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Gets version information
 *
 * @category Mainversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);

// Ask fogproject.org which versions are current, the same endpoint 1.6
// uses. This page used to work it out itself from GitHub: it took the
// FIRST entry of the /tags API as "stable". GitHub lists tags by name,
// so a non-release tag (archive/feature-fog2-gui) or a 1.6 RC sorts
// ahead of 1.5.10.x and was reported as the latest stable version. The
// 1.6 lookup also read lib/fog/system.class.php, which working-1.6 no
// longer has. The server knows each channel, so this page only relays.
$res = $FOGURLRequests->process(
    'https://fogproject.org/version/index.php',
    'POST',
    ['version' => FOG_VERSION]
);
echo json_encode(array_shift($res));
exit;
