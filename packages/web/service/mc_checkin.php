<?php
/**
 * Multicast check in
 *
 * PHP version 7.4+
 *
 * @category Multicast_Checkin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Multicast check in
 *
 * @category Multicast_Checkin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
FOGCore::getClass('TaskQueue')
    ->checkIn();
