<?php
class PrinterManagementPage extends FOGPage {
    public $node = 'printer';
    private $config;
    public function __construct($name = '') {
        $this->name = 'Printer Management';
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->config = stripos($this->obj->get('config'),'local') !== false ? _('TCP/IP') : $this->obj->get('config');
            $this->subMenu = array(
                "$this->linkformat#$this->node-gen" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Printer'] => $this->obj->get('name'),
                self::$foglang['Type'] => $this->config,
            );
        }
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            'Printer Name',
            'Printer Type',
            'Model',
            'Port',
            'File',
            'IP',
            'Config File',
        );
        $this->templates = array(
            '<span class="icon fa fa-question hand" title="${desc}"></span>',
            '<input type="checkbox" name="printer[]" value="${id}" class="toggle-action" />',
            '<a href="?node=printer&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${config}',
            '${model}',
            '${port}',
            '${file}',
            '${ip}',
            '${configFile}',
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array('class'=>'filter-false'),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        self::$returnData = function(&$Printer) {
            if (!$Printer->isValid()) return;
            $config = stripos($Printer->get('config'),'local') !== false ? _('TCP/IP') : $Printer->get('config');
            $this->data[] = array(
                'id'=>$Printer->get('id'),
                'name'=>$Printer->get('name'),
                'config'=>$config,
                'model'=>$Printer->get('model'),
                'port'=>$Printer->get('port'),
                'file'=>$Printer->get('file'),
                'ip'=>$Printer->get('ip'),
                'configFile'=>$Printer->get('configFile'),
                'desc'=>$Printer->get('description'),
            );
            unset($Printer);
        };
    }
    public function getPrinterInfo() {
        die(json_encode(array(
            'file'=>$this->obj->get('file'),
            'port'=>$this->obj->get('port'),
            'model'=>$this->obj->get('model'),
            'ip'=>$this->obj->get('ip'),
            'config'=>strtolower($this->obj->get('config')),
            'configFile'=>$this->obj->get('configFile'),
        )));
    }
    public function index() {
        $this->title = _('All printers');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['PrinterCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('PRINTER_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('PRINTER_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = 'New Printer';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (!isset($_REQUEST['printertype']) || empty($_REQUEST['printertype'])) $_REQUEST['printertype'] = $this->obj->get('config');
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        ob_start();
        array_walk($printerTypes,function(&$long,&$short) {
            printf('<option value="%s"%s>%s</option>',$short,($_REQUEST['printertype'] === $short ? ' selected' : ''),$long);
            unset($short,$long);
        });
        $optionPrinter = ob_get_clean();
        echo '<div id="printer-copy">';
        $fields = array(
            sprintf('%s',_('Copy from existing printer'))=>sprintf('%s',self::getClass('PrinterManager')->buildSelectBox($this->obj->get('id'))),
            _('Printer Type')=>sprintf('<select name="printertype">%s</select>',$optionPrinter),
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        printf('<form method="post" action="%s">',$this->formAction);
        $fields = array(
            _('Printer Description')=>sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            sprintf('%s*',_('Printer Alias'))=>sprintf('<input class="printername-input" type="text" name="alias" value="%s"/>',$_REQUEST['alias']),
            '&nbsp;'=>'e.g. \\\\printerserver\\printername',
        );
        echo '<div id="network" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        unset($fields['&nbsp;']);
        $fields = array_merge($fields,array(
            sprintf('%s*',_('Printer Port'))=>sprintf('<input class="printerport-input" type="text" name="port" value="%s"/>',$_REQUEST['port']),
        ));
        echo '<div id="iprint" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description')=>sprintf('<textarea class="printerdescription-input" name="description" rows="8" cols="40">%s</textarea>',$_REQUEST['description']),
            sprintf('%s*',_('Printer Alias'))=>sprintf('<input class="printername-input" type="text" name="alias" value="%s"/>',$_REQUEST['alias']),
            sprintf('%s*',_('Printer INF File'))=>sprintf('<input class="printerinf-input" type="text" name="inf" value="%s"/>',$_REQUEST['inf']),
            sprintf('%s*',_('Printer IP'))=>sprintf('<input class="printerip-input" type="text" name="ip" value="%s"/>',$_REQUEST['ip']),
        );
        echo '<div id="cups" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array_merge($fields,array(
            _('Printer Port')=>sprintf('<input class="printerport-input" type="text" name="port" value="%s"/>',$_REQUEST['port']),
            _('Printer Model')=>sprintf('<input class="printermodel-input" type="text" name="model" value="%s"/>',$_REQUEST['model']),
            _('Printer Config File')=>sprintf('<input class="printerconfigFile-input" type="text" name="configFile" value="%s"/>',$_REQUEST['configFile']),
        ));
        echo '<div id="local" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array('&nbsp;'=>sprintf('<input class="c" name="addprinter" type="submit" value="%s"/>',_('Add Printer')));
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        $this->render();
        echo '</form>';
        unset($this->data);
        self::$HookManager->processEvent('PRINTER_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
    }
    public function add_post() {
        self::$HookManager->processEvent('PRINTER_ADD_POST');
        try {
            $_REQUEST['alias'] = trim($_REQUEST['alias']);
            $_REQUEST['port'] = trim($_REQUEST['port']);
            $_REQUEST['inf'] = trim($_REQUEST['inf']);
            $_REQUEST['model'] = trim($_REQUEST['model']);
            $_REQUEST['ip'] = trim($_REQUEST['ip']);
            $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
            $_REQUEST['description'] = trim($_REQUEST['description']);
            $_REQUEST['printertype'] = trim($_REQUEST['printertype']);
            if (empty($_REQUEST['alias'])) throw new Exception(_('All printer definitions must have an alias/name associated with it. Unable to create!'));
            if (self::getClass('PrinterManager')->exists($_REQUEST['alias'])) throw new Exception(_('Printer name already exists. Unable to create!'));
            switch ($_REQUEST['printertype']) {
            case 'local':
                if (empty($_REQUEST['port']) || empty($_REQUEST['inf']) || empty($_REQUEST['model']) || empty($_REQUEST['ip'])) throw new Exception(_('You must specify the port, model, ip, and file.  Unable to create!'));
                $printertype = 'Local';
                break;
            case 'cups':
                if (empty($_REQUEST['inf'])) throw new Exception(_('You must specify the file to use. Unable to create!'));
                $printertype = 'Cups';
                break;
            case 'iprint':
                if (empty($_REQUEST['port'])) throw new Exception(_('You must specify the port. Unable to create!'));
                $printertype = 'iPrint';
                break;
            case 'network':
                $printertype = 'Network';
                break;
            }
            $Printer = self::getClass('Printer')
                ->set('description',$_REQUEST['description'])
                ->set('name',$_REQUEST['alias'])
                ->set('config',$printertype)
                ->set('model',$_REQUEST['model'])
                ->set('port',$_REQUEST['port'])
                ->set('file',$_REQUEST['inf'])
                ->set('configFile',$_REQUEST['configFile'])
                ->set('ip',$_REQUEST['ip']);
            if (!$Printer->save()) throw new Exception(_('Printer create failed!'));
            self::$HookManager->processEvent('PRINTER_ADD_SUCCESS',array('Printer'=>&$Printer));

            die(json_encode(array('msg'=>_('Printer Added, you may create another!'))));
        } catch (Exception $e) {
            self::$HookManager->processEvent('PRINTER_ADD_FAIL',array('Printer'=>&$Printer));
            die(json_encode(array('error'=>$e->getMessage())));
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        echo '<div id="tab-container">';
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        echo '<!-- General --><div id="printer-gen">';
        if (!isset($_REQUEST['printertype']) || empty($_REQUEST['printertype'])) $_REQUEST['printertype'] = $this->obj->get('config');
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        ob_start();
        array_walk($printerTypes,function(&$long,&$short) {
            printf('<option value="%s"%s>%s</option>',$short,($_REQUEST['printertype'] === $short ? ' selected' : ''),$long);
            unset($short,$long);
        });
        $optionPrinter = ob_get_clean();
        echo '<div id="printer-copy">';
        $fields = array(
            sprintf('%s',_('Copy from existing printer'))=>sprintf('%s',self::getClass('PrinterManager')->buildSelectBox($this->obj->get('id'))),
            _('Printer Type')=>sprintf('<select name="printertype">%s</select>',$optionPrinter),
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        printf('<form method="post" action="%s&tab=printer-gen"><br/>',$this->formAction);
        $fields = array(
            _('Printer Description')=>sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            sprintf('%s*',_('Printer Alias'))=>sprintf('<input class="printername-input" type="text" name="alias" value="%s"/>',$this->obj->get('name')),
            '&nbsp;'=>'e.g. \\\\printerserver\\printername',
        );
        echo '<div id="network" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        unset($fields['&nbsp;']);
        $fields = array_merge($fields,array(
            sprintf('%s*',_('Printer Port'))=>sprintf('<input class="printerport-input" type="text" name="port" value="%s"/>',$this->obj->get('port')),
        ));
        echo '<div id="iprint" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description')=>sprintf('<textarea name="description" rows="8" cols="40">%s</textarea>',$this->obj->get('description')),
            sprintf('%s*',_('Printer Alias'))=>sprintf('<input class="printername-input" type="text" name="alias" value="%s"/>',$this->obj->get('name')),
            sprintf('%s*',_('Printer INF File'))=>sprintf('<input class="printerinf-input" type="text" name="inf" value="%s"/>',$this->obj->get('file')),
            sprintf('%s*',_('Printer IP'))=>sprintf('<input class="printerip-input" type="text" name="ip" value="%s"/>',$this->obj->get('ip')),
        );
        echo '<div id="cups" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array_merge($fields,array(
            _('Printer Port')=>sprintf('<input class="printerport-input" type="text" name="port" value="%s"/>',$this->obj->get('port')),
            _('Printer Model')=>sprintf('<input class="printermodel-input" type="text" name="model" value="%s"/>',$this->obj->get('model')),
            _('Printer Config File')=>sprintf('<input class="printerconfigFile-input" type="text" name="configFile" value="%s"/>',$this->obj->get('configFile')),
        ));
        echo '<div id="local" class="hidden">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array('&nbsp;'=>sprintf('<input class="c" name="updateprinter" type="submit" value="%s"/>',_('Update Printer')));
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        self::$HookManager->processEvent('PRINTER_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form></div></div>';
        unset($this->data);
    }
    public function edit_post() {
        self::$HookManager->processEvent('PRINTER_EDIT_POST',array('Printer'=>&$this->obj));
        try {
            switch ($_REQUEST['tab']) {
            case 'printer-type':
                $this->setMessage(sprintf('%s: %s',_('Printer type changed to'),$_REQUEST['printertype']));
                $this->redirect($this->formAction);
                break;
            case 'printer-gen':
                $_REQUEST['alias'] = trim($_REQUEST['alias']);
                $_REQUEST['port'] = trim($_REQUEST['port']);
                $_REQUEST['inf'] = trim($_REQUEST['inf']);
                $_REQUEST['model'] = trim($_REQUEST['model']);
                $_REQUEST['ip'] = trim($_REQUEST['ip']);
                $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
                $_REQUEST['description'] = trim($_REQUEST['description']);
                $_REQUEST['printertype'] = trim($_REQUEST['printertype']);
                if (empty($_REQUEST['alias'])) throw new Exception(_('All printer definitions must have an alias/name associated with it. Unable to update!'));
                switch ($_REQUEST['printertype']) {
                case 'local':
                    if (empty($_REQUEST['port']) || empty($_REQUEST['inf']) || empty($_REQUEST['model']) || empty($_REQUEST['ip'])) throw new Exception(_('You must specify the port, model, ip, and file.  Unable to update!'));
                    $printertype = 'Local';
                    break;
                case 'cups':
                    if (empty($_REQUEST['inf'])) throw new Exception(_('You must specify the file to use. Unable to update!'));
                    $printertype = 'Cups';
                    break;
                case 'iprint':
                    if (empty($_REQUEST['port'])) throw new Exception(_('You must specify the port. Unable to update!'));
                    $printertype = 'iPrint';
                    break;
                case 'network':
                    $printertype = 'Network';
                    break;
                }
                if ($this->obj->get('name') != $_REQUEST['alias'] && $this->obj->getManager()->exists($_REQUEST['alias'])) throw new Exception(_('Printer name already exists, please choose another'));
                $this->obj
                    ->set('description',$_REQUEST['description'])
                    ->set('name',$_REQUEST['alias'])
                    ->set('config',$printertype)
                    ->set('model',$_REQUEST['model'])
                    ->set('port',$_REQUEST['port'])
                    ->set('file',$_REQUEST['inf'])
                    ->set('configFile',$_REQUEST['configFile'])
                    ->set('ip',$_REQUEST['ip']);
                break;
            }
            if (!$this->obj->save()) throw new Exception(_('Printer update failed!'));
            self::$HookManager->processEvent('PRINTER_UPDATE_SUCCESS',array('Printer'=>&$this->obj));

            die(json_encode(array('msg'=>_('Printer updated!'))));
        } catch (Exception $e) {
            self::$HookManager->processEvent('PRINTER_UPDATE_FAIL',array('Printer'=>&$this->obj));
            die(json_encode(array('error'=>$e->getMessage())));
        }
    }
}
