<?php
require('../commons/base.inc.php');
$plugin = unserialize($_SESSION['fogactiveplugin']);
if (!$plugin) die(_('Unable to determine plugin details'));
$FOGCore->title = sprintf('%s: %s',_('Plugin'),$plugin->getName());
printf('<p>%s: %s</p>',_('Plugin Description'),$plugin->getDesc());
echo '<p>This is just an example of information pushed out if the plugin is installed!</p>';
