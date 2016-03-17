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
                self::$foglang['Printer'] => stripslashes($this->obj->get('name')),
                self::$foglang['Type'] => $this->config,
            );
        }
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes,'object'=>&$this->obj,'linkformat'=>&$this->linkformat,'delformat'=>&$this->delformat,'membership'=>&$this->membership));
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
            'Edit'
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
            '<a href="?node=printer&sub=edit&id=${id}" title="Edit"><i class="icon fa fa-pencil"></i></a><a href="?node=printer&sub=delete&id=${id}" title="Delete"><i class="icon fa fa-minus-circle"></i></>',
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
            array('class'=>'c filter-false','width'=>55),
        );
    }
    public function index() {
        $this->title = _('All printers');
        if ($_SESSION['DataReturn'] > 0 && $_SESSION['PrinterCount'] > $_SESSION['DataReturn'] && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        foreach ((array)self::getClass('PrinterManager')->find() AS $i => &$Printer) {
            if (!$Printer->isValid()) continue;
            $this->config = stripos($Printer->get('config'),'local') !== false ? _('TCP/IP') : $Printer->get('config');
            $this->data[] = array(
                'id'=>$Printer->get('id'),
                'name'=>$Printer->get('name'),
                'config'=>$this->config,
                'model'=>$Printer->get('model'),
                'port'=>$Printer->get('port'),
                'file'=>$Printer->get('file'),
                'ip'=>$Printer->get('ip'),
                'configFile'=>$Printer->get('configFile'),
                'desc'=>$Printer->get('description'),
            );
            unset($Printer);
        }
        $this->HookManager->processEvent('PRINTER_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        foreach (self::getClass('PrinterManager')->search('',true) AS $i => &$Printer) {
            if (!$Printer->isValid()) continue;
            $this->config = stripos($Printer->get('config'),'local') !== false ? _('TCP/IP') : $Printer->get('config');
            $this->data[] = array(
                'id'=>$Printer->get('id'),
                'name'=>$Printer->get('name'),
                'config'=>$this->config,
                'model'=>$Printer->get('model'),
                'port'=>$Printer->get('port'),
                'file'=>$Printer->get('file'),
                'ip'=>$Printer->get('ip'),
                'configFile'=>$Printer->get('configFile'),
                'desc'=>$Printer->get('description'),
            );
            unset($Printer);
        }
        $this->HookManager->processEvent('PRINTER_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
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
        printf('<p class="c"><select name="printertype" onchange="this.form.submit()">%s</select></p></form><br/>',$optionPrinter);
        $fields = array(
            _('Printer Description') => '<textarea name="description">${desc}</textarea>',
            sprintf('%s*',_('Printer Alias')) => '<input type="text" name="alias" value="${printer_name}"/>',
        );
        switch (strtolower($_REQUEST['printertype'])) {
        case 'network':
            $fields[addslashes('e.g. \\\\printerserver\printername')] = '&nbsp;';
            break;
        case 'cups':
            $fields = array_merge($fields, array(sprintf('%s*',_('Printer INF File')) => '<input type="text" name="inf" value="${printer_inf}"/>',sprintf('%s*',_('Printer IP')) => '<input type="text" name="ip" value="${printer_ip}"/>'));
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
                _('Printer INF File') => '<input type="text" name="inf" value="${printer_inf}"/>',
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
        $this->HookManager->processEvent('PRINTER_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&tab=printer-gen">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $this->HookManager->processEvent('PRINTER_ADD_POST');
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
                $this->HookManager->processEvent('PRINTER_ADD_SUCCESS',array('Printer'=>&$Printer));
                $this->setMessage(_('Printer was created! Editing now!'));
                $this->redirect(sprintf('?node=printer&sub=edit&id=%s',$Printer->get('id')));
            }
        } catch (Exception $e) {
            $this->HookManager->processEvent('PRINTER_ADD_FAIL',array('Printer'=>&$Printer));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s', _('Edit'), stripslashes($this->obj->get('name')));
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
        if (!$_REQUEST['printertype']) $_REQUEST['printertype'] = $this->obj->get('config');
        if (!$_REQUEST['printertype']) $_REQUEST['printertype'] = 'Local';
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        ob_start();
        foreach ((array)$printerTypes AS $short => &$long) printf('<option value="%s"%s>%s</option>',$short,($_REQUEST['printertype'] == $short ? ' selected' : ''),$long);
        unset($long);
        $optionPrinter = ob_get_clean();
        switch (strtolower($_REQUEST['printertype'])) {
        case 'network':
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                sprintf('%s*',_('Printer Alias'))=>'<input type="text" name="alias" value="${printer_name}"/>',
                addslashes('e.g. \\\\printerserver\printername')=>'&nbsp;',
            );
            break;
        case 'cups':
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                sprintf('%s*',_('Printer Alias'))=>'<input type="text" name="alias" value="${printer_name}"/>',
                sprintf('%s*',_('Printer INF File'))=>'<input type="text" name="inf" value="${printer_inf}"/>',
                sprintf('%s*',_('Printer IP'))=>'<input type="text" name="ip" value="${printer_ip}"/>',
            );
            break;
        case 'iprint':
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                sprintf('%s*',_('Printer Alias'))=>'<input type="text" name="alias" value="${printer_name}"/>',
                sprintf('%s*',_('Printer Port'))=>'<input type="text" name="port" value="${printer_port}"/>',
            );
            break;
        case 'local':
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias')=>'<input type="text" name="alias" value="${printer_name}"/>',
                _('Printer Port')=>'<input type="text" name="port" value="${printer_port}"/>',
                _('Printer Model')=>'<input type="text" name="model" value="${printer_model}"/>',
                _('Printer INF File')=>'<input type="text" name="inf" value="${printer_inf}"/>',
                _('Printer Config File')=>'<input type="text" name="configFile" value="${printer_configFile}"/>',
                _('Printer IP')=>'<input type="text" name="ip" value="${printer_ip}"/>',
            );
            break;
        }
        $fields['&nbsp;'] = sprintf('<input class="c" name="%s" type="submit" value="%s"/>',strtolower($_REQUEST['printertype']),_('Update Printer'));
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
                'printer_name'=>$this->obj->get('name'),
                'printer_port'=>$this->obj->get('port'),
                'printer_model'=>$this->obj->get('model'),
                'printer_inf'=>$this->obj->get('file'),
                'printer_ip'=>$this->obj->get('ip'),
                'printer_configFile'=>$this->obj->get('configFile'),
                'desc'=>$this->obj->get('description'),
            );
        }
        unset($input);
        $this->HookManager->processEvent('PRINTER_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&tab=printer-type"><p class="c"><select class="c" name="printertype" onchange="this.form.submit()">%s</select></p><br/></form><form method="post" action="%s&tab=printer-gen">',$this->formAction,$optionPrinter,$this->formAction);
        $this->render();
        echo '</form></div></div>';
        unset($this->data);
    }
    public function edit_post() {
        $this->HookManager->processEvent('PRINTER_EDIT_POST',array('Printer'=>&$this->obj));
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
                if (isset($_REQUEST['local']) && (empty($_REQUEST['alias']) || empty($_REQUEST['port']) || empty($_REQUEST['inf']) || empty($_REQUEST['model']) || empty($_REQUEST['ip']))) throw new Exception(_('You must specify the alias, port, model, IP, and inf. Unable to create!'));
                else if (isset($_REQUEST['iprint']) && (empty($_REQUEST['alias']) || empty($_REQUEST['port']))) throw new Exception(_('You must specify the alias and port. Unable to create!'));
                else if (isset($_REQUEST['network']) && empty($_REQUEST['alias'])) throw new Exception(_('You must specify the alias. Unable to create!'));
                else if (isset($_REQUEST['cups']) && (empty($_REQUEST['alias']) || empty($_REQUEST['inf']))) throw new Exception(_('You must specify the alias and inf!'));
                if ($this->obj->get('name') != $_REQUEST['alias'] && $this->obj->getManager()->exists($_REQUEST['alias'])) throw new Exception(_('Printer name already exists, please choose another'));
                if (isset($_REQUEST['local'])) $printertype = "Local";
                else if (isset($_REQUEST['network'])) $printertype = "Network";
                else if (isset($_REQUEST['iprint'])) $printertype = "iPrint";
                else if (isset($_REQUEST['cups'])) $printertype = "Cups";
                if (isset($_REQUEST['local']) && (empty($_REQUEST['alias']) || empty($_REQUEST['port']) || empty($_REQUEST['inf']) || empty($_REQUEST['model']) || empty($_REQUEST['ip']))) throw new Exception(_('You must specify the alias, port, model, IP, and inf. Unable to create!'));
                else if (isset($_REQUEST['iprint']) && (!$_REQUEST['alias'] || !$_REQUEST['port'])) throw new Exception(_('You must specify the alias and port'));
                else if (isset($_REQUEST['network']) && (!$_REQUEST['alias'])) throw new Exception(_('You must specify the alias'));
                else if (isset($_REQUEST['cups']) && (!$_REQUEST['alias'] || !$_REQUEST['ip'] || !$_REQUEST['inf'])) throw new Exception(_('You must specify the alias, inf and ip'));
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
            $this->HookManager->processEvent('PRINTER_UPDATE_SUCCESS',array('Printer'=>&$this->obj));
            $this->setMessage(_('Printer updated!'));
        } catch (Exception $e) {
            $this->HookManager->processEvent('PRINTER_UPDATE_FAIL',array('Printer'=>&$this->obj));
            $this->setMessage($e->getMessage());
        }
        $this->redirect(sprintf('%s#%s',$this->formAction,$_REQUEST['tab']));
    }
}
