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
        if ($this->getSetting('FOG_REGISTRATION_ENABLED')) {
            try {
                $this->MACs = $this->getHostItem(false,true,true,true);
                $this->PriMAC = @array_shift($this->MACs);
                $this->Host = $this->getHostItem(false,true,true);
                $this->macsimple = strtolower(str_replace(array(':','-'),'',$this->PriMAC));
                if (!$this->regExists()) {
                    $this->modulesToJoin = $this->getSubObjectIDs('Module');
                    $this->description = sprintf('%s %s',_('Created by FOG Reg on'),$this->formatTime('now','F j, Y, g:i a'));
                    if (isset($_REQUEST['advanced'])) $this->fullReg();
                    else if ($this->getSetting('FOG_QUICKREG_AUTOPOP')) $this->quickRegAuto();
                    else $this->quickReg();
                }
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
    }
    private function stripAndDecode($item) {
        return trim(base64_decode($item));
    }
    private function regExists() {
        try {
            if ($this->Host instanceof Host && $this->Host->isValid()) throw new Exception(sprintf('%s %s',_('Already registered as'),$this->Host->get('name')));
        } catch (Exception $e) {
            echo $e->getMessage();
            return true;
        }
        return false;
    }
    private function fullReg() {
        try {
            $productKey = $this->stripAndDecode($_REQUEST['productKey']);
            $username = $this->stripAndDecode($_REQUEST['username']);
            $hostname = $this->stripAndDecode($_REQUEST['host']);
            $hostname = strtoupper((self::getClass('Host')->isHostnameSafe($hostname) ? $hostname : $this->macsimple));
            $ip = $this->stripAndDecode($_REQUEST['ip']);
            $imageid = $this->stripAndDecode($_REQUEST['imageid']);
            $imageid = (self::getClass('Image',$imageid)->isValid() ? $imageid : 0);
            $primaryuser = $this->stripAndDecode($_REQUEST['primaryuser']);
            $other1 = $this->stripAndDecode($_REQUEST['other1']);
            $other2 = $this->stripAndDecode($_REQUEST['other2']);
            $doimage = trim($_REQUEST['doimage']);
            if ($_REQUEST['doad']) {
                $OUs = explode('|',$this->getSetting('FOG_AD_DEFAULT_OU'));
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
                $ADDomain = $this->getSetting('FOG_AD_DEFAULT_DOMAINNAME');
                $ADOU = $opt;
                $ADUser = $this->getSetting('FOG_AD_DEFAULT_USER');
                $ADPass = $this->getSetting('FOG_AD_DEFAULT_PASSWORD');
                $ADPassLegacy = $this->getSetting('FOG_AD_DEFAULT_PASSWORD_LEGACY');
                $enforce = $this->getSetting('FOG_ENFORCE_HOST_CHANGES');
            }
            $groupsToJoin = explode(',',$this->stripAndDecode($_REQUEST['groupid']));
            $snapinsToJoin = explode(',',$this->stripAndDecode($_REQUEST['snapinid']));
            $this->Host = self::getClass('Host')
                ->set('name',$hostname)
                ->set('description',$this->description)
                ->set('imageID',$imageid)
                ->set('productKey',$this->encryptpw($productKey))
                ->addModule($this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addSnapin($snapinsToJoin)
                ->addPriMAC($this->PriMAC)
                ->addAddMAC($this->MACs)
                ->setAD($useAD,$ADDomain,$ADOU,$ADUser,$ADPass,false,true,$ADPassLegacy,$productKey,$enforce);
            if (!$this->Host->save()) throw new Exception(_('Failed to create Host'));
            self::$HookManager->processEvent('HOST_REGISTER',array('Host'=>&$this->Host));
            try {
                if (!$doimage) throw new Exception(_('Done, without imaging!'));
                if (!$this->Host->getImageMemberFromHostID()) throw new Exception(_('Done, No image assigned!'));
                if (!$this->Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$username)) throw new Exception(_('Done, Failed to create tasking'));
                throw new Exception(_('Done, with imaging!'));
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            self::getClass('Inventory')
                ->set('hostID',$this->Host->get('id'))
                ->set('primaryUser', $primaryuser)
                ->set('other1', $other1)
                ->set('other2', $other2)
                ->save();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    private function quickRegAuto() {
        try {
            $groupsToJoin = explode(',',trim($this->getSetting('FOG_QUICKREG_GROUP_ASSOC')));
            $autoRegSysName = trim($this->getSetting('FOG_QUICKREG_SYS_NAME'));
            $autoRegSysNumber = (int)$this->getSetting('FOG_QUICKREG_SYS_NUMBER');
            $hostname = trim((strtoupper($autoRegSysName) == 'MAC' ? $this->macsimple : $autoRegSysName));
            $hostname = (self::getClass('Host')->isHostnameSafe($hostname) ? $hostname : $this->macsimple);
            $paddingLen = substr_count($autoRegSysName,'*');
            $paddingString = null;
            if ($paddingLen > 0) {
                $paddingString = str_repeat('*',$paddingLen);
                $paddedInsert = str_pad($autoRegSysNumber,$paddingLen,0,STR_PAD_LEFT);
                if (trim(strtoupper($autoRegSysName)) == 'MAC') $hostname = $this->macsimple;
                else {
                    $hostname = str_replace($paddingString,$paddedInsert,$autoRegSysName);
                    while (self::getClass('HostManager')->exists($hostname)) {
                        $paddingString = str_repeat('*',$paddingLen);
                        $paddedInsert = str_pad(++$autoRegSysNumber,$paddingLen,0,STR_PAD_LEFT);
                        $hostname = str_replace($paddingString,$paddedInsert,$autuRegSysName);
                    }
                }
            }
            if (!self::getClass('Host')->isHostnameSafe($hostname)) $hostname = $this->macsimple;
            $this->setSetting('FOG_QUICKREG_SYS_NUMBER',++$autoRegSysNumber);
            $imageid = (int)$this->getSetting('FOG_QUICKREG_IMG_ID');
            $this->Host = self::getClass('Host')
                ->set('name',$hostname)
                ->set('description',$this->description)
                ->set('imageID',$imageid)
                ->addModule($this->modulesToJoin)
                ->addGroup($groupsToJoin)
                ->addPriMAC($this->PriMAC)
                ->addAddMAC($this->MACs);
            self::$HookManager->processEvent('HOST_REGISTER',array('Host'=>&$this->Host));
            if (!$this->Host->save()) throw new Exception(_('Failed to create Host'));
            if ($imageid && $this->Host->getImageMemberFromHostID()) {
                if (!$this->Host->createImagePackage(1,'AutoRegTask',false,false,true,false,$username)) throw new Exception(_('Done, Failed to create tasking'));
                throw new Exception(_('Done, with imaging!'));
            }
            throw new Exception(_('Done'));
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    private function quickReg() {
        try {
            $this->Host = self::getClass('Host')
                ->set('name',$this->macsimple)
                ->set('description',$this->description)
                ->addModule($this->modulesToJoin)
                ->addPriMAC($this->PriMAC)
                ->addAddMAC($this->MACs);
            self::$HookManager->processEvent('HOST_REGISTER',array('Host'=>&$this->Host));
            if (!$this->Host->save()) throw new Exception(_('Failed to create Host'));
            throw new Exception(_('Done'));
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
