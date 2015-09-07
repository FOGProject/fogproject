<?php
class Registration extends FOGBase {
    protected $Host;
    protected $MACs = array();
    protected $PriMAC;
    protected $macsimple;
    protected $modulesToJoin;
    protected $description;
    public function __construct() {
        parent::__construct();
        if ($this->FOGCore->getSetting(FOG_REGISTRATION_ENABLED)) {
            $this->MACs = $this->getHostItem(false,true,true,true);
            $this->PriMAC = @array_shift($this->MACs);
            $this->Host = $this->getHostItem(false,true,true);
            $this->macsimple = strtolower(str_replace(array(':','-'),'',$this->PriMAC));
            if (!$this->regExists()) {
                $this->modulesToJoin = $this->getClass(ModuleManager)->find('','','','','','','','id');
                $this->description = sprintf('%s %s',_('Created by FOG Reg on'),$this->formatTime('now','F j, Y, g:i a'));
                if (isset($_REQUEST[advanced])) $this->fullReg();
                else if ($this->FOGCore->getSetting(FOG_QUICKREG_AUTOPOP)) $this->quickRegAuto();
                else $this->quickReg();
            }
        }
    }
    private function stripAndDecode($item) {
        return trim(base64_decode($item));
    }
    private function regExists() {
        try {
            if ($this->Host instanceof Host && $this->Host->isValid()) throw new Exception(sprintf($sendStr,_('Already registered as'),$this->Host->get(name)));
        } catch (Exception $e) {
            print $e->getMessage();
            return true;
        }
        return false;
    }
    private function fullReg() {
        try {
            if (base64_decode($_REQUEST[productKey],true)) $productKey = trim($_REQUEST[productKey]);
            $username = $this->stripAndDecode($_REQUEST[username]);
            $hostname = $this->stripAndDecode($_REQUEST[host]);
            $hostname = ($this->getClass(HostManager)->isHostnameSafe($hostname) ? $hostname : $this->macsimple);
            $ip = $this->stripAndDecode($_REQUEST[ip]);
            $imageid = $this->stripAndDecode($_REQUEST[imageid]);
            $imageid = ($this->getClass(Image,$imageid)->isValid() ? $imageid : 0);
            $primaryuser = $this->stripAndDecode($_REQUEST[primaryuser]);
            $other1 = $this->stripAndDecode($_REQUEST[other1]);
            $other2 = $this->stripAndDecode($_REQUEST[other2]);
            $doimage = trim($_REQUEST[doimage]);
            if ($_REQUEST[doad]) {
                $OUs = explode('|',$this->FOGCore->getSetting(FOG_AD_DEFAULT_OU));
                foreach ((array)$OUs AS $i => &$OU) $OUOptions[] = $OU;
                unset($OU);
                if ($OUOptions) {
                    $OUs = array_unique((array)$OUOptions);
                    foreach ($OUs AS $i => &$OU) {
                        $opt = preg_match('#;#',$OU) ? preg_replace('#;#','',$OU) : '';
                        if ($opt) break;
                    }
                    unset($OU);
                    if (!$opt) $opt = $OUs[0];
                }
                $useAD = 1;
                $ADDomain = $this->FOGCore->getSetting(FOG_AD_DEFAULT_DOMAINNAME);
                $ADOU = $opt;
                $ADUser = $this->FOGCore->getSetting(FOG_AD_DEFAULT_USER);
                $ADPass = $this->FOGCore->getSetting(FOG_AD_DEFAULT_PASSWORD);
                $ADPassLegacy = $this->FOGCore->getSetting(FOG_AD_DEFAULT_PASSWORD_LEGACY);
            }
            $groupsToJoin = explode(',',$this->stripAndDecode($_REQUEST[groupid]));
            $snapinsToJoin = explode(',',$this->stripAndDecode($_REQUEST[snapinid]));
            $this->Host = $this->getClass(Host)
                ->set(name,$hostname)
                ->set(description,$this->description)
                ->set(imageID,$imageid)
                ->set(productKey,$productKey)
                ->addModule($this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addSnapin($snapinsToJoin)
                ->addPriMAC($this->PriMAC)
                ->addAddMAC($this->MACs)
                ->setAD($useAD,$ADDomain,$ADOU,$ADUser,$ADPass,false,true,$ADPassLegacy);
            $this->HookManager->processEvent('HOST_REGISTER',array(Host=>&$this->Host));
            if (!$this->Host->save()) throw new Exception(_('Failed to create Host'));
            try {
                if (!$doimage) throw new Exception(_('Done, without imaging!'));
                if (!$this->Host->getImageMemberFromHostID()) throw new Exception(_('Done, No image assigned!'));
                if (!$this->Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$username)) throw new Exception(_('Done, Failed to create tasking'));
                throw new Exception(_('Done, with imaging!'));
            } catch (Exception $e) {
                print $e->getMessage();
            }
            $this->getClass(Inventory)
                ->set(hostID,$this->Host->get(id))
                ->set(primaryUser, $primaryuser)
                ->set(other1, $other1)
                ->set(other2, $other2)
                ->save();
        } catch (Exception $e) {
            print $e->getMessage();
        }
    }
    private function quickRegAuto() {
        try {
            $groupsToJoin = explode(',',trim($this->FOGCore->getSetting(FOG_QUICKREG_GROUP_ASSOC)));
            $autoRegSysName = trim($this->FOGCore->getSetting(FOG_QUICKREG_SYS_NAME));
            $autoRegSysNumber = (int)$this->FOGCore->getSetting(FOG_QUICKREG_SYS_NUMBER);
            $hostname = trim((strtoupper($autoRegSysName) == 'MAC' ? $this->macsimple : $autoRegSysName));
            $hostname = ($this->getClass(HostManager)->isHostnameSafe($hostname) ? $hostname : $this->macsimple);
            $paddingLen = substr_count($autoRegSysName,'*');
            $paddingString = null;
            if ($paddingLen > 0) {
                $paddingString = str_repeat('*',$paddingLen);
                $paddedInsert = str_pad($autoRegSysNumber,$paddingLen,0,STR_PAD_LEFT);
                $hostname = trim((strtoupper($autoRegSysName) == 'MAC' ? $this->macsimple : str_replace($paddingString,$paddedInsert,$autoRegSysName)));
                $hostname = ($this->getClass(HostManager)->isHostnameSafe($hostname) ? $hostname : $this->macsimple);
            }
            $imageid = $this->FOGCore->getSetting(FOG_QUICKREG_IMG_ID);
            $this->Host = $this->getClass(Host)
                ->set(name,$hostname)
                ->set(description,$this->description)
                ->set(imageID,$imageid)
                ->addModule($this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addPriMAC($this->PriMAC)
                ->addAddMAC($this->MACs);
            $this->HookManager->processEvent('HOST_REGISTER',array(Host=>&$this->Host));
            if (!$this->Host->save()) throw new Exception(_('Failed to create Host'));
            if ($imageid && $this->Host->getImageMemberFromHostID()) {
                if (!$this->Host->createImagePackage(1,'AutoRegTask')) throw new Exception(_('Done, Failed to create tasking'));
                throw new Exception(_('Done, with imaging!'));
            }
            throw new Exception(_('Done'));
        } catch (Exception $e) {
            print $e->getMessage();
        }
    }
    private function quickReg() {
        try {
            $this->Host = $this->getClass(Host)
                ->set(name,$this->macsimple)
                ->set(description,$this->description)
                ->addModule($this->modulesToJoin)
                ->addPriMAC($this->PriMAC)
                ->addAddMAC($this->MACs);
            $this->HookManager->processEvent('HOST_REGISTER',array(Host=>&$this->Host));
            if (!$this->Host->save()) throw new Exception(_('Failed to create Host'));
            throw new Exception(_('Done'));
        } catch (Exception $e) {
            print $e->getMessage();
        }
    }
}
