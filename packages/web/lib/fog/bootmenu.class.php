<?php
class BootMenu extends FOGBase {
    private $Host,$kernel,$initrd,$booturl,$memdisk,$memtest,$web,$defaultChoice,$bootexittype,$loglevel;
    private $storage, $shutdown, $path;
    private $hiddenmenu, $timeout, $KS;
    public function __construct($Host = null) {
        parent::__construct();
        $this->loglevel = 'loglevel='.$this->getSetting(FOG_KERNEL_LOGLEVEL);
        $StorageNode = $this->getClass(StorageNodeManager)->find(array(isEnabled=>1,isMaster=>1));
        $StorageNode = @array_shift($StorageNode);
        $webserver = $this->getSetting('FOG_WEB_HOST');
        $curroot = trim(trim($this->getSetting('FOG_WEB_ROOT'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $Send['booturl'] = array(
            '#!ipxe',
            "set fog-ip $webserver",
            sprintf('set fog-webroot %s',basename($this->getSetting('FOG_WEB_ROOT'))),
            'set boot-url http://${fog-ip}/${fog-webroot}',
        );
        $this->parseMe($Send);
        $this->web = "${webserver}${webroot}";
        $exitTypes = array(
            'sanboot'=>'sanboot --no-describe --drive 0x80',
            'grub'=>'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="rootnoverify (hd0);chainloader +1"',
            'grub_first_hdd'=>'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="rootnoverify (hd0);chainloader +1"',
            'grub_first_cdrom'=>'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="cdrom --init;map --hook;root (cd0);chainloader (cd0)"',
            'grub_first_found_windows'=>'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="find --set-root /BOOTMGR;chainloader /BOOTMGR"',
            'refind_efi'=>"imgfetch \${boot-url}/service/ipxe/refind.conf\nchain -ar \${boot-url}/service/ipxe/refind.efi",
            'exit'=>'exit',
        );
        $exitSetting = false;
        $exitKeys = array_keys($exitTypes);
        if (isset($_REQUEST['platform']) && $_REQUEST['platform'] == 'efi') {
            $exitSetting = $Host instanceof Host && $Host->isValid() && $Host->get('efiexit') ? $Host->get('efiexit') : $this->getSetting('FOG_EFI_BOOT_EXIT_TYPE');
        } else {
            $exitSetting = $Host instanceof Host && $Host->isValid() && $Host->get('biosexit') ? $Host->get('biosexit') : $this->getSetting('FOG_BOOT_EXIT_TYPE');
        }
        $this->bootexittype = (in_array($exitSetting,$exitKeys) ? $exitTypes[$exitSetting] : $exitSetting);
        $ramsize = $this->getSetting('FOG_KERNEL_RAMDISK_SIZE');
        $dns = $this->getSetting('FOG_PXE_IMAGE_DNSADDRESS');
        $keymap = $this->getSetting('FOG_KEYMAP');
        $memdisk = 'memdisk';
        $memtest = $this->getSetting('FOG_MEMTEST_KERNEL');
        $bzImage = ($_REQUEST['arch'] == 'x86_64' ? $this->getSetting('FOG_TFTP_PXE_KERNEL') : $this->getSetting('FOG_TFTP_PXE_KERNEL_32'));
        $kernel = $bzImage;
        $imagefile = ($_REQUEST['arch'] == 'x86_64' ? $this->getSetting('FOG_PXE_BOOT_IMAGE') : $this->getSetting('FOG_PXE_BOOT_IMAGE_32'));
        $initrd = $imagefile;
        if ($Host && $Host->isValid()) {
            ($Host->get('kernel') ? $bzImage = $Host->get('kernel') : null);
            $kernel = $bzImage;
            $this->HookManager->processEvent('BOOT_ITEM_NEW_SETTINGS',array('Host'=>&$Host,'StorageGroup'=>&$StorageGroup,'StorageNode'=>&$StorageNode,'memtest'=>&$memtest,'memdisk'=>&$memdisk,'bzImage'=>&$bzImage,'initrd'=>&$initrd,'webroot'=>&$webroot,'imagefile'=>&$imagefile,'init'=>&$initrd));
        }
        $keySequence = $this->getSetting('FOG_KEY_SEQUENCE');
        if ($keySequence) $this->KS = $this->getClass('KeySequence',$keySequence);
        if (!$_REQUEST['menuAccess']) $this->hiddenmenu = $this->getSetting('FOG_PXE_MENU_HIDDEN');
        $timeout = ($this->hiddenmenu ? $this->getSetting('FOG_PXE_HIDDENMENU_TIMEOUT') : $this->getSetting('FOG_PXE_MENU_TIMEOUT'))* 1000;
        $this->timeout = $timeout;
        $this->booturl = "http://${webserver}${webroot}service";
        $this->Host = $Host;
        $CaponePlugInst = in_array('capone',(array)$_SESSION['PluginsInstalled']);
        $DMISet = $CaponePlugInst ? $this->getSetting('FOG_PLUGIN_CAPONE_DMI') : false;
        if ($CaponePlugInst) {
            $this->storage = $StorageNode->get('ip');
            $this->path = $StorageNode->get('path');
            $this->shutdown = $this->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN');
        }
        if ($CaponePlugInst && $DMISet) {
            $PXEMenuItem = $this->getClass('PXEMenuOptionsManager')->find(array('name'=>'fog.capone'));
            $PXEMenuItme = @array_shift($PxeMenuItem);
            if ($PXEMenuItem instanceof PXEMenuOptions && $PXEMenuItem->isValid()) $PXEMenuItem->set(args,"mode=capone shutdown=$this->shutdown storage=$this->storage:$this->path")->save();
            else {
                $this->getClass('PXEMenuOptions')
                    ->set('name','fog.capone')
                    ->set('description','Capone Deploy')
                    ->set('args',"mode=capone shutdown=$this->shutdown storage=$this->storage:$this->path")
                    ->set('params',null)
                    ->set('default',0)
                    ->set('regMenu',2)
                    ->save();
            }
            $PXEMenuItem->save();
        }
        $this->memdisk = "kernel $memdisk";
        $this->memtest = "initrd $memtest";
        $this->kernel = "kernel $bzImage $this->loglevel init=/sbin/init initrd=$initrd root=/dev/ram0 rw ramdisk_size=$ramsize keymap=$keymap web=${webserver}${webroot} consoleblank=0".($this->getSetting('FOG_KERNEL_DEBUG') ? ' debug' : '');
        $this->initrd = "imgfetch $imagefile";
        $defMenuItem = current($this->getClass('PXEMenuOptionsManager')->find(array('default'=>1)));
        $this->defaultChoice = "choose --default ".($defMenuItem && $defMenuItem->isValid() ? $defMenuItem->get('name') : 'fog.local').(!$this->hiddenmenu ? " --timeout $timeout" : " --timeout 0").' target && goto ${target}';
        $iPXE = $this->getClass(iPXEManager)->find(array('product'=>$_REQUEST['product'],'manufacturer'=>$_REQUEST['manufacturer'],'file'=>$_REQUEST['filename']));
        $iPXE = @array_shift($iPXE);
        if ($iPXE instanceof iPXE && $iPXE->isValid()) {
            if ($iPXE->get('failure')) $iPXE->set('failure',0);
            if (!$iPXE->get('success')) $iPXE->set('success',1);
            if (!$iPXE->get('version')) $iPXE->set('version',$_REQUEST['ipxever']);
            $iPXE->save();
        } else {
            $this->getClass('iPXE')
                ->set('product',$_REQUEST['product'])
                ->set('manufacturer',$_REQUEST['manufacturer'])
                ->set('mac',$Host instanceof Host && $Host->isValid() ? $Host->get('mac') : 'no mac')
                ->set('success',1)
                ->set('file',$_REQUEST['filename'])
                ->set('version',$_REQUEST['ipxever'])
                ->save();
        }
        if ($_REQUEST['username'] && $_REQUEST['password']) $this->verifyCreds();
        else if ($_REQUEST['qihost']) $this->setTasking($_REQUEST['imageID']);
        else if ($_REQUEST['delconf']) $this->delHost();
        else if ($_REQUEST['key']) $this->keyset();
        else if ($_REQUEST['sessname']) $this->sesscheck();
        else if ($_REQUEST['aprvconf']) $this->approveHost();
        else if (!$Host || !$Host->isValid()) $this->printDefault();
        else $this->getTasking();
    }
    private function chainBoot($debug=false, $shortCircuit=false) {
        if (!$this->hiddenmenu || $shortCircuit) {
            $Send['chainnohide'] = array(
                "#!ipxe",
                "cpuid --ext 29 && set arch x86_64 || set arch i386",
                "params",
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                "param menuAccess 1",
                "param debug ".($debug ? 1 : 0),
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                ":bootme",
                "chain -ar $this->booturl/ipxe/boot.php##params",
            );
        } else {
            $Send['chainhide'] = array(
                "#!ipxe",
                "cpuid --ext 29 && set arch x86_64 || set arch i386",
                'iseq ${platform} efi && set key 0x1b || set key '.($this->KS && $this->KS->isValid() ? $this->KS->get('ascii') : '0x1b'),
                'iseq ${platform} efi && set keyName ESC || set keyName '.($this->KS && $this->KS->isValid() ? $this->KS->get('name') : 'Escape'),
                'prompt --key ${key} --timeout '.$this->timeout.' Booting... (Press ${keyName} to access the menu) && goto menuAccess || '.$this->bootexittype,
                ":menuAccess",
                "login",
                "params",
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                'param username ${username}',
                'param password ${password}',
                "param menuaccess 1",
                "param debug ".($debug ? 1 : 0),
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                ":bootme",
                "chain -ar $this->booturl/ipxe/boot.php##params",
            );
        }
        $this->parseMe($Send);
    }
    private function delHost() {
        if($this->Host->destroy()) {
            $Send['delsuccess'] = array(
                "#!ipxe",
                "echo Host deleted successfully",
                "sleep 3"
            );
        } else {
            $Send['delfail'] = array(
                "#!ipxe",
                "echo Failed to destroy Host!",
                "sleep 3",
            );
        }
        $this->parseMe($Send);
        $this->chainBoot();
    }
    private function printImageIgnored() {
        $Send['ignored'] = array(
            "#!ipxe",
            "echo The MAC Address is set to be ignored for imaging tasks",
            "sleep 15",
        );
        $this->parseMe($Send);
        $this->noMenu();
    }
    private function approveHost() {
        if ($this->Host->set('pending',null)->save()) {
            $Send['approvesuccess'] = array(
                "#!ipxe",
                "echo Host approved successfully",
                "sleep 3"
            );
            $this->Host->createImagePackage(10,'Inventory',false,false,false,false,$_REQUEST['username']);
        } else {
            $Send['approvefail'] = array(
                "#!ipxe",
                "echo Host approval failed",
                "sleep 3"
            );
        }
        $this->parseMe($Send);
        $this->chainBoot();
    }
    private function printTasking($kernelArgsArray) {
        foreach($kernelArgsArray AS $i => &$arg) {
            if (!is_array($arg) && !empty($arg) || (is_array($arg) && $arg['active'] && !empty($arg))) $kernelArgs[] = (is_array($arg) ? $arg['value'] : $arg);
        }
        unset($arg);
        $kernelArgs = array_unique($kernelArgs);
        $Send['task'] = array(
            "#!ipxe",
            "$this->kernel ".implode(' ',(array)$kernelArgs),
            "$this->initrd",
            "boot",
        );
        $this->parseMe($Send);
    }
    public function delConf() {
        $Send['delconfirm'] = array(
            "#!ipxe",
            "cpuid --ext 29 && set arch x86_64 || set arch i386",
            "prompt --key y Would you like to delete this host? (y/N): &&",
            "params",
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            "param delconf 1",
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ":bootme",
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function aprvConf() {
        $Send['aprvconfirm'] = array(
            "#!ipxe",
            "cpuid --ext 29 && set arch x86_64 || set arch i386",
            "prompt --key y Would you like to approve this host? (y/N): &&",
            "params",
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            "param aprvconf 1",
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ":bootme",
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function keyreg() {
        $Send['keyreg'] = array(
            "#!ipxe",
            "cpuid --ext 29 && set arch x86_64 || set arch i386",
            "echo -n Please enter the product key>",
            "read key",
            "params",
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            'param key ${key}',
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ":bootme",
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function sesscheck() {
        $sesscount = current($this->getClass('MulticastSessionsManager')->find(array('name' => $_REQUEST['sessname'],'stateID' => array(0,1,2,3))));
        if (!$sesscount || !$sesscount->isValid()) {
            $Send['checksession'] = array(
                "#!ipxe",
                "echo No session found with that name.",
                "clear sessname",
                "sleep 3",
                "cpuid --ext 29 && set arch x86_64 || set arch i386",
                "params",
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                "param sessionJoin 1",
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                ":bootme",
                "chain -ar $this->booturl/ipxe/boot.php##params",
            );
            $this->parseMe($Send);
        } else $this->multijoin($sesscount->get('id'));
    }
    public function sessjoin() {
        $Send['joinsession'] = array(
            "#!ipxe",
            "cpuid --ext 29 && set arch x86_64 || set arch i386",
            "echo -n Please enter the session name to join> ",
            "read sessname",
            "params",
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            'param sessname ${sessname}',
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ":bootme",
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function falseTasking($mc = false,$Image = false) {
        $TaskType = new TaskType(1);
        if ($mc) {
            $Image = $mc->getImage();
            $TaskType = new TaskType(8);
        }
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = $StorageGroup->getOptimalStorageNode();
        $osid = $Image->get('osID');
        $storage = sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),'');
        $storageip = $this->FOGCore->resolveHostname($StorageNode->get('ip'));
        $img = $Image->get('path');
        $imgFormat = $Image->get('format');
        $imgType = $Image->getImageType()->get('type');
        $imgPartitionType = $Image->getImagePartitionType()->get('type');
        $imgid = $Image->get('id');
        $chkdsk = $this->getSetting('FOG_DISABLE_CHKDSK') == 1 ? 0 : 1;
        $ftp = $StorageNode->get('ip');
        $port = ($mc ? $mc->get('port') : null);
        $miningcores = $this->getSetting('FOG_MINING_MAX_CORES');
        $kernelArgsArray = array(
            "mac=$mac",
            "ftp=$ftp",
            "storage=$storage",
            "storageip=$storageip",
            "web=$this->web",
            "osid=$osid",
            "consoleblank=0",
            "irqpoll",
            "chkdsk=$chkdsk",
            "img=$img",
            "imgType=$imgType",
            "imgPartitionType=$imgPartitionType",
            "imgid=$imgid",
            "imgFormat=$imgFormat",
            "shutdown=0",
            array(
                'value' => "capone=1",
                'active' => !$this->Host || !$this->Host->isValid(),
            ),
            array(
                'value' => "port=$port mc=yes",
                'active' => $mc,
            ),
            array(
                'value' => "mining=1 miningcores=$miningcores",
                'active' => $this->getSetting('FOG_MINING_ENABLE'),
            ),
            array(
                'value' => 'debug',
                'active' => $this->getSetting('FOG_KERNEL_DEBUG'),
            ),
            array(
                'value' => 'fdrive='.$this->getSetting('FOG_NONREG_DEVICE'),
                'active' => $this->getSetting('FOG_NONREG_DEVICE'),
            ),
            $TaskType->get('kernelArgs'),
            $this->getSetting('FOG_KERNEL_ARGS'),
        );
        $this->printTasking($kernelArgsArray);
    }
    public function printImageList() {
        $Send['ImageListing'] = array(
            '#!ipxe',
            'goto MENU',
            ':MENU',
            'menu',
        );
        $defItem = 'choose target && goto ${target}';
        $Images = $this->getClass('ImageManager')->find();
        if (!$Images) {
            $Send['NoImages'] = array(
                '#!ipxe',
                'echo No Images on server found',
                'sleep 3',
            );
            $this->parseMe($Send);
            $this->chainBoot();
        } else {
            foreach($Images AS $i => &$Image) {
                if ($Image && $Image->isValid()) {
                    array_push($Send['ImageListing'],"item ".$Image->get('path').' '.$Image->get('name'));
                    if ($this->Host && $this->Host->isValid() && $this->Host->getImage() && $this->Host->getImage()->isValid() && $this->Host->getImage()->get('id') == $Image->get('id')) $defItem = 'choose --default '.$Image->get('path').' --timeout '.$this->timeout.' target && goto ${target}';
                }
            }
            unset($Image);
            array_push($Send['ImageListing'],'item return Return to menu');
            array_push($Send['ImageListing'],$defItem);
            foreach($Images AS $i => &$Image) {
                if ($Image && $Image->isValid()) {
                    $Send['pathofimage'.$Image->get('name')] = array(
                        ':'.$Image->get('path'),
                        'set imageID '.$Image->get('id'),
                        'params',
                        'param mac0 ${net0/mac}',
                        'param arch ${arch}',
                        'param imageID ${imageID}',
                        'param qihost 1',
                        'param username ${username}',
                        'param password ${password}',
                        'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                        'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                    );
                }
            }
            unset($Image);
            $Send['returnmenu'] = array(
                ':return',
                'params',
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            );
            $Send['bootmefunc'] = array(
                ':bootme',
                'chain -ar '.$this->booturl.'/ipxe/boot.php##params',
                'goto MENU',
            );
            $this->parseMe($Send);
        }
    }
    public function multijoin($msid) {
        $MultiSess = new MulticastSessions($msid);
        if ($MultiSess && $MultiSess->isValid()) {
            if ($this->Host && $this->Host->isValid()) {
                $this->Host->set(imageID,$MultiSess->get(image));
                if($this->Host->createImagePackage(8,$MultiSess->get(name),false,false,-1,false,$_REQUEST['username'],'',true)) $this->chainBoot(false,true);
            } else $this->falseTasking($MultiSess);
        }
    }
    public function keyset() {
        $this->Host->set('productKey',$this->encryptpw($_REQUEST['key']));
        if ($this->Host->save()) {
            $Send['keychangesuccess'] = array(
                "#!ipxe",
                "echo Successfully changed key",
                "sleep 3",
            );
            $this->parseMe($Send);
            $this->chainBoot();
        }
    }
    private function parseMe($Send) {
        $this->HookManager->processEvent('IPXE_EDIT',array('ipxe' => &$Send,'Host' => &$this->Host,'kernel' => &$this->kernel,'initrd' => &$this->initrd,'booturl' => &$this->booturl, 'memdisk' => &$this->memdisk,'memtest' => &$this->memtest, 'web' => &$this->web, 'defaultChoice' => &$this->defaultChoice, 'bootexittype' => &$this->bootexittype,'storage' => &$this->storage,'shutdown' => &$this->shutdown,'path' => &$this->path,'timeout' => &$this->timeout,'KS' => $this->ks));
        foreach($Send AS $ipxe => &$val) echo implode("\n",$val)."\n";
        unset($val);
    }
    public function advLogin() {
        $Send['advancedlogin'] = array(
            "#!ipxe",
            "chain -ar $this->booturl/ipxe/advanced.php",
        );
        $this->parseMe($Send);
    }
    private function debugAccess() {
        $Send['debugaccess'] = array(
            "#!ipxe",
            "$this->kernel mode=onlydebug",
            "$this->initrd",
            "boot",
        );
        $this->parseMe($Send);
    }
    public function verifyCreds() {
        if ($this->FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password'])) {
            if ($this->getSetting('FOG_ADVANCED_MENU_LOGIN') && $_REQUEST['advLog']) $this->advLogin();
            if ($_REQUEST['delhost']) $this->delConf();
            else if ($_REQUEST['keyreg']) $this->keyreg();
            else if ($_REQUEST['qihost']) $this->setTasking($_REQUEST['imageID']);
            else if ($_REQUEST['sessionJoin']) $this->sessjoin();
            else if ($_REQUEST['approveHost']) $this->aprvConf();
            else if ($_REQUEST['menuaccess']) {
                unset($this->hiddenmenu);
                $this->chainBoot(true);
            } else if ($_REQUEST['debugAccess']) $this->debugAccess();
            else if (!$this->getSetting('FOG_NO_MENU')) $this->printDefault();
            else $this->noMenu();
        } else {
            $Send['invalidlogin'] = array(
                "#!ipxe",
                "echo Invalid login!",
                "clear username",
                "clear password",
                "sleep 3",
            );
            $this->parseMe($Send);
            $this->chainBoot();
        }
    }
    public function setTasking($imgID = '') {
        if (!$imgID) $this->printImageList();
        if ($imgID) {
            if ($this->Host && $this->Host->isValid()) {
                if ($this->Host->getImage()->get(id) != $imgID) $this->Host->set(imageID,$imgID);
                if ($this->Host->getImage()->isValid()) {
                    try {
                        if($this->Host->createImagePackage(1,'AutoRegTask',false,false,-1,false,$_REQUEST['username'])) $this->chainBoot(false, true);
                    } catch (Exception $e) {
                        $Send['fail'] = array(
                            '#!ipxe',
                            'echo '.$e->getMessage(),
                            'sleep 3',
                        );
                        $this->parseMe($Send);
                    }
                }
            } else $this->falseTasking('',$this->getClass(Image,$imgID));
            $this->chainBoot(false,true);
        }
    }
    public function noMenu() {
        $Send['nomenu'] = array(
            "#!ipxe",
            "$this->bootexittype",
        );
        $this->parseMe($Send);
    }
    public function getTasking() {
        $Task = $this->Host->get('task');
        if (!$Task->isValid()) {
            if ($this->getSetting('FOG_NO_MENU')) $this->noMenu();
            else $this->printDefault();
        } else {
            if ($this->Host->get('mac')->isImageIgnored()) $this->printImageIgnored();
            $TaskType = new TaskType($Task->get('typeID'));
            $imagingTasks = array(1,2,8,15,16,17,24);
            if ($TaskType->isMulticast()) {
                $MulticastSessionAssoc = current($this->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
                $MulticastSession = new MulticastSessions($MulticastSessionAssoc->get('msID'));
                if ($MulticastSession && $MulticastSession->isValid()) $this->Host->set('imageID',$MulticastSession->get('image'));
            }
            if (in_array($TaskType->get('id'),$imagingTasks)) {
                $Image = $Task->getImage();
                $StorageGroup = $Image->getStorageGroup();
                $StorageNode = $StorageGroup->getOptimalStorageNode();
                $this->HookManager->processEvent('BOOT_TASK_NEW_SETTINGS',array('Host' => &$this->Host,'StorageNode' => &$StorageNode,'StorageGroup' => &$StorageGroup));
                if ($TaskType->isUpload() || $TaskType->isMulticast()) $StorageNode = $StorageGroup->getMasterStorageNode();
                $osid = $Image->get('osID');
                $storage = in_array($TaskType->get('id'),$imagingTasks) ? sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),($TaskType->isUpload() ? 'dev/' : '')) : null;
            }
            if ($this->Host && $this->Host->isValid()) $mac = $this->Host->get('mac');
            else $mac = $_REQUEST['mac'];
            $clamav = in_array($TaskType->get('id'),array(21,22)) ? sprintf('%s:%s',trim($StorageNode->get('ip')),'/opt/fog/clamav') : null;
            $storageip = in_array($TaskType->get('id'),$imagingTasks) ? $this->FOGCore->resolveHostname($StorageNode->get('ip')) : null;
            $img = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('path') : null;
            $imgFormat = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('format') : null;
            $imgType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImageType()->get('type') : null;
            $imgPartitionType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImagePartitionType()->get('type') : null;
            $imgid = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('id') : null;
            $ftp = $StorageNode instanceof StorageNode && $StorageNode->isValid() ? $StorageNode->get('ip') : $this->getSetting(FOG_TFTP_HOST);
            $chkdsk = $this->getSetting(FOG_DISABLE_CHKDSK) == 1 ? 0 : 1;
            $PIGZ_COMP = in_array($TaskType->get(id),$imagingTasks) ? ($Image->get(compress) > -1 && is_numeric($Image->get(compress)) ? $Image->get(compress) : $this->getSetting(FOG_PIGZ_COMP)) : $this->getSetting(FOG_PIGZ_COMP);
            $MACs = $this->Host->getMyMacs();
            $clientMacs = array_filter((array)$this->parseMacList(implode('|',(array)$MACs),false,true));
            $kernelArgsArray = array(
                "mac=$mac",
                "ftp=$ftp",
                "storage=$storage",
                "storageip=$storageip",
                "web=$this->web",
                "osid=$osid",
                "consoleblank=0",
                "irqpoll",
                array(
                    'value' => 'hostname='.$this->Host->get(name),
                    'active' => count($clientMacs),
                ),
                array(
                    'value' => "clamav=$clamav",
                    'active' => in_array($TaskType->get('id'),array(21,22)),
                ),
                array(
                    'value' => "chkdsk=$chkdsk",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => "img=$img",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => "imgType=$imgType",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => "imgPartitionType=$imgPartitionType",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => "imgid=$imgid",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => "imgFormat=$imgFormat",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => "PIGZ_COMP=-$PIGZ_COMP",
                    'active' => in_array($TaskType->get('id'),$imagingTasks),
                ),
                array(
                    'value' => 'shutdown=1',
                    'active' => $Task->get('shutdown'),
                ),
                array(
                    'value' => 'adon=1',
                    'active' => $this->Host->get('useAD'),
                ),
                array(
                    'value' => 'addomain='.$this->Host->get('ADDomain'),
                    'active' => $this->Host->get('useAD'),
                ),
                array(
                    'value' => 'adou='.$this->Host->get('ADOU'),
                    'active' => $this->Host->get('useAD'),
                ),
                array(
                    'value' => 'aduser='.$this->Host->get('ADUser'),
                    'active' => $this->Host->get('useAD'),
                ),
                array(
                    'value' => 'adpass='.$this->Host->get('ADPass'),
                    'active' => $this->Host->get('useAD'),
                ),
                array(
                    'value' => 'fdrive='.$this->Host->get('kernelDevice'),
                    'active' => $this->Host->get('kernelDevice'),
                ),
                array(
                    'value' => 'hostearly=1',
                    'active' => $this->getSetting('FOG_CHANGE_HOSTNAME_EARLY') && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
                ),
                array(
                    'value' => 'pct='.(is_numeric($this->getSetting('FOG_UPLOADRESIZEPCT')) && $this->getSetting('FOG_UPLOADRESIZEPCT') >= 5 && $this->getSetting('FOG_UPLOADRESIZEPCT') < 100 ? $this->getSetting('FOG_UPLOADRESIZEPCT') : '5'),
                    'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
                ),
                array(
                    'value' => 'ignorepg='.($this->getSetting('FOG_UPLOADIGNOREPAGEHIBER') ? 1 : 0),
                    'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
                ),
                array(
                    'value' => 'port='.($TaskType->isMulticast() ? $MulticastSession->get('port') : null),
                    'active' => $TaskType->isMulticast(),
                ),
                array(
                    'value' => 'mining=1',
                    'active' => $this->getSetting('FOG_MINING_ENABLE'),
                ),
                array(
                    'value' => 'miningcores=' . $this->getSetting('FOG_MINING_MAX_CORES'),
                    'active' => $this->getSetting('FOG_MINING_ENABLE'),
                ),
                array(
                    'value' => 'winuser='.$Task->get('passreset'),
                    'active' => $TaskType->get('id') == '11' ? true : false,
                ),
                array(
                    'value' => 'miningpath=' . $this->getSetting('FOG_MINING_PACKAGE_PATH'),
                    'active' => $this->getSetting('FOG_MINING_ENABLE'),
                ),
                array(
                    'value' => 'isdebug=yes',
                    'active' => $Task->get('isDebug'),
                ),
                array(
                    'value' => 'debug',
                    'active' => $this->getSetting('FOG_KERNEL_DEBUG'),
                ),
                $TaskType->get('kernelArgs'),
                $this->getSetting('FOG_KERNEL_ARGS'),
                $this->Host->get('kernelArgs'),
            );
            if ($Task->get('typeID') == 12 || $Task->get('typeID') == 13) $this->printDefault();
            else if ($Task->get('typeID') == 4) {
                $Send['memtest'] = array(
                    "#!ipxe",
                    "$this->memdisk iso raw",
                    "$this->memtest",
                    "boot",
                );
                $this->parseMe($Send);
            } else $this->printTasking($kernelArgsArray);
        }
    }
    private function menuItem($option, $desc) {return array("item ".$option->get('name')." ".$option->get('description'));}
        private function menuOpt($option,$type) {
            if ($option->get('id') == 1) {
                $Send = array(
                    ":".$option->get('name'),
                    "$this->bootexittype || goto MENU",
                );
            } else if ($option->get('id') == 2) {
                $Send = array(
                    ":".$option->get('name'),
                    "$this->memdisk iso raw",
                    "$this->memtest",
                    "boot || goto MENU",
                );
            } else if ($option->get('id') == 11) {
                $Send = array(
                    ":".$option->get('name'),
                    "chain -ar $this->booturl/ipxe/advanced.php || goto MENU",
                );
            } else if ($option->get('params')) {
                $Send = array(
                    ':'.$option->get('name'),
                    $option->get('params'),
                );
            } else {
                $Send = array(
                    ":$option",
                    "$this->kernel $this->loglevel $type",
                    "$this->initrd",
                    "boot || goto MENU",
                );
            }
            return $Send;
        }
    public function printDefault() {
        $Menus = $this->getClass('PXEMenuOptionsManager')->find('','','id');
        $Send['head'] = array(
            "#!ipxe",
            "cpuid --ext 29 && set arch x86_64 || set arch i386",
            "goto get_console",
            ":console_set",
            "colour --rgb 0x00567a 1 && colour --rgb 0x00567a 2 && colour --rgb 0x00567a 4 ||",
            "cpair --foreground 7 --background 2 2 ||",
            "goto MENU",
            ":alt_console",
            "cpair --background 0 1 && cpair --background 1 2 ||",
            "goto MENU",
            ":get_console",
            "console --picture $this->booturl/ipxe/bg.png --left 100 --right 80 && goto console_set || goto alt_console",
        );
        if (!$this->hiddenmenu) {
            $showDebug = $_REQUEST["debug"] === "1";
            $hostRegColor = $this->Host && $this->Host->isValid() ? '0x00567a' : '0xff0000';
            $Send['menustart'] = array(
                ":MENU",
                "menu",
                "colour --rgb $hostRegColor 0 ||",
                "cpair --foreground 1 1 ||",
                "cpair --foreground 0 3 ||",
                "cpair --foreground 4 4 ||",
                "item --gap Host is ".($this->Host && $this->Host->isValid() ? ($this->Host->get('pending') ? 'pending ' : '')."registered as ".$this->Host->get('name') : "NOT registered!"),
                "item --gap -- -------------------------------------",
            );
            $Advanced = $this->getSetting('FOG_PXE_ADVANCED');
            $AdvLogin = $this->getSetting('FOG_ADVANCED_MENU_LOGIN');
            $ArrayOfStuff = array(($this->Host && $this->Host->isValid() ? ($this->Host->get('pending') ? 6 : 1) : 0),2);
            if ($showDebug) array_push($ArrayOfStuff,3);
            if ($Advanced) array_push($ArrayOfStuff,($AdvLogin ? 5 : 4));
            foreach($Menus AS $i => &$Menu) {
                if (!in_array($Menu->get('name'),array('fog.reg','fog.reginput')) || (in_array($Menu->get('name'),array('fog.reg','fog.reginput')) && $this->getSetting('FOG_REGISTRATION_ENABLED'))) {
                    if (in_array($Menu->get('regMenu'),$ArrayOfStuff)) $Send['item-'.$Menu->get('name')] = $this->menuItem($Menu, $desc);
                }
            }
            unset($Menu);
            $Send['default'] = array(
                "$this->defaultChoice",
            );
            foreach($Menus AS $i => &$Menu) {
                if (in_array($Menu->get('regMenu'),$ArrayOfStuff)) $Send['choice-'.$Menu->get('name')] = $Menu->get('args') ? $this->menuOpt($Menu,$Menu->get('args')) : $this->menuOpt($Menu,true);
            }
            unset($Menu);
            $Send['bootme'] = array(
                ":bootme",
                "chain -ar $this->booturl/ipxe/boot.php##params ||",
                "goto MENU",
                "autoboot",
            );
            $this->parseMe($Send);
        } else $this->chainBoot(true);
    }
}
