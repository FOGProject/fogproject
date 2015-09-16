<?php
/** Gives access to the FOGCore variables. */
require_once('../commons/base.inc.php');
//$FOGCore = $GLOBALS['FOGCore'];
/** Get's the active plugin set during load up. */
$plugin = unserialize($_SESSION['fogactiveplugin']);
/** If it can't get anything from it, fail out. */
if (!$plugin) die(_('Unable to determine plugin details'));
/** Print the information you want to the screen. */
//Set's the title
$FOGCore->title = sprintf('%s: %s',_('Plugin'),$plugin->getName());
/** Print the description. */
printf('<p>%s: %s</p>',_('Plugin Description'),$plugin->getDesc());
// If the plugin is installed do these things how you constructed them.
echo '<p>This is just an example of information pushed out if the plugin is installed!</p>';
