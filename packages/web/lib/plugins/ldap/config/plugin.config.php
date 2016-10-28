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
$fog_plugin['name'] = 'LDAP';
$fog_plugin['description'] = 'LDAP plugin to use a LDAP validation with FOG'
    . '. Ensure you have the php ldap module installed and loaded on your '
    . 'server.  This can be done typically by using your distros package '
    . 'manager software.  (e.g. apt-get install php5-ldap, '
    . 'yum install php-ldap)';
$fog_plugin['menuicon'] = 'fa fa-key fa-3x fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
