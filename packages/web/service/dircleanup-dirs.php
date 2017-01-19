<?php
/**
 * Sends the directories to clean up.
 * Mainly for the legacy client.
 *
 * PHP version 5
 *
 * @category Dircleanup_Dirs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sends the directories to clean up.
 * Mainly for the legacy client.
 *
 * @category Dircleanup_Dirs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new DirectoryCleanup(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
