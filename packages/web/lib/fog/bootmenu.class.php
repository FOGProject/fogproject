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
        $webserver = self::getSetting('FOG_WEB_HOST');
        $curroot = trim(self::getSetting('FOG_WEB_ROOT'),'/');
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $this->web = sprintf('%s%s',$webserver,$webroot);
        $Send['booturl'] = array(
            '#!ipxe',
            "set fog-ip $webserver",
            sprintf('set fog-webroot %s',basename(self::getSetting('FOG_WEB_ROOT'))),
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
        $StorageNode = self::getClass('StorageNode',@min(self::getSubObjectIDs('StorageNode',array('isEnabled'=>1,'isMaster'=>1))));
        $serviceNames = array(
            'FOG_EFI_BOOT_EXIT_TYPE',
            'FOG_KERNEL_ARGS',
            'FOG_KERNEL_DEBUG',
            'FOG_KERNEL_LOGLEVEL',
            'FOG_KERNEL_RAMDISK_SIZE',
            'FOG_KEYMAP',
            'FOG_KEY_SEQUENCE',
            'FOG_MEMTEST_KERNEL',
            'FOG_PLUGIN_CAPONE_DMI',
            'FOG_PLUGIN_CAPONE_SHUTDOWN',
            'FOG_PXE_BOOT_IMAGE',
            'FOG_PXE_BOOT_IMAGE_32',
            'FOG_PXE_HIDDENMENU_TIMEOUT',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_PXE_MENU_TIMEOUT',
            'FOG_TFTP_PXE_KERNEL',
            'FOG_TFTP_PXE_KERNEL_32',
        );
        list($exit,$kernelArgs,$kernelDebug,$kernelLogLevel,$kernelRamDisk,$keyMap,$keySequence,$memtest,$caponeDMI,$caponeShutdown,$imagefile,$init_32,$hiddenTimeout,$hiddenmenu,$menuTimeout,$bzImage,$bzImage32) = self::getSubObjectIDs('Service',array('name'=>$serviceNames),'value',false,'AND','name',false,'');
        if (!in_array('capone',(array)$_SESSION['PluginsInstalled'])) {
            $serviceNames = array(
                'FOG_EFI_BOOT_EXIT_TYPE',
                'FOG_KERNEL_ARGS',
                'FOG_KERNEL_DEBUG',
                'FOG_KERNEL_LOGLEVEL',
                'FOG_KERNEL_RAMDISK_SIZE',
                'FOG_KEYMAP',
                'FOG_KEY_SEQUENCE',
                'FOG_MEMTEST_KERNEL',
                'FOG_PLUGIN_CAPONE_DMI',
                'FOG_PLUGIN_CAPONE_SHUTDOWN',
                'FOG_PXE_BOOT_IMAGE',
                'FOG_PXE_BOOT_IMAGE_32',
                'FOG_PXE_HIDDENMENU_TIMEOUT',
                'FOG_PXE_MENU_HIDDEN',
                'FOG_PXE_MENU_TIMEOUT',
                'FOG_TFTP_PXE_KERNEL',
                'FOG_TFTP_PXE_KERNEL_32',
            );
            list($exit,$kernelArgs,$kernelDebug,$kernelLogLevel,$kernelRamDisk,$keyMap,$keySequence,$memtest,$imagefile,$init_32,$hiddenTimeout,$hiddenmenu,$menuTimeout,$bzImage,$bzImage32) = self::getSubObjectIDs('Service',array('name'=>$serviceNames),'value',false,'AND','name',false,'');
        }
        $memdisk = 'memdisk';
        $loglevel = (int)$kernelLogLevel;
        $ramsize = (int)$kernelRamDisk;
        $timeout = ($hiddenmenu > 0 && !$_REQUEST['menuAccess'] ? (int)$hiddenTimeout : (int)$menuTimeout) * 1000;
        $keySequence = ($hiddenmenu > 0 && !$_REQUEST['menuAccess'] ? $keySequence : '');
        if ($_REQUEST['arch'] != 'x86_64') {
            $bzImage = $bzImage32;
            $imagefile = $init_32;
        }
        $kernel = $bzImage;
        if ($this->Host->get('kernel')) $bzImage = trim($this->Host->get('kernel'));
        if ($this->Host->get('init')) $imagefile = trim($this->Host->get('init'));
        $StorageGroup = $StorageNode->getStorageGroup();
        $exit = trim($this->Host->get($host_field_test) ? $this->Host->get($host_field_test) : self::getSetting($global_field_test));
        if (!$exit || !in_array($exit,array_keys(self::$exitTypes))) $exit = 'sanboot';
        $initrd = $imagefile;
        if ($this->Host->isValid()) {
            self::$HookManager->processEvent('BOOT_ITEM_NEW_SETTINGS',array(
                'Host' => &$this->Host,
                'StorageGroup' => &$StorageGroup,
                'StorageNode' => &$StorageNode,
                'webserver' => &$webserver,
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
        $this->hiddenmenu = ($hiddenmenu && !$_REQUEST['menuAccess']);
        $this->bootexittype = self::$exitTypes[$exit];
        $this->loglevel = "loglevel=$loglevel";
        $this->KS = self::getClass('KeySequence',$keySequence);
        $this->booturl = "http://{$webserver}{$webroot}service";
        $this->memdisk = "kernel $memdisk";
        $this->memtest = "initrd $memtest";
        $this->kernel = sprintf('kernel %s %s initrd=%s root=/dev/ram0 rw ramdisk_size=%s keymap=%s web=%s consoleblank=0%s rootfstype=ext4%s%s',
            $bzImage,
            $this->loglevel,
            basename($initrd),
            $ramsize,
            $keymap,
            $this->web,
            $kernelDebug ? ' debug' : ' ',
            $kernelArgs ? sprintf(' %s',$kernelArgs) : '',
            $this->Host->isValid() && $this->Host->get('kernelArgs') ? sprintf(' %s',$this->Host->get('kernelArgs')) : ''
        );
        $this->initrd = "imgfetch $imagefile";
        self::caponeMenu(
            $this->storage,
            $this->path,
            $this->shutdown,
            self::getSetting('FOG_PLUGIN_CAPONE_DMI'),
            self::getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN'),
            $StorageNode,
            self::$FOGCore
        );
        $defaultMenu = self::getClass('PXEMenuOptions',@max(self::getSubObjectIDs('PXEMenuOptions',array('default'=>1))));
        $menuname = $defaultMenu->isValid() ? trim($defaultMenu->get('name')) : 'fog.local';
        unset($defaultMenu);
        self::getDefaultMenu($this->timeout,$menuname,$this->defaultChoice);
        $this->ipxeLog();
        if ($this->Host->isValid() && $this->Host->get('task')->isValid()) {
            $this->getTasking();
            exit;
        }
        $_REQUEST['extraargs'] = trim($_REQUEST['extraargs']);
        if ($_REQUEST['extraargs']) $_SESSION['extraargs'] = $_REQUEST['extraargs'];
        if (isset($_REQUEST['username'])) $this->verifyCreds();
        else if ($_REQUEST['qihost']) $this->setTasking($_REQUEST['imageID']);
        else if ($_REQUEST['delconf']) $this->delHost();
        else if ($_REQUEST['key']) $this->keyset();
        else if ($_REQUEST['sessname']) $this->sesscheck();
        else if ($_REQUEST['aprvconf']) $this->approveHost();
        else if (!$this->Host->isValid()) $this->printDefault();
        else $this->getTasking();
    }
    private static function caponeMenu(&$storage, &$path, &$shutdown,$DMISet,$Shutdown,&$StorageNode,&$FOGCore) {
        if (!in_array('capone',(array)$_SESSION['PluginsInstalled'])) return;
        if (!$DMISet) return;
        $storage = $StorageNode->get('ip');
        $path = $StorageNode->get('path');
        $shutdown = $Shutdown;
        $args = trim("mode=capone shutdown=$shutdown storage=$storage:$path");
        $CaponeMenu = self::getClass('PXEMenuOptions',FOGCore::getSubObjectIDs('PXEMenuOptions',array('name'=>'fog.capone')));
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
            'file' => sprintf("'%s'",trim(basename($_REQUEST['filename']))),
            'product' => sprintf("'%s'",trim($_REQUEST['product'])),
            'manufacturer' => sprintf("'%s'",trim($_REQUEST['manufacturer'])),
            'mac' => $this->Host->isValid() ? $this->Host->get('mac')->__toString() : '',
        );
        self::getClass('iPXE',@max(self::getSubObjectIDs('iPXE',$findWhere)))
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
        $kernelArgs = array();
        $kernelArgs = array_map(function(&$arg) use (&$kernelArgs) {
            if (empty($arg)) return;
            if (is_array($arg)) {
                if (!(isset($arg['value']) && $arg['value'])) return;
                if (!(isset($arg['active']) && $arg['active'])) return;
                return preg_replace('#mode=debug|mode=onlydebug#i','isdebug=yes',$arg['value']);
            }
            return preg_replace('#mode=debug|mode=onlydebug#i','isdebug=yes',$arg);
        },(array)$kernelArgsArray);
        $kernelArgs = array_values(array_filter(array_unique($kernelArgs)));
        $kernelArgs = implode(' ',(array)$kernelArgs);
        $Send['task'][($this->Host->isValid() ? $this->Host->get('task')->get('typeID') : 1)] = array(
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
        $MulticastSession = self::getClass('MulticastSessions',@max(self::getSubObjectIDs('MulticastSessions',$findWhere)));
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
        $storage = escapeshellcmd(sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),''));
        $storageip = self::$FOGCore->resolveHostname($StorageNode->get('ip'));
        $img = escapeshellcmd($Image->get('path'));
        $imgFormat = $Image->get('format');
        $imgType = $Image->getImageType()->get('type');
        $imgPartitionType = $Image->getPartitionType();
        $imgid = $Image->get('id');
        $chkdsk = self::getSetting('FOG_DISABLE_CHKDSK') == 1 ? 0 : 1;
        $ftp = $StorageNode->get('ip');
        $port = ($mc ? $mc->get('port') : null);
        $kernelArgsArray = array(
            "mac=$mac",
            "ftp=$ftp",
            "storage=$storage",
            "storageip=$storageip",
            "osid=$osid",
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
                'value' => vsprintf('mining=1 miningcores=%s miningpath=%s',self::getSubObjectIDs('Service',array('name'=>array('FOG_MINING_MAX_CORES','FOG_MINING_PACKAGE_PATH')),'value')),
                'active' => self::getSetting('FOG_MINING_ENABLE'),
            ),
            array(
                'value' => 'debug',
                'active' => self::getSetting('FOG_KERNEL_DEBUG'),
            ),
            array(
                'value' => 'fdrive='.self::getSetting('FOG_NONREG_DEVICE'),
                'active' => self::getSetting('FOG_NONREG_DEVICE'),
            ),
            $TaskType->get('kernelArgs'),
            self::getSetting('FOG_KERNEL_ARGS'),
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
        $Images = self::getClass('ImageManager')->find(array('isEnabled'=>1));
        if (!$Images) {
            $Send['NoImages'] = array(
                'echo No Images on server found',
                'sleep 3',
            );
            $this->parseMe($Send);
            $this->chainBoot();
        } else {
            array_map(function(&$Image) use (&$Send,&$defItem) {
                if (!$Image->isValid()) return;
                array_push($Send['ImageListing'],sprintf('item %s %s',$Image->get('path'),$Image->get('name')));
                if (!$this->Host->isValid()) return;
                if (!$this->Host->getImage()->isValid()) return;
                if ((int)$this->Host->getImage()->get('id') === (int)$Image->get('id')) $defItem = sprintf('choose --default %s --timeout %d target && goto ${target}',$Image->get('path'),(int)$this->timeout);
                unset($Image);
            },(array)$Images);
            array_push($Send['ImageListing'],'item return Return to menu');
            array_push($Send['ImageListing'],$defItem);
            array_map(function(&$Image) use (&$Send) {
                if (!$Image->isValid()) return;
                $Send[sprintf('pathofimage%s',$Image->get('name'))] = array(
                    sprintf(':%s',$Image->get('path')),
                    sprintf('set imageID %d',(int)$Image->get('id')),
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
                unset($Image);
            },(array)$Images);
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
        $MultiSess = self::getClass('MulticastSessions',$msid);
        if (!$MultiSess->isValid()) return;
        if ($MultiSess->getImage()->get('id') != $this->Host->getImage()->get('id')) $this->Host->set('imageID',$MultiSess->getImage()->get('id'));
        $shutdown = stripos('shutdown=1',$_SESSION['extraargs']);
        $isdebug = preg_match('#isdebug=yes|mode=debug|mode=onlydebug#i',$_SESSION['extraargs']);
        $this->Host->isValid() ? $this->Host->createImagePackage(8,$MultiSess->get('name'),$shutdown,$isdebug,-1,false,$_REQUEST['username'],'',true,true) : $this->falseTasking($MultiSess);
        $this->Host->isValid() ? $this->chainBoot(false,true) : '';
    }
    public function keyset() {
        if (!$this->Host->isValid()) return;
        $this->Host->set('productKey',$this->encryptpw($_REQUEST['key']));
        if (!$this->Host->save()) return;
        $Send['keychangesuccess'] = array(
            'echo Successfully changed key',
            'sleep 3',
        );
        $this->parseMe($Send);
        $this->chainBoot();
    }
    private function parseMe($Send) {
        self::$HookManager->processEvent('IPXE_EDIT',array('ipxe' => &$Send,'Host' => &$this->Host,'kernel' => &$this->kernel,'initrd' => &$this->initrd,'booturl' => &$this->booturl, 'memdisk' => &$this->memdisk,'memtest' => &$this->memtest, 'web' => &$this->web, 'defaultChoice' => &$this->defaultChoice, 'bootexittype' => &$this->bootexittype,'storage' => &$this->storage,'shutdown' => &$this->shutdown,'path' => &$this->path,'timeout' => &$this->timeout,'KS' => $this->ks));
        array_walk_recursive($Send,function(&$val,&$key) {
            printf('%s%s',implode("\n",(array)$val),"\n");
            unset($val,$key);
        });
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
        if (self::getSetting('FOG_NO_MENU')) $this->noMenu();
        if (self::$FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password'])->isValid()) {
            if (self::getSetting('FOG_ADVANCED_MENU_LOGIN') && $_REQUEST['advLog']) $this->advLogin();
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
        if (!$imgID) {
            $this->printImageList();
            return;
        }
        if (!$this->Host->isValid()) {
            $this->falseTasking('',self::getClass('Image',$imgID));
            return;
        }
        if ($this->Host->getImage()->get('id') != $imgID) $this->Host->set('imageID',$imgID);
        if (!$this->Host->getImage()->isValid()) return;
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
                $MulticastSessionAssoc = current(self::getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
                $MulticastSession = new MulticastSessions($MulticastSessionAssoc->get('msID'));
                if ($MulticastSession && $MulticastSession->isValid()) $this->Host->set('imageID',$MulticastSession->get('image'));
            }
            if (in_array($TaskType->get('id'),$imagingTasks)) {
                $Image = $Task->getImage();
                $StorageGroup = $Image->getStorageGroup();
                $StorageNode = $StorageGroup->getOptimalStorageNode($Image->get('id'));
                self::$HookManager->processEvent('BOOT_TASK_NEW_SETTINGS',array('Host' => &$this->Host,'StorageNode' => &$StorageNode,'StorageGroup' => &$StorageGroup));
                if ($TaskType->isUpload() || $TaskType->isMulticast()) $StorageNode = $StorageGroup->getMasterStorageNode();
                $osid = $Image->get('osID');
                $storage = escapeshellcmd(in_array($TaskType->get('id'),$imagingTasks) ? sprintf('%s:/%s/%s',trim($StorageNode->get('ip')),trim($StorageNode->get('path'),'/'),($TaskType->isUpload() ? 'dev/' : '')) : null);
            }
            if ($this->Host && $this->Host->isValid()) $mac = $this->Host->get('mac');
            else $mac = $_REQUEST['mac'];
            $clamav = in_array($TaskType->get('id'),array(21,22)) ? sprintf('%s:%s',trim($StorageNode->get('ip')),'/opt/fog/clamav') : null;
            $storageip = in_array($TaskType->get('id'),$imagingTasks) ? self::$FOGCore->resolveHostname($StorageNode->get('ip')) : null;
            $img = escapeshellcmd(in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('path') : null);
            $imgFormat = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('format') : null;
            $imgType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImageType()->get('type') : null;
            $imgPartitionType = in_array($TaskType->get('id'),$imagingTasks) ? $Image->getImagePartitionType()->get('type') : null;
            $imgid = in_array($TaskType->get('id'),$imagingTasks) ? $Image->get('id') : null;
            $ftp = $StorageNode instanceof StorageNode && $StorageNode->isValid() ? $StorageNode->get('ip') : self::getSetting(FOG_TFTP_HOST);
            $chkdsk = self::getSetting(FOG_DISABLE_CHKDSK) == 1 ? 0 : 1;
            $PIGZ_COMP = in_array($TaskType->get(id),$imagingTasks) ? ($Image->get(compress) > -1 && is_numeric($Image->get(compress)) ? $Image->get(compress) : self::getSetting(FOG_PIGZ_COMP)) : self::getSetting(FOG_PIGZ_COMP);
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
                "osid=$osid",
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
                    'active' => self::getSetting('FOG_CHANGE_HOSTNAME_EARLY') && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
                ),
                array(
                    'value' => 'pct='.(is_numeric(self::getSetting('FOG_UPLOADRESIZEPCT')) && self::getSetting('FOG_UPLOADRESIZEPCT') >= 5 && self::getSetting('FOG_UPLOADRESIZEPCT') < 100 ? self::getSetting('FOG_UPLOADRESIZEPCT') : '5'),
                    'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
                ),
                array(
                    'value' => 'ignorepg='.(self::getSetting('FOG_UPLOADIGNOREPAGEHIBER') ? 1 : 0),
                    'active' => $TaskType->isUpload() && in_array($TaskType->get('id'),$imagingTasks) ? true : false,
                ),
                array(
                    'value' => 'port='.($TaskType->isMulticast() ? $MulticastSession->get('port') : null),
                    'active' => $TaskType->isMulticast(),
                ),
                array(
                    'value' => vsprintf('mining=1 miningcores=%s miningpath=%s',self::getSubObjectIDs('Service',array('name'=>array('FOG_MINING_MAX_CORES','FOG_MINING_PACKAGE_PATH')),'value')),
                    'active' => self::getSetting('FOG_MINING_ENABLE'),
                ),
                array(
                    'value' => 'winuser='.$Task->get('passreset'),
                    'active' => $TaskType->get('id') == '11',
                ),
                array(
                    'value' => 'isdebug=yes',
                    'active' => $Task->get('isDebug'),
                ),
                array(
                    'value' => 'debug',
                    'active' => self::getSetting('FOG_KERNEL_DEBUG'),
                ),
                array(
                    'value' => 'seconds='.self::getSetting('FOG_WIPE_TIMEOUT'),
                    'active' => in_array($TaskType->get('id'),range(18,20)),
                ),
                $TaskType->get('kernelArgs'),
                self::getSetting('FOG_KERNEL_ARGS'),
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
        $name = preg_replace('#[\s]+#','_',$option->get('name'));
        return array("item $name $desc");
    }
    private function menuOpt($option,$type) {
        $name = preg_replace('#[\s]+#','_',$option->get('name'));
        $name = trim(":$name");
        $type = trim($type);
        $Send = array($name);
        if (trim($option->get('params'))) {
            $params = explode("\n",$option->get('params'));
            $params = array_map('trim',(array)$params);
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
        if ($this->Host->isValid() && self::getSetting('FOG_NO_MENU')) $this->noMenu();
        if ($this->hiddenmenu) {
            $this->chainBoot(true);
            return;
        }
        $Menus = self::getClass('PXEMenuOptionsManager')->find('','','id');
        $ipxeGrabs = array(
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_IPXE_BG_FILE',
            'FOG_IPXE_HOST_CPAIRS',
            'FOG_IPXE_INVALID_HOST_COLOURS',
            'FOG_IPXE_MAIN_COLOURS',
            'FOG_IPXE_MAIN_CPAIRS',
            'FOG_IPXE_MAIN_FALLBACK_CPAIRS',
            'FOG_IPXE_VALID_HOST_COLOURS',
            'FOG_PXE_ADVANCED',
            'FOG_REGISTRATION_ENABLED',
        );
        list($AdvLogin,$bgfile,$hostCpairs,$hostInvalid,$mainColors,$mainCpairs,$mainFallback,$hostValid,$Advanced,$regEnabled) = self::getSubObjectIDs('Service',array('name'=>$ipxeGrabs),'value',false,'AND','name',false,'');
        $Send['head'] = array_merge(
            array(
                'cpuid --ext 29 && set arch x86_64 || set arch i386',
                'goto get_console',
                ':console_set',
            ),
            explode("\n",$mainColors),
            explode("\n",$mainCpairs),
            array(
                'goto MENU',
                ':alt_console'
            ),
            explode("\n",$mainFallback),
            array(
                'goto MENU',
                ':get_console',
                "console --picture $this->booturl/ipxe/$bgfile --left 100 --right 80 && goto console_set || goto alt_console",
            )
        );
        $showDebug = $_REQUEST['debug'] === 1;
        $hostRegColor = $this->Host->isValid() ? $hostValid : $hostInvalid;
        $reg_string = 'NOT registered!';
        if ($this->Host->isValid()) $reg_string = $this->Host->get('pending') ? 'pending approval!' : "registered as {$this->Host->get(name)}!";
        $Send['menustart'] = array_merge(
            array(
                ':MENU',
                'menu',
                $hostRegColor,
            ),
            explode("\n",$hostCpairs),
            array(
                "item --gap Host is $reg_string",
                'item --gap -- -------------------------------------',
            )
        );
        $RegArrayOfStuff = array(($this->Host->isValid() ? ($this->Host->get('pending') ? 6 : 1) : 0),2);
        if (!$regEnabled) $RegArrayOfStuff = array_diff($RegArrayOfStuff,array(0));
        if ($showDebug) array_push($RegArrayOfStuff,3);
        if ($Advanced) array_push($RegArrayOfStuff,($AdvLogin ? 5 : 4));
        $Menus = (array)self::getClass('PXEMenuOptionsManager')->find(array('regMenu'=>$RegArrayOfStuff),'','id');
        array_map(function(&$Menu) use (&$Send) {
            $Send["item-{$Menu->get(name)}"] = $this->menuItem($Menu,trim($Menu->get('description')));
            unset($Menu);
        },(array)$Menus);
        $Send['default'] = array($this->defaultChoice);
        array_map(function(&$Menu) use (&$Send) {
            $Send["choice-{$Menu->get(name)}"] = $this->menuOpt($Menu,trim($Menu->get('args')));
            unset($Menu);
        },(array)$Menus);
        $Send['bootme'] = array(
            ':bootme',
            "chain -ar $this->booturl/ipxe/boot.php##params ||",
            'goto MENU',
            'autoboot',
        );
        $this->parseMe($Send);
    }
}
