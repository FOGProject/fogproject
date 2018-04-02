<?php
/**
 * Printer management page.
 *
 * PHP version 5
 *
 * @category PrinterManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Printer management page.
 *
 * @category PrinterManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterManagement extends FOGPage
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
        $printercopySelector = self::getClass('PrinterManager')
            ->buildSelectBox('', 'printercopy');

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'printercopy',
                _('Copy from existing')
            ) => $printercopySelector,
            self::makeLabel(
                $labelClass,
                'printertype',
                _('Printer Type')
            ) => $printerSel
        ];

        self::$HookManager->processEvent(
            'PRINTER_COPY-TYPE_FIELDS',
            ['fields' => &$fields]
        );
        $printerCopy = '<div class="printer-copy">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // Network
        $fields = [
            self::makeLabel(
                $labelClass,
                'printernetwork',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printernetwork',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptionnetwork',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptionnetwork',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'configfilenetwork',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfilenetwork',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_NETWORK_FIELDS',
            ['fields' => &$fields]
        );
        $printerNetwork = '<div class="network hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // iPrint
        $fields = [
            self::makeLabel(
                $labelClass,
                'printeriprint',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printeriprint',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptioniprint',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptioniprint',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'portiprint',
                _('Printer Port')
            ) => self::makeInput(
                'form-control printerport-input',
                'port',
                '9000',
                'text',
                'portiprint',
                $port,
                true
            ),
            self::makeLabel(
                $labelClass,
                'configfileiprint',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfileiprint',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_IPRINT_FIELDS',
            ['fields' => &$fields]
        );

        $printeriPrint = '<div class="iprint hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // CUPS
        $fields = [
            self::makeLabel(
                $labelClass,
                'printercups',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printercups',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptioncups',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptioncups',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'infcups',
                _('Printer INF File')
            ) => self::makeInput(
                'form-control printerinf-input',
                'inf',
                'C:\Windows\System32\Drivers\printer.inf',
                'text',
                'infcups',
                $inf,
                true
            ),
            self::makeLabel(
                $labelClass,
                'ipcups',
                _('Printer IP')
            ) => self::makeInput(
                'form-control printerip-input',
                'ip',
                '192.168.1.252',
                'text',
                'ipcups',
                $ip,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
            ),
            self::makeLabel(
                $labelClass,
                'configfilecups',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfilecups',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_CUPS_FIELDS',
            ['fields' => &$fields]
        );
        $printerCups = '<div class="cups hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // Local
        $fields = [
            self::makeLabel(
                $labelClass,
                'printerlocal',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printerlocal',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptionlocal',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptionlocal',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'portlocal',
                _('Printer Port')
            ) => self::makeInput(
                'form-control printerport-input',
                'port',
                '9000',
                'text',
                'portlocal',
                $port,
                true
            ),
            self::makeLabel(
                $labelClass,
                'inflocal',
                _('Printer INF File')
            ) => self::makeInput(
                'form-control printerinf-input',
                'inf',
                'C:\Windows\System32\Drivers\printer.inf',
                'text',
                'inflocal',
                $inf,
                true
            ),
            self::makeLabel(
                $labelClass,
                'iplocal',
                _('Printer IP')
            ) => self::makeInput(
                'form-control printerip-input',
                'ip',
                '192.168.1.252',
                'text',
                'iplocal',
                $ip,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
            ),
            self::makeLabel(
                $labelClass,
                'modellocal',
                _('Printer Model')
            ) => self::makeInput(
                'form-control printermodel-input',
                'model',
                _('Printer Model'),
                'text',
                'modellocal',
                $model,
                true
            ),
            self::makeLabel(
                $labelClass,
                'configfilelocal',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfilelocal',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_LOCAL_FIELDS',
            ['fields' => &$fields]
        );
        $printerLocal = '<div class="local hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );

        self::$HookManager->processEvent(
            'PRINTER_ADD_BUTTON',
            ['buttons' => &$buttons]
        );

        echo self::makeFormTag(
            'form-horizontal',
            'printer-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="printer-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Printer');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $printerCopy;
        echo $printerNetwork;
        echo $printeriPrint;
        echo $printerCups;
        echo $printerLocal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
            filter_input(INPUT_POST, 'printer')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $port = trim(
            filter_input(INPUT_POST, 'port')
        );
        $inf = trim(
            filter_input(INPUT_POST, 'inf')
        );
        $ip = trim(
            filter_input(INPUT_POST, 'ip')
        );
        $config = trim(
            filter_input(INPUT_POST, 'printertype')
        );
        $configFile = trim(
            filter_input(INPUT_POST, 'configFile')
        );
        $model = trim(
            filter_input(INPUT_POST, 'model')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('PrinterManager')
                ->exists($printer);
            if ($exists) {
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
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'PRINTER_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Printer added!'),
                    'title' => _('Printer Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PRINTER_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Printer Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=printer&sub=edit&id='
        //    . $Printer->get('id')
        //);
        self::$HookManager->processEvent(
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
        $printer = (
            filter_input(INPUT_POST, 'printer') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $port = (
            filter_input(INPUT_POST, 'port') ?:
            $this->obj->get('port')
        );
        $inf = (
            filter_input(INPUT_POST, 'inf') ?:
            $this->obj->get('file')
        );
        $ip = (
            filter_input(INPUT_POST, 'ip') ?:
            $this->obj->get('ip')
        );
        $config = (
            filter_input(INPUT_POST, 'printertype') ?:
            $this->obj->get('config')
        );
        $configFile = (
            filter_input(INPUT_POST, 'configFile') ?:
            $this->obj->get('configFile')
        );
        $model = (
            filter_input(INPUT_POST, 'model') ?:
            $this->obj->get('model')
        );
        $printerTypes = [
            'Local'=>_('TCP/IP Port Printer'),
            'iPrint'=>_('iPrint Printer'),
            'Network'=>_('Network Printer'),
            'Cups'=>_('CUPS Printer')
        ];
        $printerSel = self::selectForm(
            'printertype',
            $printerTypes,
            $config,
            true
        );
        $printercopySelector = self::getClass('PrinterManager')
            ->buildSelectBox('', 'printercopy');

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'printercopy',
                _('Copy from existing')
            ) => $printercopySelector,
            self::makeLabel(
                $labelClass,
                'printertype',
                _('Printer Type')
            ) => $printerSel
        ];

        self::$HookManager->processEvent(
            'PRINTER_COPY-TYPE_FIELDS',
            ['fields' => &$fields]
        );

        $printerCopy = '<div class="printer-copy">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // Network
        $fields = [
            self::makeLabel(
                $labelClass,
                'printernetwork',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printernetwork',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptionnetwork',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptionnetwork',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'configfilenetwork',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfilenetwork',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_NETWORK_FIELDS',
            ['fields' => &$fields]
        );
        $printerNetwork = '<div class="network hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // iPrint
        $fields = [
            self::makeLabel(
                $labelClass,
                'printeriprint',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printeriprint',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptioniprint',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptioniprint',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'portiprint',
                _('Printer Port')
            ) => self::makeInput(
                'form-control printerport-input',
                'port',
                '9000',
                'text',
                'portiprint',
                $port,
                true
            ),
            self::makeLabel(
                $labelClass,
                'configfileiprint',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfileiprint',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_IPRINT_FIELDS',
            ['fields' => &$fields]
        );

        $printeriPrint = '<div class="iprint hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // CUPS
        $fields = [
            self::makeLabel(
                $labelClass,
                'printercups',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printercups',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptioncups',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptioncups',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'infcups',
                _('Printer INF File')
            ) => self::makeInput(
                'form-control printerinf-input',
                'inf',
                'C:\Windows\System32\Drivers\printer.inf',
                'text',
                'infcups',
                $inf,
                true
            ),
            self::makeLabel(
                $labelClass,
                'ipcups',
                _('Printer IP')
            ) => self::makeInput(
                'form-control printerip-input',
                'ip',
                '192.168.1.252',
                'text',
                'ipcups',
                $ip,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
            ),
            self::makeLabel(
                $labelClass,
                'configfilecups',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfilecups',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_CUPS_FIELDS',
            ['fields' => &$fields]
        );
        $printerCups = '<div class="cups hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // Local
        $fields = [
            self::makeLabel(
                $labelClass,
                'printerlocal',
                _('Printer Name/Alias')
                . '<br/>('
                . _('e.g.')
                . ' \\\\printerserver\\printername'
                . ')'
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printerlocal',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'descriptionlocal',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'descriptionlocal',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'portlocal',
                _('Printer Port')
            ) => self::makeInput(
                'form-control printerport-input',
                'port',
                '9000',
                'text',
                'portlocal',
                $port,
                true
            ),
            self::makeLabel(
                $labelClass,
                'inflocal',
                _('Printer INF File')
            ) => self::makeInput(
                'form-control printerinf-input',
                'inf',
                'C:\Windows\System32\Drivers\printer.inf',
                'text',
                'inflocal',
                $inf,
                true
            ),
            self::makeLabel(
                $labelClass,
                'iplocal',
                _('Printer IP')
            ) => self::makeInput(
                'form-control printerip-input',
                'ip',
                '192.168.1.252',
                'text',
                'iplocal',
                $ip,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
            ),
            self::makeLabel(
                $labelClass,
                'modellocal',
                _('Printer Model')
            ) => self::makeInput(
                'form-control printermodel-input',
                'model',
                _('Printer Model'),
                'text',
                'modellocal',
                $model,
                true
            ),
            self::makeLabel(
                $labelClass,
                'configfilelocal',
                _('Printer Configuration File')
            ) => self::makeInput(
                'form-control printerconfigfile-input',
                'configFile',
                _('Printer Configuration File'),
                'text',
                'configfilelocal',
                $configFile
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_LOCAL_FIELDS',
            ['fields' => &$fields]
        );
        $printerLocal = '<div class="local hidden">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-right'
        );

        self::$HookManager->processEvent(
            'PRINTER_GENERAL_BUTTONs',
            ['buttons' => &$buttons]
        );

        echo self::makeFormTag(
            'form-horizontal',
            'printer-general-form',
            self::makeTabUpdateURL(
                'printer-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $printerCopy;
        echo $printerNetwork;
        echo $printeriPrint;
        echo $printerCups;
        echo $printerLocal;
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo $this->deleteModal();
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
            'generator' => function () {
                $this->printerGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Hosts'),
                        'id' => 'printer-membership',
                        'generator' => function () {
                            $this->printerMembership();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Printer General Post
     *
     * @return void
     */
    public function printerGeneralPost()
    {
        $printer = trim(
            filter_input(INPUT_POST, 'printer')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $port = trim(
            filter_input(INPUT_POST, 'port')
        );
        $inf = trim(
            filter_input(INPUT_POST, 'inf')
        );
        $ip = trim(
            filter_input(INPUT_POST, 'ip')
        );
        $config = trim(
            filter_input(INPUT_POST, 'printertype')
        );
        $configFile = trim(
            filter_input(INPUT_POST, 'configFile')
        );
        $model = trim(
            filter_input(INPUT_POST, 'model')
        );

        $exists = self::getClass('PrinterManager')
            ->exists($printer);
        if ($printer != $this->obj->get('name')
            && $exists
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
            . self::makeTabUpdateURL(
                'printer-hosts',
                $this->obj->get('id')
            )
            . '" ';

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
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<!-- Host Membership -->';
        echo '<div class="box-group" id="membership">';
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
                    ['isDefault' => 0]
                );
            if (count($defaulton ?: [])) {
                self::getClass('PrinterAssociationManager')
                    ->update(
                        [
                            'printerID' => $this->obj->get('id'),
                            'hostID' => $defaulton
                        ],
                        '',
                        ['isDefault' => 1]
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PRINTER_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Printer updated!'),
                    'title' => _('Printer Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
    /**
     * Present the export information.
     *
     * @return void
     */
    public function export()
    {
        // The data to use for building our table.
        $this->headerData = [];
        $this->attributes = [];

        $obj = self::getClass('PrinterManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            $this->headerData[] = $common;
            $this->attributes[] = [];
            unset($real);
        }

        $this->title = _('Export Printers');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Printers');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'When you click on the item you want to export, it can only select '
            . 'what is currently viewable on the screen. This includes searched '
            . 'and the current page. Please use the selector to choose the amount '
            . 'of items you would like to export.'
        );
        echo '</p>';
        $this->render(12, 'printer-export-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Present the export list.
     *
     * @return void
     */
    public function getExportList()
    {
        header('Content-type: application/json');
        $obj = self::getClass('PrinterManager');
        $table = $obj->getTable();
        $sqlstr = $obj->getQueryStr();
        $filterstr = $obj->getFilterStr();
        $totalstr = $obj->getTotalStr();
        $dbcolumns = $obj->getColumns();
        $pass_vars = $columns = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        // Setup our columns for the CSV.
        // Automatically removes the id column.
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        self::$HookManager->processEvent(
            'PRINTER_EXPORT_ITEMS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr
            )
        );
        exit;
    }
}
