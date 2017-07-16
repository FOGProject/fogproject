<?php
/**
 * Plugin configuration file.
 *
 * PHP version 5
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   George Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin configuration file.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = array();
$fog_plugin['name'] = 'windowskey';
$fog_plugin['description'] = sprintf(
    '%s %s. %s %s. %s %s. %s: %s %s.',
    _('Windows keys is a plugin that associates product keys'),
    _('for Microsoft Windows to images'),
    _('Those images should be activated with the associated'),
    _('key'),
    _('The key will be assigned to registered hosts when a'),
    _('deploy task occurs for it'),
    _('NOTE'),
    _('When the plugin is removed, the assigned key will remain'),
    _('with the host')
);
$fog_plugin['menuicon'] = 'fa fa-windows fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
