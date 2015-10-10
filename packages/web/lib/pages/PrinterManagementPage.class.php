<?php
class PrinterManagementPage extends FOGPage {
    public $node = 'printer';
    private $config;
    public function __construct($name = '') {
        $this->name = 'Printer Management';
        parent::__construct($this->name);
        if ($_REQUEST[id]) {
            $this->obj = $this->getClass(Printer,$_REQUEST[id]);
            $this->config = stripos($this->obj->get(config),'local') !== false ? _('TCP/IP') : $this->obj->get(config);
            $this->subMenu = array(
                "$this->linkformat#$this->node-gen" => $this->foglang[General],
                $this->membership => $this->foglang[Membership],
                $this->delformat => $this->foglang['Delete'],
            );
            $this->notes = array(
                $this->foglang[Printer] => $this->obj->get(name),
                $this->foglang[Type] => $this->config,
            );
        }
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        // Header row
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" />',
            'Printer Name',
            'Printer Type',
            'Model',
            'Port',
            'File',
            'IP',
            'Edit'
        );
        // Row templates
        $this->templates = array(
            '<span class="icon fa fa-question hand" title="${desc}"></span>',
            '<input type="checkbox" name="printer[]" value="${id}" class="toggle-action" />',
            '<a href="?node=printer&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${config}',
            '${model}',
            '${port}',
            '${file}',
            '${ip}',
            '<a href="?node=printer&sub=edit&id=${id}" title="Edit"><i class="icon fa fa-pencil"></i></a><a href="?node=printer&sub=delete&id=${id}" title="Delete"><i class="icon fa fa-minus-circle"></i></>',
        );
        // Row attributes
        $this->attributes = array(
            array('class'=>'c filter-false',width=>16),
            array('class'=>'filter-false'),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array('class'=>'c filter-false',width=>55),
        );
    }
    // Pages
    public function index() {
        // Set title
        $this->title = _('All printers');
        if ($_SESSION[DataReturn] > 0 && $_SESSION[PrinterCount] > $_SESSION[DataReturn] && $_REQUEST[sub] != 'list')
            $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        // Find data
        $Printers = $this->getClass(PrinterManager)->find();
        // Row data
        foreach ($Printers AS $i => &$Printer) {
            $this->config = stripos($Printer->get(config),'local') !== false ? _('TCP/IP') : $Printer->get(config);
            $this->data[] = array(
                id=>$Printer->get(id),
                name=>quotemeta($Printer->get(name)),
                config=>$this->config,
                model=>$Printer->get(model),
                port=>$Printer->get(port),
                file=>$Printer->get(file),
                ip=>$Printer->get(ip),
                desc=>$Printer->get(description),
            );
        }
        unset($Printer);
        // Hook
        $this->HookManager->processEvent(PRINTER_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function search_post() {
        // Find data -> Push data
        $Printers = $this->getClass(PrinterManager)->search();
        foreach ($Printers AS $i => &$Printer) {
            $this->config = stripos($Printer->get(config),'local') !== false ? _('TCP/IP') : $Printer->get(config);
            $this->data[] = array(
                id=>$Printer->get(id),
                name=>$Printer->get(name),
                config=>$this->config,
                model=>$Printer->get(model),
                port=>$Printer->get(port),
                file=>$Printer->get(file),
                ip=>$Printer->get(ip),
                desc=>$Printer->get(description),
            );
        }
        unset($Printer);
        // Hook
        $this->HookManager->processEvent(PRINTER_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function add() {
        // Set title
        $this->title = 'New Printer';
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        echo '<!-- General --><div id="printer-gen">';
        if(!isset($_REQUEST[printertype])) $_REQUEST[printertype] = "Local";
        echo '<form id="printerform" action="?node='.$_REQUEST[node].'&sub='.$_REQUEST[sub].'&tab=printer-type" method="post" >';
        $printerTypes = array(
            'Local'=>_('TCP/IP Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        foreach ((array)$printerTypes AS $short => &$long)
            $optionPrinter .= '<option value="'.$short.'" '.($_REQUEST[printertype] == $short ? 'selected="selected"' : '').'>'.$long.'</option>';
        echo '<center><select name="printertype" onchange="this.form.submit()">'.$optionPrinter.'</select></center></form><br/>';
        unset($long);
        $fields = array(
            _('Printer Description') => '<textarea name="description">${desc}</textarea>',
            _('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}"/>',
        );
        switch (strtolower($_REQUEST[printertype])) {
            case 'network';
            $fields[addslashes('e.g. \\\\printerserver\\printername')] = '&nbsp;';
            break;
            case 'cups';
            $fields = array_merge($fields, array(_('Printer INF File').'*' => '<input type="text" name="inf" value="${printer_inf}" />',_('Printer IP').'*' => '<input type="text" name="ip" value="${printer_ip}" />'));
            break;
            case 'iprint';
            $fields = array(
                _('Printer Description') => '<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
                _('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
            );
            break;
            case 'local';
            $fields = array(
                _('Printer Description') => '<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias').'*' => '<input type="text" name="alias" value="${printer_name}" />',
                _('Printer Port').'*' => '<input type="text" name="port" value="${printer_port}" />',
                _('Printer Model').'*' => '<input type="text" name="model" value="${printer_model}" />',
                _('Printer INF File').'*' => '<input type="text" name="inf" value="${printer_inf}" />',
                _('Printer IP').'*' => '<input type="text" name="ip" value="${printer_ip}" />',
            );
            break;
        }
        $fields['&nbsp;'] = '<input name="'.strtolower($_REQUEST[printertype]).'" type="submit" value="'._('Update Printer').'" />';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
                printer_name=>$this->DB->sanitize($_REQUEST[alias]),
                printer_port=>$_REQUEST[port],
                printer_model=>$_REQUEST[model],
                printer_inf=>$this->DB->sanitize($_REQUEST[inf]),
                printer_ip=>$_REQUEST[ip],
                desc=>$_REQUEST[description],
            );
        }
        unset($input);
        echo '<form method="post" action="'.$this->formAction.'&tab=printer-gen">';
        // Hook
        $this->HookManager->processEvent(PRINTER_ADD,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            // Hook
            $this->HookManager->processEvent(PRINTER_ADD_POST);
            switch ($_REQUEST[tab]) {
                case 'printer-type';
                $this->setMessage('Printer type changed to: '.$_REQUEST[printertype]);
                $this->redirect('?node=printer&sub=add');
                break;
                case 'printer-gen';
                //Remove spaces from beginning and end offields needed.
                $_REQUEST[alias] = trim($_REQUEST[alias]);
                $_REQUEST[port] = trim($_REQUEST[port]);
                $_REQUEST[inf] = trim($_REQUEST[inf]);
                $_REQUEST[model] = trim($_REQUEST[model]);
                $_REQUEST[ip] = trim($_REQUEST[ip]);
                $_REQUEST[description] = trim($_REQUEST[description]);
                // Set the printer type
                if (isset($_REQUEST[local])) $printertype = "Local";
                else if (isset($_REQUEST[network])) $printertype = "Network";
                else if (isset($_REQUEST[iprint])) $printertype = "iPrint";
                else if (isset($_REQUEST[cups])) $printertype = "Cups";
                // Error checking
                if (isset($_REQUEST[local]) && (empty($_REQUEST[alias]) || empty($_REQUEST[port]) || empty($_REQUEST[inf]) || empty($_REQUEST[model]))) throw new Exception(_('You must specify the alias, port, model, and inf. Unable to create!'));
                else if (isset($_REQUEST[iprint]) && (empty($_REQUEST[alias]) || empty($_REQUEST[port]))) throw new Exception(_('You must specify the alias and port. Unable to create!'));
                else if (isset($_REQUEST[network]) && empty($_REQUEST[alias])) throw new Exception(_('You must specify the alias. Unable to create!'));
                else if (isset($_REQUEST[cups]) && (!$_REQUEST[alias] || !$_REQUEST[ip] || !$_REQUEST[inf])) throw new Exception(_('You must specify the alias, inf and ip'));
                if ($this->getClass(Printer)->getManager()->exists($_REQUEST[alias])) throw new Exception(_('Printer already exists'));
                // Finish Creating the printer
                $Printer = $this->getClass(Printer)
                    ->set(description,$_REQUEST[description])
                    ->set(name,$this->DB->sanitize($_REQUEST[alias]))
                    ->set(config,$printertype)
                    ->set(model,$_REQUEST[model])
                    ->set('file',$_REQUEST[inf])
                    ->set(port,$_REQUEST[port])
                    ->set(ip,$_REQUEST[ip]);
                // Save
                if (!$Printer->save()) throw new Exception(_('Could not create printer'));
                // Hook
                $this->HookManager->processEvent(PRINTER_ADD_SUCCESS,array(Printer=>&$Printer));
                //Send message to user
                $this->setMessage(_('Printer was created! Editing now!'));
                //Redirect to edit
                $this->redirect('?node=printer&sub=edit&id='.$Printer->get(id));
                break;
            }
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(PRINTER_ADD_FAIL,array(Printer=>&$Printer));
            // Set session message
            $this->setMessage($e->getMessage());
            // Redirect user.
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        // Title
        $this->title = sprintf('%s: %s', 'Edit', $this->obj->get(name));
        echo '<div id="tab-container">';
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // Output
        echo '<!-- General --><div id="printer-gen">';
        if (!$_REQUEST[printertype]) $_REQUEST[printertype] = $this->obj->get(config);
        if (!$_REQUEST[printertype]) $_REQUEST[printertype] = 'Local';
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        foreach ((array)$printerTypes AS $short => &$long) $optionPrinter .= '<option value="'.$short.'" '.($_REQUEST[printertype] == $short ? 'selected="selected"' : '').'>'.$long.'</option>';
        unset($long);
        switch (strtolower($_REQUEST[printertype])) {
            case 'network';
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias').'*'=>'<input type="text" name="alias" value="${printer_name}" />',
                addslashes('e.g. \\\\printerserver\\printername')=>'&nbsp;',
            );
            break;
            case 'cups';
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias').'*'=>'<input type="text" name="alias" value="${printer_name}" />',
                _('Printer INF File').'*'=>'<input type="text" name="inf" value="${printer_inf}" />',
                _('Printer IP').'*'=>'<input type="text" name="ip" value="${printer_ip}" />',
            );
            break;
            case 'iprint';
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias').'*'=>'<input type="text" name="alias" value="${printer_name}" />',
                _('Printer Port').'*'=>'<input type="text" name="port" value="${printer_port}" />',
            );
            break;
            case 'local';
            $fields = array(
                _('Printer Description')=>'<textarea name="description" rows="8" cols="40">${desc}</textarea>',
                _('Printer Alias').'*'=>'<input type="text" name="alias" value="${printer_name}" />',
                _('Printer Port').'*'=>'<input type="text" name="port" value="${printer_port}" />',
                _('Printer Model').'*'=>'<input type="text" name="model" value="${printer_model}" />',
                _('Printer INF File').'*'=>'<input type="text" name="inf" value="${printer_inf}" />',
                _('Printer IP').'*'=>'<input type="text" name="ip" value="${printer_ip}" />',
            );
            break;
        }
        $fields['&nbsp;'] = '<input name="'.strtolower($_REQUEST[printertype]).'" type="submit" value="'._('Update Printer').'" />';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
                printer_name=>$this->DB->sanitize($this->obj->get(name)),
                printer_port=>$this->obj->get(port),
                printer_model=>$this->obj->get(model),
                printer_inf=>$this->obj->get(file),
                printer_ip=>$this->obj->get(ip),
                desc=>$this->obj->get(description),
            );
        }
        unset($input);
        // Hook
        $this->HookManager->processEvent(PRINTER_EDIT,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        echo '<form method="post" action="'.$this->formAction.'&tab=printer-type"><select name="printertype" onchange="this.form.submit()">'.$optionPrinter.'</select></form><form method="post" action="'.$this->formAction.'&tab=printer-gen">';
        $this->render();
        echo '</form></div></div>';
        unset($this->data);
    }
    public function edit_post() {
        // Hook
        $this->HookManager->processEvent(PRINTER_EDIT_POST,array(Printer=>&$this->obj));
        // POST
        try {
            switch ($_REQUEST[tab]) {
                // Switch the printer type
                case 'printer-type';
                $this->setMessage('Printer type changed to: '.$_REQUEST[printertype]);
                $this->redirect('?node=printer&sub=edit&id='.$this->obj->get(id));
                break;
                case 'printer-gen';
                //Remove beginning and trailing spaces
                $_REQUEST[alias] = trim($_REQUEST[alias]);
                $_REQUEST[port] = trim($_REQUEST[port]);
                $_REQUEST[inf] = trim($_REQUEST[inf]);
                $_REQUEST[model] = trim($_REQUEST[model]);
                $_REQUEST[ip] = trim($_REQUEST[ip]);
                $_REQUEST[description] = trim($_REQUEST[description]);
                if ($this->obj->get(name) != $_REQUEST[alias] && $this->obj->getManager()->exists($_REQUEST[alias])) throw new Exception(_('Printer name already exists, please choose another'));
                // Error checking
                if (isset($_REQUEST[local]) && (empty($_REQUEST[alias]) || empty($_REQUEST[port]) || empty($_REQUEST[inf]) || empty($_REQUEST[model]) || empty($_REQUEST[ip]))) throw new Exception(_('You must specify the alias, port, model, IP, and inf. Unable to create!'));
                else if (isset($_REQUEST[iprint]) && (empty($_REQUEST[alias]) || empty($_REQUEST[port]))) throw new Exception(_('You must specify the alias and port. Unable to create!'));
                else if (isset($_REQUEST[network]) && empty($_REQUEST[alias])) throw new Exception(_('You must specify the alias. Unable to create!'));
                else if (isset($_REQUEST[cups]) && (empty($_REQUEST[alias]) || empty($_REQUEST[inf]))) throw new Exception(_('You must specify the alias and inf!'));
                if ($this->obj->get(name) != $_REQUEST[alias] && $this->obj->getManager()->exists($_REQUEST[alias])) throw new Exception(_('Printer already exists'));
                // Set the printer type
                if (isset($_REQUEST[local])) $printertype = "Local";
                else if (isset($_REQUEST[network])) $printertype = "Network";
                else if (isset($_REQUEST[iprint])) $printertype = "iPrint";
                else if (isset($_REQUEST[cups])) $printertype = "Cups";
                // Error checking
                if (isset($_REQUEST[local]) && (empty($_REQUEST[alias]) || empty($_REQUEST[port]) || empty($_REQUEST[inf]) || empty($_REQUEST[model]) || empty($_REQUEST[ip]))) throw new Exception(_('You must specify the alias, port, model, IP, and inf. Unable to create!'));
                else if (isset($_REQUEST[iprint]) && (!$_REQUEST[alias] || !$_REQUEST[port])) throw new Exception(_('You must specify the alias and port'));
                else if (isset($_REQUEST[network]) && (!$_REQUEST[alias])) throw new Exception(_('You must specify the alias'));
                else if (isset($_REQUEST[cups]) && (!$_REQUEST[alias] || !$_REQUEST[ip] || !$_REQUEST[inf])) throw new Exception(_('You must specify the alias, inf and ip'));
                // Update Object
                $this->obj
                    ->set(description,$_REQUEST[description])
                    ->set(name,$_REQUEST[alias])
                    ->set(config,$printertype)
                    ->set(model,$_REQUEST[model])
                    ->set(port,$_REQUEST[port])
                    ->set('file',$_REQUEST[inf])
                    ->set(ip,$_REQUEST[ip]);
                break;
            }
            // Save
            if (!$this->obj->save()) throw new Exception('Printer update failed!');
            // Hook
            $this->HookManager->processEvent(PRINTER_UPDATE_SUCCESS,array(Printer=>&$this->obj));
            // Set session message
            $this->setMessage('Printer updated!');
        } catch (Exception $e) {
            // Hook
            $this->HookManager->processEvent(PRINTER_UPDATE_FAIL,array(Printer=>&$this->obj));
            // Set session message
            $this->setMessage($e->getMessage());
        }
        // Redirect for user
        $this->redirect('?node=printer&sub=edit&id='.$this->obj->get('id').'#'.$_REQUEST['tab']);
    }
}
