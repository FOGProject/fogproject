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
        echo '<!-- General --><div id="printer-gen">';
        if(!isset($_REQUEST['printertype'])) $_REQUEST['printertype'] = "Local";
        printf('<form method="post" id="printerform" action="%s&tab=printer-type">',$this->formAction);
        $printerTypes = array(
            'Local'=>_('TCP/IP Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        ob_start();
        foreach ((array)$printerTypes AS $short => &$long) printf('<option value="%s"%s>%s</option>',$short,($_REQUEST['printertype'] == $short ? ' selected' : ''),$long);
        unset($long);
        $optionPrinter = ob_get_clean();
        printf('<p class="c"><select name="printertype">%s</select></p></form><br/>',$optionPrinter);
        $fields = array(
            _('Printer Description') => '<textarea name="description">${desc}</textarea>',
            sprintf('%s*',_('Printer Alias')) => '<input class="printername-input" type="text" name="alias" value="${printer_name}"/>',
        );
        switch (strtolower($_REQUEST['printertype'])) {
        case 'network':
            $fields['e.g. \\\\\\\\printerserver\\\\printername'] = '&nbsp;';
            break;
        case 'cups':
            $fields = array_merge($fields, array(sprintf('%s*',_('Printer INF File')) => '<input class="printerinf-input" type="text" name="inf" value="${printer_inf}"/>',sprintf('%s*',_('Printer IP')) => '<input type="text" name="ip" value="${printer_ip}"/>'));
            break;
        case 'iprint':
            $fields = array(
                _('Printer Description') => '<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                sprintf('%s*',_('Printer Alias')) => '<input type="text" name="alias" value="${printer_name}"/>',
                sprintf('%s*',_('Printer Port')) => '<input type="text" name="port" value="${printer_port}"/>',
            );
            break;
        case 'local':
            $fields = array(
                _('Printer Description') => '<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias') => '<input type="text" name="alias" value="${printer_name}"/>',
                _('Printer Port') => '<input type="text" name="port" value="${printer_port}"/>',
                _('Printer Model') => '<input type="text" name="model" value="${printer_model}"/>',
                _('Printer INF File') => '<input class="printerinf-input" type="text" name="inf" value="${printer_inf}"/>',
                _('Printer Config File') => '<input type="text" name="configFile" value="${printer_configFile}"/>',
                _('Printer IP') => '<input type="text" name="ip" value="${printer_ip}"/>',
            );
            break;
        }
        $fields['&nbsp;'] = sprintf('<input name="%s" type="submit" value="%s"/>',strtolower($_REQUEST['printertype']),_('Update Printer'));
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
                'printer_name'=>$_REQUEST['alias'],
                'printer_port'=>$_REQUEST['port'],
                'printer_model'=>$_REQUEST['model'],
                'printer_inf'=>$_REQUEST['inf'],
                'printer_ip'=>$_REQUEST['ip'],
                'printer_configFile'=>$_REQUEST['configFile'],
                'desc'=>$_REQUEST['description'],
            );
        }
        unset($input,$fields);
        self::$HookManager->processEvent('PRINTER_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&tab=printer-gen">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            self::$HookManager->processEvent('PRINTER_ADD_POST');
            switch ($_REQUEST['tab']) {
            case 'printer-type':
                $this->setMessage(sprintf('%s: %s',_('Printer type changed to'),$_REQUEST['printertype']));
                $this->redirect('?node=printer&sub=add');
                break;
            case 'printer-gen':
                $_REQUEST['alias'] = trim($_REQUEST['alias']);
                $_REQUEST['port'] = trim($_REQUEST['port']);
                $_REQUEST['inf'] = trim($_REQUEST['inf']);
                $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
                $_REQUEST['model'] = trim($_REQUEST['model']);
                $_REQUEST['ip'] = trim($_REQUEST['ip']);
                $_REQUEST['description'] = trim($_REQUEST['description']);
                if (isset($_REQUEST['local'])) $printertype = 'Local';
                else if (isset($_REQUEST['network'])) $printertype = 'Network';
                else if (isset($_REQUEST['iprint'])) $printertype = 'iPrint';
                else if (isset($_REQUEST['cups'])) $printertype = 'Cups';
                if (isset($_REQUEST['local']) && (empty($_REQUEST['alias']) || empty($_REQUEST['port']) || empty($_REQUEST['inf']) || empty($_REQUEST['model']))) throw new Exception(_('You must specify the alias, port, model, and inf. Unable to create!'));
                else if (isset($_REQUEST['iprint']) && (empty($_REQUEST['alias']) || empty($_REQUEST['port']))) throw new Exception(_('You must specify the alias and port. Unable to create!'));
                else if (isset($_REQUEST['network']) && empty($_REQUEST['alias'])) throw new Exception(_('You must specify the alias. Unable to create!'));
                else if (isset($_REQUEST['cups']) && (!$_REQUEST['alias'] || !$_REQUEST['ip'] || !$_REQUEST['inf'])) throw new Exception(_('You must specify the alias, inf and ip'));
                if (self::getClass('PrinterManager')->exists($_REQUEST['alias'])) throw new Exception(_('Printer already exists'));
                $Printer = self::getClass('Printer')
                    ->set('description',$_REQUEST['description'])
                    ->set('name',$_REQUEST['alias'])
                    ->set('config',$printertype)
                    ->set('model',$_REQUEST['model'])
                    ->set('file',$_REQUEST['inf'])
                    ->set('port',$_REQUEST['port'])
                    ->set('configFile',$_REQUEST['configFile'])
                    ->set('ip',$_REQUEST['ip']);
                if (!$Printer->save()) throw new Exception(_('Could not create printer'));
                self::$HookManager->processEvent('PRINTER_ADD_SUCCESS',array('Printer'=>&$Printer));
                $this->setMessage(_('Printer was created! Editing now!'));
                $this->redirect(sprintf('?node=printer&sub=edit&id=%s',$Printer->get('id')));
            }
        } catch (Exception $e) {
            self::$HookManager->processEvent('PRINTER_ADD_FAIL',array('Printer'=>&$Printer));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
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
                if (empty($_REQUEST['alias'])) throw new Exception(_('All printer definitions must have an alias/name associated with it. Unable to create!'));
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
