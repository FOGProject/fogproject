<?php
require('../commons/base.inc.php');
try
{
	// Set the services so all id's can be enabled.
	foreach($FOGCore->getClass('ModuleManager')->find() AS $Module)
		$ids[] = $Module->get('id');
	$groupid[] = base64_decode($_REQUEST['groupid']);
	$HostManager = new HostManager();
	$ifconfig = explode('HWaddr',base64_decode($_REQUEST['mac']));
	$mac = strtolower(trim($ifconfig[1]));
	// Verify MAC is okay.
	$MACAddress = new MACAddress($mac);
	$Host = $HostManager->getHostByMacAddresses($mac);
	if (!$MACAddress->isValid())
		throw new Exception(_('Invalid MAC Address'));
	// Set safe and simple mac for hostname if needed.
	$macsimple = str_replace(':','',$mac);
	// Make sure it's a unique name.
	if($_REQUEST['advanced'] == '1')
	{
		$username = base64_decode(trim($_REQUEST['username']));
		// trim the hostname (no spaces left or right of the name)
		$host=trim(base64_decode($_REQUEST['host']));
		// If it's filled out and is safe, set that as hostname, otherwise set it as mac.
		($host != null && strlen($host) > 0 && $HostManager->isSafeHostName($host) ? $realhost = $host : $realhost = $macsimple);
		// Set the description
		$desc = _("Created by FOG Reg on")." " . date("F j, Y, g:i a");
		// Set ip if filled out.
		$ip=trim(base64_decode($_REQUEST["ip"]));
		// Set the image ID, if there is one.
		$imageid=trim(base64_decode($_REQUEST["imageid"]));
		($imageid != null && is_numeric($imageid) && $imageid > 0 ? $realimageid = $imageid : $realimageid = '');
		$locationid=trim(base64_decode($_REQUEST['location']));
		($locationid != null && is_numeric($locationid) && $locationid > 0 ? $reallocid = $locationid : $locationid = '');
		// Filled out?
		$primaryuser=trim(base64_decode($_REQUEST["primaryuser"]));
		$other1=trim(base64_decode($_REQUEST["other1"]));
		$other2=trim(base64_decode($_REQUEST["other2"]));
		// Is it going to be imaged?
		$doimage=trim($_REQUEST["doimage"]);
		// If it's to join to AD, set the values.
		if($_REQUEST['doad'] == '1')
		{
			$strDoAD="1";
			$strADDomain = $FOGCore->getSetting( "FOG_AD_DEFAULT_DOMAINNAME");
			$strADOU = $FOGCore->getSetting( "FOG_AD_DEFAULT_OU");
			$strADUser = $FOGCore->getSetting( "FOG_AD_DEFAULT_USER");
			$strADPass = $FOGCore->getSetting( "FOG_AD_DEFAULT_PASSWORD");
		}
		// Create the host.
		$Host = new Host(array(
			'name' => $realhost,
			'description' => sprintf('%s %s',_('Created by FOG Reg on'),date('F j, Y, g:i a')),
			'mac' => $mac,
			'imageID' => $realimageid,
			'useAD' => $strDoAD,
			'ADDomain' => $strADDomain,
			'ADOU' => $strADOU,
			'ADUser' => $strADUser,
			'ADPass' => $strADPass,
			'createdTime' => date("Y-m-d H:i:s"),
			'createdBy' => 'FOGREG',
		));
		$Host->addModule($ids);
		if ($Host->save())
		{
			$GroupAssoc = new GroupAssociation(array(
				'groupID' => base64_decode($_REQUEST['groupid']),
				'hostID' => $Host->get('id'),
			));
			$GroupAssoc->save();
			$LocPlugInst = current($FOGCore->getClass('PluginManager')->find(array('name' => 'location')));
			if ($LocPlugInst)
			{
				$LocationAssoc = new LocationAssociation(array(
					'locationID' => $reallocid,
					'hostID' => $Host->get('id'),
				));
				$LocationAssoc->save();
			}
			// If to be imaged, create the package.
			if ($doimage == '1')
			{
				if ($Host->getImageMemberFromHostID())
				{
					$other .= ' chkdsk='.($FOGCore->getSetting('FOG_DISABLE_CHKDSK') == '1' ? '0' : '1');
					$other .= ($FOGCore->getSetting('FOG_CHANGE_HOSTNAME_EARLY') == 1 ? ' hostname='.$Host->get('name') : '');
					$tmp;
					if($Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$username))
						print _('Done, with imaging!');
					else
						throw new Exception(_('Failed to create image task.').": $tmp");
				}
				else
					throw new Exception(_('No image assigned for this host.'));
			}
			else
				print _('Done!');
			// If inventory for this host already exists, update the values, otherwise create new inventory record.
			$Inventory = current($FOGCore->getClass('InventoryManager')->find(array('hostID' => $Host->get('id'))));
			if ($Inventory)
			{
				$Inventory->set('primaryUser',$primaryuser)
						  ->set('other1',$other1)
						  ->set('other2',$other2)
						  ->save();
			}
			else
			{
				$Inventory = new Inventory(array(
					'hostID' => $Host->get('id'),
					'primaryUser' => $primaryuser,
					'other1' => $other1,
					'other2' => $other2,
					'createdTime' => date('Y-m-d H:i:s')
				));
				$Inventory->save();
			}
		}
		else
			throw new Exception(_('Failed to save new Host!'));
	}
	else
	{
		// Get the autoreg group id:
		$groupid[] = $FOGCore->getSetting('FOG_QUICKREG_GROUP_ASSOC');
		// Quick Registration
		if ($FOGCore->getSetting('FOG_QUICKREG_AUTOPOP') == '1')
		{
			// Get the image id if autopop is set.
			$Image = new Image($FOGCore->getSetting('FOG_QUICKREG_IMG_ID'));
			$realimageid = ($Image && $Image->isValid() ? $Image->get('id') : '');
			// get the name to use
			$autoregSysName = $FOGCore->getSetting('FOG_QUICKREG_SYS_NAME');
			// get the increment to use
			$autoregSysNumber = $FOGCore->getSetting('FOG_QUICKREG_SYS_NUMBER');
			// pad as and where necessary.
			$paddingLen = substr_count($autoregSysName,'*');
			$paddingString = null;
			if ($paddingLen > 0)
			{
				$paddingString = str_repeat('*',$paddingLen);
				$paddedInsert = str_pad($autoregSysNumber, $paddingLen, '0',STR_PAD_LEFT);
				// Coolest part: if the MAC is designated to be the name
				// Set it, otherwise use the predefined values.
				(strtoupper($autoregSysName) == 'MAC' ? $realhost = $macsimple : $realhost = str_replace($paddingString,$paddedInsert,$autoregSysName));
			}
			else
				// Same as above.
				(strtoupper($autoregSysName) == 'MAC' ? $realhost = $macsimple : $realhost = $autoregSysName);
			// As long as the host doesn't exist, create it.
			if (!$Host || !$Host->isValid())
			{
				$Host = new Host(array(
					'name' => $realhost,
					'description' => sprintf('%s %s',_('Created by FOG Reg on'),date('F j, Y, g:i a')),
					'mac' => $mac,
					'imageID' => $realimageid,
					'createdTime' => date('Y-m-d H:i:s'),
					'createdBy' => 'FOGREG'
				));
				$Host->addModule($ids);
			}
			if ($Host->save())
			{
				$Host = new Host($Host->get('id'));
				$Host->addGroup($groupid)->save();
				// Increment the Quickreg System Number if needed.
				(strtoupper($autoregSysName == 'MAC' ? null : $FOGCore->setSetting('FOG_QUICKREG_SYS_NUMBER',($autoregSysNumber+1))));
				if ($Host->getImageMemberFromHostID())
				{
					$tmp;
					// As long as the image id is valid create image package.
					if ($realimageid > 0)
					{
						if ($Host->createImagePackage(1,'AutoRegTask'))
							print _('Done, with imaging!');
						else
							print _('Done, but without imaging!');
					}
					else
						print _('Done, but without imaging!');
				}
				else
					print _('Done!');
			}
			else
				throw new Exception(_('Failed to save new Host!'));
		}
		else
		{
			// If it's not autopop, then just save the host as MAC name.
			$realhost = $macsimple;
			if (!$Host || !$Host->isValid())
			{
				$Host = new Host(array(
					'name' => $realhost,
					'description' => sprintf('%s %s',_('Created by FOG Reg on'),date('F j, Y, g:i a')),
					'mac' => $mac,
					'createdTime' => date('Y-m-d H:i:s'),
					'createdBy' => 'FOGREG',
				));
				$Host->set('modules',$ids);
				if ($Host->save())
					print _('Done');
				else
					throw new Exception(_('Failed to save new Host!'));
			}
			else
				print _('Already registered as').': '.$Host->get('name');
		}
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
