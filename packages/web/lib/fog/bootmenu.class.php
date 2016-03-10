<?php
class BootMenu extends FOGBase {
    private $Host;
    private $kernel;
    private $initrd;
    private $booturl;
    private $memdisk;
    private $memtest;
    private $web;
    private $defaultChoice;
    private $bootexittype;
    private $loglevel;
    private $storage;
    private $shutdown;
    private $path;
    private $hiddenmenu;
    private $timeout;
    private $KS;
    private static $exitTypes = array(
        'sanboot' => 'sanboot --no-describe --drive 0x80',
        'grub' => 'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="rootnoverify (hd0);chainloader +1"',
        'grub_first_hdd' => 'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="rootnoverify (hd0);chainloader +1"',
        'grub_first_cdrom' => 'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="cdrom --init;map --hook;root (cd0);chainloader (cd0)"',
        'grub_first_found_windows' => 'chain -ar ${boot-url}/service/ipxe/grub.exe --config-file="find --set-root /BOOTMGR;chainloader /BOOTMGR"',
        'refind_efi' => "imgfetch \${boot-url}/service/ipxe/refind.conf\nchain -ar \${boot-url}/service/ipxe/refind.efi",
        'exit' => 'exit',
    );
    public function __construct($Host = null) {
        parent::__construct();
        $webserver = $this->getSetting('FOG_WEB_HOST');
        $curroot = trim($this->getSetting('FOG_WEB_ROOT'),'/');
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $this->web = sprintf('%s%s',$webserver,$webroot);
        $Send['booturl'] = array(
            '#!ipxe',
            "set fog-ip $webserver",
            sprintf('set fog-webroot %s',basename($this->getSetting('FOG_WEB_ROOT'))),
            'set boot-url http://${fog-ip}/${fog-webroot}',
        );
        $this->parseMe($Send);
        $this->Host = $Host;
        $host_field_test = 'biosexit';
        $global_field_test = 'FOG_BOOT_EXIT_TYPE';
        if ($_REQUEST['platform'] == 'efi') {
            $host_field_test = 'efiexit';
            $global_field_test = 'FOG_EFI_BOOT_EXIT_TYPE';
        }
        $StorageNode = $this->getClass('StorageNode',@min($this->getSubObjectIDs('StorageNode',array('isEnabled'=>1,'isMaster'=>1))));
        $loglevel = (int)$this->getSetting('FOG_KERNEL_LOGLEVEL');
        $memdisk = 'memdisk';
        $ramsize = $this->getSetting('FOG_KERNEL_RAMDISK_SIZE');
        $dns = $this->getSetting('FOG_PXE_IMAGE_DNSADDRESS');
        $keymap = $this->getSetting('FOG_KEYMAP');
        $memtest = $this->getSetting('FOG_MEMTEST_KERNEL');
        $bzImage = $this->getSetting('FOG_TFTP_PXE_KERNEL_32');
        $imagefile = $this->getSetting('FOG_PXE_BOOT_IMAGE_32');
        $timeout = $this->getSetting('FOG_PXE_MENU_TIMEOUT') * 1000;
        if (!$_REQUEST['menuAccess']) $hiddenmenu = (int)$this->getSetting('FOG_PXE_MENU_HIDDEN');
        if ($hiddenmenu) {
            $keySequence = $this->getSetting('FOG_KEY_SEQUENCE');
            $timeout = $this->getSetting('FOG_PXE_HIDDENMENU_TIMEOUT') * 1000;
        }
        if ($_REQUEST['arch'] == 'x86_64') {
            $bzImage = $this->getSetting('FOG_TFTP_PXE_KERNEL');
            $imagefile = $this->getSetting('FOG_PXE_BOOT_IMAGE');
        }
        $kernel = $bzImage;
        if ($this->Host->get('kernel')) $bzImage = trim($this->Host->get('kernel'));
        $StorageGroup = $StorageNode->getStorageGroup();
        $exit = trim($this->Host->get($host_field_test) ? $this->Host->get($host_field_test) : $this->getSetting($global_field_test));
        if (!$exit || !in_array($exit,array_keys(self::$exitTypes))) $exit = 'sanboot';
        $initrd = $imagefile;
        if ($this->Host->isValid()) {
            $this->HookManager->processEvent('BOOT_ITEM_NEW_SETTINGS',array(
                'Host' => &$this->Host,
                'StorageGroup' => &$StorageGroup,
                'StorageNode' => &$StorageNode,
                'webroot' => &$webroot,
                'memtest' => &$memtest,
                'memdisk' => &$memdisk,
                'bzImage' => &$bzImage,
                'imagefile' => &$imagefile,
                'initrd' => &$initrd,
                'loglevel' => &$loglevel,
                'ramsize' => &$ramsize,
                'keymap' => &$keymap,
                'timeout' => &$timeout,
                'keySequence' => &$keySequence,
            ));
        }
        $kernel = $bzImage;
        $initrd = $imagefile;
        $this->timeout = $timeout;
        $this->hiddenmenu = $hiddenmenu;
        $this->bootexittype = self::$exitTypes[$exit];
        $this->loglevel = "loglevel=$loglevel";
        $this->KS = $this->getClass('KeySequence',$keySequence);
        $this->booturl = "http://{$webserver}{$webroot}service";
        $this->memdisk = "kernel $memdisk";
        $this->memtest = "initrd $memtest";
        $this->kernel = sprintf('kernel %s %s initrd=%s root=/dev/ram0 rw ramdisk_size=%s keymap=%s web=%s conosoleblank=0%s',
            $bzImage,
            $this->loglevel,
            basename($initrd),
            $ramsize,
            $keymap,
            $this->web,
            $this->getSetting('FOG_KERNEL_DEBUG') ? ' debug' : ''
        );
        $this->initrd = "imgfetch $imagefile";
        self::caponeMenu(
            $this->storage,
            $this->path,
            $this->shutdown,
            $this->getSetting('FOG_PLUGIN_CAPONE_DMI'),
            $this->getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN'),
            $StorageNode,
            $this->FOGCore
        );
        $defaultMenu = $this->getClass('PXEMenuOptions',$this->getSubObjectIDs('PXEMenuOptions',array('default'=>1)));
        $menuname = $defaultMenu->isValid() ? trim($defaultMenu->get('name')) : 'fog.local';
        unset($defaultMenu);
        self::getDefaultMenu($this->timeout,$menuname,$this->defaultChoice);
        $this->ipxeLog();
        if (trim($_REQUEST['extraargs']) && $_SESSION['extraargs'] != trim($_REQUEST['extraargs'])) $_SESSION['extraargs'] = trim($_REQUEST['extraargs']);
        if (isset($_REQUEST['username'])) $this->verifyCreds();
        else if ($_REQUEST['qihost']) $this->setTasking($_REQUEST['imageID']);
        else if ($_REQUEST['delconf']) $this->delHost();
        else if ($_REQUEST['key']) $this->keyset();
        else if ($_REQUEST['sessname']) $this->sesscheck();
        else if ($_REQUEST['aprvconf']) $this->approveHost();
        else if (!$this->Host->isValid()) $this->printDefault();
        else $this->getTasking();
    }
    private static function caponeMenu(&$storage, &$path, &$shutdown,&$DMISet,&$Shutdown,&$StorageNode,&$FOGCore) {
        if (!in_array('capone',(array)$_SESSION['PluginsInstalled'])) return;
        if (!$DMISet) return;
        $storage = $StorageNode->get('ip');
        $path = $StorageNode->get('path');
        $shutdown = $Shutdown;
        $args = trim("mode=capone shutdown=$shutdown storage=$storage:$path");
        $CaponeMenu = $FOGCore->getClass('PXEMenuOptions',$FOGCore->getSubObjectIDs('PXEMenuOptions',array('name'=>'fog.capone')));
        if (!$CaponeMenu->isValid()) {
            $CaponeMenu->set('name','fog.capone')
                ->set('description',_('Capone Deploy'))
                ->set('args',$args)
                ->set('params',null)
                ->set('default',0)
                ->set('regMenu',2);
        } else if (trim($CaponeMenu->get('args')) !== $args) $CaponeMenu->set('args',$args);
        $CaponeMenu->save();
    }
    private static function getDefaultMenu($timeout,$name,&$default) {
        $default = "choose --default $name --timeout $timeout target && goto \${target}";
    }
    private function ipxeLog() {
        $findWhere = array(
            'file' => trim(basename($_REQUEST['filename'])),
            'product' => trim($_REQUEST['product']),
            'manufacturer' => trim($_REQUEST['manufacturer']),
            'mac' => $this->Host->isValid() ? $this->Host->get('mac')->__toString() : '',
        );
        $this->getClass('iPXE',@max($this->getSubObjectIDs('iPXE',$findWhere)))
            ->set('product',$findWhere['product'])
            ->set('manufacturer',$findWhere['manufacturer'])
            ->set('mac', $findWhere['mac'])
            ->set('success',1)
            ->set('failure',0)
            ->set('file',$findWhere['file'])
            ->set('version',trim($_REQUEST['ipxever']))
            ->save();
    }
    private function chainBoot($debug=false, $shortCircuit=false) {
        $debug = (int)$debug;
        if (!$this->hiddenmenu || $shortCircuit) {
            $Send['chainnohide'] = array(
                'cpuid --ext 29 && set arch x86_64 || set arch i386',
                'params',
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                'param menuAccess 1',
                "param debug $debug",
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                ':bootme',
                "chain -ar $this->booturl/ipxe/boot.php##params",
            );
        } else {
            $KSKey = $this->KS->isValid() ? trim($this->KS->get('ascii')) : '0x1b';
            $KSName = $this->KS->isValid() ? trim($this->KS->get('name')) : 'Escape';
            $Send['chainhide'] = array(
                'cpuid --ext 29 && set arch x86_64 || set arch i386',
                "iseq \${platform} efi && set key 0x1b || set key $KSKey",
                "iseq \${platform} efi && set keyName ESC || set keyName $KSName",
                "prompt --key \${key} --timeout $this->timeout Booting... (Press \${keyName} to access the menu) && goto menuAccess || $this->bootexittype",
                ':menuAccess',
                'login',
                'params',
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                'param username ${username}',
                'param password ${password}',
                'param menuaccess 1',
                "param debug $debug",
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                ':bootme',
                "chain -ar $this->booturl/ipxe/boot.php##params",
            );
        }
        $this->parseMe($Send);
    }
    private function delHost() {
        if($this->Host->destroy()) {
            $Send['delsuccess'] = array(
                'echo Host deleted successfully',
                'sleep 3'
            );
        } else {
            $Send['delfail'] = array(
                'echo Failed to destroy Host!',
                'sleep 3',
            );
        }
        $this->parseMe($Send);
        $this->chainBoot();
    }
    private function printImageIgnored() {
        $Send['ignored'] = array(
            'echo The MAC Address is set to be ignored for imaging tasks',
            'sleep 15',
        );
        $this->parseMe($Send);
        $this->printDefault();
    }
    private function approveHost() {
        if ($this->Host->set('pending',null)->save()) {
            $Send['approvesuccess'] = array(
                'echo Host approved successfully',
                'sleep 3'
            );
            $shutdown = stripos('shutdown=1',$_SESSION['extraargs']);
            $isdebug = preg_match('#isdebug=yes|mode=debug|mode=onlydebug#i',$_SESSION['extraargs']);
            $this->Host->createImagePackage(10,'Inventory',$shutdown,$isdebug,false,false,$_REQUEST['username']);
        } else {
            $Send['approvefail'] = array(
                'echo Host approval failed',
                'sleep 3'
            );
        }
        $this->parseMe($Send);
        $this->chainBoot();
    }
    private function printTasking($kernelArgsArray) {
        $kernelArgs = '';
        foreach($kernelArgsArray AS &$arg) {
            if (empty($arg)) continue;
            if (is_array($arg)) {
                if (!$arg['active']) continue;
                if (!$arg['value']) continue;
                $kernelArgs[] = preg_replace('#mode=debug|mode=onlydebug#i','isdebug=yes',$arg['value']);
            } else $kernelArgs[] = preg_replace('#mode=debug|mode=onlydebug#i','isdebug=yes',$arg);
            unset($arg);
        }
        $kernelArgs = array_unique($kernelArgs);
        $kernelArgs = implode(' ',(array)$kernelArgs);
        $Send['task'] = array(
            "$this->kernel $kernelArgs",
            $this->initrd,
            'boot',
        );
        $this->parseMe($Send);
    }
    public function delConf() {
        $Send['delconfirm'] = array(
            'cpuid --ext 29 && set arch x86_64 || set arch i386',
            'prompt --key y Would you like to delete this host? (y/N): &&',
            'params',
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            'param delconf 1',
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ':bootme',
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function aprvConf() {
        $Send['aprvconfirm'] = array(
            'cpuid --ext 29 && set arch x86_64 || set arch i386',
            'prompt --key y Would you like to approve this host? (y/N): &&',
            'params',
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            'param aprvconf 1',
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ':bootme',
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function keyreg() {
        $Send['keyreg'] = array(
            'cpuid --ext 29 && set arch x86_64 || set arch i386',
            'echo -n Please enter the product key : ',
            'read key',
            'params',
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            'param key ${key}',
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ':bootme',
            "chain -ar $this->booturl/ipxe/boot.php##params",
        );
        $this->parseMe($Send);
    }
    public function sesscheck() {
        $findWhere = array(
            'name' => trim($_REQUEST['sessname']),
            'stateID' => array_merge($this->getQueuedStates(),(array)$this->getProgressState()),
        );
        $MulticastSession = $this->getClass('MulticastSessions',@max($this->getSubObjectIDs('MulticastSessions',$findWhere)));
        if (!$MulticastSession->isValid()) {
            $Send['checksession'] = array(
                'echo No session found with that name.',
                'clear sessname',
                'sleep 3',
                'cpuid --ext 29 && set arch x86_64 || set arch i386',
                'params',
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                'param sessionJoin 1',
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                ':bootme',
                "chain -ar $this->booturl/ipxe/boot.php##params",
            );
            $this->parseMe($Send);
            return;
        }
        $this->multijoin($MulticastSession->get('id'));
    }
    public function sessjoin() {
        $Send['joinsession'] = array(
            'cpuid --ext 29 && set arch x86_64 || set arch i386',
            'echo -n Please enter the session name to join > ',
            'read sessname',
            'params',
            'param mac0 ${net0/mac}',
            'param arch ${arch}',
            'param platform ${platform}',
            'param sessname ${sessname}',
            'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
            'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
            ':bootme',
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
        $StorageNode = $StorageGroup->getOptimalStorageNode($Image->get('id'));
        $osid = $Image->get('osID');
        $storage = sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),'');
        $storageip = $this->FOGCore->resolveHostname($StorageNode->get('ip'));
        $img = $Image->get('path');
        $imgFormat = $Image->get('format');
        $imgType = $Image->getImageType()->get('type');
        $imgPartitionType = $Image->getPartitionType();
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
            'goto MENU',
            ':MENU',
            'menu',
        );
        $defItem = 'choose target && goto ${target}';
        $Images = $this->getClass('ImageManager')->find(array('isEnabled'=>1));
        if (!$Images) {
            $Send['NoImages'] = array(
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
                "chain -ar $this->booturl/ipxe/boot.php##params",
                'goto MENU',
            );
            $this->parseMe($Send);
        }
    }
    public function multijoin($msid) {
        $MultiSess = new MulticastSessions($msid);
        $shutdown = stripos('shutdown=1',$_SESSION['extraargs']);
        $isdebug = preg_match('#isdebug=yes|mode=debug|mode=onlydebug#i',$_SESSION['extraargs']);
        if ($MultiSess->isValid()) {
            if ($this->Host->isValid()) {
                $this->Host->set('imageID',$MultiSess->get('image'));
                if ($this->Host->createImagePackage(8,$MultiSess->get('name'),$shutdown,$isdebug,-1,false,$_REQUEST['username'],'',true)) $this->chainBoot(false,true);
            } else $this->falseTasking($MultiSess);
        }
    }
    public function keyset() {
        $this->Host->set('productKey',$this->encryptpw($_REQUEST['key']));
        if ($this->Host->save()) {
            $Send['keychangesuccess'] = array(
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
            "chain -ar $this->booturl/ipxe/advanced.php",
        );
        $this->parseMe($Send);
    }
    private function debugAccess() {
        $Send['debugaccess'] = array(
            "$this->kernel isdebug=yes",
            "$this->initrd",
            "boot",
        );
        $this->parseMe($Send);
    }
    public function verifyCreds() {
        if ($this->getSetting('FOG_NO_MENU')) $this->noMenu();
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
            else $this->printDefault();
        } else {
            $Send['invalidlogin'] = array(
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
        $shutdown = stripos('shutdown=1',$_SESSION['extraargs']);
        $isdebug = preg_match('#isdebug=yes|mode=debug|mode=onlydebug#i',$_SESSION['extraargs']);
        if (!$imgID) $this->printImageList();
        if ($imgID) {
            if ($this->Host->isValid()) {
                if ($this->Host->getImage()->get('id') != $imgID) $this->Host->set('imageID',$imgID);
                if ($this->Host->getImage()->isValid()) {
                    try {
                        $this->Host->createImagePackage(1,'AutoRegTask',$shutdown,$isdebug,-1,false,$_REQUEST['username']);
                        $this->chainBoot(false, true);
                    } catch (Exception $e) {
                        $Send['fail'] = array(
                            sprintf('echo %s',$e->getMessage()),
                            'sleep 3',
                        );
                        $this->parseMe($Send);
                    }
                }
            } else $this->falseTasking('',$this->getClass('Image',$imgID));
            $this->chainBoot(false,true);
        }
    }
    public function noMenu() {
        $Send['nomenu'] = array(
            "$this->bootexittype",
        );
        $this->parseMe($Send);
        exit;
    }
    public function getTasking() {
        $Task = $this->Host->get('task');
        if (!$Task->isValid() || $Task->isSnapinTasking()) {
            $this->printDefault();
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
                $StorageNode = $StorageGroup->getOptimalStorageNode($Image->get('id'));
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
            if ($this->Host->get('useAD')) {
                $addomain = preg_replace('#\ #','+_+',$this->Host->get('ADDomain'));
                $adou = preg_replace('#\ #','+_+',$this->Host->get('ADOU'));
                $aduser = preg_replace('#\ #','+_+',$this->Host->get('ADUser'));
                $adpass = preg_replace('#\ #','+_+',$this->Host->get('ADPass'));
            }
            $fdrive = $this->Host->get('kernelDevice');
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
                    'value' => "hostname={$this->Host->get(name)}",
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
                    'value' => "adon=1 addomain=\"$addomain\" adou=\"$adou\" aduser=\"$aduser\" adpass=\"$adpass\"",
                    'active' => $this->Host->get('useAD'),
                ),
                array(
                    'value' => "fdrive=$fdrive",
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
                    'active' => $TaskType->get('id') == '11',
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
                array(
                    'value' => 'seconds='.$this->getSetting('FOG_WIPE_TIMEOUT'),
                    'active' => in_array($TaskType->get('id'),range(18,20)),
                ),
                $TaskType->get('kernelArgs'),
                $this->getSetting('FOG_KERNEL_ARGS'),
                $this->Host->get('kernelArgs'),
            );
            if ($Task->get('typeID') == 4) {
                $Send['memtest'] = array(
                    "$this->memdisk iso raw",
                    "$this->memtest",
                    "boot",
                );
                $this->parseMe($Send);
            } else $this->printTasking($kernelArgsArray);
        }
    }
    private function menuItem($option, $desc) {
        return array("item {$option->get(name)} $desc");
    }
    private function menuOpt($option,$type) {
        $name = trim(":{$option->get(name)}");
        $type = trim($type);
        $Send = array($name);
        if (trim($option->get('params'))) {
            $params = explode("\n",$option->get('params'));
            $params = array_map('trim',$params);
            if ($type) {
                $index = array_search('params',$params);
                if ($index != false && is_numeric($index)) $this->array_insert_after($index,$params,'extra',"param extraargs \"$type\"");
            }
            $params = trim(implode("\n",(array)$params));
            $Send = array_merge($Send,array($params));
        }
        switch ((int)$option->get('id')) {
        case 1:
            $Send = array_merge($Send,array("$this->bootexittype || goto MENU"));
            break;
        case 2:
            $Send = array_merge($Send,array("$this->memdisk iso raw",$this->memtest,'boot || goto MENU'));
            break;
        case 11:
            $Send = array_merge($Send,array("chain -ar $this->booturl/ipxe/advanced.php || goto MENU"));
            break;
        }
        if (!$params && $type) $Send = array_merge($Send,array("$this->kernel $this->loglevel $type",$this->initrd,'boot || goto MENU'));
        return $Send;
    }
    public function printDefault() {
        if ($this->Host->isValid() && $this->getSetting('FOG_NO_MENU')) $this->noMenu();
        if ($this->hiddenmenu) {
            $this->chainBoot(true);
            return;
        }
        $Menus = $this->getClass('PXEMenuOptionsManager')->find('','','id');
        $Send['head'] = array(
            'cpuid --ext 29 && set arch x86_64 || set arch i386',
            'goto get_console',
            ':console_set',
            'colour --rgb 0x00567a 1 ||',
            'colour --rgb 0x00567a 2 ||',
            'colour --rgb 0x00567a 4 ||',
            'cpair --foreground 7 --background 2 2 ||',
            'goto MENU',
            ':alt_console',
            'cpair --background 0 1 ||',
            'cpair --background 1 2 ||',
            'goto MENU',
            ':get_console',
            "console --picture $this->booturl/ipxe/bg.png --left 100 --right 80 && goto console_set || goto alt_console",
        );
        $showDebug = $_REQUEST['debug'] === 1;
        $hostRegColor = $this->Host->isValid() ? '0x00567a' : '0xff0000';
        $reg_string = 'NOT registered!';
        if ($this->Host->isValid()) $reg_string = $this->Host->get('pending') ? 'pending approval!' : "registered as {$this->Host->get(name)}!";
        $Send['menustart'] = array(
            ':MENU',
            'menu',
            "colour --rgb $hostRegColor 0 ||",
            'cpair --foreground 1 1 ||',
            'cpair --foreground 0 3 ||',
            'cpair --foreground 4 4 ||',
            "item --gap Host is $reg_string",
            'item --gap -- -------------------------------------',
        );
        $Advanced = $this->getSetting('FOG_PXE_ADVANCED');
        $AdvLogin = $this->getSetting('FOG_ADVANCED_MENU_LOGIN');
        $RegArrayOfStuff = array(($this->Host->isValid() ? ($this->Host->get('pending') ? 6 : 1) : 0),2);
        if (!$this->getSetting('FOG_REGISTRATION_ENABLED')) $RegArrayOfStuff = array_diff($RegArrayOfStuff,array(0));
        if ($showDebug) array_push($RegArrayOfStuff,3);
        if ($Advanced) array_push($RegArrayOfStuff,($AdvLogin ? 5 : 4));
        foreach ($this->getClass('PXEMenuOptionsManager')->find(array('regMenu'=>$RegArrayOfStuff),'','id') AS &$Menu) {
            $Send["item-{$Menu->get(name)}"] = $this->menuItem($Menu,trim($Menu->get('description')));
            unset($Menu);
        }
        $Send['default'] = array($this->defaultChoice);
        foreach ($this->getClass('PXEMenuOptionsManager')->find(array('regMenu'=>$RegArrayOfStuff),'','id') AS &$Menu) {
            $Send["choice-{$Menu->get(name)}"] = $this->menuOpt($Menu,trim($Menu->get('args')));
            unset($Menu);
        }
        $Send['bootme'] = array(
            ':bootme',
            "chain -ar $this->booturl/ipxe/boot.php##params ||",
            'goto MENU',
            'autoboot',
        );
        $this->parseMe($Send);
    }
}
