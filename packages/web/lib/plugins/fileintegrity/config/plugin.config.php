<?php
/**
 * Plugin configuration file.
 *
 * PHP version 5
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Wayne Workman <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin configuration file.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Wayne Workman <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = array();
$fog_plugin['name'] = 'fileintegrity';
$fog_plugin['description'] = sprintf(
    '%s %s, %s, %s %s.',
    _('Associates the files on nodes'),
    _('and stores their respective checksums'),
    _('mod dates'),
    _('and the location of the file on that'),
    _('particular node')
);
$fog_plugin['menuicon'] = 'fa fa-list-ol fa-3x fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
