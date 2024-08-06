<?php
/**
 * The FOG Configuration Page display.
 *
 * PHP version 5
 *
 * @category FOGConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The FOG Configuration Page display.
 *
 * @category FOGConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGConfigurationPage extends FOGPage
{
    /**
     * The node this page enacts for.
     *
     * @var string
     */
    public $node = 'about';
    /**
     * Initializes the about page.
     *
     * @param string $name the name to add.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'FOG Configuration';
        parent::__construct($this->name);
    }
    /**
     * Redirects to the version when initially entering
     * this page.
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->version();
    }
    /**
     * Prints the version information for the page.
     *
     * @return void
     */
    public function version()
    {
        $this->title = _('FOG Version Information');

        // Get our storage node urls.
        Route::listem('storagenode');
        $StorageNodes = json_decode(
            Route::getData()
        );
        $StorageNodes = $StorageNodes->data;
        ob_start();
        foreach ($StorageNodes as &$StorageNode) {
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
            $id = str_replace(' ', '_', $StorageNode->name);
            $url = filter_var(
                sprintf(
                    '%s://%s/fog/status/kernelvers.php',
                    self::$httpproto,
                    $StorageNode->ip
                ),
                FILTER_SANITIZE_URL
            );
            echo '<div class="panel box box-primary">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo '<a data-toggle="collapse" data-parent="#nodekernvers" href="#'
                . $id
                . '">';
            echo $StorageNode->name;
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="'
                . $id
                . '" class="panel-collapse collapse">';
            echo '<div class="box-body">';
            if (!$StorageNode->online) {
                echo '<div class="alert alert-warning">';
                echo _('Storage Node is currently unavailable');
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                continue;
            }
            echo '<div class="kernvers" urlcall="'
                . $url
                . '">';
            echo '</dl>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($StorageNode);
        }
        $renderNodes = ob_get_clean();

        // Main Grouping
        echo '<div class="box-group" id="fogversion">';

        // FOG Version Information.
        echo '<div class="box box-default">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body placehere" vers="'
            . FOG_VERSION
            . '">';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo '</div>';
        echo '</div>';

        // Kernel information
        echo '<div class="box-group" id="nodekernvers">';
        echo '<div class="box box-warning">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo _('Versions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $renderNodes;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // End Main Grouping
        echo '</div>';
    }
    /**
     * Display the fog license information
     *
     * @return void
     */
    public function license()
    {
        $this->title = _('GNU General Public License');

        $lang = '';
        switch (self::$locale) {
            case 'de':
                $lang = 'de_DE';
                break;
            case 'en':
                $lang = 'en_US';
                break;
            case 'es':
                $lang = 'es_ES';
                break;
            case 'fr':
                $lang = 'fr_FR';
                break;
            case 'it':
                $lang = 'it_IT';
                break;
            case 'pt':
                $lang = 'pt_BR';
                break;
            case 'zh':
                $lang = 'zh_CN';
                break;
            default:
                $lang = 'en_US';
        }
        $file = BASEPATH . 'management/languages/'
            . $lang
            . '.UTF-8/gpl-3.0.txt';
        $contents = nl2br(
            file_get_contents($file)
        );
        echo '<!-- License Information -->';
        echo '<div class="box-group" id="license">';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $contents;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Show the kernel update page.
     *
     * @return void
     */
    public function kernel()
    {
        $this->title = _('Kernel Update');

        $this->headerData = [
            _('Tag Name'),
            _('Version'),
            _('Architecture'),
            _('Type'),
            _('Date')
        ];

        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];

        $buttons = self::makeButton(
            'download-send',
            _('Download'),
            'btn btn-primary pull-right'
        );

        $confirmDownloadBtn = self::makeButton(
            'confirmDownload',
            _('Download'),
            'btn btn-primary pull-right'
        );
        $cancelDownloadBtn = self::makeButton(
            'cancelDownload',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );

        $downloadModal = self::makeModal(
            'downloadModal',
            _('Confirm Download'),
            '<p class="help-block">'
            . _('Confirm you would like to download a new kernel')
            . ' '
            . _('to your fog storage node.')
            . ' '
            . _('Use the input below to set the name for your new kernel.')
            . '</p>'
            . '<div class="kernel-input">'
            . self::makeInput(
                'form-control',
                'kernel-name',
                '',
                'text',
                'kernel-name',
                '',
                true
            )
            . '</div>',
            $confirmDownloadBtn . $cancelDownloadBtn,
            '',
            'info'
        );

        echo '<div class="box-group" id="kernel-update">';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '<div>';
        echo '<p class="help-block">';
        printf(
            '%s %s %s. %s, %s, %s %s. %s, %s %s, %s.',
            _('This section allows you to update'),
            _('the Linux kernel which is used to'),
            _('boot the client computers'),
            _('In FOG'),
            _('this kernel holds all the drivers for the client computer'),
            _('so if you are unable to boot a client you may wish to'),
            _('update to a newer kernel which may have more drivers built in'),
            _('This installation process may take a few minutes'),
            _('as FOG will attempt to go out to the internet'),
            _('to get the requested Kernel'),
            _('so if it seems like the process is hanging please be patient')
        );
        echo '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $downloadModal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Download the form.
     *
     * @return void
     */
    public function kernelPost()
    {
        header('Content-type: application/json');
        $dstName = filter_input(INPUT_POST, 'dstName');
        $file = trim(base64_decode(filter_input(INPUT_POST, 'file')));
        $tmpFile = sprintf(
            '%s%s%s%s',
            DS,
            str_replace(["\\",'/'], '', sys_get_temp_dir()),
            DS,
            basename(trim($dstName))
        );
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        $_SESSION['allow_ajax_kdl'] = true;
        $_SESSION['dest-kernel-file'] = basename(trim($dstName));
        $_SESSION['tmp-kernel-file'] = $tmpFile;
        $_SESSION['dl-kernel-file'] = $file;
        try {
            if (empty($dstName)) {
                throw new Exception(_('A filename is required!'));
            }
            if (empty($file)) {
                throw new Exception(
                    _('No external data to download the file from')
                );
            }
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Starting download'),
                    'title' => _('Download Starting')
                ]
            );
        } catch (Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Start Download Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Show the initrd update page.
     *
     * @return void
     */
    public function initrd()
    {
        $this->title = _('initrd (Initial Ramdisk) Update');

        $this->headerData = [
            _('Tag Name'),
            _('Version'),
            _('Architecture'),
            _('Type'),
            _('Date')
        ];

        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];

        $buttons = self::makeButton(
            'download-send',
            _('Download'),
            'btn btn-primary pull-right'
        );

        $confirmDownloadBtn = self::makeButton(
            'confirmDownload',
            _('Download'),
            'btn btn-primary pull-right'
        );
        $cancelDownloadBtn = self::makeButton(
            'cancelDownload',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );

        $downloadModal = self::makeModal(
            'downloadModal',
            _('Confirm Download'),
            '<p class="help-block">'
            . _('Confirm you would like to download a new initrd')
            . ' '
            . _('to your fog storage node.')
            . ' '
            . _('Use the input below to set the name for your new initrd.')
            . '</p>'
            . '<div class="initrd-input">'
            . self::makeInput(
                'form-control',
                'initrd-name',
                '',
                'text',
                'initrd-name',
                '',
                true
            )
            . '</div>',
            $confirmDownloadBtn . $cancelDownloadBtn,
            '',
            'info'
        );

        echo '<div class="box-group" id="initrd-update">';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '<div>';
        echo '<p class="help-block">';
        printf(
            '%s %s %s. %s, %s %s, %s.',
            _('This section allows you to update'),
            _('the initrd (initial ramdisk) which is alongside the'),
            _('kernel to boot the client computers'),
            _('This installation process may take a few minutes'),
            _('as FOG will attempt to go out to the internet'),
            _('to get the requested initrd'),
            _('so if it seems like the process is hanging please be patient')
        );
        echo '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $downloadModal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Download the form.
     *
     * @return void
     */
    public function initrdPost()
    {
        header('Content-type: application/json');
        $dstName = filter_input(INPUT_POST, 'dstName');
        $file = trim(base64_decode(filter_input(INPUT_POST, 'file')));
        $tmpFile = sprintf(
            '%s%s%s%s',
            DS,
            str_replace(["\\",'/'], '', sys_get_temp_dir()),
            DS,
            basename(trim($dstName))
        );
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        $_SESSION['allow_ajax_idl'] = true;
        $_SESSION['dest-initrd-file'] = basename(trim($dstName));
        $_SESSION['tmp-initrd-file'] = $tmpFile;
        $_SESSION['dl-initrd-file'] = $file;
        try {
            if (empty($dstName)) {
                throw new Exception(_('A filename is required!'));
            }
            if (empty($file)) {
                throw new Exception(
                    _('No external data to download the file from')
                );
            }
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Starting download'),
                    'title' => _('Download Starting')
                ]
            );
        } catch (Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Start Download Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Display the ipxe menu configurations.
     *
     * @return void
     */
    public function pxemenu()
    {
        $this->title = _('iPXE Menu Configuration');

        $this->headerData = [
            _('Setting'),
            _('Value')
        ];

        $this->attributes = [
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('iPXE Menu Configuration');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('For ipxe command related items (e.g. colour, cpair, etc...) click ')
            . '<a href="http://ipxe.org/cmd" target="_blank">'
            . _('here')
            . '</a>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'ipxe-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Ipxe Menu List getter.
     *
     * @return void
     */
    public function getIpxeList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $ServicesToSee = [
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_BOOT_EXIT_TYPE',
            'FOG_EFI_BOOT_EXIT_TYPE',
            'FOG_IPXE_BG_FILE',
            'FOG_IPXE_HOST_CPAIRS',
            'FOG_IPXE_INVALID_HOST_COLOURS',
            'FOG_IPXE_MAIN_COLOURS',
            'FOG_IPXE_MAIN_CPAIRS',
            'FOG_IPXE_MAIN_FALLBACK_CPAIRS',
            'FOG_IPXE_VALID_HOST_COLOURS',
            'FOG_KEY_SEQUENCE',
            'FOG_NO_MENU',
            'FOG_PXE_ADVANCED',
            'FOG_PXE_HIDDENMENU_TIMEOUT',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_PXE_MENU_TIMEOUT'
        ];
        $needstobecheckbox = [
            $ServicesToSee[0] => true,
            $ServicesToSee[11] => true,
            $ServicesToSee[14] => true
        ];
        $needstobenumeric = [
            $ServicesToSee[13] => true,
            $ServicesToSee[15] => true
        ];
        $where = "`settingKey` IN ('"
            . implode("','", $ServicesToSee)
            . "')";
        $settingMan = self::getClass('SettingManager');
        $table = $settingMan->getTable();
        $dbcolumns = $settingMan->getColumns();
        $sqlStr = $settingMan->getQueryStr();
        $filterStr = $settingMan->getFilterStr();
        $totalStr = $settingMan->getTotalStr()
            . ($where ? ' WHERE ' . $where : '');
        $columns = [];
        foreach ($dbcolumns as $common => &$real) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            $columns[] = [
                'db' => $real,
                'dt' => 'inputValue',
                'formatter' => function ($d, $row) use (
                    $needstobenumeric,
                    $needstobecheckbox
                ) {
                    switch ($row['settingKey']) {
                        case 'FOG_KEY_SEQUENCE':
                            $input = self::getClass('KeySequenceManager')
                                ->buildSelectBox(
                                    $row['settingValue'],
                                    $row['settingID']
                                );
                            break;
                        case 'FOG_BOOT_EXIT_TYPE':
                        case 'FOG_EFI_BOOT_EXIT_TYPE':
                            $input = Setting::buildExitSelector(
                                $row['settingID'],
                                $row['settingValue'],
                                false,
                                $row['settingKey']
                            );
                            break;
                        case (isset($needstobecheckbox[$row['settingKey']])):
                            $input = self::makeInput(
                                '',
                                $row['settingID'],
                                '',
                                'checkbox',
                                $row['settingKey'],
                                '',
                                false,
                                false,
                                -1,
                                -1,
                                ($row['settingValue'] > 0 ? 'checked' : '')
                            );
                            break;
                        case (isset($needstobenumeric[$row['settingKey']])):
                            $input = self::makeInput(
                                'form-control',
                                $row['settingID'],
                                '',
                                'number',
                                $row['settingKey'],
                                $row['settingValue']
                            );
                            break;
                        default:
                            $input = self::makeTextarea(
                                'form-control',
                                $row['settingID'],
                                '',
                                $row['settingKey'],
                                $row['settingValue']
                            );
                    }
                    return $input;
                }
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                'settingID',
                $columns,
                $sqlStr,
                $filterStr,
                $totalStr,
                $where
            )
        );
        exit;
    }
    /**
     * Stores the changes made.
     *
     * @return void
     */
    public function pxemenuPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('PXEMENU_POST');
        $ServicesToSee = [
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_BOOT_EXIT_TYPE',
            'FOG_EFI_BOOT_EXIT_TYPE',
            'FOG_IPXE_BG_FILE',
            'FOG_IPXE_HOST_CPAIRS',
            'FOG_IPXE_INVALID_HOST_COLOURS',
            'FOG_IPXE_MAIN_COLOURS',
            'FOG_IPXE_MAIN_CPAIRS',
            'FOG_IPXE_MAIN_FALLBACK_CPAIRS',
            'FOG_IPXE_VALID_HOST_COLOURS',
            'FOG_KEY_SEQUENCE',
            'FOG_NO_MENU',
            'FOG_PXE_ADVANCED',
            'FOG_PXE_HIDDENMENU_TIMEOUT',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_PXE_MENU_TIMEOUT'
        ];
        $checkbox = [
            'FOG_ADVANCED_MENU_LOGIN' => true,
            'FOG_NO_MENU' => true,
            'FOG_PXE_MENU_HIDDEN' => true
        ];
        $needstobenumeric = [
            $ServicesToSee[13] => true,
            $ServicesToSee[15] => true
        ];

        $serverFault = false;
        try {
            parse_str(
                file_get_contents('php://input'),
                $vars
            );
            $items = [];
            foreach ($vars as $key => &$val) {
                Route::indiv('setting', $key);
                $set = trim($val);
                $Service = json_decode(
                    Route::getData()
                );
                $name = trim($Service->name);
                $val = trim($Service->value);
                if ($val == $set) {
                    continue;
                }
                if (isset($checkbox[$name])) {
                    $set = intval($set) < 1 ? 0 : 1;
                } elseif (isset($needstobenumeric[$name])) {
                    if (isset($needstobenumeric[$name]) && !is_numeric($set)) {
                        throw new Exception(
                            $name . ' ' . _('value must be numeric')
                        );
                    }
                }
                unset($val);
                $items[] = [$key, $name, $set];
                unset($Service);
                unset($val);
            }
            if (count($items) > 0) {
                $SettingMan = new SettingManager();
                $insert_fields = [
                    'id',
                    'name',
                    'value'
                ];
                if (!$SettingMan->insertBatch($insert_fields, $items)) {
                    $serverFault = true;
                    throw new Exception(_('Settings update failed!'));
                }
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('iPXE config successfully stored!'),
                    'title' => _('iPXE Config Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Config Update Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Presents mac listing information.
     *
     * @return void
     */
    public function maclist()
    {
        $this->title = _('MAC Address Manufacturer Listing');
        $modalupdatebtn = self::makeButton(
            'updatemacsConfirm',
            _('Confirm'),
            'btn btn-outline pull-right'
        );
        $modalupdatebtn .= self::makeButton(
            'updatemacsCancel',
            _('Cancel'),
            'btn btn-outline pull-left'
        );
        $modaldeletebtn = self::makeButton(
            'deletemacsConfirm',
            _('Confirm'),
            'btn btn-outline pull-right'
        );
        $modaldeletebtn .= self::makeButton(
            'deletemacsCancel',
            _('Cancel'),
            'btn btn-outline pull-left'
        );
        $buttons = self::makeButton(
            'updatemacs',
            _('Update MAC List'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'deletemacs',
            _('Delete MAC List'),
            'btn btn-danger pull-left'
        );
        $modalupdate = self::makeModal(
            'updatemacsmodal',
            _('Update MAC Listing'),
            _('Confirm that you would like to update the MAC vendor listing'),
            $modalupdatebtn,
            '',
            'primary'
        );
        $modaldelete = self::makeModal(
            'deletemacsmodal',
            _('Delete MAC Listings'),
            _('Confirm that you would like to delete the MAC vendor listing'),
            $modaldeletebtn,
            '',
            'warning'
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Import known mac address makers');
        echo '</p>';
        echo '<p class="help-block">';
        echo '<a href="http://standards-oui.ieee.org/oui.txt">';
        echo 'http://standards-oui.ieee.org/oui.txt';
        echo '</a>';
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo _('Current Records');
        echo ': ';
        echo '<span id="lookupcount">' . self::getMACLookupCount() . '</span>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $modalupdate;
        echo $modaldelete;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Safes the data for real for the mac address stuff.
     *
     * @return void
     */
    public function maclistPost()
    {
        if (isset($_POST['update'])) {
            self::clearMACLookupTable();
            $url = 'http://standards-oui.ieee.org/oui.txt';
            $data = self::$FOGURLRequests->process($url);
            $data = array_shift($data);
            $items = [];
            $start = 18;
            $imported = 0;
            $pat = '#^([0-9a-fA-F]{2}[:\-]){2}([0-9a-fA-F]{2}).*$#';
            foreach (preg_split("/((\r?\n)|(\n?\r))/", $data) as $line) {
                $line = trim($line);
                if (!preg_match($pat, $line)) {
                    continue;
                }
                $mac = trim(
                    substr(
                        $line,
                        0,
                        8
                    )
                );
                $mak = trim(
                    substr(
                        $line,
                        $start,
                        strlen($line) - $start
                    )
                );
                if (strlen($mac) != 8
                    || strlen($mak) < 1
                ) {
                    continue;
                }
                $items[] = [
                    $mac,
                    $mak
                ];
            }
            if (count($items) > 0) {
                list(
                    $first_id,
                    $affected_rows
                ) = self::getClass('OUIManager')
                ->insertBatch(
                    [
                        'prefix',
                        'name'
                    ],
                    $items
                );
                $imported += $affected_rows;
                unset($items);
            }
            unset($first_id);
        }
        if (isset($_POST['clear'])) {
            self::clearMACLookupTable();
        }
        echo json_encode(
            ['count' => self::getMACLookupCount()]
        );
        exit;
    }
    /**
     * Gets the osid information
     *
     * @return void
     */
    public function getOSID()
    {
        $imageid = (int)filter_input(INPUT_POST, 'image_id');
        $osname = self::getClass(
            'Image',
            $imageid
        )->getOS()->get('name');
        echo json_encode($osname ? $osname : _('No Image specified'));
        exit;
    }
    /**
     * Save updates to the fog settings information.
     *
     * @return void
     */
    public function settingsPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('SETTINGS_POST');
        $regenrange = range(0, 24, .25);
        $viewvals = [-1, 10, 25, 50, 100, 250, 500];
        array_shift($regenrange);
        $checkbox = [
            'FOG_ENFORCE_HOST_CHANGES' => true,
            'FOG_API_ENABLED' => true,
            'FOG_ENABLE_SHOW_PASSWORDS' => true,
            'FOG_PXE_MENU_HIDDEN' => true,
            'FOG_NO_MENU' => true,
            'FOG_ADVANCED_MENU_LOGIN' => true,
            'FOG_KERNEL_DEBUG' => true,
            'FOG_REGISTRATION_ENABLED' => true,
            'FOG_IMAGE_LIST_MENU' => true,
            'FOG_EMAIL_ACTION' => true,
            'FOG_QUICKREG_AUTOPOP' => true,
            'FOG_QUICKREG_PROD_KEY_BIOS' => true,
            'FOG_CLIENT_AUTOUPDATE' => true,
            'FOG_CLIENT_AUTOLOGOFF_ENABLED' => true,
            'FOG_CLIENT_CLIENTUPDATER_ENABLED' => true,
            'FOG_CLIENT_DIRECTORYCLEANER_ENABLED' => true,
            'FOG_CLIENT_DISPLAYMANAGER_ENABLED' => true,
            'FOG_CLIENT_GREENFOG_ENABLED' => true,
            'FOG_CLIENT_HOSTREGISTER_ENABLED' => true,
            'FOG_CLIENT_HOSTNAMECHANGER_ENABLED' => true,
            'FOG_CLIENT_POWERMANAGEMENT_ENABLED' => true,
            'FOG_CLIENT_PRINTERMANAGER_ENABLED' => true,
            'FOG_CLIENT_SNAPIN_ENABLED' => true,
            'FOG_CLIENT_TASKREBOOT_ENABLED' => true,
            'FOG_TASK_FORCE_ENABLED' => true,
            'FOG_CLIENT_USERCLEANUP_ENABLED' => true,
            'FOG_CLIENT_USERTRACKER_ENABLED' => true,
            'FOG_USE_SLOPPY_NAME_LOOKUPS' => true,
            'FOG_CAPTUREIGNOREPAGEHIBER' => true,
            'FOG_USE_ANIMATION_EFFECTS' => true,
            'FOG_USE_LEGACY_TASKLIST' => true,
            'FOG_HOST_LOOKUP' => true,
            'FOG_ADVANCED_STATISTICS' => true,
            'FOG_DISABLE_CHKDSK' => true,
            'FOG_CHANGE_HOSTNAME_EARLY' => true,
            'FOG_FORMAT_FLAG_IN_GUI' => true,
            'FOG_FTP_IMAGE_SIZE' => true,
            'FOG_TASKING_ADV_SHUTDOWN_ENABLED' => true,
            'FOG_TASKING_ADV_WOL_ENABLED' => true,
            'FOG_TASKING_ADV_DEBUG_ENABLED' => true,
            'FOG_REAUTH_ON_DELETE' => true,
            'FOG_REAUTH_ON_EXPORT' => true,
            'FOG_ALWAYS_LOGGED_IN' => true,
            'FOG_PLUGINSYS_ENABLED' => true,
            'FOG_LOG_INFO' => true,
            'FOG_LOG_ERROR' => true,
            'FOG_LOG_DEBUG' => true
        ];
        Route::ids('image', false);
        $imageids = json_decode(
            Route::getData(),
            true
        );
        Route::ids('group', false);
        $groupids = json_decode(
            Route::getData(),
            true
        );
        $needstobenumeric = [
            // FOG Boot Settings
            'FOG_PXE_MENU_TIMEOUT' => true,
            'FOG_PIGZ_COMP' => range(0, 22),
            'FOG_KEY_SEQUENCE' => range(1, 35),
            'FOG_PXE_HIDDENMENU_TIMEOUT' => true,
            'FOG_KERNEL_LOGLEVEL' => range(0, 7),
            'FOG_WIPE_TIMEOUT' => true,
            // FOG Linux Service Logs
            'SERVICE_LOG_SIZE' => true,
            // FOG Linux Service Sleep Times
            'PINGHOSTSLEEPTIME' => true,
            'SERVICESLEEPTIME' => true,
            'SNAPINREPSLEEPTIME' => true,
            'SCHEDULERSLEEPTIME' => true,
            'FILEDELETEQUEUESLEEPTIME' => true,
            'IMAGEREPSLEEPTIME' => true,
            'MULTICASESLEEPTIME' => true,
            // FOG Quick Registration
            'FOG_QUICKREG_IMG_ID' => self::fastmerge(
                (array)0,
                $imageids
            ),
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => self::fastmerge(
                (array)0,
                $groupids
            ),
            // FOG Service
            'FOG_CLIENT_CHECKIN_TIME' => true,
            'FOG_CLIENT_MAXSIZE' => true,
            'FOG_GRACE_TIMEOUT' => true,
            // FOG Service - Auto Log Off
            'FOG_CLIENT_AUTOLOGOFF_MIN' => true,
            // FOG Service - Display manager
            'FOG_CLIENT_DISPLAYMANAGER_X' => true,
            'FOG_CLIENT_DISPLAYMANAGER_Y' => true,
            'FOG_CLIENT_DISPLAYMANAGER_R' => true,
            // FOG Service - Host Register
            'FOG_QUICKREG_MAX_PENDING_MACS' => true,
            // FOG View Settings
            'FOG_VIEW_DEFAULT_SCREEN' => $viewvals,
            'FOG_DATA_RETURNED' => true,
            // General Settings
            'FOG_CAPTURERESIZEPCT' => true,
            'FOG_CHECKIN_TIMEOUT' => true,
            'FOG_MEMORY_LIMIT' => true,
            'FOG_SNAPIN_LIMIT' => true,
            'FOG_FTP_PORT' => range(1, 65535),
            'FOG_FTP_TIMEOUT' => true,
            'FOG_BANDWIDTH_TIME' => true,
            'FOG_URL_BASE_CONNECT_TIMEOUT' => true,
            'FOG_URL_BASE_TIMEOUT' => true,
            'FOG_URL_AVAILABLE_TIMEOUT' => true,
            'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT' => self::fastmerge(
                (array)0,
                range(2, 6)
            ),
            // Login Settings
            'FOG_INACTIVITY_TIMEOUT' => range(1, 24),
            'FOG_REGENERATE_TIMEOUT' => $regenrange,
            // Multicast Settings
            'FOG_UDPCAST_STARTINGPORT' => range(1, 65535),
            'FOG_MULTICASE_MAX_SESSIONS' => true,
            'FOG_UDPCAST_MAXWAIT' => true,
            'FOG_MULTICAST_PORT_OVERRIDE' => range(0, 65535),
            // Proxy Settings
            'FOG_PROXY_PORT' => range(0, 65535),
            // User Management
            'FOG_USER_MINPASSLENGTH' => true,
        ];
        $needstobeip = [
            // Multicast Settings
            'FOG_MULTICAST_ADDRESS' => true,
            'FOG_MULTICAST_RENDEZVOUS' => true,
            // Proxy Settings
            'FOG_PROXY_IP' => true,
        ];
        unset($findWhere, $setWhere);

        $serverFault = false;
        try {
            parse_str(
                file_get_contents('php://input'),
                $vars
            );
            $combined = $vars + $_POST + $_FILES;
            foreach ($combined as $key => &$val) {
                Route::indiv('setting', $key);
                if (!isset($_FILES[$key]) || !$_FILES[$key]) {
                    $set = trim(filter_var($val));
                }
                $Setting = json_decode(
                    Route::getData()
                );
                $name = trim($Setting->name);
                $val = trim($Setting->value);
                if ($val && $val == $set) {
                    continue;
                }
                if (isset($checkbox[$name])) {
                    $set = intval($set) < 1 ? 0 : 1;
                } elseif (isset($needstobenumeric[$name])) {
                    switch ($needstobenumeric[$name]) {
                        case ($needstobenumeric[$name] === true):
                            if (in_array(0, (array)$needstobenumeric[$name]) && !$set) {
                                $set = 0;
                            }
                            if (!is_numeric($set)) {
                                throw new Exception(
                                    $name . ' ' . _('value must be numeric')
                                );
                            }
                            break;
                        default:
                            if (in_array(0, (array)$needstobenumeric[$name]) && !$set) {
                                $set = 0;
                            }
                            if (!is_numeric($set)) {
                                throw new Exception(
                                    $name . ' ' . _('value must be numeric')
                                );
                            }
                            if (!in_array($set, (array)$needstobenumeric[$name])) {
                                throw new Exception(
                                    $name . ' ' . _('value is not in the required range')
                                );
                            }
                    }
                } elseif (isset($needstobeip[$name])) {
                    if (!filter_var($set, FILTER_VALIDATE_IP)) {
                        throw new Exception(
                            $name . ' ' . _('value must be a valid IP Address')
                        );
                    }
                }
                switch ($name) {
                    case 'FOG_AD_DEFAULT_PASSWORD':
                        $set = (
                            preg_match('/^\*{32}$/', $set) ?
                            self::getSetting($name) :
                            $set
                        );
                        break;
                    case 'FOG_API_TOKEN':
                        $set = base64_decode($set);
                        break;
                    case 'FOG_MEMORY_LIMIT':
                        if ($set < 128) {
                            throw new Exception(
                                _('Memory limit cannot be less than 128')
                            );
                        }
                        break;
                    case 'FOG_CLIENT_BANNER_SHA':
                        continue 2;
                    case 'FOG_CLIENT_BANNER_IMAGE':
                        $banner = filter_input(INPUT_POST, 'banner');
                        $set = $banner;
                        if (!$banner) {
                            self::setSetting('FOG_CLIENT_BANNER_SHA', '');
                        }
                        if (!($_FILES[$key]['name']
                            && file_exists($_FILES[$key]['tmp_name']))
                        ) {
                            continue 2;
                        }
                        $set = preg_replace(
                            '/[^\-\w\.]+/',
                            '_',
                            trim(basename($_FILES[$key]['name']))
                        );
                        $src = sprintf(
                            '%s/%s',
                            dirname($_FILES[$key]['tmp_name']),
                            basename($_FILES[$key]['tmp_name'])
                        );
                        list(
                            $width,
                            $height,
                            $type,
                            $attr
                        ) = getimagesize($src);
                        $validExtensions = [
                            'jpg',
                            'jpeg',
                            'png',
                        ];
                        $extensionCheck = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                        if (!in_array($extensionCheck, $validExtensions)) {
                            throw new Exception(
                                _('Upload file extension must be, jpg, jpeg, or png')
                            );
                        }
                        $extensionCheck = strtolower(pathinfo($set, PATHINFO_EXTENSION));
                        if (!in_array($extensionCheck, $validExtensions)) {
                            throw new Exception(
                                _('Created file extension must be, jpg, jpeg, or png')
                            );
                        }
                        if ($width != 650) {
                            throw new Exception(
                                _('Width must be 650 pixels.')
                            );
                        }
                        if ($height != 120) {
                            throw new Exception(
                                _('Height must be 120 pixels.')
                            );
                        }
                        $dest = sprintf(
                            '%s%smanagement%sother%s%s',
                            BASEPATH,
                            DS,
                            DS,
                            DS,
                            $set
                        );
                        $hash = hash_file(
                            'sha512',
                            $src
                        );
                        if (!move_uploaded_file($src, $dest)) {
                            self::setSetting('FOG_CLIENT_BANNER_SHA', '');
                            $set = '';
                            throw new Exception(_('Failed to install logo file'));
                        } else {
                            self::setSetting('FOG_CLIENT_BANNER_SHA', $hash);
                        }
                }
                $items[] = [$key, $name, $set];
                unset($Setting);
            }
            if (count($items) > 0) {
                $SettingMan = self::getClass('SettingManager');
                $insert_fields = [
                    'id',
                    'name',
                    'value'
                ];
                if (!$SettingMan->insertBatch($insert_fields, $items)) {
                    $serverFault = true;
                    throw new Exception(_('Settings update failed!'));
                }
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Settings successfully stored!'),
                    'title' => _('Settings Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Settings Update Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Tablize the fog settings.
     *
     * @return void
     */
    public function settings()
    {
        $this->title = _('FOG Settings');

        $this->headerData = [
            _('Setting'),
            _('Value')
        ];

        $this->attributes = [
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('FOG Settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'settings-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Gets and displays log files.
     *
     * @return void
     */
    public function logviewer()
    {
        Route::listem('storagegroup');
        $StorageGroups = json_decode(
            Route::getData()
        );

        // Log selector.
        $logtype = 'error';
        $logparse = function ($log) use (
            &$files,
            &$StorageNode,
            &$logtype
        ) {
            $str = sprintf(
                '%s %s log (%s)',
                (
                    preg_match('#nginx#i', $log) ?
                    'NGINX' :
                    (
                        preg_match('#apache|httpd#', $log) ?
                        'Apache' :
                        (
                            preg_match('#fpm#i', $log) ?
                            'PHP-FPM' :
                            ''
                        )
                    )
                ),
                $logtype,
                basename($log)
            );
            $files[$StorageNode->name][_($str)] = $log;
        };
        foreach ($StorageGroups->data as &$StorageGroup) {
            if (count($StorageGroup->enablednodes ?: []) < 1) {
                continue;
            }
            $StorageNode = $StorageGroup->masternode;
            Route::logfiles($StorageNode->id);
            $fogfiles = json_decode(
                Route::getData(),
                true
            );
            try {
                $apacheerrlog = preg_grep(
                    '#(error[\_|\.]log$)#i',
                    $fogfiles
                );
                $apacheacclog = preg_grep(
                    '#(access[\_|\.]log$)#i',
                    $fogfiles
                );
                list(
                    $filedeletelogname,
                    $imagereplicatorlogname,
                    $imagesizelogname,
                    $multicastlogname,
                    $pinghostlogname,
                    $schedulerlogname,
                    $servicelogname,
                    $snapinhashlogname,
                    $snapinreplicatorlogname,
                ) = self::getSetting([
                    'FILEDELETEQUEUELOGFILENAME',
                    'IMAGEREPLICATORLOGFILENAME',
                    'IMAGESIZELOGFILENAME',
                    'MULTICASTLOGFILENAME',
                    'PINGHOSTLOGFILENAME',
                    'SCHEDULERLOGFILENAME',
                    'SERVICEMASTERLOGFILENAME',
                    'SNAPINHASHLOGFILENAME',
                    'SNAPINREPLICATORLOGFILENAME',
                ]);
                $multicastlog = preg_grep(
                    '#('.$multicastlogname.'$)#i',
                    $fogfiles
                );
                $multicastlog = array_shift($multicastlog);
                $schedulerlog = preg_grep(
                    '#('.$schedulerlogname.'$)#i',
                    $fogfiles
                );
                $schedulerlog = array_shift($schedulerlog);
                $imgrepliclog = preg_grep(
                    '#('.$imagereplicatorlogname.'$)#i',
                    $fogfiles
                );
                $imgrepliclog = array_shift($imgrepliclog);
                $imagesizelog = preg_grep(
                    '#('.$imagesizelogname.'$)#i',
                    $fogfiles
                );
                $imagesizelog = array_shift($imagesizelog);
                $snapinreplog = preg_grep(
                    '#('.$snapinreplicatorlogname.'$)#i',
                    $fogfiles
                );
                $snapinreplog = array_shift($snapinreplog);
                $snapinhashlog = preg_grep(
                    '#('.$snapinhashlogname.'$)#i',
                    $fogfiles
                );
                $snapinhashlog = array_shift($snapinhashlog);
                $pinghostlog = preg_grep(
                    '#('.$pinghostlogname.'$)#i',
                    $fogfiles
                );
                $pinghostlog = array_shift($pinghostlog);
                $filedeletequeuelog = preg_grep(
                    '#('.$filedeletelogname.'$)#i',
                    $fogfiles
                );
                $filedeletequeuelog = array_shift($filedeletequeuelog);
                $svcmasterlog = preg_grep(
                    '#('.$servicelogname.'$)#i',
                    $fogfiles
                );
                $svcmasterlog = array_shift($svcmasterlog);
                $imgtransferlogs = preg_grep(
                    '#('.$imagereplicatorlogname.'.transfer)#i',
                    $fogfiles
                );
                $snptransferlogs = preg_grep(
                    '#('.$snapinreplicatorlogname.'.transfer)#i',
                    $fogfiles
                );
                $files[$StorageNode->name] = [
                    (
                        $svcmasterlog ?
                        _('Service Master') :
                        null
                    )=> (
                        $svcmasterlog ?
                        $svcmasterlog :
                        null
                    ),
                    (
                        $multicastlog ?
                        _('Multicast') :
                        null
                    ) => (
                        $multicastlog ?
                        $multicastlog :
                        null
                    ),
                    (
                        $schedulerlog ?
                        _('Scheduler') :
                        null
                    ) => (
                        $schedulerlog ?
                        $schedulerlog :
                        null
                    ),
                    (
                        $imgrepliclog ?
                        _('Image Replicator') :
                        null
                    ) => (
                        $imgrepliclog ?
                        $imgrepliclog :
                        null
                    ),
                    (
                        $imagesizelog ?
                        _('Image Size') :
                        null
                    ) => (
                        $imagesizelog ?
                        $imagesizelog :
                        null
                    ),
                    (
                        $snapinreplog ?
                        _('Snapin Replicator') :
                        null
                    ) => (
                        $snapinreplog ?
                        $snapinreplog :
                        null
                    ),
                    (
                        $snapinhashlog ?
                        _('Snapin Hash') :
                        null
                    ) => (
                        $snapinhashlog ?
                        $snapinhashlog :
                        null
                    ),
                    (
                        $pinghostlog ?
                        _('Ping Hosts') :
                        null
                    ) => (
                        $pinghostlog ?
                        $pinghostlog :
                        null
                    ),
                    (
                        $filedeletequeuelog ?
                        _('File Delete Queue') :
                        null
                    ) => (
                        $filedeletequeuelog ?
                        $filedeletequeuelog :
                        null
                    ),
                ];
                array_map($logparse, (array)$apacheerrlog);
                $logtype = 'access';
                array_map($logparse, (array)$apacheacclog);
                foreach ((array)$imgtransferlogs as &$file) {
                    $str = self::stringBetween(
                        $file,
                        'transfer.',
                        '.log'
                    );
                    $str = sprintf(
                        '%s %s',
                        $str,
                        _('Image Transfer Log')
                    );
                    $files[$StorageNode->name][$str] = $file;
                    unset($file);
                }
                foreach ((array)$snptransferlogs as &$file) {
                    $str = self::stringBetween(
                        $file,
                        'transfer.',
                        '.log'
                    );
                    $str = sprintf(
                        '%s %s',
                        $str,
                        _('Snapin Transfer Log')
                    );
                    $files[$StorageNode->name][$str] = $file;
                    unset($file);
                }
                $files[$StorageNode->name] = array_filter(
                    (array)$files[$StorageNode->name]
                );
            } catch (Exception $e) {
                $files[$StorageNode->name] = [
                    $e->getMessage() => null,
                ];
            }
            $ip[$StorageNode->name] = $StorageNode->ip;
            self::$HookManager->processEvent(
                'LOG_VIEWER_HOOK',
                [
                    'files' => &$files,
                    'StorageNode' => &$StorageNode
                ]
            );
            unset($StorageGroup);
        }
        unset($StorageGroups);

        ob_start();
        echo '<select name="logtype" class="fog-select2" id="logToView">';
        foreach ($files as $nodename => &$filearray) {
            $first = true;
            foreach ((array)$filearray as $value => &$file) {
                if ($first) {
                    printf(
                        '<option disabled> ------- %s ------- </option>',
                        $nodename
                    );
                    $first = false;
                }
                printf(
                    '<option value="%s||%s"%s>%s</option>',
                    base64_encode($ip[$nodename]),
                    $file,
                    (
                        isset($_POST['logtype']) && $value == $_POST['logtype'] ?
                        ' selected' :
                        ''
                    ),
                    $value
                );
                unset($file);
            }
            unset($filearray);
        }
        unset($files);
        echo '</select>';
        $logSelector = ob_get_clean();

        // Line Selector
        $vals = [
            10,
            25,
            50,
            100,
            250,
            500,
            1000
        ];
        ob_start();
        echo '<select name="n" class="form-control" id="linesToView">';
        foreach ((array)$vals as $i => &$value) {
            printf(
                '<option value="%s"%s>%s</option>',
                $value,
                (
                    $value == filter_input(
                        INPUT_POST,
                        'n',
                        FILTER_SANITIZE_NUMBER_INT
                    ) ?
                    ' selected' :
                    ''
                ),
                $value
            );
            unset($value);
        }
        unset($vals);
        echo '</select>';
        $lineSelector = ob_get_clean();

        $this->title = _('FOG Log Viewer');

        $buttons = self::makeButton(
            'logresume',
            _('Resume'),
            'btn btn-success pull-right'
        );
        $buttons .= self::makeButton(
            'logpause',
            _('Pause'),
            'btn btn-warning pull-left'
        );

        echo self::makeFormTag(
            'form-horizontal',
            'logviewer-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '<hr/>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 control-label',
            'logToView',
            _('File')
        );
        echo $logSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 control-label',
            'linesToView',
            _('Lines')
        );
        echo $lineSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 control-label',
            'reverse',
            _('Reverse')
            . ' '
            . self::makeInput(
                '',
                'reverse',
                '',
                'checkbox',
                'reverse'
            )
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body" id="logsGoHere">';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Present the config screen.
     *
     * @return void
     */
    public function config()
    {
        self::$HookManager->processEvent('CONFIGURATION');

        $this->title = _('Configuration Import/Export');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'import',
                _('Import Database')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'input-group-btn',
                'import',
                '<span class="btn btn-info">'
                . _('Browse')
                . self::makeInput(
                    'hidden',
                    'dbFile',
                    '',
                    'file',
                    'import',
                    '',
                    true
                )
                . '</span>'
            )
            . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                '',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>'
        ];

        $buttons = self::makeButton(
            'exportdb',
            _('Export'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'importdb',
            _('Import'),
            'btn btn-warning pull-left'
        );

        self::$HookManager->processEvent(
            'IMPORT_DB_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'import-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        );
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Process import of config data
     *
     * @return void
     */
    public function configPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('IMPORT_POST');
        $Schema = self::getClass('Schema');
        $serverFault = false;
        try {
            if (isset($_POST['toExport'])) {
                $backup_name = 'fog_backup_'
                    . self::formatTime('', 'Ymd_His');
                $tmpfile = '/tmp/' . $backup_name;
                $data = '';
                self::getClass('Mysqldump')->start($tmpfile);
                if (!file_exists($tmpfile) || !is_readable($tmpfile)) {
                    throw new Exception(_('Could not read file from tmp folder.'));
                }
                $fh = fopen($tmpfile, 'rb');
                while (!feof($fh)) {
                    $data .= fread($fh, 4096);
                }
                fclose($fh);
                if (file_exists($tmpfile)) {
                    unlink($tmpfile);
                }
                echo json_encode(
                    [
                        'title' => _('Export Success'),
                        'msg' => _('Export Complete'),
                        '_filename' => $backup_name,
                        '_content' => $data
                    ]
                );
                unset($data);
                exit;
            } else {
                if ($_FILES['dbFile']['error'] > 0) {
                    throw new UploadException($_FILES['dbFile']['error']);
                }
                $original = $Schema->exportdb('', false);
                $tmp_name = htmlentities(
                    $_FILES['dbFile']['tmp_name'],
                    ENT_QUOTES | ENT_HTML401,
                    'utf-8'
                );
                $dir_name = dirname($tmp_name);
                $tmp_name = basename($tmp_name);
                $filename = sprintf(
                    '%s%s%s',
                    $dir_name,
                    DS,
                    $tmp_name
                );
                $result = self::getClass('Schema')->importdb($filename);
                if (true !== $result) {
                    $serverFault = true;
                    throw new Exception(_('Import failed!'));
                }
                $code = HTTPResponseCodes::HTTP_ACCEPTED;
                $hook = 'CONFIG_IMPORT_SUCCESS';
                $msg = json_encode(
                    [
                        'msg' => _('Imported successfully!'),
                        'title' => _('Import Database Success')
                    ]
                );
            }
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'CONFIG_IMPORT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Import Database Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Settings list tester.
     *
     * @return void
     */
    public function getSettingsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $needstobecheckbox = [
            'FOG_REGISTRATION_ENABLED' => true,
            'FOG_PXE_MENU_HIDDEN' => true,
            'FOG_QUICKREG_AUTOPOP' => true,
            'FOG_CLIENT_AUTOUPDATE' => true,
            'FOG_CLIENT_AUTOLOGOFF_ENABLED' => true,
            'FOG_CLIENT_CLIENTUPDATER_ENABLED' => true,
            'FOG_CLIENT_DIRECTORYCLEANER_ENABLED' => true,
            'FOG_CLIENT_DISPLAYMANAGER_ENABLED' => true,
            'FOG_CLIENT_GREENFOG_ENABLED' => true,
            'FOG_CLIENT_HOSTREGISTER_ENABLED' => true,
            'FOG_CLIENT_HOSTNAMECHANGER_ENABLED' => true,
            'FOG_CLIENT_POWERMANAGEMENT_ENABLED' => true,
            'FOG_CLIENT_PRINTERMANAGER_ENABLED' => true,
            'FOG_CLIENT_SNAPIN_ENABLED' => true,
            'FOG_CLIENT_TASKREBOOT_ENABLED' => true,
            'FOG_CLIENT_USERCLEANUP_ENABLED' => true,
            'FOG_CLIENT_USERTRACKER_ENABLED' => true,
            'FOG_ADVANCED_STATISTICS' => true,
            'FOG_CHANGE_HOSTNAME_EARLY' => true,
            'FOG_DISABLE_CHKDSK' => true,
            'FOG_HOST_LOOKUP' => true,
            'FOG_CAPTUREIGNOREPAGEHIBER' => true,
            'FOG_USE_ANIMATION_EFFECTS' => true,
            'FOG_USE_LEGACY_TASKLIST' => true,
            'FOG_USE_SLOPPY_NAME_LOOKUPS' => true,
            'FOG_PLUGINSYS_ENABLED' => true,
            'FOG_FORMAT_FLAG_IN_GUI' => true,
            'FOG_NO_MENU' => true,
            'FOG_ALWAYS_LOGGED_IN' => true,
            'FOG_ADVANCED_MENU_LOGIN' => true,
            'FOG_TASK_FORCE_REBOOT' => true,
            'FOG_EMAIL_ACTION' => true,
            'FOG_FTP_IMAGE_SIZE' => true,
            'FOG_KERNEL_DEBUG' => true,
            'FOG_ENFORCE_HOST_CHANGES' => true,
            'FOG_LOGIN_INFO_DISPLAY' => true,
            'MULTICASTGLOBALENABLED' => true,
            'SCHEDULERGLOBALENABLED' => true,
            'FILEDELETEQUEUEGLOBALENABLED' => true,
            'PINGHOSTGLOBALENABLED' => true,
            'IMAGESIZEGLOBALENABLED' => true,
            'IMAGEREPLICATORGLOBALENABLED' => true,
            'SNAPINREPLICATORGLOBALENABLED' => true,
            'SNAPINHASHGLOBALENABLED' => true,
            'FOG_QUICKREG_IMG_WHEN_REG' => true,
            'FOG_QUICKREG_PROD_KEY_BIOS' => true,
            'FOG_TASKING_ADV_SHUTDOWN_ENABLED' => true,
            'FOG_TASKING_ADV_WOL_ENABLED' => true,
            'FOG_TASKING_ADV_DEBUG_ENABLED' => true,
            'FOG_API_ENABLED' => true,
            'FOG_ENABLE_SHOW_PASSWORDS' => true,
            'FOG_IMAGE_LIST_MENU' => true,
            'FOG_REAUTH_ON_DELETE' => true,
            'FOG_REAUTH_ON_EXPORT' => true,
            'FOG_LOG_INFO' => true,
            'FOG_LOG_ERROR' => true,
            'FOG_LOG_DEBUG' => true,
        ];
        self::$HookManager->processEvent(
            'NEEDSTOBECHECKBOX',
            ['needstobecheckbox' => &$needstobecheckbox]
        );
        Route::ids('image', false);
        $imageids = json_decode(
            Route::getData(),
            true
        );
        Route::ids('group', false);
        $groupids = json_decode(
            Route::getData(),
            true
        );
        $viewvals = [-1, 10, 25, 50, 100, 250, 500];
        $regenrange = range(0, 24, .25);
        $needstobenumeric = [
            // FOG Boot Settings
            'FOG_PXE_MENU_TIMEOUT' => true,
            'FOG_PIGZ_COMP' => range(0, 22),
            'FOG_KEY_SEQUENCE' => range(1, 35),
            'FOG_PXE_HIDDENMENU_TIMEOUT' => true,
            'FOG_KERNEL_LOGLEVEL' => range(0, 7),
            'FOG_WIPE_TIMEOUT' => true,
            // FOG Linux Service Logs
            'SERVICE_LOG_SIZE' => true,
            // FOG Linux Service Sleep Times
            'PINGHOSTSLEEPTIME' => true,
            'SERVICESLEEPTIME' => true,
            'SNAPINREPSLEEPTIME' => true,
            'SCHEDULERSLEEPTIME' => true,
            'FILEDELETEQUEUESLEEPTIME' => true,
            'IMAGEREPSLEEPTIME' => true,
            'MULTICASESLEEPTIME' => true,
            // FOG Quick Registration
            'FOG_QUICKREG_IMG_ID' => self::fastmerge(
                (array)0,
                $imageids
            ),
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => self::fastmerge(
                (array)0,
                $groupids
            ),
            // FOG Service
            'FOG_CLIENT_CHECKIN_TIME' => true,
            'FOG_CLIENT_MAXSIZE' => true,
            'FOG_GRACE_TIMEOUT' => true,
            // FOG Service - Auto Log Off
            'FOG_CLIENT_AUTOLOGOFF_MIN' => true,
            // FOG Service - Display manager
            'FOG_CLIENT_DISPLAYMANAGER_X' => true,
            'FOG_CLIENT_DISPLAYMANAGER_Y' => true,
            'FOG_CLIENT_DISPLAYMANAGER_R' => true,
            // FOG Service - Host Register
            'FOG_QUICKREG_MAX_PENDING_MACS' => true,
            // FOG View Settings
            'FOG_VIEW_DEFAULT_SCREEN' => $viewvals,
            'FOG_DATA_RETURNED' => true,
            // General Settings
            'FOG_CAPTURERESIZEPCT' => true,
            'FOG_CHECKIN_TIMEOUT' => true,
            'FOG_MEMORY_LIMIT' => true,
            'FOG_SNAPIN_LIMIT' => true,
            'FOG_FTP_PORT' => range(1, 65535),
            'FOG_FTP_TIMEOUT' => true,
            'FOG_BANDWIDTH_TIME' => true,
            'FOG_URL_BASE_CONNECT_TIMEOUT' => true,
            'FOG_URL_BASE_TIMEOUT' => true,
            'FOG_URL_AVAILABLE_TIMEOUT' => true,
            'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT' => self::fastmerge(
                (array)0,
                range(2, 6)
            ),
            // Login Settings
            'FOG_INACTIVITY_TIMEOUT' => range(1, 24),
            'FOG_REGENERATE_TIMEOUT' => $regenrange,
            // Multicast Settings
            'FOG_UDPCAST_STARTINGPORT' => range(1, 65535),
            'FOG_MULTICASE_MAX_SESSIONS' => true,
            'FOG_UDPCAST_MAXWAIT' => true,
            'FOG_MULTICAST_PORT_OVERRIDE' => range(0, 65535),
            // Proxy Settings
            'FOG_PROXY_PORT' => range(0, 65535),
            // User Management
            'FOG_USER_MINPASSLENGTH' => true,
        ];
        $settingMan = self::getClass('SettingManager');
        $table = $settingMan->getTable();
        $dbcolumns = $settingMan->getColumns();
        $sqlStr = $settingMan->getQueryStr();
        $filterStr = $settingMan->getFilterStr();
        $totalStr = $settingMan->getTotalStr();
        $columns = [];
        foreach ($dbcolumns as $common => &$real) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            $columns[] = [
                'db' => $real,
                'dt' => 'inputValue',
                'formatter' => function ($d, $row) use (
                    $needstobenumeric,
                    $needstobecheckbox
                ) {
                    switch ($row['settingKey']) {
                        case 'FOG_VIEW_DEFAULT_SCREEN':
                            $vals = [
                                _('10') => 10,
                                _('25') => 25,
                                _('50') => 50,
                                _('100') => 100,
                                _('All') => -1
                            ];
                            ob_start();
                            echo '<select '
                                . 'class="form-control" name="'
                                . $row['settingID']
                                . '" autocomplete="off" id="'
                                . $row['settingKey']
                                . '">';
                            foreach ($vals as $text => &$val) {
                                echo '<option value="'
                                    . $val
                                    . '"'
                                    . (
                                        $val == $row['settingValue'] ?
                                        ' selected' :
                                        ''
                                    )
                                    . '>';
                                echo $text;
                                echo '</option>';
                                unset($val);
                            }
                            echo '</select>';
                            $input = ob_get_clean();
                            break;
                        case 'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT':
                            $vals = [
                                _('Partclone Gzip') => 0,
                                _('Partclone Gzip Split 200MiB') => 2,
                                _('Partclone Uncompressed') => 3,
                                _('Partclone Uncompressed 200MiB') => 4,
                                _('Partclone Zstd') => 5,
                                _('Partclone Zstd Split 200MiB') => 6
                            ];
                            ob_start();
                            echo '<select '
                                . 'class="form-control" name="'
                                . $row['settingID']
                                . '" autocomplete="off" id="'
                                . $row['settingKey']
                                . '">';
                            foreach ($vals as $text => &$val) {
                                echo '<option value="'
                                    . $val
                                    . '"'
                                    . (
                                        $val == $row['settingValue'] ?
                                        ' selected' :
                                        ''
                                    )
                                    . '>';
                                echo $text;
                                echo '</option>';
                                unset($val);
                            }
                            echo '</select>';
                            $input = ob_get_clean();
                            break;
                        case 'FOG_MULTICAST_DUPLEX':
                            $vals = [
                                'HALF_DUPLEX' => '--half-duplex',
                                'FULL_DUPLEX' => '--full-duplex'
                            ];
                            ob_start();
                            echo '<select '
                                . 'class="form-control" name="'
                                . $row['settingID']
                                . '" autocomplete="off" id="'
                                . $row['settingKey']
                                . '">';
                            foreach ($vals as $text => &$val) {
                                echo '<option value="'
                                    . $val
                                    . '"'
                                    . (
                                        $val == $row['settingValue'] ?
                                        ' selected' :
                                        ''
                                    )
                                    . '>';
                                echo $text;
                                echo '</option>';
                                unset($val);
                            }
                            echo '</select>';
                            $input = ob_get_clean();
                            break;
                        case 'FOG_DEFAULT_LOCALE':
                            $langs =& self::$foglang['Language'];
                            $vals = array_flip($langs);
                            ob_start();
                            echo '<select '
                                . 'class="form-control" name="'
                                . $row['settingID']
                                . '" autocomplete="off" id="'
                                . $row['settingKey']
                                . '">';
                            foreach ($vals as $text => &$val) {
                                echo '<option value="'
                                    . $val
                                    . '"'
                                    . (
                                        $val == $row['settingValue'] ?
                                        ' selected' :
                                        ''
                                    )
                                    . '>';
                                echo $text;
                                echo '</option>';
                                unset($val);
                            }
                            echo '</select>';
                            $input = ob_get_clean();
                            break;
                        case 'FOG_QUICKREG_IMG_ID':
                        case 'FOG_QUICKREG_GROUP_ASSOC':
                        case 'FOG_KEY_SEQUENCE':
                            switch ($row['settingKey']) {
                                case 'FOG_QUICKREG_IMG_ID':
                                    $objGetter = 'image';
                                    break;
                                case 'FOG_QUICKREG_GROUP_ASSOC':
                                    $objGetter = 'group';
                                    break;
                                case 'FOG_KEY_SEQUENCE':
                                    $objGetter = 'keysequence';
                                    break;
                            }
                            $input = self::getClass($objGetter.'manager')->buildSelectBox(
                                $row['settingValue'],
                                $row['settingID']
                            );
                            break;
                        case 'FOG_BOOT_EXIT_TYPE':
                        case 'FOG_EFI_BOOT_EXIT_TYPE':
                            $input = Setting::buildExitSelector(
                                $row['settingID'],
                                $row['settingValue'],
                                false,
                                $row['settingKey']
                            );
                            break;
                        case 'FOG_TZ_INFO':
                            $dt = self::niceDate('now');
                            $tzIDs = DateTimeZone::listIdentifiers();
                            ob_start();
                            echo '<select class="form-control" name="'
                                . $row['settingID']
                                . '" id="'
                                . $row['settingKey']
                                . '">';
                            foreach ((array)$tzIDs as $i => &$tz) {
                                $current_tz = self::getClass('DateTimeZone', $tz);
                                $offset = $current_tz->getOffset($dt);
                                $transition = $current_tz->getTransitions(
                                    $dt->getTimestamp(),
                                    $dt->getTimestamp()
                                );
                                $abbr = $transition[0]['abbr'];
                                $offset = sprintf(
                                    '%+03d:%02u',
                                    floor($offset / 3600),
                                    floor(abs($offset) % 3600 / 60)
                                );
                                printf(
                                    '<option value="%s"%s>%s [%s %s]</option>',
                                    $tz,
                                    (
                                        $row['settingValue'] == $tz ?
                                        ' selected' :
                                        ''
                                    ),
                                    $tz,
                                    $abbr,
                                    $offset
                                );
                                unset(
                                    $current_tz,
                                    $offset,
                                    $transition,
                                    $abbr,
                                    $offset,
                                    $tz
                                );
                            }
                            echo '</select>';
                            $input = ob_get_clean();
                            break;
                        case 'FOG_COMPANY_COLOR':
                            $input = self::makeInput(
                                'jscolor {required:false} {refine: false} form-control',
                                $row['settingID'],
                                '',
                                'text',
                                $row['settingKey'],
                                $row['settingValue'],
                                false,
                                false,
                                -1,
                                6
                            );
                            break;
                        case 'FOG_CLIENT_BANNER_SHA':
                            $input = self::makeInput(
                                'form-control',
                                $row['settingID'],
                                '',
                                'text',
                                $row['settingKey'],
                                $row['settingValue'],
                                false,
                                false,
                                -1,
                                -1,
                                '',
                                true
                            );
                            break;
                        case 'FOG_QUICKREG_OS_ID':
                            $image = new Image(self::getSetting('FOG_QUICKREG_IMG_ID'));
                            if (!$image->isValid()) {
                                $osname = _('No image specified');
                            } else {
                                $osname = $image->get('os')->get('name');
                            }
                            $input = '<p id="'
                                . $row['settingKey']
                                . '">'
                                . $osname
                                . '</p>';
                            break;
                        case 'FOG_CLIENT_BANNER_IMAGE':
                            $input = '<div class="input-group">'
                                . self::makeLabel(
                                    'input-group-btn',
                                    $row['settingKey'],
                                    '<span class="btn btn-info">'
                                    . _('Browse')
                                    . self::makeInput(
                                        'hidden',
                                        $row['settingID'],
                                        '',
                                        'file',
                                        $row['settingKey'],
                                        '',
                                        true
                                    )
                                    . '</span>'
                                )
                                . self::makeInput(
                                    'form-control filedisp',
                                    'banner',
                                    '',
                                    'text',
                                    '',
                                    $row['settingValue'],
                                    false,
                                    false,
                                    -1,
                                    -1,
                                    '',
                                    true
                                )
                                . '</div>';
                            break;
                        case 'FOG_COMPANY_TOS':
                        case 'FOG_AD_DEFAULT_OU':
                            $input = self::makeTextarea(
                                'form-control',
                                $row['settingID'],
                                '',
                                $row['settingKey'],
                                $row['settingValue']
                            );
                            break;
                        case (isset($needstobecheckbox[$row['settingKey']])):
                            $input = self::makeInput(
                                '',
                                $row['settingID'],
                                '',
                                'checkbox',
                                $row['settingKey'],
                                '',
                                false,
                                false,
                                -1,
                                -1,
                                ($row['settingValue'] > 0 ? 'checked' : '')
                            );
                            break;
                        case 'FOG_API_TOKEN':
                            $input = '<div class="input-group">';
                            $input .= self::makeInput(
                                'form-control token',
                                $row['settingID'],
                                '',
                                'text',
                                $row['settingKey'],
                                base64_encode($row['settingValue']),
                                false,
                                false,
                                -1,
                                -1,
                                '',
                                true
                            );
                            $input .= '<div class="input-group-btn">';
                            $input .= self::makeButton(
                                'resettoken',
                                _('Reset Token'),
                                'btn btn-warning resettoken'
                            );
                            $input .= '</div>';
                            $input .= '</div>';
                            break;
                        case (preg_match('#pass#i', $row['settingKey'])
                            && !preg_match('#(valid|min)#i', $row['settingKey'])):
                            switch ($row['settingKey']) {
                                case 'FOG_STORAGENODE_MYSQLPASS':
                                    $input = self::makeInput(
                                        'form-control',
                                        $row['settingID'],
                                        '',
                                        'text',
                                        $row['settingKey'],
                                        $row['settingValue']
                                    );
                                    break;
                                case 'FOG_AD_DEFAULT_PASSWORD':
                                    $input = '<div class="input-group">'
                                        . self::makeInput(
                                            'form-control',
                                            $row['settingID'],
                                            '',
                                            'password',
                                            $row['settingKey'],
                                            (
                                                $row['settingValue'] ?
                                                '********************************' :
                                                ''
                                            )
                                        )
                                        . '</div>';
                                    break;
                                default:
                                    $input = '<div class="input-group">'
                                        . self::makeInput(
                                            'form-control',
                                            $row['settingID'],
                                            '',
                                            'password',
                                            $row['settingKey'],
                                            $row['settingValue']
                                        )
                                        . '</div>';
                                    break;
                            }
                            break;
                        case 'FOG_PIGZ_COMP':
                            $input = self::makeInput(
                                'form-control slider',
                                $row['settingID'],
                                '6',
                                'text',
                                $row['settingKey'],
                                $row['settingValue'],
                                false,
                                false,
                                -1,
                                -1,
                                'data-slider-min="0" '
                                . 'data-slider-max="22" '
                                . 'data-slider-step="1" '
                                . 'data-slider-value="' . $row['settingValue'] . '" '
                                . 'data-slider-orientation="horizontal" '
                                . 'data-slider-selection="before" '
                                . 'data-slider-tooltip="show" '
                                . 'data-slider-id="blue"'
                            );
                            break;
                        case 'FOG_KERNEL_LOGLEVEL':
                            $input = self::makeInput(
                                'form-control slider',
                                $row['settingID'],
                                '4',
                                'text',
                                $row['settingKey'],
                                $row['settingValue'],
                                false,
                                false,
                                -1,
                                -1,
                                'data-slider-min="0" '
                                . 'data-slider-max="7" '
                                . 'data-slider-step="1" '
                                . 'data-slider-value="' . $row['settingValue'] . '" '
                                . 'data-slider-orientation="horizontal" '
                                . 'data-slider-selection="before" '
                                . 'data-slider-tooltip="show" '
                                . 'data-slider-id="blue"'
                            );
                            break;
                        case 'FOG_INACTIVITY_TIMEOUT':
                            $input = self::makeInput(
                                'form-control slider',
                                $row['settingID'],
                                '1',
                                'text',
                                $row['settingKey'],
                                $row['settingValue'],
                                false,
                                false,
                                -1,
                                -1,
                                'data-slider-min="1" '
                                . 'data-slider-max="24" '
                                . 'data-slider-step="1" '
                                . 'data-slider-value="' . $row['settingValue'] . '" '
                                . 'data-slider-orientation="horizontal" '
                                . 'data-slider-selection="before" '
                                . 'data-slider-tooltip="show" '
                                . 'data-slider-id="blue"'
                            );
                            break;
                        case 'FOG_REGENERATE_TIMEOUT':
                            $input = self::makeInput(
                                'form-control slider',
                                $row['settingID'],
                                '0.50',
                                'text',
                                $row['settingKey'],
                                $row['settingValue'],
                                false,
                                false,
                                -1,
                                -1,
                                'data-slider-min="0.25" '
                                . 'data-slider-max="24" '
                                . 'data-slider-step="0.25" '
                                . 'data-slider-value="' . $row['settingValue'] . '" '
                                . 'data-slider-orientation="horizontal" '
                                . 'data-slider-selection="before" '
                                . 'data-slider-tooltip="show" '
                                . 'data-slider-id="blue"'
                            );
                            break;
                        default:
                            $type = 'text';
                            if (isset($needstobenumeric[$row['settingKey']])) {
                                $type = 'number';
                            }
                            $input = self::makeInput(
                                'form-control',
                                $row['settingID'],
                                '',
                                $type,
                                $row['settingKey'],
                                $row['settingValue']
                            );
                    }
                    return $input;
                }
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                'settingID',
                $columns,
                $sqlStr,
                $filterStr,
                $totalStr
            )
        );
        exit;
    }
}
