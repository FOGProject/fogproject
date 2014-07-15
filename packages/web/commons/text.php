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
$GLOBALS['foglang'] = array();
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
<<<<<<< HEAD
=======
$foglang['InvalidMAC']				= _('Invalid MAC Address!');
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
// Language menu options.
$foglang['Language']['zh']			= _('中国的');
$foglang['Language']['en']			= _('English');
$foglang['Language']['es']			= _('Español');
$foglang['Language']['fr']			= _('Français');
<<<<<<< HEAD
=======
$foglang['Language']['de']			= _('Deutsch');
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
$foglang['Language']['it']			= _('Italiano');
