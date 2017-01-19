<?php
/**
 * Checks for any jobs for the host
 *
 * PHP version 5
 *
 * @category Jobs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Checks for any jobs for the host
 *
 * @category Jobs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new Jobs(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
