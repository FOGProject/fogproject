<?php
/**
 * Printer management page.
 *
 * PHP version 7.4+
 *
 * @category PrinterManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Items\Printer;
use FOG\Managers\PrinterAssociationManager;
use FOG\Managers\PrinterManager;
use FOG\Router\HTTPResponseCodes;

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
        $this->name = _('Printer Management');
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
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'file' => $this->obj->get('file'),
                'port' => $this->obj->get('port'),
                'model' => $this->obj->get('model'),
                'ip' => $this->obj->get('ip'),
                'uri' => $this->obj->get('uri'),
                'config' => strtolower($this->obj->get('config')),
                'configFile' => $this->obj->get('configFile')
            ]
        ));
    }
    /**
     * Builds the unified printer form body shared by add(), addModal() and
     * printerGeneral().
     *
     * The form is one always-visible common block (copy-from, type, name,
     * description) followed by four type-specific blocks (only one shown at a
     * time, toggled client-side). Requirements are relaxed to what a printer
     * actually needs operationally: the name is always required and a TCP/IP
     * port printer (Local) requires an IP/hostname to reach; everything else
     * is optional. Port defaults to 9100 (RAW) server-side when left blank.
     *
     * @param array $values current field values keyed by
     *                      printer/description/port/inf/ip/config/
     *                      configFile/model
     *
     * @return string the concatenated form-section markup
     */
    private function _printerFormSections(array $values)
    {
        $printer = $values['printer'] ?? '';
        $description = $values['description'] ?? '';
        $port = $values['port'] ?? '';
        $inf = $values['inf'] ?? '';
        $ip = $values['ip'] ?? '';
        $config = $values['config'] ?? '';
        $configFile = $values['configFile'] ?? '';
        $model = $values['model'] ?? '';
        $uri = $values['uri'] ?? '';
        if (!$config) {
            $config = 'Local';
        }

        $labelClass = 'col-sm-3 col-form-label';

        $printerTypes = [
            'Local' => _('TCP/IP Port Printer'),
            'iPrint' => _('iPrint Printer'),
            'Network' => _('Network Printer'),
            'Cups' => _('CUPS Printer'),
        ];
        $printerSel = self::selectForm(
            'printertype',
            $printerTypes,
            $config,
            true
        );
        $printercopySelector = (new PrinterManager())
            ->buildSelectBox('', 'printercopy');

        // Common block: copy-from, type, name and description are shared by
        // every type and always visible.
        $nameLabel = _('Printer Name/Alias')
            . '<br/><small class="text-muted">'
            . _('For a network/SMB share use its path, e.g.')
            . ' \\\\printerserver\\printername</small>';
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
            ) => $printerSel,
            self::makeLabel(
                $labelClass,
                'printer',
                $nameLabel
            ) => self::makeInput(
                'form-control printername-input',
                'printer',
                _('Printer Name'),
                'text',
                'printer',
                $printer,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Printer Description')
            ) => self::makeTextarea(
                'form-control printerdescription-input',
                'description',
                _('Printer Description'),
                'description',
                $description
            ),
            // Design 0010 section 2. Optional, and empty is the normal
            // state: Items\Printer::uri() derives one from the type and the
            // address fields, so every printer created before this column
            // existed keeps working with nobody editing it. Filling it in
            // is the override, and it is the only way to express a
            // driverless IPP printer -- which FOG's four types cannot say
            // at all.
            self::makeLabel(
                $labelClass,
                'uri',
                _('Device URI')
                . '<br/><small class="text-muted">'
                . _('Optional. Leave empty to derive it from the type and '
                    . 'address above. Examples:')
                . ' socket://10.0.4.20:9100, ipp://printer.corp/ipp/print, '
                . 'smb://srv/HP4550</small>'
            ) => self::makeInput(
                'form-control printeruri-input',
                'uri',
                'socket://10.0.4.20:9100',
                'text',
                'uri',
                $uri
            )
        ];

        self::$HookManager->processEvent(
            'PRINTER_COPY-TYPE_FIELDS',
            ['fields' => &$fields]
        );
        $printerCommon = '<div class="printer-common">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // Network — the share path lives in the name; only an optional
        // configuration file is type-specific.
        $fields = [
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
        $printerNetwork = '<div class="printer-type-section network d-none">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // iPrint
        $fields = [
            self::makeLabel(
                $labelClass,
                'portiprint',
                _('Printer Port')
            ) => self::makeInput(
                'form-control printerport-input',
                'port',
                '9100',
                'text',
                'portiprint',
                $port
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
        $printeriPrint = '<div class="printer-type-section iprint d-none">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // CUPS
        $fields = [
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
                $inf
            ),
            self::makeLabel(
                $labelClass,
                'ipcups',
                _('Printer IP')
            ) => self::makeInput(
                'form-control printerip-input',
                'ip',
                _('192.168.1.252 or printer.example.com:9100'),
                'text',
                'ipcups',
                $ip
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
        $printerCups = '<div class="printer-type-section cups d-none">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        // Local (TCP/IP port printer) — needs somewhere to print to, so the
        // IP/hostname is the one required type-specific field.
        $fields = [
            self::makeLabel(
                $labelClass,
                'portlocal',
                _('Printer Port')
            ) => self::makeInput(
                'form-control printerport-input',
                'port',
                '9100',
                'text',
                'portlocal',
                $port
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
                $inf
            ),
            self::makeLabel(
                $labelClass,
                'iplocal',
                _('Printer IP')
            ) => self::makeInput(
                'form-control printerip-input',
                'ip',
                _('192.168.1.252 or printer.example.com:9100'),
                'text',
                'iplocal',
                $ip,
                true
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
                $model
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
        $printerLocal = '<div class="printer-type-section local d-none">'
            . self::formFields($fields)
            . '</div>';
        unset($fields);

        return $printerCommon
            . $printerNetwork
            . $printeriPrint
            . $printerCups
            . $printerLocal;
    }
    /**
     * Normalizes a submitted printer type to its canonical form, rejecting
     * anything the model does not accept.
     *
     * @param string $config the raw submitted printertype
     *
     * @throws \Exception when the type is empty or unknown
     *
     * @return string the canonical type (Local|Cups|iPrint|Network)
     */
    private function _normalizePrinterType($config)
    {
        switch (strtolower((string)$config)) {
            case 'local':
                return 'Local';
            case 'cups':
                return 'Cups';
            case 'iprint':
                return 'iPrint';
            case 'network':
                return 'Network';
        }
        throw new \Exception(_('Please select a valid printer type.'));
    }
    /**
     * Applies the default RAW port (9100) for port-based printer types when
     * the port was left blank.
     *
     * @param string $printertype the canonical printer type
     * @param string $port        the submitted port value
     *
     * @return string the port to store
     */
    private function _defaultPrinterPort($printertype, $port)
    {
        if ($port === '' && in_array($printertype, ['Local', 'iPrint'], true)) {
            return '9100';
        }
        return $port;
    }
    /**
     * Forms for creating a new printer.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Printer');

        $sections = $this->_printerFormSections(
            [
                'printer' => filter_input(INPUT_POST, 'printer'),
                'description' => filter_input(INPUT_POST, 'description'),
                'port' => filter_input(INPUT_POST, 'port'),
                'inf' => filter_input(INPUT_POST, 'inf'),
                'ip' => filter_input(INPUT_POST, 'ip'),
                'uri' => filter_input(INPUT_POST, 'uri'),
                'config' => filter_input(INPUT_POST, 'printertype'),
                'configFile' => filter_input(INPUT_POST, 'configFile'),
                'model' => filter_input(INPUT_POST, 'model')
            ]
        );

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'PRINTER_ADD_BUTTON',
            ['buttons' => &$buttons]
        );

        $this->renderCreateForm(
            'printer',
            [[_('Create New Printer'), $sections]],
            $buttons
        );
    }
    /**
     * Forms for creating a new printer.
     *
     * @return void
     */
    public function addModal()
    {
        $sections = $this->_printerFormSections(
            [
                'printer' => filter_input(INPUT_POST, 'printer'),
                'description' => filter_input(INPUT_POST, 'description'),
                'port' => filter_input(INPUT_POST, 'port'),
                'inf' => filter_input(INPUT_POST, 'inf'),
                'ip' => filter_input(INPUT_POST, 'ip'),
                'uri' => filter_input(INPUT_POST, 'uri'),
                'config' => filter_input(INPUT_POST, 'printertype'),
                'configFile' => filter_input(INPUT_POST, 'configFile'),
                'model' => filter_input(INPUT_POST, 'model')
            ]
        );

        echo self::makeFormTag(
            '',
            'create-form',
            '../management/index.php?node=printer&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $sections;
        echo '</form>';
    }
    /**
     * Actually create the item.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Printer',
            'PRINTER_ADD',
            _('Printer added!'),
            _('Printer Create Success'),
            _('Printer Create Fail'),
            function (&$serverFault) {
                $printer = trim(
                    (string)filter_input(INPUT_POST, 'printer')
                );
                $description = trim(
                    (string)filter_input(INPUT_POST, 'description')
                );
                $port = trim(
                    (string)filter_input(INPUT_POST, 'port')
                );
                $inf = trim(
                    (string)filter_input(INPUT_POST, 'inf')
                );
                $ip = trim(
                    (string)filter_input(INPUT_POST, 'ip')
                );
                $config = trim(
                    (string)filter_input(INPUT_POST, 'printertype')
                );
                $configFile = trim(
                    (string)filter_input(INPUT_POST, 'configFile')
                );
                $model = trim(
                    (string)filter_input(INPUT_POST, 'model')
                );
                $uri = trim(
                    (string)filter_input(INPUT_POST, 'uri')
                );

                if ($printer === '') {
                    throw new \Exception(
                        _('Please enter a printer name.')
                    );
                }
                $exists = (new PrinterManager())
                    ->exists($printer);
                if ($exists) {
                    throw new \Exception(
                        _('A printer already exists with this name!')
                    );
                }
                $printertype = $this->_normalizePrinterType($config);
                $port = $this->_defaultPrinterPort($printertype, $port);
                if ($printertype === 'Local' && $ip === '') {
                    throw new \Exception(
                        _('A TCP/IP port printer requires an IP address or hostname.')
                    );
                }
                $Printer = (new Printer())
                    ->set('name', $printer)
                    ->set('description', $description)
                    ->set('config', $printertype)
                    ->set('model', $model)
                    ->set('port', $port)
                    ->set('file', $inf)
                    ->set('configFile', $configFile)
                    ->set('ip', $ip)
                    ->set('uri', $uri);
                if (!$Printer->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add printer failed!'));
                }
                return $Printer;
            }
        );
    }
    /**
     * Printer general fields
     *
     * @return void
     */
    public function printerGeneral()
    {
        $sections = $this->_printerFormSections(
            [
                'printer' => (
                    filter_input(INPUT_POST, 'printer') ?:
                    $this->obj->get('name')
                ),
                'description' => (
                    filter_input(INPUT_POST, 'description') ?:
                    $this->obj->get('description')
                ),
                'port' => (
                    filter_input(INPUT_POST, 'port') ?:
                    $this->obj->get('port')
                ),
                'inf' => (
                    filter_input(INPUT_POST, 'inf') ?:
                    $this->obj->get('file')
                ),
                'ip' => (
                    filter_input(INPUT_POST, 'ip') ?:
                    $this->obj->get('ip')
                ),
                'config' => (
                    filter_input(INPUT_POST, 'printertype') ?:
                    $this->obj->get('config')
                ),
                'configFile' => (
                    filter_input(INPUT_POST, 'configFile') ?:
                    $this->obj->get('configFile')
                ),
                'model' => (
                    filter_input(INPUT_POST, 'model') ?:
                    $this->obj->get('model')
                ),
                'uri' => (
                    filter_input(INPUT_POST, 'uri') ?:
                    $this->obj->get('uri')
                )
            ]
        );

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
        );

        self::$HookManager->processEvent(
            'PRINTER_GENERAL_BUTTONS',
            ['buttons' => &$buttons]
        );

        $this->renderGeneralForm(
            'printer',
            $sections,
            $buttons
        );
    }
    /**
     * Edit printer object.
     *
     * @return void
     */
    public function edit()
    {
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
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Printer General Post
     *
     * @return void
     */
    public function printerGeneralPost()
    {
        $printer = trim(
            (string)filter_input(INPUT_POST, 'printer')
        );
        $description = trim(
            (string)filter_input(INPUT_POST, 'description')
        );
        $port = trim(
            (string)filter_input(INPUT_POST, 'port')
        );
        $inf = trim(
            (string)filter_input(INPUT_POST, 'inf')
        );
        $ip = trim(
            (string)filter_input(INPUT_POST, 'ip')
        );
        $config = trim(
            (string)filter_input(INPUT_POST, 'printertype')
        );
        $configFile = trim(
            (string)filter_input(INPUT_POST, 'configFile')
        );
        $model = trim(
            (string)filter_input(INPUT_POST, 'model')
        );
        $uri = trim(
            (string)filter_input(INPUT_POST, 'uri')
        );

        if ($printer === '') {
            throw new \Exception(
                _('Please enter a printer name.')
            );
        }
        $exists = (new PrinterManager())
            ->exists($printer);
        if ($printer != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A printer already exists with this name!')
            );
        }
        $printertype = $this->_normalizePrinterType($config);
        $port = $this->_defaultPrinterPort($printertype, $port);
        if ($printertype === 'Local' && $ip === '') {
            throw new \Exception(
                _('A TCP/IP port printer requires an IP address or hostname.')
            );
        }
        $this->obj
            ->set('name', $printer)
            ->set('description', $description)
            ->set('config', $printertype)
            ->set('model', $model)
            ->set('port', $port)
            ->set('file', $inf)
            ->set('configFile', $configFile)
            ->set('ip', $ip)
            ->set('uri', $uri);
    }
    /**
     * Printer hosts display.
     *
     * @return void
     */
    public function printerHosts()
    {
        // Host Associations
        $this->renderAssocTab(
            'printer-host',
            _('Printer Host Associations'),
            _('Host Name'),
            'host',
            'btn btn-primary float-end'
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'printer-host',
                $this->obj->get('id')
            )
            . '" ';

        // Set Printer as default on hosts.
        $this->headerData[1] = _('Default');
        $buttons = self::makeButton(
            'printer-host-default-send',
            _('Make default'),
            'btn btn-primary float-end',
            $props
        );
        $buttons .= self::makeButton(
            'printer-host-default-remove',
            _('Unset default'),
            'btn btn-warning float-start',
            $props
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Set Printer as Default for Hosts');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'printer-host-default-table', $buttons);
        echo '</div>';
        echo '<div class="card-footer">';
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
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            )
            . self::makeButton(
                "confirmHostDefaultDeleteModal",
                _('Unset'),
                'btn btn-outline-secondary float-end'
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
        $this->assocPost('addHost', 'removeHost');
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
                (new PrinterAssociationManager())->update(
                    [
                        'hostID' => $hosts,
                        'isDefault' => 1
                    ],
                    '',
                    ['isDefault' => 0]
                );
                (new PrinterAssociationManager())->update(
                    [
                        'printerID' => $this->obj->get('id'),
                        // Schema 426 made paIsDefault a tinyint(1); the
                        // empty string this also matched was the 1.5-origin
                        // varchar's "never set", normalized to 0 on upgrade.
                        'hostID' => $hosts,
                        'isDefault' => 0
                    ],
                    '',
                    ['isDefault' => 1]
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
                (new PrinterAssociationManager())->update(
                    [
                        'printerID' => $this->obj->get('id'),
                        'hostID' => $hosts,
                        'isDefault' => 1,
                    ],
                    '',
                    ['isDefault' => 0]
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
     * Printer -> host list, limited to hosts already associated with this
     * printer. Feeds the "Set Printer as Default for Hosts" table, where a
     * default only makes sense for a host the printer is actually assigned to.
     *
     * Unlike getHostsList() this omits the removeFromQuery association column:
     * with it present, pluck() reindexes past it and a server-side ORDER BY on
     * the isDefault column misresolves to the association alias. Dropping it
     * keeps isDefault index-aligned so the table can sort defaults-first.
     *
     * @return void
     */
    public function getHostsDefaultList()
    {
        $join = [
            'LEFT OUTER JOIN `printerAssoc` ON '
            . "`hosts`.`hostID` = `printerAssoc`.`paHostID` "
            . "AND `printerAssoc`.`paPrinterID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'paIsDefault',
            'dt' => 'isDefault'
        ];
        return $this->obj->getItemsList(
            'host',
            'printerassociation',
            $join,
            '`printerAssoc`.`paHostID` IS NOT NULL',
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
        $this->handleEditPost(
            'Printer',
            'PRINTER_EDIT',
            _('Printer updated!'),
            _('Printer Update Success'),
            _('Printer Update Fail'),
            function (&$serverFault) {
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
                    throw new \Exception(_('Printer update failed!'));
                }
            }
        );
    }
}
