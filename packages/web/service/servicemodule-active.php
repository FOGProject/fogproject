<?php
require('../commons/base.inc.php');
try
{
	if (!$_REQUEST['moduleid'] && $_REQUEST['newService'])
		throw new Exception("#!ok\n#sleep=".$FOGCore->getSetting('FOG_SERVICE_CHECKIN_TIME')."\n#force=".$FOGCore->getSetting('FOG_TASK_FORCE_REBOOT')."\n#maxsize=".$FOGCore->getSetting('FOG_CLIENT_MAXSIZE')."\n#promptTime=".$FOGCore->getSetting('FOG_GRACE_TIMEOUT'));
	if ($_REQUEST['newService'] && $_REQUEST['get_srv_key'])
	{
		$output = file_get_contents(BASEPATH.'/management/other/ssl/srvpublic.key');
		$output = base64_encode($output);
		throw new Exception("#!ok\n#srv_pub_key=$output");
	}
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if ($Host && $Host->isValid() && $_REQUEST['pub_key'])
		$pub_key = $FOGCore->certDecrypt($_REQUEST['pub_key']);
	if ($pub_key)
		$Host->set('pub_key',$pub_key)->save();
	// Get the true module ID for comparing what the host has.
	$moduleID = current($FOGCore->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['moduleid'])));
	// get the module id
	if (!$moduleID || !$moduleID->isValid())
	{
		if ($_REQUEST['moduleid'] == 'dircleaner' || $_REQUEST['moduleid'] == 'dircleanup')
			$_REQUEST['moduleid'] = array('dircleaner','dircleanup');
		if ($_REQUEST['moduleid'] == 'snapin' || $_REQUEST['moduleid'] == 'snapinclient')
			$_REQUEST['moduleid'] = array('snapin','snapinclient');
		$moduleID = current($FOGCore->getClass('ModuleManager')->find(array('shortName' => $_REQUEST['moduleid']),'OR'));
		if (!$moduleID || !$moduleID->isValid())
			throw new Exception('#!um');
	}
	// Associate the moduleid param with the global name.
	$moduleName = array(
		'dircleanup' => $FOGCore->getSetting('FOG_SERVICE_DIRECTORYCLEANER_ENABLED'),
		'usercleanup' => $FOGCore->getSetting('FOG_SERVICE_USERCLEANUP_ENABLED'),
		'displaymanager' => $FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_ENABLED'),
		'autologout' => $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_ENABLED'),
		'greenfog' => $FOGCore->getSetting('FOG_SERVICE_GREENFOG_ENABLED'),
		'hostnamechanger' => $FOGCore->getSetting('FOG_SERVICE_HOSTNAMECHANGER_ENABLED'),
		'snapin' => $FOGCore->getSetting('FOG_SERVICE_SNAPIN_ENABLED'),
		'snapinclient' => $FOGCore->getSetting('FOG_SERVICE_SNAPIN_ENABLED'),
		'clientupdater' => $FOGCore->getSetting('FOG_SERVICE_CLIENTUPDATER_ENABLED'),
		'hostregister' => $FOGCore->getSetting('FOG_SERVICE_HOSTREGISTER_ENABLED'),
		'printermanager' => $FOGCore->getSetting('FOG_SERVICE_PRINTERMANAGER_ENABLED'),
		'taskreboot' => $FOGCore->getSetting('FOG_SERVICE_TASKREBOOT_ENABLED'),
		'usertracker' => $FOGCore->getSetting('FOG_SERVICE_USERTRACKER_ENABLED'),
	);
	// If it's globally disabled, return that so the client doesn't keep trying it.
	if (!$moduleName[$moduleID->get('shortName')])
		throw new Exception('#!ng');
	if ($Host && $Host->isValid())
	{
		if (!$Host->get('pending'))
		{
			foreach((array)$Host->get('modules') AS $Module)
			{
				if ($Module && $Module->isValid() && $Module->get('isDefault'))
					$activeIDs[] = $Module->get('id');
			}
			$Datatosend = (in_array($moduleID->get('id'),(array)$activeIDs) ? '#!ok' : '#!nh')."\n";
		}
		else if ($Host->get('pending'))
			$Datatosend = ($_REQUEST['moduleid'] == 'hostregister' ? '#!ok' : '#!ih')."\n";
	}
	else
		$Datatosend = ($_REQUEST['moduleid'] == 'hostregister' ? '#!ok' : '#!ih')."\n";
}
catch(Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($_REQUEST['get_srv_key'])
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else if ($Host && $Host->isvalid() && $Host->get('pub_key') && $_REQUEST['newService'])
	print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
else if ($_REQUEST['newService'] && $FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
