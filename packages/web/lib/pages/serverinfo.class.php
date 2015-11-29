<?php
class ServerInfo extends FOGPage {
    public $node = 'hwinfo';
    public function __construct($name = '') {
        $this->name = 'Hardware Information';
        parent::__construct($this->name);
        $this->obj = $this->getClass('StorageNode',$_REQUEST['id']);
        $this->menu = array(
            "?node=storage&sub=edit&id={$_REQUEST['id']}" => _('Edit Node'),
        );
        $this->notes = array(
            "{$this->foglang['Storage']} {$this->foglang['Node']}" => $this->obj->get('name'),
            _('Hostname / IP') => $this->obj->get('ip'),
            $this->foglang['ImagePath'] => $this->obj->get('path'),
            $this->foglang['FTPPath'] => $this->obj->get('ftppath'),
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
        if ($this->obj) {
            $curroot = trim(trim($this->obj->get(webroot),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $URL = sprintf('http://%s%sstatus/hw.php',$this->obj->get('ip'),$webroot);
            if ($ret = $this->FOGURLRequests->process($URL)) {
                $section = 0;
                $arGeneral = array();
                $arFS = array();
                $arNIC = array();
                foreach((array)explode("\n",$ret[0]) AS $i => &$line) {
                    $line = trim( $line );
                    switch ($line) {
                    case '@@start':
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
                    case '@@end':
                        $section = 3;
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
                }
                unset($line);
                for($i=0;$i<count($arNIC);$i++) {
                    $arNicParts = explode("$$", $arNIC[$i]);
                    if (count($arNicParts) == 5) {
                        $NICTransSized[] = $this->formatByteSize($arNicParts[2]);
                        $NICRecSized[] = $this->formatByteSize($arNicParts[1]);
                        $NICErrInfo[] = $arNicParts[3];
                        $NICDropInfo[] = $arNicParts[4];
                        $NICTrans[] = sprintf('%s %s',$arNicParts[0],_('TX'));
                        $NICRec[] = sprintf('%s %s',$arNicParts[0],_('RX'));
                        $NICErr[] =	sprintf('%s %s',$arNicParts[0],_('Errors'));
                        $NICDro[] = sprintf('%s %s',$arNicParts[0],_('Dropped'));
                    }
                }
                if(count($arGeneral)>=1) {
                    $fields = array(
                        sprintf('<b>%s</b>',_('General Information')) => '&nbsp;',
                        _('Storage Node') => $this->obj->get(name),
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
                    $i = 0;
                    foreach((array)$NICTrans AS $i => &$txtran) {
                        $ethName = explode(' ',$NICTrans[$i]);
                        $fields[sprintf('<b>%s %s</b>',$ethName[0],_('Information'))] = '&nbsp;';
                        $fields[$NICTrans[$i]] = $NICTransSized[$i];
                        $fields[$NICRec[$i]] = $NICRecSized[$i];
                        $fields[$NICErr[$i]] = $NICErrInfo[$i];
                        $fields[$NICDro[$i]] = $NICDropInfo[$i];
                        $i++;
                    }
                    unset($txtran);
                }
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        'field'=>$field,
                        'input'=>$input,
                    );
                }
                $this->HookManager->processEvent('SERVER_INFO_DISP',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
                $this->render();
            } else printf('<p>%s</p>',_('Unable to pull server information!'));
        } else printf('<p>%s</p>',_('Invalid Server Information!'));
    }
}
