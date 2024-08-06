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

        $labelClass = 'col-sm-3 control-label';

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
            'btn btn-primary pull-right'
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
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Forms for creating a new printer.
     *
     * @return void
     */
    public function addModal()
    {
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

        $labelClass = 'col-sm-3 control-label';

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

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=printer&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $printerCopy;
        echo $printerNetwork;
        echo $printeriPrint;
        echo $printerCups;
        echo $printerLocal;
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

        $labelClass = 'col-sm-3 control-label';

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
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'PRINTER_GENERAL_BUTTONS',
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
        echo '<div class="box-footer with-border">';
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
                        'id' => 'printer-host',
                        'generator' => function () {
                            $this->printerHosts();
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
     * Printer hosts display.
     *
     * @return void
     */
    public function printerHosts()
    {
        // Host Associations
        $this->headerData = [
            _('Host Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'printer-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons .= self::makeButton(
            'printer-host-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'printer-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Printer Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'printer-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';

        // Set Printer as default on hosts.
        $this->headerData[1] = _('Default');
        $buttons = self::makeButton(
            'printer-host-default-send',
            _('Make default'),
            'btn btn-info pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'printer-host-default-remove',
            _('Unset default'),
            'btn btn-warning pull-left',
            $props
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Set Printer as Default for Hosts');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'printer-host-default-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo self::makeModal(
            'unsetHostDefaultModal',
            _('Unset printer as default printer'),
            _(
                'Please confirm you would like to unset the default printer from '
                . ' the selected hosts'
            ),
            self::makeButton(
                "closeHostDefaultDeleteModal",
                _('Cancel'),
                'btn btn-outline pull-left',
                'data-dismiss="modal"'
            )
            . self::makeButton(
                "confirmHostDefaultDeleteModal",
                _('Unset'),
                'btn btn-outline pull-right'
            ),
            '',
            'warning'
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Printer host post elements
     *
     * @return void
     */
    public function printerHostPost()
    {
        if (isset($_POST['confirmadd'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['additems'];
            if (count($hosts ?: []) > 0) {
                $this->obj->addHost($hosts);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['remitems'];
            if (count($hosts ?: []) > 0) {
                $this->obj->removeHost($hosts);
            }
        }
        if (isset($_POST['confirmadddefault'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['additems'];
            $hostsToAssoc = array_diff(
                $hosts,
                $this->obj->get('hosts')
            );
            if (count($hostsToAssoc ?: []) > 0) {
                $this->obj->addHost($hostsToAssoc)->save();
            }
            if (count($hosts ?: []) > 0) {
                self::getClass('PrinterAssociationManager')->update(
                    [
                        'hostID' => $hosts,
                        'isDefault' => 1
                    ],
                    '',
                    ['isDefault' => '0']
                );
                self::getClass('PrinterAssociationManager')->update(
                    [
                        'printerID' => $this->obj->get('id'),
                        'hostID' => $hosts,
                        'isDefault' => ['0', '']
                    ],
                    '',
                    ['isDefault' => '1']
                );
            }
        }
        if (isset($_POST['confirmdeldefault'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['remitems'];
            if (count($hosts ?: []) > 0) {
                self::getClass('PrinterAssociationManager')->update(
                    [
                        'printerID' => $this->obj->get('id'),
                        'hostID' => $hosts,
                        'isDefault' => 1,
                    ],
                    '',
                    ['isDefault' => '0']
                );
            }
        }
    }
    /**
     * Printer -> host list
     *
     * @return void
     */
    public function getHostsList()
    {
        $join = [
            'LEFT OUTER JOIN `printerAssoc` ON '
            . "`hosts`.`hostID` = `printerAssoc`.`paHostID` "
            . "AND `printerAssoc`.`paPrinterID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'printerAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        $columns[] = [
            'db' => 'paIsDefault',
            'dt' => 'isDefault'
        ];
        return $this->obj->getItemsList(
            'host',
            'printerassociation',
            $join,
            '',
            $columns
        );
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
                case 'printer-host':
                    $this->printerHostPost();
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
}
