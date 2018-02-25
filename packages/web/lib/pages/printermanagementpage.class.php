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
        $this->name = 'Printer Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Printer Name'),
            _('Printer Type'),
            _('Model'),
            _('Port'),
            _('File'),
            _('IP'),
            _('Config File')
        ];
        $this->templates = [
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Gets the printer information.
     *
     * @return void
     */
    public function getPrinterInfo()
    {
        echo json_encode(
            [ 
                'file' => $this->obj->get('file'),
                'port' => $this->obj->get('port'),
                'model' => $this->obj->get('model'),
                'ip' => $this->obj->get('ip'),
                'config' => strtolower($this->obj->get('config')),
                'configFile' => $this->obj->get('configFile')
            ]
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
        $this->title = _('Create New Printer');
        /**
         * Setup our variables for back up/incorrect settings without
         * making the user reset entirely
         */
        $printer = filter_input(INPUT_POST, 'printer');
        $description = filter_input(INPUT_POST, 'description');
        $port = filter_input(INPUT_POST, 'port');
        $inf = filter_input(INPUT_POST, 'inf');
        $ip = filter_input(INPUT_POST, 'ip');
        $config = filter_input(INPUT_POST, 'printertype');
        $configFile = filter_input(INPUT_POST, 'configFile');
        $model = filter_input(INPUT_POST, 'model');
        if (!$config) {
            $config = 'Local';
        }
        $printerTypes = [
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        ];
        $printerSel = self::selectForm(
            'printertype',
            $printerTypes,
            $config,
            true
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="printercopy">'
            . _('Copy from existing')
            . '</label>' => self::getClass('PrinterManager')->buildSelectBox('','printercopy'),
            '<label class="col-sm-2 control-label" for="printertype">'
            . _('Printer Type')
            . '</label>' => $printerSel
        ];
        self::$HookManager->processEvent(
            'PRINTER_COPY-TYPE_FIELDS',
            [
                'fields' => &$fields,
            ]
        );
        $printerCopy = '<div class="printer-copy">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // Network
        $fields = [
            '<label class="col-sm-2 control-label" for="printernetwork">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printernetwork" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="descnetwork">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="descnetwork" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="filenetwork">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="filenetwork" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_NETWORK_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerNetwork = '<div class="network hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // iPrint
        $fields = [
            '<label class="col-sm-2 control-label" for="printeriprint">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printeriprint" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="desciprint">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="desciprint" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="portiprint">'
            . _('Printer Port')
            . '</label>' => '<input type="text" name="port" id="portiprint" '
            . 'value="'
            . $port
            . '" class="form-control" autocomplete="off" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="fileiprint">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="fileiprint" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_IPRINT_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printeriPrint = '<div class="iprint hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // CUPS
        $fields = [
            '<label class="col-sm-2 control-label" for="printercups">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printercups" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="desccups">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="desccups" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="infcups">'
            . _('Printer INF File')
            . '</label>' => '<input type="text" name="inf" value="'
            . $inf
            . '" id="infcups" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="ipcups">'
            . _('Printer IP')
            . '</label>' => '<input type="text" name="ip" value="'
            . $ip
            . '" id="ipcups" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="filecups">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="filecups" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_CUPS_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerCups = '<div class="cups hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // Local
        $fields = [
            '<label class="col-sm-2 control-label" for="printerlocal">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printerlocal" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="desclocal">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="desclocal" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="portlocal">'
            . _('Printer Port')
            . '</label>' => '<input type="text" name="port" id="portlocal" '
            . 'value="'
            . $port
            . '" class="form-control" autocomplete="off" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="inflocal">'
            . _('Printer INF File')
            . '</label>' => '<input type="text" name="inf" value="'
            . $inf
            . '" id="inflocal" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="iplocal">'
            . _('Printer IP')
            . '</label>' => '<input type="text" name="ip" value="'
            . $ip
            . '" id="iplocal" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="modellocal">'
            . _('Printer Model')
            . '</label>' => '<input type="text" name="model" value="'
            . $model
            . '" id="modellocal" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="filelocal">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="filelocal" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_LOCAL_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerLocal = '<div class="local hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        echo '<div class="box box-solid" id="printer-create">';
        echo '<form id="printer-create-form" class="form-horizontal" method="post action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Printer General -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Printer');
        echo '</h3>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $printerCopy;
        echo $printerNetwork;
        echo $printeriPrint;
        echo $printerCups;
        echo $printerLocal;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="send">'
            . _('Create')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Actually create the item.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('PRINTER_ADD_POST');
        $printer = trim(
            filter_input(
                INPUT_POST,
                'printer'
            )
        );
        $description = trim(
            filter_input(
                INPUT_POST,
                'description'
            )
        );
        $port = trim(
            filter_input(
                INPUT_POST,
                'port'
            )
        );
        $inf = trim(
            filter_input(
                INPUT_POST,
                'inf'
            )
        );
        $ip = trim(
            filter_input(
                INPUT_POST,
                'ip'
            )
        );
        $config = trim(
            filter_input(
                INPUT_POST,
                'printertype'
            )
        );
        $configFile = trim(
            filter_input(
                INPUT_POST,
                'configFile'
            )
        );
        $model = trim(
            filter_input(
                INPUT_POST,
                'model'
            )
        );
        $serverFault = false;
        try {
            if (!$printer) {
                throw new Exception(
                    _('A printer name is required!')
                );
            }
            if (self::getClass('PrinterManager')->exists($printer)) {
                throw new Exception(
                    _('A printer already exists with this name!')
                );
            }
            switch (strtolower($config)) {
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
                ->set('name', $printer)
                ->set('description', $description)
                ->set('config', $printertype)
                ->set('model', $model)
                ->set('port', $port)
                ->set('file', $inf)
                ->set('configFile', $configFile)
                ->set('ip', $ip);
            if (!$Printer->save()) {
                $serverFault = true;
                throw new Exception(_('Add printer failed!'));
            }
            $code = 201;
            $hook = 'PRINTER_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Printer added!'),
                    'title' => _('Printer Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'PRINTER_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Printer Create Fail')
                ]
            );
        }
        //header('Location: ../management/index.php?node=host&sub=edit&id=' . $Printer->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'Printer' => &$Printer,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
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
        $printer = filter_input(INPUT_POST, 'printer') ?:
            $this->obj->get('name');
        $description = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
        $port = filter_input(INPUT_POST, 'port') ?:
            $this->obj->get('port');
        $inf = filter_input(INPUT_POST, 'inf') ?:
            $this->obj->get('file');
        $ip = filter_input(INPUT_POST, 'ip') ?:
            $this->obj->get('ip');
        $config = filter_input(INPUT_POST, 'printertype') ?:
            $this->obj->get('config');
        $configFile = filter_input(INPUT_POST, 'configFile') ?:
            $this->obj->get('configFile');
        $model = filter_input(INPUT_POST, 'model') ?:
            $this->obj->get('model');
        $printerTypes = [
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer'),
        ];
        $printerSel = self::selectForm(
            'printertype',
            $printerTypes,
            $config,
            true
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="printercopy">'
            . _('Copy from existing')
            . '</label>' => self::getClass('PrinterManager')->buildSelectBox(
                $this->obj->get('id'),
                'printercopy'
            ),
            '<label class="col-sm-2 control-label" for="printertype">'
            . _('Printer Type')
            . '</label>' => $printerSel
        ];
        self::$HookManager->processEvent(
            'PRINTER_COPY-TYPE_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerCopy = '<div class="printer-copy">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // Network
        $fields = [
            '<label class="col-sm-2 control-label" for="printernetwork">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printernetwork" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="descnetwork">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="descnetwork" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="filenetwork">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="filenetwork" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_NETWORK_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerNetwork = '<div class="network hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // iPrint
        $fields = [
            '<label class="col-sm-2 control-label" for="printeriprint">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printeriprint" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="desciprint">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="desciprint" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="portiprint">'
            . _('Printer Port')
            . '</label>' => '<input type="text" name="port" id="portiprint" '
            . 'value="'
            . $port
            . '" class="form-control" autocomplete="off" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="fileiprint">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="fileiprint" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_IPRINT_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printeriPrint = '<div class="iprint hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // CUPS
        $fields = [
            '<label class="col-sm-2 control-label" for="printercups">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printercups" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="desccups">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="desccups" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="infcups">'
            . _('Printer INF File')
            . '</label>' => '<input type="text" name="inf" value="'
            . $inf
            . '" id="infcups" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="ipcups">'
            . _('Printer IP')
            . '</label>' => '<input type="text" name="ip" value="'
            . $ip
            . '" id="ipcups" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="filecups">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="filecups" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_CUPS_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerCups = '<div class="cups hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        // Local
        $fields = [
            '<label class="col-sm-2 control-label" for="printerlocal">'
            . _('Printer Name/Alias')
            . '<br/>('
            . _('e.g.')
            . ' \\\\printerserver\\printername'
            . ')</label>' => '<input type="text" name="printer" '
            . 'value="'
            . $printer
            . '" class="form-control" id="printerlocal" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="desclocal">'
            . _('Printer Description')
            . '</label>' => '<textarea name="description" id="desclocal" class="form-control">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="portlocal">'
            . _('Printer Port')
            . '</label>' => '<input type="text" name="port" id="portlocal" '
            . 'value="'
            . $port
            . '" class="form-control" autocomplete="off" '
            . 'required/>',
            '<label class="col-sm-2 control-label" for="inflocal">'
            . _('Printer INF File')
            . '</label>' => '<input type="text" name="inf" value="'
            . $inf
            . '" id="inflocal" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="iplocal">'
            . _('Printer IP')
            . '</label>' => '<input type="text" name="ip" value="'
            . $ip
            . '" id="iplocal" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="modellocal">'
            . _('Printer Model')
            . '</label>' => '<input type="text" name="model" value="'
            . $model
            . '" id="modellocal" class="form-control" required/>',
            '<label class="col-sm-2 control-label" for="filelocal">'
            . _('Printer Configuration File')
            . '</label>' => '<input type="text" name="configFile" value="'
            . $configFile
            . '" id="filelocal" class="form-control"/>'
        ];
        self::$HookManager->processEvent(
            'PRINTER_LOCAL_FIELDS',
            [
                'fields' => &$fields
            ]
        );
        $printerLocal = '<div class="local hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);
        echo '<form id="printer-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('printer-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $printerCopy;
        echo $printerNetwork;
        echo $printeriPrint;
        echo $printerCups;
        echo $printerLocal;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Edit printer object.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'printer-general',
            'generator' => function() {
                $this->printerGeneral();
            }
        ];

        // Membership
        $tabData[] = [
            'name' => _('Host Membership'),
            'id' => 'printer-membership',
            'generator' => function() {
                $this->printerMembership();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * Printer General Post
     *
     * @return void
     */
    public function printerGeneralPost()
    {
        $printer = trim(
            filter_input(
                INPUT_POST,
                'printer'
            )
        );
        $description = trim(
            filter_input(
                INPUT_POST,
                'description'
            )
        );
        $port = trim(
            filter_input(
                INPUT_POST,
                'port'
            )
        );
        $inf = trim(
            filter_input(
                INPUT_POST,
                'inf'
            )
        );
        $ip = trim(
            filter_input(
                INPUT_POST,
                'ip'
            )
        );
        $config = trim(
            filter_input(
                INPUT_POST,
                'printertype'
            )
        );
        $configFile = trim(
            filter_input(
                INPUT_POST,
                'configFile'
            )
        );
        $model = trim(
            filter_input(
                INPUT_POST,
                'model'
            )
        );
        if (!$printer) {
            throw new Exception(
                _('A printer name is required!')
            );
        }
        if ($printer != $this->obj->get('name')
            && self::getClass('PrinterManager')->exists($printer)
        ) {
            throw new Exception(
                _('A printer already exists with this name!')
            );
        }
        switch (strtolower($config)) {
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
        $this->obj
            ->set('name', $printer)
            ->set('description', $description)
            ->set('config', $printertype)
            ->set('model', $model)
            ->set('port', $port)
            ->set('file', $inf)
            ->set('configFile', $configFile)
            ->set('ip', $ip);
    }
    /**
     * Printer Membership tab
     *
     * @return void
     */
    public function printerMembership()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=printer-membership" ';

        echo '<!-- Host Membership -->';
        echo '<div class="box-group" id="membership">';
        // =================================================================
        // Associated Hosts
        $buttons = self::makeButton(
            'membership-default',
            _('Update Default'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'membership-add',
            _('Add selected'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Host Name'),
            _('Default Printer'),
            _('Host Associated')
        ];
        $this->templates = [
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="updatemembership" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'printer-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Printer membership post elements
     *
     * @return void
     */
    public function printerMembershipPost()
    {
        if (isset($_POST['updatedefault'])) {
            $items = filter_input_array(
                INPUT_POST,
                [
                    'defaulton' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $defaulton = $items['defaulton'];
            self::getClass('PrinterAssociationManager')
                ->update(
                    [
                        'printerID' => $this->obj->get('id'),
                    ],
                    '',
                    [
                        'isDefault' => 0
                    ]
                );
            if (count($defaulton ?: [])) {
                self::getClass('PrinterAssociationManager')
                    ->update(
                        [
                            'printerID' => $this->obj->get('id'),
                            'hostID' => $defaulton
                        ],
                        '',
                        [
                            'isDefault' => 1
                        ]
                    );
            }
        }
        if (isset($_POST['updatemembership'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membership' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membership'];
            $this->obj->addHost($membership);
        }
        if (isset($_POST['membershipdel'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membershipRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membershipRemove'];
            self::getClass('PrinterAssociationManager')->destroy(
                [
                    'printerID' => $this->obj->get('id'),
                    'hostID' => $membership
                ]
            );
        }
    }
    /**
     * Printer -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostsSqlStr = "SELECT `%s`,"
            . "IF(`paPrinterID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `paPrinterID`
            FROM `%s`
            CROSS JOIN `printers`
            LEFT OUTER JOIN `printerAssoc`
            ON `printers`.`pID` = `printerAssoc`.`paPrinterID`
            AND `hosts`.`hostID` = `printerAssoc`.`paHostID`
            %s
            %s
            %s";
        $hostsFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`paPrinterID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `paPrinterID`
            FROM `%s`
            CROSS JOIN `printers`
            LEFT OUTER JOIN `printerAssoc`
            ON `printers`.`pID` = `printerAssoc`.`paPrinterID`
            AND `hosts`.`hostID` = `printerAssoc`.`paHostID`
            %s";
        $hostsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
        }
        $columns[] = [
            'db' => 'paPrinterID',
            'dt' => 'association'
        ];
        $columns[] = [
            'db' => 'paIsDefault',
            'dt' => 'isDefault'
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'hosts',
                'hostID',
                $columns,
                $hostsSqlStr,
                $hostsFilterStr,
                $hostsTotalStr
            )
        );
        exit;
    }
    /**
     * Save the edits.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'PRINTER_EDIT_POST',
            ['Printer' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'printer-general':
                $this->printerGeneralPost();
                break;
            case 'printer-membership':
                $this->printerMembershipPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Printer update failed!'));
            }
            $code = 201;
            $hook = 'PRINTER_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Printer updated!'),
                    'title' => _('Printer Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'PRINTER_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Printer Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'Printer' => &$this->obj,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
        echo $msg;
        exit;
    }
}
