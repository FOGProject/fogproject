<?php
/** Gives access to the FOGCore variables. */
require('../commons/base.inc.php');
//$FOGCore = $GLOBALS['FOGCore'];
/** Get's the active plugin set during load up. */
$plugin = unserialize($_SESSION["fogactiveplugin"]);
/** If it can't get anything from it, fail out. */
if  ($plugin == null)
	die("Unable to determine plugin details");
/** Print the information you want to the screen. */
//Set's the title
$FOGCore->title = _('Plugin').': '.$plugin->getName();
/** Print the description. */
print "\n\t\t\t<p>"._('Plugin Description').': '.$plugin->getDesc().'</p>';
// If the plugin is installed run these items. Only if there's anything to do.
if ($_REQUEST['basics'] == 1)
{
	$FOGCore->setSetting('FOG_PLUGIN_CAPONE_DMI',$_REQUEST['dmifield']);
	$FOGCore->setSetting('FOG_PLUGIN_CAPONE_SHUTDOWN',$_REQUEST['shutdown']);
}
if($_REQUEST['addass'] == 1)
{
	$Capone = new Capone(array(
		'imageID' => $_REQUEST['image'],
		'osID'	  => $FOGCore->getClass('Image',$_REQUEST['image'])->get('osID'),
		'key'	  => $_REQUEST['key']
	));
	$Capone->save();
}
if($_REQUEST['kill'] !== null)
{
	$Capone = new Capone($_REQUEST['kill']);
	$Capone->destroy();
}
print "\n\t\t\t".'<p>This is just an example of information pushed out if the plugin is installed!</p>';
