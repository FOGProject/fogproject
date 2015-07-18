<?php
require_once('../commons/base.inc.php');
if ($FOGCore->getSetting(FOG_REGISTRATION_ENABLED)) {
    try {
        // Set the services so all id's can be enabled.
        $ids = $FOGCore->getClass(ModuleManager)->find('','','','','','','','id');
        $MACs = $FOGCore->getHostItem(false,true,true,true);
        $PriMAC = array_shift($MACs);
        // Set safe and simple mac for hostname if needed.
        $macsimple = strtolower(str_replace(':','',str_replace('-','',$PriMAC)));
        $Host = $FOGCore->getHostItem(false,true,true);
        $HostManager = $FOGCore->getClass(HostManager);
        // Make sure it's a unique name.
        if((!$Host || !$Host->isValid()) && $_REQUEST[advanced] == 1) {
            if (base64_decode($_REQUEST[productKey],true)) $productKey = trim($_REQUEST[productKey]);
            $username = base64_decode(trim($_REQUEST[username]));
            $host=trim(base64_decode($_REQUEST[host]));
            ($host != null && strlen($host) > 0 && $HostManager->isSafeHostName($host) ? $realhost = $host : $realhost = $macsimple);
            $desc = _("Created by FOG Reg on")." " . $FOGCore->formatTime('now',"F j, Y, g:i a");
            $ip=trim(base64_decode($_REQUEST[ip]));
            $imageid = trim(base64_decode($_REQUEST[imageid]));
            $Image = $FOGCore->getClass(Image,$imageid);
            $realimageid = ($Image && $Image->isValid() ? $Image->get('id') : '0');
            $locationid=trim(base64_decode($_REQUEST[location]));
            ($locationid != null && is_numeric($locationid) && $locationid > 0 ? $reallocid = $locationid : $locationid = '');
            $primaryuser=trim(base64_decode($_REQUEST[primaryuser]));
            $other1=trim(base64_decode($_REQUEST[other1]));
            $other2=trim(base64_decode($_REQUEST[other2]));
            $doimage=trim($_REQUEST[doimage]);
            if($_REQUEST[doad]) {
                $OUs = explode('|',$FOGCore->getSetting(FOG_AD_DEFAULT_OU));
                foreach ((array)$OUs AS $i => &$OU) $OUOptions[] = $OU;
                unset($OU);
                if ($OUOptions) {
                    $OUs = array_unique((array)$OUOptions);
                    foreach ($OUs AS $i => &$OU) {
                        $opt = preg_match('#;#i',$OU) ? preg_replace('#;#i','',$OU) : '';
                        $optionOU = $opt ? $opt : '';
                        if ($optionOU) break;
                    }
                    unset($OU);
                    if (!$optionOU) $optionOU = $OUs[0];
                }
                $strDoAD="1";
                $strADDomain = $FOGCore->getSetting(FOG_AD_DEFAULT_DOMAINNAME);
                $strADOU = $optionOU;
                $strADUser = $FOGCore->getSetting(FOG_AD_DEFAULT_USER);
                $strADPass = $FOGCore->getSetting(FOG_AD_DEFAULT_PASSWORD);
                $strADPassLegacy = $FOGCore->getSetting(FOG_AD_DEFAULT_PASSWORD_LEGACY);
            }
            $groupid = explode(',',trim(base64_decode($_REQUEST[groupid])));
            $snapinid = explode(',',trim(base64_decode($_REQUEST[snapinid])));
            $Host = $FOGCore->getClass(Host)
                ->set(name,$realhost)
                ->set(description,sprintf('%s %s',_('Created by FOG Reg on'),$FOGCore->formatTime('now','F j, Y, g:i a')))
                ->set(imageID,$realimageid)
                ->set(useAD,$strDoAD)
                ->set(ADDomain,$strADDomain)
                ->set(ADOU,$strADOU)
                ->set(ADUser,$strADUser)
                ->set(ADPass,$strADPass)
                ->set(ADPassLegacy,$strADPassLegacy)
                ->set(productKey,$productKey)
                ->set(createdTime,$FOGCore->formatTime('now','Y-m-d H:i:s'))
                ->set(createdBy,'FOGREG');
            if (!$Host->save()) throw new Exception(_('Failed to save new Host'));
            $Host->addModule($ids)
                ->addGroup($groupid)
                ->addSnapin($snapinid)
                ->addPriMAC($PriMAC)
                ->addAddMAC($MACs)
                ->save();
            $LocPlugInst = in_array('location',(array)$_SESSION[PluginsInstalled]);
            if ($LocPlugInst) {
                $FOGCore->getClass(LocationAssociation)
                    ->set(locationID,$reallocid)
                    ->set(hostID,$Host->get(id))
                    ->save();
            }
            if ($doimage) {
                if (!$Host->getImageMemberFromHostID()) throw new Exception(_('No image assigned for this host.'));
                $other .= ' chkdsk='.($FOGCore->getSetting(FOG_DISABLE_CHKDSK) == 1 ? 0 : 1);
                $other .= ($FOGCore->getSetting(FOG_CHANGE_HOSTNAME_EARLY) == 1 ? ' hostname='.$Host->get(name) : '');
                $tmp;
                if(!$Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$username))
                    throw new Exception(_('Failed to create image task.').": $tmp");
                print _('Done, with imaging!');
            } else print _('Done!');
            $FOGCore->getClass(Inventory)
                ->set(hostID,$Host->get(id))
                ->set(primaryUser,$primaryuser)
                ->set(other1,$other1)
                ->set(other2,$other2)
                ->set(createdTime,$FOGCore->formatTime('now','Y-m-d H:i:s'))
                ->save();
        } else if (!$Host || !$Host->isValid()) {
            $groupid = explode(',',trim($FOGCore->getSetting(FOG_QUICKREG_GROUP_ASSOC)));
            if ($FOGCore->getSetting(FOG_QUICKREG_AUTOPOP)) {
                $Image = $FOGCore->getClass(Image,$FOGCore->getSetting(FOG_QUICKREG_IMG_ID));
                $realimageid = ($Image->isValid() ? $Image->get(id) : '');
                $autoregSysName = $FOGCore->getSetting(FOG_QUICKREG_SYS_NAME);
                $autoregSysNumber = (int)$FOGCore->getSetting(FOG_QUICKREG_SYS_NUMBER);
                $paddingLen = substr_count($autoregSysName,'*');
                $paddingString = null;
                if ($paddingLen > 0) {
                    $paddingString = str_repeat('*',$paddingLen);
                    $paddedInsert = str_pad($autoregSysNumber, $paddingLen, 0,STR_PAD_LEFT);
                    $realhost = (strtoupper($autoregSysName) == 'MAC' ? $macsimple : str_replace($paddingString,$paddedInsert,$autoregSysName));
                    $FOGCore->setSetting(FOG_QUICKREG_SYS_NUMBER,($autoregSysNumber + 1));
                } else $realhost = (strtoupper($autoregSysName) == 'MAC' ? $macsimple : $autoregSysName);
                $Host = $FOGCore->getClass(Host)
                    ->set(name,$realhost)
                    ->set(description,sprintf('%s %s',_('Created by FOG Reg on'),$FOGCore->formatTime('now','F j, Y, g:i a')))
                    ->set(imageID,$realimageid)
                    ->set(createdTime,$FOGCore->formatTime('now','Y-m-d H:i:s'))
                    ->set(createdBy,'FOGREG');
                if (!$Host->save()) throw new Exception(_('Failed to save new Host'));
                $Host->addModule($ids)
                    ->addGroup($groupid)
                    ->addPriMAC($PriMAC)
                    ->addAddMAC($MACs)
                    ->save();
                if ($Image->isValid() && $Host->getImageMemberFromHostID()) {
                    if ($Host->createImagePackage(1,'AutoRegTask')) print _('Done, with imaging!');
                    else print _('Done, but unable to create task!');
                }
                else print _('Done!');
            } else {
                $realhost = $macsimple;
                if (!$Host || !$Host->isValid()) {
                    $Host = $FOGCore->getClass(Host)
                        ->set(name,$realhost)
                        ->set(description,sprintf('%s %s',_('Created by FOG Reg on'),$FOGCore->formatTime('now','F j, Y, g:i a')))
                        ->set(createdTime,$FOGCore->formatTime('now','Y-m-d H:i:s'))
                        ->set(createdBy,'FOGREG');
                    if (!$Host->save()) throw new Exception(_('Failed to save new Host'));
                    $Host->addModule($ids)
                        ->addPriMAC($PriMAC)
                        ->addAddMAC($MACs)
                        ->save();
                    print _('Done');
                } else print _('Already registered as').': '.$Host->get(name);
            }
        } else print _('Already registered as').': '.$Host->get(name);
    } catch (Exception $e) {
        print $e->getMessage();
    }
}
