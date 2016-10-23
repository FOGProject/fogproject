<?php
/**
 * Plugin run file.
 *
 * PHP version 5
 *
 * @category Run
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin run file.
 *
 * @category Run
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$pluginIDs = self::getSubObjectIDs(
    'Plugin',
    array('name' => 'example')
);
$pluginIDs = @min($pluginIDs);
$plugin = new Plugin($pluginIDs);
if (!$plugin->isValid()) {
    die(
        _('Unable to determine plugin details')
    );
}
$FOGCore->title = sprintf(
    '%s: %s',
    _('Plugin'),
    $plugin->get('name')
);
printf(
    '<p>%s: %s</p>',
    _('Plugin Description'),
    $plugin->get('description')
);
echo '<p>This is just an example of information pushed out '
    . 'if the plugin is installed!</p>';
