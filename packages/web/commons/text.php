<?php
/**
 * This is a starter file. It's purpose, in my eyes, is to contain
 * all the text within fog that needs to be translated for other
 * languages. The idea is to make the translations needed all
 * in one file. You just call the variable and array you need.
 * The other idea of this is to make one location for multiple
 * calls. For example, Host updated and Printer updated would
 * only need to be called as %s updated. The word updated, could
 * then be translated just the one time for all the languages.
 * Then the element Host or Printer could be translated later.
 *
 * PHP version 5
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
//Singular, status words to translate.
$foglang['Display'] = _('Display');
$foglang['Auto'] = _('Auto');
$foglang['Model'] = _('Model');
$foglang['Inventory'] = _('Inventory');
$foglang['OS'] = _('O/S');
$foglang['Edit'] = _('Edit');
$foglang['Delete'] = _('Delete');
$foglang['Deleted'] = _('Deleted');
$foglang['All'] = _('All');
$foglang['Add'] = _('Add');
$foglang['Search'] = _('Search');
$foglang['Storage'] = _('Storage');
$foglang['Snapin'] = _('Snapin');
$foglang['Snapins'] = _('Snapins');
$foglang['Remove'] = _('Remove');
$foglang['Removed'] = _('Removed');
$foglang['Enabled'] = _('Enabled');
$foglang['Management'] = _('Management');
$foglang['Update'] = _('Update');
$foglang['Image'] = _('Image');
$foglang['Images'] = _('Images');
$foglang['Node'] = _('Node');
$foglang['Group'] = _('Group');
$foglang['Groups'] = _('Groups');
$foglang['Logout'] = _('Logout');
$foglang['Host'] = _('Host');
$foglang['Hosts'] = _('Hosts');
$foglang['Bandwidth'] = _('Bandwidth');
$foglang['BandwidthReplication'] = _('Replication Bandwidth');
$foglang['BandwidthRepHelp'] = sprintf(
    '%s. %s %s. %s %s %s, %s.',
    _('This setting limits the bandwidth for replication between nodes'),
    _('It operates by getting the max bandwidth setting of the node'),
    _('it\'s transmitting to'),
    _('So if you are trying to transmit to remote node A'),
    _('and node A only has a 5Mbps and you want the speed'),
    _('limited to 1Mbps on that node'),
    _('you set the bandwidth field on that node to 1000')
);
$foglang['Transmit'] = _('Transmit');
$foglang['Receive'] = _('Receive');
$foglang['New'] = _('New');
$foglang['User'] = _('User');
$foglang['Users'] = _('Users');
$foglang['Name'] = _('Name');
$foglang['Members'] = _('Members');
$foglang['Advanced'] = _('Advanced');
$foglang['Hostname'] = _('Hostname');
$foglang['IP'] = _('IP');
$foglang['MAC'] = _('MAC');
$foglang['Version'] = _('Version');
$foglang['Text'] = _('Text');
$foglang['Graphical'] = _('Graphical');
$foglang['File'] = _('File');
$foglang['Path'] = _('Path');
$foglang['Shutdown'] = _('Shutdown');
$foglang['Reboot'] = _('Reboot');
$foglang['Time'] = _('Time');
$foglang['Action'] = _('Action');
$foglang['Printer'] = _('Printer');
$foglang['PowerManagement'] = _('Power Management');
$foglang['Client'] = _('Client');
$foglang['Task'] = _('Task');
$foglang['Username'] = _('Username');
$foglang['Service'] = _('Service');
$foglang['General'] = _('General');
$foglang['Mode'] = _('Mode');
$foglang['Date'] = _('Date');
$foglang['Clear'] = _('Clear');
$foglang['Desc'] = _('Description');
$foglang['Here'] = _('here');
$foglang['NOT'] = _('NOT');
$foglang['or'] = _('or');
$foglang['Row'] = _('Row');
$foglang['Errors'] = _('Errors');
$foglang['Error'] = _('Error');
$foglang['Export'] = _('Export');
$foglang['Schedule'] = _('Schedule');
$foglang['Deploy'] = _('Deploy');
$foglang['Capture'] = _('Capture');
$foglang['Multicast'] = _('Multicast');
$foglang['Status'] = _('Status');
$foglang['Actions'] = _('Actions');
$foglang['Hosts'] = _('Hosts');
$foglang['State'] = _('State');
$foglang['Kill'] = _('Kill');
$foglang['Kernel'] = _('Kernel');
$foglang['Location'] = _('Location');
$foglang['N/A'] = _('N/A');
$foglang['Home'] = _('Home');
$foglang['Report'] = _('Report');
$foglang['Reports'] = _('Reports');
$foglang['Login'] = _('Login');
$foglang['Queued'] = _('Queued');
$foglang['Complete'] = _('Complete');
$foglang['Unknown'] = _('Unknown');
$foglang['Force'] = _('Force');
$foglang['Type'] = _('Type');
$foglang['Settings'] = _('Settings');
$foglang['FOG'] = _('FOG');
$foglang['Active'] = _('Active');
$foglang['Printers'] = _('Printers');
$foglang['Directory'] = _('Directory');
$foglang['AD'] = _('Active Directory');
$foglang['VirusHistory'] = _('Virus History');
$foglang['LoginHistory'] = _('Login History');
$foglang['ImageHistory'] = _('Image History');
$foglang['SnapinHistory'] = _('Snapin History');
$foglang['Configuration'] = _('Configuration');
$foglang['Plugin'] = _('Plugin');
$foglang['Locations'] = _('Locations');
$foglang['Location'] = _('Location');
$foglang['License'] = _('License');
$foglang['KernelUpdate'] = _('Kernel Update');
$foglang['PXEBootMenu'] = _('iPXE General Configuration');
$foglang['ClientUpdater'] = _('Client Updater');
$foglang['HostnameChanger'] = _('Hostname Changer');
$foglang['HostRegistration'] = _('Host Registration');
$foglang['SnapinClient'] = _('Snapin Client');
$foglang['TaskReboot'] = _('Task Reboot');
$foglang['UserCleanup'] = _('User Cleanup');
$foglang['UserTracker'] = _('User Tracker');
$foglang['SelManager'] = _('%s Manager');
$foglang['GreenFOG'] = _('Green FOG');
$foglang['DirectoryCleaner'] = _('Directory Cleaner');
$foglang['MACAddrList'] = _('MAC Address List');
$foglang['FOGSettings'] = _('FOG Settings');
$foglang['ServerShell'] = _('Server Shell');
$foglang['LogViewer'] = _('Log Viewer');
$foglang['ConfigSave'] = _('Configuration Save');
$foglang['FOGSFPage'] = _('FOG Sourceforge Page');
$foglang['FOGWebPage'] = _('FOG Home Page');
$foglang['NewSearch'] = _('New Search');
$foglang['ListAll'] = _('List All %s');
$foglang['CreateNew'] = _('Create New %s');
$foglang['Tasks'] = _('Tasks');
$foglang['ClientSettings'] = _('Client Settings');
$foglang['Plugins'] = _('Plugins');
$foglang['BasicTasks'] = _('Basic Tasks');
$foglang['Membership'] = _('Membership');
$foglang['ImageAssoc'] = _('Image Association');
$foglang['SelMenu'] = _('%s Menu');
$foglang['PrimaryGroup'] = _('Primary Group');
$foglang['AllSN'] = _('All Storage Nodes');
$foglang['AddSN'] = _('Add Storage Node');
$foglang['AllSG'] = _('All Storage Groups');
$foglang['AddSG'] = _('Add Storage Group');
$foglang['ActiveTasks'] = _('Active Tasks');
$foglang['ActiveMCTasks'] = _('Active Multicast Tasks');
$foglang['ActiveSnapins'] = _('Active Snapin Tasks');
$foglang['ScheduledTasks'] = _('Scheduled Tasks');
$foglang['InstalledPlugins'] = _('Installed Plugins');
$foglang['InstallPlugins'] = _('Install Plugins');
$foglang['ActivatePlugins'] = _('Activate Plugins');
$foglang['ExportConfig'] = _('Export Configuration');
$foglang['ImportConfig'] = _('Import Configuration');
$foglang['Slogan'] = _('Open Source Computer Cloning Solution');
$foglang['InvalidMAC'] = _('Invalid MAC Address!');
$foglang['PXEConfiguration'] = _('iPXE Menu Item Settings');
$foglang['PXEMenuCustomization'] = _('iPXE Menu Customization');
$foglang['NewMenu'] = _('iPXE New Menu Entry');
$foglang['Submit'] = _('Save Changes');
$foglang['RequiredDB'] = _('Required database field is empty');
$foglang['NoResults'] = _('No results found');
$foglang['isRequired'] = _('%s is required');
// Page Names
$foglang['Host Management'] = _('Host Management');
$foglang['Storage Management'] = _('Storage Management');
$foglang['Task Management'] = _('Task Management');
$foglang['Client Management'] = _('Client Management');
$foglang['Dashboard'] = _('Dashboard');
$foglang['Service Configuration'] = _('Service Configuration');
$foglang['Report Management'] = _('Report Management');
$foglang['Printer Management'] = _('Printer Management');
$foglang['FOG Configuration'] = _('FOG Configuration');
$foglang['Group Management'] = _('Group Management');
$foglang['Image Management'] = _('Image Management');
$foglang['User Management'] = _('User Management');
$foglang['Hardware Information'] = _('Hardware Information');
$foglang['Snapin Management'] = _('Snapin Management');
$foglang['Plugin Management'] = _('Plugin Management');
$foglang['Location Management'] = _('Location Management');
$foglang['Access Management'] = _('Access Control Management');
// Help page translations
$foglang['GenHelp'] = _('FOG General Help');
// Sub Menu translates
$foglang['PendingHosts'] = _('Pending Hosts');
$foglang['LastDeployed'] = _('Last Deployed');
$foglang['LastCaptured'] = _('Last Captured');
$foglang['DeployMethod'] = _('Deploy Method');
$foglang['ImageType'] = _('Image Type');
$foglang['NoAvail'] = _('Not Available');
$foglang['ExportHost'] = _('Export Hosts');
$foglang['ImportHost'] = _('Import Hosts');
$foglang['ExportUser'] = _('Export Users');
$foglang['ImportUser'] = _('Import Users');
$foglang['ExportImage'] = _('Export Images');
$foglang['ImportImage'] = _('Import Images');
$foglang['ExportGroup'] = _('Export Groups');
$foglang['ImportGroup'] = _('Import Groups');
$foglang['ExportSnapin'] = _('Export Snapins');
$foglang['ImportSnapin'] = _('Import Snapins');
$foglang['ExportPrinter'] = _('Export Printers');
$foglang['ImportPrinter'] = _('Import Printers');
$foglang['EquipLoan'] = _('Equipment Loan');
$foglang['HostList'] = _('Host List');
$foglang['ImageLog'] = _('Imaging Log');
$foglang['PendingMACs'] = _('Pending MACs');
$foglang['SnapinLog'] = _('Snapin Log');
$foglang['UploadRprts'] = _('Upload Reports');
// FOG Sub Menu translates
$foglang['MainMenu'] = _('Main Menu');
// ProcessLogin
$foglang['InvalidLogin'] = _('Invalid Login');
$foglang['NotAllowedHere'] = _('Not allowed here');
$foglang['ManagementLogin'] = _('Management Login');
$foglang['Password'] = _('Password');
$foglang['FOGSites'] = _('Estimated FOG Sites');
$foglang['LatestVer'] = _('Latest Version');
$foglang['LatestDevVer'] = _('Latest Development Version');
// Image class Translates
$foglang['ProtectedImage'] = _('Image is protected and cannot be deleted');
$foglang['ProtectedSnapin'] = _('Snapin is protected and cannot be deleted');
$foglang['NoMasterNode'] = _('No master nodes are enabled to delete this image');
$foglang['FailedDeleteImage'] = _('Failed to delete image files');
$foglang['FailedDelete'] = _('Failed to delete file');
// PXEMenu Translates
$foglang['NotRegHost'] = _('Not Registered Hosts');
$foglang['RegHost'] = _('Registered Hosts');
$foglang['AllHosts'] = _('All Hosts');
$foglang['DebugOpts'] = _('Debug Options');
$foglang['AdvancedOpts'] = _('Advanced Options');
$foglang['AdvancedLogOpts'] = _('Advanced Login Required');
$foglang['PendRegHost'] = _('Pending Registered Hosts');
// FOGCore Translates
$foglang['n/a'] = _('n/a');
// Service Translates
$foglang['DirExists'] = _('Directory Already Exists');
$foglang['TimeExists'] = _('Time Already Exists');
$foglang['UserExists'] = _('User Already Exists');
// Host class translates
$foglang['NoActSnapJobs'] = _('No Active Snapin Jobs Found For Host');
$foglang['FailedTask'] = _('Failed to create task');
$foglang['InTask'] = _('Host is already a member of an active task');
$foglang['HostNotValid'] = _('Host is not valid');
$foglang['GroupNotValid'] = _('Group is not valid');
$foglang['TaskTypeNotValid'] = _('Task Type is not valid');
$foglang['ImageNotValid'] = _('Image is not valid');
$foglang['ImageGroupNotValid'] = _('The image storage group assigned is not valid');
$foglang['SnapNoAssoc'] = _('There are no snapins associated with this host');
$foglang['SnapDeploy'] = _('Snapins Are already deployed to this host');
$foglang['NoFoundSG'] = sprintf(
    '%s %s.',
    _('Could not find a Storage Node is'),
    _('there one enabled within this Storage Group')
);
$foglang['SGNotValid'] = sprintf(
    '%s',
    _('The storage groups associated storage node is not valid')
);
$foglang['InPast'] = _('Scheduled date is in the past');
$foglang['TaskSchExists'] = sprintf(
    '%s',
    _('A task already exists for this host at the scheduled tasking')
);
$foglang['MinNotValid'] = _('Minute value is not valid');
$foglang['HourNotValid'] = _('Hour value is not valid');
$foglang['DOMNotValid'] = _('Day of month value is not valid');
$foglang['MonthNotValid'] = _('Month value is not valid');
$foglang['DOWNotValid'] = _('Day of week value is not valid');
// MAC Address class translates
$foglang['NoHostFound'] = _('No Host found for MAC Address');
// ManagerController class translates
$foglang['PleaseSelect'] = _('Please select an option');
// HostManager Class translates
$foglang['ErrorMultipleHosts'] = sprintf(
    '%s',
    _('Error multiple hosts returned for list of mac addresses')
);
// User class translates
$foglang['SessionTimeout'] = _('Session timeout');
// Storage Page translates
$foglang['SN'] = _('Storage Node');
$foglang['SG'] = _('Storage Group');
$foglang['GraphEnabled'] = _('Graph Enabled');
$foglang['MasterNode'] = _('Master Node');
$foglang['IsMasterNode'] = _('Is Master Node');
$foglang['SNName'] = _('Storage Node Name');
$foglang['SNDesc'] = _('Storage Node Description');
$foglang['IPAdr'] = _('IP Address');
$foglang['MaxClients'] = _('Max Clients');
$foglang['ImagePath'] = _('Image Path');
$foglang['FTPPath'] = _('FTP Path');
$foglang['SnapinPath'] = _('Snapin Path');
$foglang['SSLPath'] = _('SSL Path');
$foglang['Interface'] = _('Interface');
$foglang['IsEnabled'] = _('Is Enabled');
$foglang['IsGraphEnabled'] = _('Is Graph Enabled');
$foglang['OnDash'] = _('On Dashboard');
$foglang['ManUser'] = _('Management Username');
$foglang['ManPass'] = _('Management Password');
$foglang['CautionPhrase'] = sprintf(
    '%s! %s, %s %s %s. %s %s. %s, %s, %s, %s, %s, %s.',
    _('Use extreme caution with this setting'),
    _('This setting'),
    _('if used incorrectly could potentially'),
    _('wipe out all of your images stored on'),
    _('all current storage nodes'),
    _('The \'Is Master Node\' setting defines which'),
    _('node is the distributor of the images'),
    _('If you add a blank node'),
    _('meaning a node that has no images on it'),
    _('and set it to master'),
    _('it will distribute its store'),
    _('which is empty'),
    _('to all nodes in the group')
);
$foglang['StorageNameRequired'] = sprintf(
    $foglang['isRequired'],
    _('Storage Node Name')
);
$foglang['StorageNameExists'] = _('Storage Node already exists');
$foglang['StorageIPRequired'] = sprintf(
    $foglang['isRequired'],
    _('Storage Node IP')
);
$foglang['StorageClientsRequired'] = sprintf(
    $foglang['isRequired'],
    _('Storage Node Max Clients')
);
$foglang['StorageIntRequired'] = sprintf(
    $foglang['isRequired'],
    _('Storage Node Interface')
);
$foglang['StorageUserRequired'] = sprintf(
    $foglang['isRequired'],
    _('Storage Node Username')
);
$foglang['StoragePassRequired'] = sprintf(
    $foglang['isRequired'],
    _('Storage Node Password')
);
$foglang['SNCreated'] = _('Storage Node Created');
$foglang['SNUpdated'] = _('Storage Node Updated');
$foglang['DBupfailed'] = _('Database Update Failed');
$foglang['ConfirmDel'] = _('Please confirm you want to delete');
$foglang['FailDelSN'] = _('Failed to destroy Storage Node');
$foglang['SNDelSuccess'] = _('Storage Node deleted');
$foglang['SGName'] = _('Storage Group Name');
$foglang['SGDesc'] = _('Storage Group Description');
$foglang['SGNameReq'] = sprintf(
    $foglang['isRequired'],
    $foglang['SGName']
);
$foglang['SGExist'] = _('Storage Group Already Exists');
$foglang['SGCreated'] = _('Storage Group Created');
$foglang['SGUpdated'] = _('Storage Group Updated');
$foglang['OneSG'] = _('You must have at least one Storage Group');
$foglang['SGDelSuccess'] = _('Storage Group deleted');
$foglang['FailDelSG'] = _('Failed to destroy Storage Group');
$foglang['InvalidClass'] = _('Invalid Class');
$foglang['NotExtended'] = _('Class is not extended from FOGPage');
$foglang['DoNotList'] = _('Do not list on menu');
// Language menu options.
$foglang['LanguagePhrase'] = _('Language');
$foglang['Language']['zh'] = '中国的';
$foglang['Language']['en'] = 'English';
$foglang['Language']['es'] = 'Español';
$foglang['Language']['fr'] = 'Français';
$foglang['Language']['de'] = 'Deutsch';
$foglang['Language']['it'] = 'Italiano';
$foglang['Language']['pt'] = 'Português';
