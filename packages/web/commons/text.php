<?php
/** This is a starter file.  It's purpose, in my eyes, is to contain
 ** all the text within fog that needs to be translated for other
 ** languages.  The idea is to make the translations needed all
 ** in one file.  You just call the variable and array you need.
 ** The other idea of this is to make one location for multiple
 ** calls.  For example, Host updated and Printer updated would
 ** only need to be called as %s updated.  The word updated, could
 ** then be translated just the one time for all the languages.
 ** Then the element Host or Printer could be translated later.
 **/
//Set the global var to simpler calling.
$foglang 							= $GLOBALS['foglang'];
//Singular, status words to translate.
$foglang['Display']					= _('Display');
$foglang['Auto']					= _('Auto');
$foglang['Model']					= _('Model');
$foglang['Inventory']				= _('Inventory');
$foglang['OS']						= _('O/S');
$foglang['Edit'] 					= _('Edit');
$foglang['Delete'] 					= _('Delete');
$foglang['Deleted'] 				= _('Deleted');
$foglang['All'] 					= _('All');
$foglang['Add'] 					= _('Add');
$foglang['Search'] 					= _('Search');
$foglang['Storage'] 				= _('Storage');
$foglang['Snapin'] 					= _('Snapin');
$foglang['Snapins']					= _('Snapins');
$foglang['Remove'] 					= _('Remove');
$foglang['Removed'] 				= _('Removed');
$foglang['Enabled'] 				= _('Enabled');
$foglang['Management'] 				= _('Management');
$foglang['Update'] 					= _('Update');
$foglang['Image'] 					= _('Image');
$foglang['Images']					= _('Images');
$foglang['Node']					= _('Node');
$foglang['Group']					= _('Group');
$foglang['Groups']					= _('Groups');
$foglang['Logout']					= _('Logout');
$foglang['Host']					= _('Host');
$foglang['Hosts']					= _('Hosts');
$foglang['Bandwidth']				= _('Bandwidth');
$foglang['Transmit']				= _('Transmit');
$foglang['Receive']					= _('Receive');
$foglang['New']						= _('New');
$foglang['User']					= _('User');
$foglang['Users']					= _('Users');
$foglang['Name']					= _('Name');
$foglang['Members']					= _('Members');
$foglang['Advanced']				= _('Advanced');
$foglang['Hostname']				= _('Hostname');
$foglang['IP']						= _('IP');
$foglang['MAC']						= _('MAC');
$foglang['Version']					= _('Version');
$foglang['Text']					= _('Text');
$foglang['Graphical']				= _('Graphical');
$foglang['File']					= _('File');
$foglang['Path']					= _('Path');
$foglang['Shutdown']				= _('Shutdown');
$foglang['Reboot']					= _('Reboot');
$foglang['Time']					= _('Time');
$foglang['Action']					= _('Action');
$foglang['Printer']					= _('Printer');
$foglang['Client']					= _('Client');
$foglang['Task']					= _('Task');
$foglang['Username']				= _('Username');
$foglang['Service']					= _('Service');
$foglang['General']					= _('General');
$foglang['Mode']					= _('Mode');
$foglang['Date']					= _('Date');
$foglang['Clear']					= _('Clear');
$foglang['Desc']					= _('Description');
$foglang['Here']					= _('here');
$foglang['NOT']						= _('NOT');
$foglang['or']						= _('or');
$foglang['Row']						= _('Row');
$foglang['Errors']					= _('Errors');
$foglang['Error']					= _('Error');
$foglang['Export']					= _('Export');
$foglang['Schedule']				= _('Schedule');
$foglang['Deploy']					= _('Deploy');
$foglang['Upload']					= _('Upload');
$foglang['Multicast']				= _('Multicast');
$foglang['Status']					= _('Status');
$foglang['Actions']					= _('Actions');
$foglang['Hosts']					= _('Hosts');
$foglang['State']					= _('State');
$foglang['Kill']					= _('Kill');
$foglang['Kernel']					= _('Kernel');
$foglang['Location']				= _('Location');
$foglang['N/A']						= _('N/A');
$foglang['Home']					= _('Home');
$foglang['Report']					= _('Report');
$foglang['Reports']					= _('Reports');
$foglang['Login']					= _('Login');
$foglang['Queued']					= _('Queued');
$foglang['Complete']				= _('Complete');
$foglang['Unknown']					= _('Unknown');
$foglang['Force']					= _('Force');
$foglang['Type']					= _('Type');
$foglang['Settings']				= _('Settings');
$foglang['FOG']						= _('FOG');
$foglang['Active']					= _('Active');
$foglang['Printers']				= _('Printers');
$foglang['Directory']				= _('Directory');
$foglang['AD']						= _('Active Directory');
$foglang['VirusHistory']			= _('Virus History');
$foglang['LoginHistory']			= _('Login History');
$foglang['Configuration']			= _('Configuration');
$foglang['Plugin']					= _('Plugin');
$foglang['Locations']				= _('Locations');
$foglang['Location']				= _('Location');
$foglang['License']					= _('License');
$foglang['KernelUpdate']			= _('Kernel Update');
$foglang['PXEBootMenu']				= _('PXE Boot Menu');
$foglang['ClientUpdater']			= _('Client Updater');
$foglang['HostnameChanger']			= _('Hostname Changer');
$foglang['HostRegistration']		= _('Host Registration');
$foglang['SnapinClient']			= _('Snapin Client');
$foglang['TaskReboot']				= _('Task Reboot');
$foglang['UserCleanup']				= _('User Cleanup');
$foglang['UserTracker']				= _('User Tracker');
$foglang['SelManager']				= _('%s Manager');
$foglang['GreenFOG']				= _('Green FOG');
$foglang['DirectoryCleaner']		= _('Directory Cleaner');
$foglang['MACAddrList']				= _('MAC Address List');
$foglang['FOGSettings']				= _('FOG Settings');
$foglang['ServerShell']				= _('Server Shell');
$foglang['LogViewer']				= _('Log Viewer');
$foglang['ConfigSave']				= _('Configuration Save');
$foglang['FOGSFPage']				= _('FOG Sourceforge Page');
$foglang['FOGWebPage']				= _('FOG Home Page');
$foglang['NewSearch']				= _('New Search');
$foglang['ListAll']					= _('List All %s');
$foglang['CreateNew']				= _('Create New %s');
$foglang['BasicTasks']				= _('Basic Tasks');
$foglang['Membership']				= _('Membership');
$foglang['ImageAssoc']				= _('Image Association');
$foglang['SelMenu']					= _('%s Menu');
$foglang['PrimaryGroup']			= _('Primary Group');
$foglang['AllSN']					= _('All Storage Nodes');
$foglang['AddSN']					= _('Add Storage Node');
$foglang['AllSG']					= _('All Storage Groups');
$foglang['AddSG']					= _('Add Storage Group');
$foglang['ActiveTasks'] 			= _('Active Tasks');
$foglang['ActiveMCTasks']  			= _('Active Multicast Tasks');
$foglang['ActiveSnapins']  			= _('Active Snapin Tasks');
$foglang['ScheduledTasks'] 			= _('Scheduled Tasks');
$foglang['InstalledPlugins']		= _('Installed Plugins');
$foglang['ActivatePlugins']			= _('Activate Plugins');
$foglang['ExportConfig']			= _('Export Configuration');
$foglang['ImportConfig']			= _('Import Configuration');
$foglang['Slogan']					= _('Open Source Computer Cloning Solution');
// Language menu options.
$foglang['Language']['zh']			= _('中国的');
$foglang['Language']['en']			= _('English');
$foglang['Language']['es']			= _('Español');
$foglang['Language']['fr']			= _('Français');
$foglang['Language']['it']			= _('Italiano');
/** Sub Menu Items Common items will contain placeholders.
 ** Each node has it's own subset.
 */
// This checks values for sub/sub menu item generation.
$foglinktype = 'id';
$id = $_GET['id'];
$delformat = $_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=delete&'.$foglinktype.'='.$id;
$linkformat = $_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=edit&'.$foglinktype.'='.$id;
// Group Sub/Sub menu items.
if ($_GET['node'] == 'group')
{
	$foglang['SubMenu']['group']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['group']['list'] = sprintf($foglang['ListAll'],$foglang['Groups']);
	$foglang['SubMenu']['group']['add'] = sprintf($foglang['CreateNew'],$foglang['Group']);
	if ($_GET['id'])
	{
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-general'] = $foglang['General'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-tasks'] = $foglang['BasicTasks'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-membership'] = $foglang['Membership'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-image'] = $foglang['ImageAssoc'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-snap-add'] = $foglang['Add'].' '.$foglang['Snapins'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-snap-del'] = $foglang['Remove'].' '.$foglang['Snapins'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-service'] = $foglang['Service'].' '.$foglang['Settings'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-active-directory'] = $foglang['AD'];
		$foglang['SubMenu']['group']['id'][$linkformat.'#group-printers'] = $foglang['Printers'];
		$foglang['SubMenu']['group']['id'][$delformat] = $foglang['Delete'];
	}
}
// Host Sub/Sub menu items.
if ($_GET['node'] == 'host')
{
	$foglang['SubMenu']['host']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['host']['list'] = sprintf($foglang['ListAll'],$foglang['Hosts']);
	$foglang['SubMenu']['host']['add'] = sprintf($foglang['CreateNew'],$foglang['Host']);
	$foglang['SubMenu']['host']['export'] = _('Export Hosts');
	$foglang['SubMenu']['host']['import'] = _('Import Hosts');
	if($_GET['id'])
	{
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-general'] = $foglang['General'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-grouprel'] = $foglang['Groups'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-tasks'] = $foglang['BasicTasks'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-active-directory'] = $foglang['AD'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-printers'] = $foglang['Printers'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-snapins'] = $foglang['Snapins'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-service'] = $foglang['Service'].' '.$foglang['Settings'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-hardware-inventory'] = $foglang['Inventory'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-virus-history'] = $foglang['VirusHistory'];
		$foglang['SubMenu']['host']['id'][$linkformat.'#host-login-history'] = $foglang['LoginHistory'];
		$foglang['SubMenu']['host']['id'][$delformat] = $foglang['Delete'];
	}
}
// Image Sub/Sub menu items.
if ($_GET['node'] == 'images')
{
	$foglang['SubMenu']['images']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['images']['list'] = sprintf($foglang['ListAll'],$foglang['Images']);
	$foglang['SubMenu']['images']['add'] = sprintf($foglang['CreateNew'],$foglang['Image']);
	if ($_GET['id'])
	{
		$foglang['SubMenu']['images']['id'][$linkformat.'#image-gen'] = $foglang['General'];
		$foglang['SubMenu']['images']['id'][$linkformat.'#image-host'] = $foglang['Host'];
		$foglang['SubMenu']['images']['id'][$delformat] = $foglang['Delete'];
	}
}
// Printer Sub/Sub menu items.
if ($_GET['node'] == 'printer' || $_GET['node'] == 'print')
{
	$foglang['SubMenu']['printer']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['printer']['list'] = sprintf($foglang['ListAll'],$foglang['Printers']);
	$foglang['SubMenu']['printer']['add'] = sprintf($foglang['CreateNew'],$foglang['Printer']);
	if ($_GET['id'])
	{
		$foglang['SubMenu']['printer']['id'][$linkformat] = $foglang['General'];
		$foglang['SubMenu']['printer']['id'][$delformat] = $foglang['Delete'];
	}
}
// Configuration Sub/Sub menu items.
if ($_GET['node'] == 'about')
{
	$foglang['SubMenu']['about']['license'] = $foglang['License'];
	$foglang['SubMenu']['about']['kernel-update'] = $foglang['KernelUpdate'];
	$foglang['SubMenu']['about']['pxemenu']	= $foglang['PXEBootMenu'];
	$foglang['SubMenu']['about']['client-updater'] = $foglang['ClientUpdater'];
	$foglang['SubMenu']['about']['mac-list'] = $foglang['MACAddrList'];
	$foglang['SubMenu']['about']['settings'] = $foglang['FOGSettings'];
	$foglang['SubMenu']['about']['log'] = $foglang['LogViewer'];
	$foglang['SubMenu']['about']['config'] = $foglang['ConfigSave'];
	$foglang['SubMenu']['about']['http://www.sf.net/projects/freeghost'] = $foglang['FOGSFPage'];
	$foglang['SubMenu']['about']['http://fogproject.org'] = $foglang['FOGWebPage'];
}
// Report Sub/Sub menu items, created Dynamically..
if ($_GET['node'] == 'report')
{
	$foglang['SubMenu']['report']['home'] = $foglang['Home'];
	$foglang['SubMenu']['report']['equip-loan'] = _('Equipment Loan');
	$foglang['SubMenu']['report']['host-list'] = _('Host List');
	$foglang['SubMenu']['report']['imaging-log'] = _('Imaging Log');
	$foglang['SubMenu']['report']['inventory'] = _('Inventory');
	$foglang['SubMenu']['report']['pend-mac'] = _('Pending MACs');
	$foglang['SubMenu']['report']['snapin-log'] = _('Snapin Log');
	$foglang['SubMenu']['report']['user-track'] = _('User Login Hist');
	$foglang['SubMenu']['report']['vir-hist'] = _('Virus History');
	// Report link for the files contained within the reports directory.
	$reportlink = $_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=file&f=';
	$dh = opendir($GLOBALS['FOGCore']->getSetting('FOG_REPORT_DIR'));
	if ($dh != null)
	{
		while (!(($f=readdir($dh)) === false))
		{
			if (is_file($GLOBALS['FOGCore']->getSetting('FOG_REPORT_DIR').$f))
			{
				if (substr($f,strlen($f) - strlen('.php')) === '.php' && substr($f,0,strlen($f) -4) != 'Imaging Log')
					$foglang['SubMenu']['report'][$reportlink.base64_encode($f)] = substr($f,0,strlen($f) -4);
			}
		}
	}
	$foglang['SubMenu']['report']['upload'] = _('Upload Reports');
}
// Service Sub/Sub menu items.
if ($_GET['node'] == 'service')
{
	// The service links redirects/tabs.
	$servicelink = $_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=edit';
	$foglang['SubMenu']['service'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'#home'] = $foglang['Home'];
	$foglang['SubMenu']['service'][$servicelink.'#autologout'] = $foglang['Auto'].' '.$foglang['Logout'];
	$foglang['SubMenu']['service'][$servicelink.'#clientupdater'] = $foglang['ClientUpdater'];
	$foglang['SubMenu']['service'][$servicelink.'#dircleanup'] = $foglang['DirectoryCleaner'];
	$foglang['SubMenu']['service'][$servicelink.'#displaymanager'] = sprintf($foglang['SelManager'],$foglang['Display']);
	$foglang['SubMenu']['service'][$servicelink.'#greenfog'] = $foglang['GreenFOG'];
	$foglang['SubMenu']['service'][$servicelink.'#hostregister'] = $foglang['HostRegistration'];
	$foglang['SubMenu']['service'][$servicelink.'#hostnamechanger'] = $foglang['HostnameChanger'];
	$foglang['SubMenu']['service'][$servicelink.'#printermanager'] = sprintf($foglang['SelManager'],$foglang['Printer']);
	$foglang['SubMenu']['service'][$servicelink.'#snapin'] = $foglang['SnapinClient'];
	$foglang['SubMenu']['service'][$servicelink.'#taskreboot'] = $foglang['TaskReboot'];
	$foglang['SubMenu']['service'][$servicelink.'#usercleanup'] = $foglang['UserCleanup'];
	$foglang['SubMenu']['service'][$servicelink.'#usertracker'] = $foglang['UserTracker'];
}
// Snapin Sub/Sub menu items.
if ($_GET['node'] == 'snapin')
{
	$foglang['SubMenu']['snapin']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['snapin']['list'] = sprintf($foglang['ListAll'],$foglang['Snapins']);
	$foglang['SubMenu']['snapin']['add'] = sprintf($foglang['CreateNew'],$foglang['Snapin']);
	if ($_GET['id'])
	{
		$foglang['SubMenu']['snapin']['id'][$linkformat.'#snap-gen'] = $foglang['General'];
		$foglang['SubMenu']['snapin']['id'][$linkformat.'#snap-host'] = $foglang['Host'];
		$foglang['SubMenu']['snapin']['id'][$delformat] = $foglang['Delete'];
	}
}
// Storage Sub/Sub menu items.
if ($_GET['node'] == 'storage')
{
	$foglang['SubMenu']['storage'][$_SERVER['PHP_SELF'].'?node='.$_GET['node']] = $foglang['AllSN'];
	$foglang['SubMenu']['storage'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=add-storage-node'] = $foglang['AddSN'];
	$foglang['SubMenu']['storage'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=storage-group'] = $foglang['AllSG'];
	$foglang['SubMenu']['storage'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=add-storage-group'] = $foglang['AddSG'];
	if ($_GET['sub'] == 'edit')
	{
		$foglang['SubMenu']['storage']['id'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub='.$_GET['sub'].'&id='.$_GET['id']] = $foglang['General'];
		$foglang['SubMenu']['storage']['id'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=delete-storage-node&id='.$_GET['id']] = $foglang['Delete'];
	}
	if ($_GET['sub'] == 'edit-storage-group')
	{
		$foglang['SubMenu']['storage']['id'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub='.$_GET['sub'].'&id='.$_GET['id']] = $foglang['General'];
		$foglang['SubMenu']['storage']['id'][$_SERVER['PHP_SELF'].'?node='.$_GET['node'].'&sub=delete-storage-group&id='.$_GET['id']] = $foglang['Delete'];
	}
}
// Task Sub/Sub menu items.
if ($_GET['node'] == 'tasks')
{
	$foglang['SubMenu']['tasks']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['tasks']['active'] = $foglang['ActiveTasks'];
	$foglang['SubMenu']['tasks']['listhosts'] = sprintf($foglang['ListAll'],$foglang['Hosts']);
	$foglang['SubMenu']['tasks']['listgroups'] = sprintf($foglang['ListAll'],$foglang['Groups']);
	$foglang['SubMenu']['tasks']['active-multicast'] = $foglang['ActiveMCTasks'];
	$foglang['SubMenu']['tasks']['active-snapins'] = $foglang['ActiveSnapins'];
	$foglang['SubMenu']['tasks']['scheduled'] = $foglang['ScheduledTasks'];
}
// User Sub/Sub menu items.
if ($_GET['node'] == 'users')
{
	$foglang['SubMenu']['users']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['users']['list'] = sprintf($foglang['ListAll'],$foglang['Users']);
	$foglang['SubMenu']['users']['add'] = sprintf($foglang['CreateNew'],$foglang['User']);
	if ($_GET['id'])
	{
		$foglang['SubMenu']['users']['id'][$linkformat] = $foglang['General'];
		$foglang['SubMenu']['users']['id'][$delformat] = $foglang['Delete'];
	}
}
// Location Sub/Sub menu items.
if ($_GET['node'] == 'location' || $_GET['node'] == 'locations')
{
	$foglang['SubMenu']['location']['search'] = $foglang['NewSearch'];
	$foglang['SubMenu']['location']['list'] = sprintf($foglang['ListAll'],$foglang['Locations']);
	$foglang['SubMenu']['location']['add'] = sprintf($foglang['CreateNew'],$foglang['Location']);
	if ($_GET['id'])
	{
		$foglang['SubMenu']['location']['id'][$linkformat] = $foglang['General'];
		$foglang['SubMenu']['location']['id'][$delformat] = $foglang['Delete'];
	}
}
// Plugin Sub/Sub menu items.
if ($_GET['node'] == 'plugin')
{
	$foglang['SubMenu']['plugin']['home'] = $foglang['Home'];
	$foglang['SubMenu']['plugin']['installed'] = $foglang['InstalledPlugins'];
	$foglang['SubMenu']['plugin']['activate'] = $foglang['ActivatePlugins'];
}
// ServerInfo Sub/Sub menu items.
if ($_GET['node'] == 'hwinfo')
{
	$foglang['SubMenu']['hwinfo']['home&id='.$_GET['id']] = $foglang['Home'];
}
