<?php
class ServerInfo extends FOGPage {
    public $node = 'hwinfo';
    public function __construct($name = '') {
        $this->name = 'Hardware Information';
        parent::__construct($this->name);
        $this->obj = self::getClass('StorageNode',$_REQUEST['id']);
        $this->menu = array(
            "?node=storage&sub=edit&id={$_REQUEST['id']}" => _('Edit Node')
        );
        $this->notes = array(
            "{$this->foglang['Storage']} {$this->foglang['Node']}" => $this->obj->get('name'),
            _('Hostname / IP') => $this->obj->get('ip'),
            $this->foglang['ImagePath'] => $this->obj->get('path'),
            $this->foglang['FTPPath'] => $this->obj->get('ftppath')
        );
    }
    public function index() {
        $this->home();
    }
    public function home() {
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (!$this->obj->isValid()) {
            printf('<p>%s</p>',_('Invalid Server Information!'));
            return;
        }
        $curroot = trim(trim($this->obj->get('webroot'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $URL = sprintf('http://%s%sstatus/hw.php',$this->obj->get('ip'),$webroot);
        $ret = $this->FOGURLRequests->process($URL);
        $ret = trim($ret[0]);
        if (empty($ret) || !$ret) {
            printf('<p>%s</p>',_('Unable to pull server information!'));
            return;
        }
        $section = 0;
        $arGeneral = array();
        $arFS = array();
        $arNIC = array();
        array_map(function(&$line) use (&$section,&$arGeneral,&$arFS,&$arNIC) {
            $line = trim($line);
            switch ($line) {
            case '@@start':
            case '@@end':
                break;
            case '@@general':
                $section = 0;
                break;
            case '@@fs':
                $section = 1;
                break;
            case '@@nic':
                $section = 2;
                break;
            default:
                switch ($section) {
                case 0:
                    $arGeneral[] = $line;
                    break;
                case 1:
                    $arFS[] = $line;
                    break;
                case 2:
                    $arNIC[] = $line;
                    break;
                }
                break;
            }
            unset($line);
        },(array)explode("\n",$ret));
        array_map(function(&$nic) use (&$NICTransSized,&$NICRecSized,&$NICErrInfo,&$NICDropInfo,&$NICTrans,&$NICRec,&$NICErr,&$NICDro) {
            $nicparts = explode("$$", $nic);
            if (count($nicparts) == 5) {
                $NICTransSized[] = $this->formatByteSize($nicparts[2]);
                $NICRecSized[] = $this->formatByteSize($nicparts[1]);
                $NICErrInfo[] = $nicparts[3];
                $NICDropInfo[] = $nicparts[4];
                $NICTrans[] = sprintf('%s %s',$nicparts[0],_('TX'));
                $NICRec[] = sprintf('%s %s',$nicparts[0],_('RX'));
                $NICErr[] =	sprintf('%s %s',$nicparts[0],_('Errors'));
                $NICDro[] = sprintf('%s %s',$nicparts[0],_('Dropped'));
            }
            unset($nic);
        },(array)$arNIC);
        if (count($arGeneral) < 1) {
            printf(_('Unable to find basic information'));
            return;
        }
        $fields = array(
            sprintf('<b>%s</b>',_('General Information')) => '&nbsp;',
            _('Storage Node') => $this->obj->get('name'),
            _('IP') => $this->FOGCore->resolveHostname($this->obj->get('ip')),
            _('Kernel') => $arGeneral[0],
            _('Hostname') => $arGeneral[1],
            _('Uptime') => $arGeneral[2],
            _('CPU Type') => $arGeneral[3],
            _('CPU Count') => $arGeneral[4],
            _('CPU Model') => $arGeneral[5],
            _('CPU Speed') => $arGeneral[6],
            _('CPU Cache') => $arGeneral[7],
            _('Total Memory') => $arGeneral[8],
            _('Used Memory') => $arGeneral[9],
            _('Free Memory') => $arGeneral[10],
            sprintf('<b>%s</b>',_('File System Information')) => '&nbsp;',
            _('Total Disk Space') => $arFS[0],
            _('Used Disk Space') => $arFS[1],
            sprintf('<b>%s</b>',_('Network Information')) => '&nbsp;',
        );
        array_walk($NICTrans,function(&$txtran,&$index) use (&$NICTransSized,&$NICRecSized,&$NICErrInfo,&$NICDropInfo,&$NICTrans,&$NICRec,&$NICErr,&$NICDro,&$fields) {
            $ethName = explode(' ',$txtran);
            $fields[sprintf('<b>%s %s</b>',$ethName[0],_('Information'))] = '&nbsp;';
            $fields[$NICTrans[$index]] = $NICTransSized[$index];
            $fields[$NICRec[$index]] = $NICRecSized[$index];
            $fields[$NICErr[$index]] = $NICErrInfo[$index];
            $fields[$NICDro[$index]] = $NICDropInfo[$index];
            unset($txtran,$index);
        });
        unset($arGeneral,$arNIC,$arFS,$NICTransSized,$NICRecSized,$NICErrInfo,$NICDropInfo,$NICTrans,$NICRec,$NICErr,$NICDro);
        $this->data = array();
        array_walk($fields,function(&$input,&$field) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        });
        $this->HookManager->processEvent('SERVER_INFO_DISP',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
}
