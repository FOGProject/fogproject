<?php
/**
 * Printer management page.
 *
 * PHP version 5
 *
 * @category PrinterManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Printer management page.
 *
 * @category PrinterManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterManagementPage extends FOGPage
{
    /**
     * The node this page operates from.
     *
     * @var string
     */
    public $node = 'printer';
    /**
     * The printer config type.
     *
     * @var string
     */
    private $_config;
    /**
     * Initializes the class.
     *
     * @param string $name The name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        global $id;
        $this->name = 'Printer Management';
        parent::__construct($this->name);
        if ($id) {
            $this->_config = _('TCP/IP');
            if (false === stripos($this->obj->get('config'), 'local')) {
                $this->_config = $this->obj->get('config');
            }
            $this->subMenu = array(
                "$this->linkformat#$this->node-gen" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Printer'] => $this->obj->get('name'),
                self::$foglang['Type'] => $this->_config,
            );
        }
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes,
                    'object' => &$this->obj,
                    'linkformat' => &$this->linkformat,
                    'delformat' => &$this->delformat,
                    'membership' => &$this->membership
                )
            );
        $this->headerData = array(
            '',
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler"/>'
            . '</label>',
            _('Printer Name'),
            _('Printer Type'),
            _('Model'),
            _('Port'),
            _('File'),
            _('IP'),
            _('Config File')
        );
        $this->templates = array(
            '<i class="icon fa fa-question hand"></i>',
            '<label for="printer-${id}">'
            . '<input type="checkbox" name="printer[]" '
            . 'value="${id}" class="toggle-action" id="host-${id}"/>'
            . '</label>',
            '<a href="?node=printer&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${config}',
            '${model}',
            '${port}',
            '${file}',
            '${ip}',
            '${configFile}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'id' => 'printer-${name}',
                'class' => 'filter-false',
                'title' => '${desc}',
                'data-toggle' => 'tooltip',
                'data-placement' => 'right'
            ),
            array(
                'class' => 'filter-false'
            ),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array()
        );
        /**
         * Lamda function to return data either by list or search.
         *
         * @param object $Image the object to use.
         *
         * @return void
         */
        self::$returnData = function (&$Printer) {
            $config = _('TCP/IP');
            if (false === stripos($Printer->config, 'local')) {
                $config = $Printer->config;
            }
            $this->data[] = array(
                'id' => $Printer->id,
                'name' => $Printer->name,
                'config' => $config,
                'model' => $Printer->model,
                'port' => $Printer->port,
                'file' => $Printer->file,
                'ip' => $Printer->ip,
                'configFile' => $Printer->configFile,
                'desc' => $Printer->description
            );
            unset($Printer);
        };
    }
    /**
     * Gets the printer information.
     *
     * @return void
     */
    public function getPrinterInfo()
    {
        echo json_encode(
            array(
                'file' => $this->obj->get('file'),
                'port' => $this->obj->get('port'),
                'model' => $this->obj->get('model'),
                'ip' => $this->obj->get('ip'),
                'config' => strtolower($this->obj->get('config')),
                'configFile' => $this->obj->get('configFile')
            )
        );
        exit;
    }
    /**
     * Forms for creating a new printer.
     *
     * @return void
     */
    public function add()
    {
        $name = filter_input(INPUT_POST, 'alias');
        $desc = filter_input(INPUT_POST, 'description');
        $port = filter_input(INPUT_POST, 'port');
        $inf = filter_input(INPUT_POST, 'inf');
        $ip = filter_input(INPUT_POST, 'ip');
        $config = filter_input(INPUT_POST, 'printertype');
        $configFile = filter_input(INPUT_POST, 'configFile');
        $model = filter_input(INPUT_POST, 'model');
        $this->title = 'New Printer';
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (!$config) {
            $config = 'Local';
        }
        $printerTypes = array(
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        );
        $printerSel = self::selectForm(
            'printertype',
            $printerTypes,
            $config,
            true
        );
        $fields = array(
            '<label for="printer">'
            . _('Copy from existing')
            . '</label>' => self::getClass('PrinterManager')->buildSelectBox(
                $this->obj->get('id')
            ),
            '<label for="printertype">'
            . _('Printer Type')
            . '</label>' => $printerSel
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            'PRINTER_COPY_DATA',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $printerCopy = '<div id="printer-copy">'
            . $this->process(12)
            . '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $fields
        );
        // Network
        $fields = array(
            '<label for="namenetwork">'
            . _('Printer Name/Alias')
            . '</label>'
            . '<br/>'
            . _('e.g.')
            . ' \\\\printerserver\\printername' => '<div class="input-group">'
            . '<input type="text" name="alias" id="namenetwork" value="'
            . $name
            . '" class="form-control printername-input" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="descnetwork">'
            . _('Printer Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="descnetwork" class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="addprinternetwork">'
            . _('Add New Printer')
            . '</label>' => '<button type="submit" name="add" '
            . 'id="addprinternetwork" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            'PRINTER_NETWORK',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $printerNetwork = '<div class="hiddeninitially" id="network">'
            . $this->process(12)
            . '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $fields
        );
        // iPrint
        $fields = array(
            '<label for="nameiprint">'
            . _('Printer Name/Alias')
            . '</label>'
            . '<br/>'
            . _('e.g.')
            . ' \\\\printerserver\\printername' => '<div class="input-group">'
            . '<input type="text" name="alias" id="nameiprint" value="'
            . $name
            . '" class="form-control printername-input" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="desciprint">'
            . _('Printer Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desciprint" class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="portiprint">'
            . _('Printer Port')
            . '</label>' => '<div class="input-group">'
            . '<input type="number" name="port" id="portiprint" '
            . 'value="'
            . $port
            . '" class="form-control printerport-input" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="addprinteriprint">'
            . _('Add New Printer')
            . '</label>' => '<button type="submit" name="add" id="addprinteriprint" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            'PRINTER_IPRINT',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $printeriPrint = '<div class="hiddeninitially" id="iprint">'
            . $this->process(12)
            . '</div>';
        unset(
            $fields,
            $this->data,
            $this->form,
            $this->headerData
        );
        // CUPS
        $fields = array(
            '<label for="namecups">'
            . _('Printer Name/Alias')
            . '</label>'
            . '<br/>'
            . _('e.g.')
            . ' \\\\printerserver\\printername' => '<div class="input-group">'
            . '<input type="text" name="alias" id="namecups" value="'
            . $name
            . '" class="form-control printername-input" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="desccups">'
            . _('Printer Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desccups" class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="infcups">'
            . _('Printer INF File')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="inf" value="'
            . $inf
            . '" id="infcups" class="printerinf-input form-control" '
            . 'required/>'
            . '</div>',
            '<label for="ipcups">'
            . _('Printer IP')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ip" value="'
            . $ip
            . '" id="ipcups" class="printerip-input form-control" '
            . 'required/>'
            . '</div>',
            '<label for="addprintercups">'
            . _('Add New Printer')
            . '</label>' => '<button type="submit" name="add" id="addprintercups" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent(
            'PRINTER_CUPS',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $printerCups = '<div class="hiddeninitially" id="cups">'
            . $this->process(12)
            . '</div>';
        unset(
            $fields,
            $this->data,
            $this->form,
            $this->headerData
        );
        // Local
        $fields = array(
            '<label for="namelocal">'
            . _('Printer Name/Alias')
            . '</label>'
            . '<br/>'
            . _('e.g.')
            . ' \\\\printerserver\\printername' => '<div class="input-group">'
            . '<input type="text" name="alias" id="namelocal" value="'
            . $name
            . '" class="form-control printername-input" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="desclocal">'
            . _('Printer Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desclocal" class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="portlocal">'
            . _('Printer Port')
            . '</label>' => '<div class="input-group">'
            . '<input type="number" name="port" id="portlocal" '
            . 'value="'
            . $port
            . '" class="form-control printerport-input" autocomplete="off" '
            . 'required/>'
            . '</div>',
            '<label for="inflocal">'
            . _('Printer INF File')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="inf" value="'
            . $inf
            . '" id="inflocal" class="printerinf-input form-control" '
            . 'required/>'
            . '</div>',
            '<label for="iplocal">'
            . _('Printer IP')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ip" value="'
            . $ip
            . '" id="iplocal" class="printerip-input form-control" '
            . 'required/>'
            . '</div>',
            '<label for="modellocal">'
            . _('Printer Model')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="model" value="'
            . $model
            . '" id="modellocal" class="printermodel-input form-control" '
            . 'required/>'
            . '</div>',
            '<label for="addprinterlocal">'
            . _('Add New Printer')
            . '</label>' => '<button type="submit" name="add" id="addprinterlocal" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        $printerLocal = '<div class="hiddeninitially" id="local">'
            . $this->process(12)
            . '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Create New Printer');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo $printerCopy;
        echo $printerNetwork;
        echo $printeriPrint;
        echo $printerCups;
        echo $printerLocal;
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Actually create the item.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('PRINTER_ADD_POST');
        try {
            $_REQUEST['alias'] = trim($_REQUEST['alias']);
            $_REQUEST['port'] = trim($_REQUEST['port']);
            $_REQUEST['inf'] = trim($_REQUEST['inf']);
            $_REQUEST['model'] = trim($_REQUEST['model']);
            $_REQUEST['ip'] = trim($_REQUEST['ip']);
            $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
            $_REQUEST['description'] = trim($_REQUEST['description']);
            $_REQUEST['printertype'] = trim(strtolower($_REQUEST['printertype']));
            if (empty($_REQUEST['alias'])) {
                throw new Exception(_('A name must be set'));
            }
            if (self::getClass('PrinterManager')->exists($_REQUEST['alias'])) {
                throw new Exception(_('Printer name already exists'));
            }
            switch ($_REQUEST['printertype']) {
            case 'local':
                $printertype = 'Local';
                break;
            case 'cups':
                $printertype = 'Cups';
                break;
            case 'iprint':
                $printertype = 'iPrint';
                break;
            case 'network':
                $printertype = 'Network';
                break;
            }
            $Printer = self::getClass('Printer')
                ->set('description', $_REQUEST['description'])
                ->set('name', $_REQUEST['alias'])
                ->set('config', $printertype)
                ->set('model', $_REQUEST['model'])
                ->set('port', $_REQUEST['port'])
                ->set('file', $_REQUEST['inf'])
                ->set('configFile', $_REQUEST['configFile'])
                ->set('ip', $_REQUEST['ip']);
            if (!$Printer->save()) {
                throw new Exception(_('Printer create/updated failed!'));
            }
            $hook = 'PRINTER_ADD_SUCCESS';
            $msg = json_encode(
                array('msg' => _('Printer added'))
            );
        } catch (Exception $e) {
            $hook = 'PRINTER_ADD_FAIL';
            $msg = json_encode(
                array('error' => $e->getMessage())
            );
        }
        self::$HookManager->processEvent(
            $hook,
            array('Printer' => &$Printer)
        );
        unset($Printer);
        echo $msg;
        exit;
    }
    /**
     * Printer general fields
     *
     * @return void
     */
    public function printerGeneral()
    {
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (!isset($_POST['printertype']) || empty($_POST['printertype'])) {
            $_POST['printertype'] = $this->obj->get('config');
        }
        $alias = filter_input(
            INPUT_POST, 'alias'
        ) ?: $this->obj->get('name');
        $desc = filter_input(
            INPUT_POST, 'description'
        ) ?: $this->obj->get('description');
        $fields = array(
            '<label for="printer-copy">'
            . _('Copy from existing printer')
            . '</label>' => '<div class="input-group">'
            . self::getClass('PrinterManager')->buildSelectBox(
                $this->obj->get('id'),
                'printer-copy'
            )
            . '</div>',
            '<label for="printertype">'
            . _('Printer Type')
            . '</label>' => '<div class="input-group">'
            . Printer::buildPrinterTypeSelector()
            . '</div>',
            '<label for="alias">'
            . _('Printer Alias')
            . '<br/>'
            . '<i>(e.g. \\\\printerserver\printername)</i>'
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="form-control" name="'
            . 'alias" id="alias" value="'
            . $alias
            . '"/>'
            . '</div>',
            '<label for="description">'
            . _('Printer Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="description" class="'
            . 'form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'PRINTER_GENERAL_FIELDS',
            array(
                'fields' => &$fields
            )
        );
        array_walk($fields, $this->fieldsToData);
        echo '<!-- General -->';
        echo '<div id="printer-gen" class="tab-pane fade in active">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Printer General');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method-"post" action="'
            . $this->formAction
            . '&tab=printer-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Edit printer object.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        unset($this->headerData);
        unset($this->data);
        echo '<div class="col-xs-9 tab-content">';
        $this->printerGeneral();
        echo '</div>';
        /*echo '<div id="network" class="hiddeninitially">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        unset($fields['&nbsp;']);
        $fields = self::fastmerge(
            $fields,
            array(
                sprintf(
                    '%s*',
                    _('Printer Port')
                ) => sprintf(
                    '<input class="printerport-input" type='
                    . '"text" name="port" value="%s"/>',
                    $this->obj->get('port')
                ),
            )
        );
        echo '<div id="iprint" class="hiddeninitially">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            _('Printer Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            sprintf(
                '%s*',
                _('Printer Alias')
            ) => sprintf(
                '<input class="printername-input" type='
                . '"text" name="alias" value="%s"/>',
                $this->obj->get('name')
            ),
            sprintf(
                '%s*',
                _('Printer INF File')
            ) => sprintf(
                '<input class="printerinf-input" type='
                . '"text" name="inf" value="%s"/>',
                $this->obj->get('file')
            ),
            sprintf(
                '%s*',
                _('Printer IP')
            ) => sprintf(
                '<input class="printerip-input" type='
                . '"text" name="ip" value="%s"/>',
                $this->obj->get('ip')
            ),
        );
        echo '<div id="cups" class="hiddeninitially">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = self::fastmerge(
            $fields,
            array(
                _('Printer Port') => sprintf(
                    '<input class="printerport-input" type='
                    . '"text" name="port" value="%s"/>',
                    $this->obj->get('port')
                ),
                _('Printer Model') => sprintf(
                    '<input class="printermodel-input" type='
                    . '"text" name="model" value="%s"/>',
                    $this->obj->get('model')
                ),
                _('Printer Config File') => sprintf(
                    '<input class="printerconfigFile-input" type='
                    . '"text" name="configFile" value="%s"/>',
                    $this->obj->get('configFile')
                ),
            )
        );
        echo '<div id="local" class="hiddeninitially">';
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        $this->render();
        echo '</div>';
        unset($this->data);
        $fields = array(
            '&nbsp;' => sprintf(
                '<input class="c" name="updateprinter" type="submit" value="%s"/>',
                _('Update Printer')
            )
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        self::$HookManager
            ->processEvent(
                'PRINTER_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        echo '</form></div></div>';
        unset($this->data);*/
    }
    /**
     * Save the edits.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'PRINTER_EDIT_POST',
                array('Printer' => &$this->obj)
            );
        try {
            switch ($_REQUEST['tab']) {
            case 'printer-type':
                self::setMessage(
                    sprintf(
                        '%s: %s',
                        _('Printer type changed to'),
                        $_REQUEST['printertype']
                    )
                );
                self::redirect($this->formAction);
                break;
            case 'printer-gen':
                $_REQUEST['alias'] = trim($_REQUEST['alias']);
                $_REQUEST['port'] = trim($_REQUEST['port']);
                $_REQUEST['inf'] = trim($_REQUEST['inf']);
                $_REQUEST['model'] = trim($_REQUEST['model']);
                $_REQUEST['ip'] = trim($_REQUEST['ip']);
                $_REQUEST['configFile'] = trim($_REQUEST['configFile']);
                $_REQUEST['description'] = trim($_REQUEST['description']);
                $_REQUEST['printertype'] = trim(
                    strtolower($_REQUEST['printertype'])
                );
                if (empty($_REQUEST['alias'])) {
                    throw new Exception(_('A name must be set'));
                }
                switch ($_REQUEST['printertype']) {
                case 'local':
                    $printertype = 'Local';
                    break;
                case 'cups':
                    $printertype = 'Cups';
                    break;
                case 'iprint':
                    $printertype = 'iPrint';
                    break;
                case 'network':
                    $printertype = 'Network';
                    break;
                }
                if ($this->obj->get('name') != $_REQUEST['alias']
                    && $this->obj->getManager()->exists($_REQUEST['alias'])
                ) {
                    throw new Exception(_('Printer name already exists'));
                }
                $this->obj
                    ->set('description', $_REQUEST['description'])
                    ->set('name', $_REQUEST['alias'])
                    ->set('config', $printertype)
                    ->set('model', $_REQUEST['model'])
                    ->set('port', $_REQUEST['port'])
                    ->set('file', $_REQUEST['inf'])
                    ->set('configFile', $_REQUEST['configFile'])
                    ->set('ip', $_REQUEST['ip']);
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Printer update failed!'));
            }
            $hook = 'PRINTER_UPDATE_SUCCESS';
            $msg = json_encode(
                array('msg' => _('Printer updated!'))
            );
        } catch (Exception $e) {
            $hook = 'PRINTER_UPDATE_FAIL';
            $msg = json_encode(
                array('error' => $e->getMessage())
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Printer' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
