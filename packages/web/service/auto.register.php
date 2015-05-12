<?php
require_once('../commons/base.inc.php');
if ($FOGCore->getSetting('FOG_REGISTRATION_ENABLED')) {
	try {
		// Set the services so all id's can be enabled.
		$ids = $FOGCore->getClass('ModuleManager')->find('','','','','','','','id');
		$MACs = $FOGCore->getHostItem(false,true,true,true);
		$PriMAC = array_shift($MACs);
		// Set safe and simple mac for hostname if needed.
		$macsimple = strtolower(str_replace(':','',$PriMAC));
		$Host = $FOGCore->getHostItem(false,true,true);
		$HostManager = $FOGCore->getClass('HostManager');
		// Make sure it's a unique name.
		if((!$Host || !$Host->isValid()) && $_REQUEST['advanced'] == '1') {
			if (base64_decode($_REQUEST['productKey'],true)) $productKey = trim($_REQUEST['productKey']);
			$username = base64_decode(trim($_REQUEST['username']));
			$host=trim(base64_decode($_REQUEST['host']));
			($host != null && strlen($host) > 0 && $HostManager->isSafeHostName($host) ? $realhost = $host : $realhost = $macsimple);
			$desc = _("Created by FOG Reg on")." " . $FOGCore->formatTime('now',"F j, Y, g:i a");
			$ip=trim(base64_decode($_REQUEST["ip"]));
			$imageid = trim(base64_decode($_REQUEST['imageid']));
			$Image = ($imageid && is_numeric($imageid) && $imageid > 0 ? new Image($imageid) : new Image(array('id' => 0)));
			$realimageid = ($Image && $Image->isValid() ? $Image->get('id') : '0');
			$locationid=trim(base64_decode($_REQUEST['location']));
			($locationid != null && is_numeric($locationid) && $locationid > 0 ? $reallocid = $locationid : $locationid = '');
			$primaryuser=trim(base64_decode($_REQUEST["primaryuser"]));
			$other1=trim(base64_decode($_REQUEST["other1"]));
			$other2=trim(base64_decode($_REQUEST["other2"]));
			$doimage=trim($_REQUEST["doimage"]);
			if($_REQUEST['doad'] == '1') {
				$OUs = explode('|',$FOGCore->getSetting('FOG_AD_DEFAULT_OU'));
				foreach ((array)$OUs AS $OU) $OUOptions[] = $OU;
				if ($OUOptions) {
					$OUs = array_unique((array)$OUOptions);
					foreach ($OUs AS $OU) {
						$opt = preg_match('#;#i',$OU) ? preg_replace('#;#i','',$OU) : '';
						$optionOU = $opt ? $opt : '';
						if ($optionOU) break;
					}
					if (!$optionOU) $optionOU = $OUs[0];
				}
				$strDoAD="1";
				$strADDomain = $FOGCore->getSetting('FOG_AD_DEFAULT_DOMAINNAME');
				$strADOU = $optionOU;
				$strADUser = $FOGCore->getSetting('FOG_AD_DEFAULT_USER');
				$strADPass = $FOGCore->getSetting('FOG_NEW_CLIENT') ? $FOGCore->getSetting('FOG_AD_DEFAULT_PASSWORD') : $FOGCore->getSetting('FOG_AD_DEFAULT_PASSWORD_LEGACY');
			}
			// Create the host.
			$Host = new Host(array(
				'name' => $realhost,
				'description' => sprintf('%s %s',_('Created by FOG Reg on'),date('F j, Y, g:i a')),
				'imageID' => $realimageid,
				'useAD' => $strDoAD,
				'ADDomain' => $strADDomain,
				'ADOU' => $strADOU,
				'ADUser' => $strADUser,
				'ADPass' => $strADPass,
				'productKey' => $productKey,
				'createdTime' => $FOGCore->formatTime('now',"Y-m-d H:i:s"),
				'createdBy' => 'FOGREG',
			));
			$groupid = explode(',',trim(base64_decode($_REQUEST['groupid'])));
			$snapinid = explode(',',trim(base64_decode($_REQUEST['snapinid'])));
			$Host->addModule($ids);
			$Host->addGroup($groupid);
			$Host->addSnapin($snapinid);
			$Host->addPriMAC($PriMAC);
			$Host->addAddMAC($MACs);
			if (!$Host->save())
				throw new Exception(_('Failed to save new Host!'));
			$LocPlugInst = current($FOGCore->getClass('PluginManager')->find(array('name' => 'location')));
			if ($LocPlugInst) {
				$LocationAssoc = new LocationAssociation(array(
					'locationID' => $reallocid,
					'hostID' => $Host->get('id'),
				));
				$LocationAssoc->save();
			}
			if ($doimage == '1') {
				if (!$Host->getImageMemberFromHostID())
					throw new Exception(_('No image assigned for this host.'));
				$other .= ' chkdsk='.($FOGCore->getSetting('FOG_DISABLE_CHKDSK') == '1' ? '0' : '1');
				$other .= ($FOGCore->getSetting('FOG_CHANGE_HOSTNAME_EARLY') == 1 ? ' hostname='.$Host->get('name') : '');
				$tmp;
				if(!$Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$username))
					throw new Exception(_('Failed to create image task.').": $tmp");
				print _('Done, with imaging!');
			} else print _('Done!');
			$Inventory = $Host->get('inventory');
			if ($Inventory && $Inventory->isValid()) {
				$Inventory->set('primaryUser',$primaryuser)
						  ->set('other1',$other1)
						  ->set('other2',$other2)
						  ->save();
			} else {
				$Inventory = new Inventory(array(
					'hostID' => $Host->get('id'),
					'primaryUser' => $primaryuser,
					'other1' => $other1,
					'other2' => $other2,
					'createdTime' => $FOGCore->formatTime('now','Y-m-d H:i:s'),
				));
				$Inventory->save();
			}
		} else if (!$Host || !$Host->isValid()) {
			$groupid = explode(',',trim($FOGCore->getSetting('FOG_QUICKREG_GROUP_ASSOC')));
			if ($FOGCore->getSetting('FOG_QUICKREG_AUTOPOP')) {
				$Image = ($FOGCore->getSetting('FOG_QUICKREG_IMG_ID') ? new Image($FOGCore->getSetting('FOG_QUICKREG_IMG_ID')) : new Image(array('id' => 0)));
				$realimageid = ($Image->isValid() ? $Image->get('id') : '');
				$autoregSysName = $FOGCore->getSetting('FOG_QUICKREG_SYS_NAME');
				$autoregSysNumber = (int)$FOGCore->getSetting('FOG_QUICKREG_SYS_NUMBER');
				$paddingLen = substr_count($autoregSysName,'*');
				$paddingString = null;
				if ($paddingLen > 0) {
					$paddingString = str_repeat('*',$paddingLen);
					$paddedInsert = str_pad($autoregSysNumber, $paddingLen, '0',STR_PAD_LEFT);
					$realhost = (strtoupper($autoregSysName) == 'MAC' ? $macsimple : str_replace($paddingString,$paddedInsert,$autoregSysName));
					$FOGCore->setSetting('FOG_QUICKREG_SYS_NUMBER',($autoregSysNumber + 1));
				} else $realhost = (strtoupper($autoregSysName) == 'MAC' ? $macsimple : $autoregSysName);
				if (!$Host || !$Host->isValid()) {
					$Host = new Host(array(
						'name' => $realhost,
						'description' => sprintf('%s %s',_('Created by FOG Reg on'),date('F j, Y, g:i a')),
						'imageID' => $realimageid,
						'createdTime' => $FOGCore->formatTime('now','Y-m-d H:i:s'),
						'createdBy' => 'FOGREG'
					));
				}
				$Host->addModule($ids);
				$Host->addGroup($groupid);
				$Host->addPriMAC($PriMAC);
				$Host->addAddMAC($MACs);
				if (!$Host->save()) throw new Exception(_('Failed to save new Host!'));
				if ($Image->isValid() && $Host->getImageMemberFromHostID()) {
					if ($Host->createImagePackage(1,'AutoRegTask')) print _('Done, with imaging!');
					else print _('Done, but unable to create task!');
				}
				else print _('Done!');
			} else {
				$realhost = $macsimple;
				if (!$Host || !$Host->isValid()) {
					$Host = new Host(array(
						'name' => $realhost,
						'description' => sprintf('%s %s',_('Created by FOG Reg on'),date('F j, Y, g:i a')),
						'createdTime' => $FOGCore->formatTime('now','Y-m-d H:i:s'),
						'createdBy' => 'FOGREG',
					));
					$Host->addPriMAC($PriMAC);
					$Host->addAddMAC($MACs);
					$Host->addModule($ids);
					if (!$Host->save())
						throw new Exception(_('Failed to save new Host!'));
					print _('Done');
				} else print _('Already registered as').': '.$Host->get('name');
			}
		}
	} catch (Exception $e) {
		print $e->getMessage();
	}
}
