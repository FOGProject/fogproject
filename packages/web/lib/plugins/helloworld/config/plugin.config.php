<?php
/**
 * Hello World example plugin configuration.
 *
 * This file is the plugin's manifest, read by Plugin::readManifest() during
 * discovery. The $fog_plugin array tells FOG the plugin's machine name (must
 * match the directory name), its description, its menu icon, its own version,
 * the range of FOG it supports, and any plugins it depends on. The machine
 * name is also the routing "node" used in ?node=<name>&sub=<method>.
 *
 * Every key except 'name' is optional; a manifest that declares nothing but a
 * name still works, which is why plugins written before the manifest existed
 * keep loading unchanged.
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
// Your plugin's own version. Shown in Plugin Management and written to
// plugins.pVersion, so "which build is installed here" has an answer. Nothing
// compares it to anything -- it is yours to number as you like.
$fog_plugin['version'] = '1.6.0';
// The range of FOG this plugin works on. Both bounds are optional and an
// absent bound means no bound, so a plugin declaring neither runs everywhere.
// FOG refuses to activate or install a plugin outside its range, and
// deactivates an already-active one on the boot after an upgrade moves the
// server out of it -- installed state and applied migrations are kept, so
// re-activating once you ship a compatible build is one click.
//
// Only the numeric core is compared: FOG_VERSION is '1.6.0-beta.3318' on a
// beta, and version_compare() sorts that BELOW '1.6.0'.
$fog_plugin['fog_min'] = '1.6.0';
// $fog_plugin['fog_max'] = '1.7.0';
// Other plugins that must be installed and active before this one can be.
// Names are directory names. Plugins turned on in the same batch count as
// satisfying each other, so ordering in the UI doesn't matter.
// $fog_plugin['requires'] = ['location'];
$fog_plugin['author'] = 'FOG Project';
$fog_plugin['homepage'] = 'https://fogproject.org';
