<?php
/**
 * Plugin configuration file.
 *
 * PHP version 5
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
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
$fog_plugin['name'] = 'location';
$fog_plugin['description'] = sprintf(
    '%s %s %s. %s %s %s.',
    _('Location is a plugin that allows your FOG Server'),
    _('to operate in an environment where there may be'),
    _('multiple places to get your image'),
    _('This is especially useful if you have multiple'),
    _('sites with clients moving back and forth'),
    _('between different sites')
);
$fog_plugin['menuicon'] = 'fa fa-globe fa-3x fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
