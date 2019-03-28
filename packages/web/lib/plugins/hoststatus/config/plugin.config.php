<?php
/**
 * Plugin configuration file.
 *
 * @category Config
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@ehu.eus>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = array();
$fog_plugin['name'] = 'hoststatus';
$fog_plugin['description'] = sprintf(
    '%s %s. %s. %s. %s.',
    _('Host Status is a plugin that adds a new entry in the Host edit Page'),
    _('that detects the status on the fly, poweron or poweroff and the OS, of the client'),
    _('<p>Possible status: Windows, Linux, FOS and Unknown'),
    _('<p>Dependencies: port TCP 445 open in the client side'),
    _('<p>Version 1.5.5')
);
$fog_plugin['menuicon'] = 'fa fa-eye fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
