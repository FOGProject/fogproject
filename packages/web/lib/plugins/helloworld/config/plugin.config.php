<?php
/**
 * Hello World example plugin configuration.
 *
 * This file is read by Plugin::getPlugins() during discovery. The
 * $fog_plugin array tells FOG the plugin's machine name (must match the
 * directory name), its description, the menu icon, and a (legacy) entry
 * point. The machine name is also the routing "node" used in
 * ?node=<name>&sub=<method>.
 *
 * PHP version 5
 *
 * @category HelloWorld
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$fog_plugin = [];
// Machine name. MUST equal this plugin's directory name (lowercase).
$fog_plugin['name'] = 'helloworld';
$fog_plugin['description'] = 'Skeleton example plugin demonstrating the FOG '
    . 'plugin structure (config, model, manager, page, hooks, JS).';
// A font-awesome class ("fa ...") is rendered as an icon; anything else is
// treated as an <img> src.
$fog_plugin['menuicon'] = 'fa fa-cube fa-fw';
$fog_plugin['menuicon_hover'] = null;
// Legacy/conventional entry point. No plugin actually ships this file today
// (routing is handled by the node -> page-class mapping), but every plugin
// declares it for consistency.
$fog_plugin['entrypoint'] = 'html/run.php';
