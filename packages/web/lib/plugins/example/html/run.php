<?php
$plugin = self::getClass('Plugin',@min($this->getSubObjectIDs(array('name'=>$_SESSION['fogactiveplugin']))));
if (!$plugin) die(_('Unable to determine plugin details'));
$FOGCore->title = sprintf('%s: %s',_('Plugin'),$plugin->get('name'));
printf('<p>%s: %s</p>',_('Plugin Description'),$plugin->get('description'));
echo '<p>This is just an example of information pushed out if the plugin is installed!</p>';
