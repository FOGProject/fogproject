<?php
/**
 * Plugin configuration file.
 *
 * PHP version 5
 *
 * @category Config
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin configuration file.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = array();
$fog_plugin['name'] = 'ntfy';
$fog_plugin['description'] = 'Adds ntfy notifications '
    . 'using either ntfy.sh default server or self-hosted server.';
$fog_plugin['menuicon'] = 'fa fa-comment fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = 'html/run.php';
