<?php
/**
 * Plugin configuration file.
 *
 * PHP version 5
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin configuration file.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = array();
$fog_plugin['name'] = 'wolbroadcast';
$fog_plugin['description'] = 'Allows you to create WOL across '
    . 'separate broadcast addresses. '
    . 'Should only be used if you cannot edit your network switches.';
$fog_plugin['menuicon'] = 'fa fa-plug fa-3x fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
