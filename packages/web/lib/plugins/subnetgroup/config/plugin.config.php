<?php
/**
 * Subnet Group plugin
 *
 * PHP version 5
 *
 * @category Subnet_Group
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access control plugin
 *
 * @category Subnet_Group
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = [];
$fog_plugin['name'] = 'subnetgroup';
$fog_plugin['description'] = 'Associates host groups with IP subnets'
    . ' in order to automatically assign hosts according to their IP address';
$fog_plugin['menuicon'] = 'fa fa-wifi fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
