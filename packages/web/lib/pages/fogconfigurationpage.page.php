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
        $this->name = _('FOG Configuration');
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
     * Builds the standard AdminLTE box scaffold shared by every sub-view.
     *
     * Replaces the hand-echoed box/card-header/card-body/card-footer skeleton
     * that each view used to repeat. Returns a string so callers can compose
     * it (e.g. wrap in a <form>).
     *
     * @param string $title The box title (already translated/escaped).
     * @param string $body  The card-body HTML.
     * @param array  $opts  color, collapse, help, footer, id, bodyId,
     *                      bodyClass, bodyAttrs.
     *
     * @return string
     */
    private function _box($title, $body, array $opts = [])
    {
        $color     = $opts['color']     ?? 'solid';
        $collapse  = $opts['collapse']  ?? false;
        $help      = $opts['help']      ?? '';
        $footer    = $opts['footer']    ?? '';
        $id        = $opts['id']        ?? '';
        $bodyId    = $opts['bodyId']    ?? '';
        $bodyClass = $opts['bodyClass'] ?? '';
        $bodyAttrs = $opts['bodyAttrs'] ?? '';

        $o = '';
        if ($id !== '') {
            $o .= '<div id="' . $id . '">';
        }
        $cardClass = ($color === 'solid' || $color === '')
            ? 'card'
            : 'card card-' . $color . ' card-outline';
        $o .= '<div class="' . $cardClass . '">';
        $o .= '<div class="card-header">';
        if ($collapse) {
            $o .= '<div class="card-tools float-end">'
                . self::$FOGCollapseBox
                . '</div>';
        }
        $o .= '<h4 class="card-title">' . $title . '</h4>';
        if ($help !== '') {
            $o .= '<p class="form-text">' . $help . '</p>';
        }
        $o .= '</div>';
        $o .= '<div class="card-body'
            . ($bodyClass !== '' ? ' ' . $bodyClass : '')
            . '"'
            . ($bodyId !== '' ? ' id="' . $bodyId . '"' : '')
            . ($bodyAttrs !== '' ? ' ' . $bodyAttrs : '')
            . '>';
        $o .= $body;
        $o .= '</div>';
        if ($footer !== '') {
            $o .= '<div class="card-footer">' . $footer . '</div>';
        }
        $o .= '</div>';
        if ($id !== '') {
            $o .= '</div>';
        }
        return $o;
    }
    /**
     * Emits a JSON response and exits. Centralizes the content-type header +
     * status code + json_encode + exit pattern repeated by the *Post methods.
     *
     * @param int   $code    HTTP status code.
     * @param array $payload Data to JSON-encode.
     *
     * @return never
     */
    private function _jsonExit($code, array $payload)
    {
        header('Content-type: application/json');
        $this->jsonSend($code, json_encode($payload));
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
            echo '<div class="card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo '<a data-bs-toggle="collapse" data-bs-parent="#nodekernvers" href="#'
                . $id
                . '">';
            echo $StorageNode->name;
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="'
                . $id
                . '" class="collapse">';
            echo '<div class="card-body">';
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
        echo '<div id="fogversion">';

        // FOG Version Information. Body is filled in by fog.about.home.js via
        // the .placehere hook (it reads the vers attribute).
        echo $this->_box(
            $this->title,
            '',
            [
                'bodyClass' => 'placehere',
                'bodyAttrs' => 'vers="' . FOG_VERSION . '"'
            ]
        );

        // Per-node kernel versions. The grouping div id is the accordion parent
        // (#nodekernvers) referenced by each node panel in $renderNodes.
        echo $this->_box(
            _('Versions'),
            $renderNodes,
            ['id' => 'nodekernvers']
        );

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
        echo $this->_box(
            $this->title,
            $contents,
            ['id' => 'license']
        );
    }
    /**
     * Show the kernel update page.
     *
     * @return void
     */
    public function kernel()
    {
        $this->_downloadView('kernel');
    }
    /**
     * Process the kernel download request.
     *
     * @return void
     */
    public function kernelPost()
    {
        $this->_downloadPost('kernel');
    }
    /**
     * Show the initrd update page.
     *
     * @return void
     */
    public function initrd()
    {
        $this->_downloadView('initrd');
    }
    /**
     * Process the initrd download request.
     *
     * @return void
     */
    public function initrdPost()
    {
        $this->_downloadPost('initrd');
    }
    /**
     * Render the kernel/initrd download view.
     *
     * kernel() and initrd() were byte-for-byte identical except for the words
     * "kernel"/"initrd" and the name-input id. Both JS files target the same
     * element ids (download-send, downloadModal, confirmDownload, dataTable),
     * differing only on {type}-name, so the markup is fully shared here.
     *
     * @param string $type 'kernel' or 'initrd'.
     *
     * @return void
     */
    private function _downloadView($type)
    {
        $isKernel = ($type === 'kernel');
        $this->title = $isKernel
            ? _('Kernel Update')
            : _('initrd (Initial Ramdisk) Update');

        $this->headerData = [
            _('Tag Name'),
            _('Version'),
            _('Architecture'),
            _('Type'),
            _('Date')
        ];
        $this->attributes = [[], [], [], [], []];

        $buttons = self::makeButton(
            'download-send',
            _('Download'),
            'btn btn-primary float-end'
        );
        $confirmDownloadBtn = self::makeButton(
            'confirmDownload',
            _('Download'),
            'btn btn-primary float-end'
        );
        $cancelDownloadBtn = self::makeButton(
            'cancelDownload',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );

        if ($isKernel) {
            $confirmNew = _('Confirm you would like to download a new kernel');
            $nameForNew =
                _('Use the input below to set the name for your new kernel.');
            $help = sprintf(
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
        } else {
            $confirmNew = _('Confirm you would like to download a new initrd');
            $nameForNew =
                _('Use the input below to set the name for your new initrd.');
            $help = sprintf(
                '%s %s %s. %s, %s %s, %s.',
                _('This section allows you to update'),
                _('the initrd (initial ramdisk) which is alongside the'),
                _('kernel to boot the client computers'),
                _('This installation process may take a few minutes'),
                _('as FOG will attempt to go out to the internet'),
                _('to get the requested initrd'),
                _('so if it seems like the process is hanging please be patient')
            );
        }

        $downloadModal = self::makeModal(
            'downloadModal',
            _('Confirm Download'),
            '<p class="form-text">'
            . $confirmNew
            . ' '
            . _('to your fog storage node.')
            . ' '
            . $nameForNew
            . '</p>'
            . '<div class="' . $type . '-input">'
            . self::makeInput(
                'form-control',
                $type . '-name',
                '',
                'text',
                $type . '-name',
                '',
                true
            )
            . '</div>',
            $confirmDownloadBtn . $cancelDownloadBtn,
            '',
            'info'
        );

        echo $this->_box(
            $this->title,
            $this->process(
                12,
                'dataTable',
                $buttons,
                'display table table-bordered table-striped'
            ),
            [
                'id' => $type . '-update',
                'help' => $help,
                'footer' => $downloadModal
            ]
        );
    }
    /**
     * Process a kernel/initrd download request.
     *
     * kernelPost()/initrdPost() were identical except for the session-key
     * names; those are reconstructed from $type to match the readers in
     * fogpage.class.php (allow_ajax_kdl/idl, {dest,tmp,dl}-{type}-file).
     *
     * @param string $type 'kernel' or 'initrd'.
     *
     * @return void
     */
    private function _downloadPost($type)
    {
        self::checkAuthAndCSRF();
        $dstName = filter_input(INPUT_POST, 'dstName');
        $file = trim(base64_decode(filter_input(INPUT_POST, 'file')));
        $tmpFile = sprintf(
            '%s%s%s%s',
            DS,
            str_replace(["\\", '/'], '', sys_get_temp_dir()),
            DS,
            basename(trim($dstName))
        );
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        $abbr = ($type === 'kernel') ? 'kdl' : 'idl';
        $_SESSION['allow_ajax_' . $abbr] = true;
        $_SESSION['dest-' . $type . '-file'] = basename(trim($dstName));
        $_SESSION['tmp-' . $type . '-file'] = $tmpFile;
        $_SESSION['dl-' . $type . '-file'] = $file;
        try {
            if (empty($dstName)) {
                throw new Exception(_('A filename is required!'));
            }
            if (empty($file)) {
                throw new Exception(
                    _('No external data to download the file from')
                );
            }
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_SUCCESS,
                [
                    'msg' => _('Starting download'),
                    'title' => _('Download Starting')
                ]
            );
        } catch (Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Start Download Fail')
                ]
            );
        }
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

        echo $this->_box(
            $this->title,
            $this->process(
                12,
                'ipxe-table',
                '',
                'display table table-bordered table-striped'
            ),
            [
                'help' => _('For ipxe command related items (e.g. colour, cpair, etc...) click ')
                . '<a href="http://ipxe.org/cmd" target="_blank">'
                . _('here')
                . '</a>'
            ]
        );
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
            // Only the value field carries the rendered input column; binding
            // it to settingValue lets the global search match values too.
            if ($common !== 'value') {
                continue;
            }
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
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
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
        ));
    }
    /**
     * Stores the changes made.
     *
     * @return void
     */
    public function pxemenuPost()
    {
        self::checkAuthAndCSRF();
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
        $this->jsonSend($code, $msg);
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
            'btn btn-outline-secondary float-end'
        );
        $modalupdatebtn .= self::makeButton(
            'updatemacsCancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start'
        );
        $modaldeletebtn = self::makeButton(
            'deletemacsConfirm',
            _('Confirm'),
            'btn btn-outline-secondary float-end'
        );
        $modaldeletebtn .= self::makeButton(
            'deletemacsCancel',
            _('Cancel'),
            'btn btn-outline-secondary float-start'
        );
        $buttons = self::makeButton(
            'updatemacs',
            _('Update MAC List'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'deletemacs',
            _('Delete MAC List'),
            'btn btn-danger float-start'
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
        echo $this->_box(
            $this->title,
            _('Current Records')
            . ': '
            . '<span id="lookupcount">'
            . self::getMACLookupCount()
            . '</span>',
            [
                'help' => _('Import known mac address makers')
                . '<br>'
                . '<a href="http://standards-oui.ieee.org/oui.txt">'
                . 'http://standards-oui.ieee.org/oui.txt'
                . '</a>',
                'footer' => $buttons . $modalupdate . $modaldelete
            ]
        );
    }
    /**
     * Safes the data for real for the mac address stuff.
     *
     * @return void
     */
    public function maclistPost()
    {
        self::checkAuthAndCSRF();
        if (isset($_POST['update'])) {
            $url = 'https://standards-oui.ieee.org/oui/oui.txt';
            $data = self::$FOGURLRequests->process($url);
            $data = is_array($data) ? array_shift($data) : $data;
            $items = [];
            $start = 18;
            $imported = 0;
            $pat = '#^([0-9a-fA-F]{2}[:\-]){2}([0-9a-fA-F]{2}).*$#';
            foreach (preg_split("/((\r?\n)|(\n?\r))/", (string)$data) as $line) {
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
                // Build the refreshed list in a side table and swap it in
                // atomically, rather than truncating up front. The live table
                // keeps serving lookups for the whole import, and a failed or
                // empty download leaves it untouched instead of wiping it. A
                // fresh side table also sidesteps the install-dependent unique
                // index on the live table (present only on upgraded installs).
                $OUITable = self::getClass('OUI', '', true);
                $OUITable = $OUITable['databaseTable'];
                $tmpTable = $OUITable . '_temp';
                self::$DB->query("DROP TABLE IF EXISTS `$tmpTable`");
                self::$DB->query("CREATE TABLE `$tmpTable` LIKE `$OUITable`");
                list(
                    $first_id,
                    $affected_rows
                ) = self::getClass('OUIManager')
                ->insertBatch(
                    [
                        'prefix',
                        'name'
                    ],
                    $items,
                    $tmpTable
                );
                $imported += $affected_rows;
                if ($imported > 0) {
                    $oldTable = $OUITable . '_old';
                    self::$DB->query("DROP TABLE IF EXISTS `$oldTable`");
                    self::$DB->query(
                        "RENAME TABLE `$OUITable` TO `$oldTable`, "
                        . "`$tmpTable` TO `$OUITable`"
                    );
                    self::$DB->query("DROP TABLE IF EXISTS `$oldTable`");
                } else {
                    self::$DB->query("DROP TABLE IF EXISTS `$tmpTable`");
                }
                unset($items);
            }
            unset($first_id);
        }
        if (isset($_POST['clear'])) {
            self::clearMACLookupTable();
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            ['count' => self::getMACLookupCount()]
        ));
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
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($osname ? $osname : _('No Image specified')));
    }
    /**
     * Single source of truth for setting validation metadata, shared by
     * settingsPost() (server-side validation) and getSettingsList() (input
     * rendering). These maps were previously duplicated in both methods and
     * had to be kept in sync by hand.
     *
     * Numeric constraints are expressed as one of:
     *   true                       any numeric value
     *   ['min' => x, 'max' => y]   integer-valued, within an inclusive range
     *   ['set' => [...]]           value matching one of an explicit list
     *
     * Expressing the large port ranges as bounds (rather than the old
     * range(1, 65535) membership arrays) avoids allocating hundreds of
     * thousands of array elements on every settings load/save.
     *
     * @return array{checkbox:array,numeric:array,ip:array}
     */
    private function _settingsMeta()
    {
        $checkbox = [
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
            ['needstobecheckbox' => &$checkbox]
        );

        $imageids = Route::getIds('image', false);
        $groupids = Route::getIds('group', false);

        $viewvals = [-1, 10, 25, 50, 100, 250, 500];
        $regenrange = range(0, 24, .25);
        array_shift($regenrange);

        $numeric = [
            // FOG Boot Settings
            'FOG_PXE_MENU_TIMEOUT' => true,
            'FOG_PIGZ_COMP' => ['min' => 0, 'max' => 22],
            'FOG_KEY_SEQUENCE' => ['min' => 1, 'max' => 35],
            'FOG_PXE_HIDDENMENU_TIMEOUT' => true,
            'FOG_KERNEL_LOGLEVEL' => ['min' => 0, 'max' => 7],
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
            'FOG_QUICKREG_IMG_ID' => ['set' => self::fastmerge((array)0, $imageids)],
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => ['set' => self::fastmerge((array)0, $groupids)],
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
            'FOG_VIEW_DEFAULT_SCREEN' => ['set' => $viewvals],
            'FOG_DATA_RETURNED' => true,
            // General Settings
            'FOG_CAPTURERESIZEPCT' => true,
            'FOG_CHECKIN_TIMEOUT' => true,
            'FOG_MEMORY_LIMIT' => true,
            'FOG_SNAPIN_LIMIT' => true,
            'FOG_FTP_PORT' => ['min' => 1, 'max' => 65535],
            'FOG_FTP_TIMEOUT' => true,
            'FOG_BANDWIDTH_TIME' => true,
            'FOG_URL_BASE_CONNECT_TIMEOUT' => true,
            'FOG_URL_BASE_TIMEOUT' => true,
            'FOG_URL_AVAILABLE_TIMEOUT' => true,
            'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT' => ['set' => self::fastmerge((array)0, range(2, 6))],
            // Login Settings
            'FOG_INACTIVITY_TIMEOUT' => ['min' => 1, 'max' => 24],
            'FOG_REGENERATE_TIMEOUT' => ['set' => $regenrange],
            // Multicast Settings
            'FOG_UDPCAST_STARTINGPORT' => ['min' => 1, 'max' => 65535],
            // Was FOG_MULTICASE_MAX_SESSIONS, which matches no setting, so
            // this bound had never actually been applied to anything.
            'FOG_MULTICAST_MAX_SESSIONS' => true,
            'FOG_UDPCAST_MAXWAIT' => true,
            // Deliberately not numeric: this is now a comma separated pool
            // of base ports. MulticastSession::portPool() drops anything
            // udp-sender could not use.
            // Proxy Settings
            'FOG_PROXY_PORT' => ['min' => 0, 'max' => 65535],
            // User Management
            'FOG_USER_MINPASSLENGTH' => true,
        ];

        $ip = [
            // Multicast Settings
            'FOG_MULTICAST_ADDRESS' => true,
            'FOG_MULTICAST_RENDEZVOUS' => true,
            // Proxy Settings
            'FOG_PROXY_IP' => true,
        ];

        // Settings whose value is baked into the page shell (other/index.php)
        // or the theme CSS loaded in the <head>. The settings page only reloads
        // its own fragment after a save, so these do not visibly apply until the
        // whole page is reloaded. Each was verified to be actively consumed:
        //   FOG_THEME              -> page.class.php loads css/$FOG_THEME
        //   FOG_VIEW_DEFAULT_SCREEN-> shell #pageLength (other/index.php)
        //   FOG_TABLE_SCROLL_MODE  -> shell #scrollMode (other/index.php)
        //   FOG_PLUGINSYS_ENABLED  -> plugin menus/pages loaded at boot
        $refresh = [
            'FOG_THEME' => true,
            'FOG_VIEW_DEFAULT_SCREEN' => true,
            'FOG_TABLE_SCROLL_MODE' => true,
            'FOG_PLUGINSYS_ENABLED' => true,
        ];
        self::$HookManager->processEvent(
            'NEEDSPAGEREFRESH',
            ['needspagerefresh' => &$refresh]
        );

        return [
            'checkbox' => $checkbox,
            'numeric' => $numeric,
            'ip' => $ip,
            'refresh' => $refresh,
        ];
    }
    /**
     * Build a standard settings <select> input.
     *
     * Replaces several byte-for-byte identical inline select/option loops in
     * the settings list formatter.
     *
     * @param int|string $id    the settingID (used as the field name)
     * @param string     $key   the settingKey (used as the element id)
     * @param mixed      $value the currently stored value (for selection)
     * @param array      $vals  map of display text => option value
     *
     * @return string
     */
    private static function _selectInput($id, $key, $value, array $vals)
    {
        $html = '<select '
            . 'class="form-control" name="'
            . $id
            . '" autocomplete="off" id="'
            . $key
            . '">';
        foreach ($vals as $text => $val) {
            $html .= '<option value="'
                . Initiator::e($val)
                . '"'
                . (
                    $val == $value ?
                    ' selected' :
                    ''
                )
                . '>'
                . Initiator::e($text)
                . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
    /**
     * Build a bootstrap-slider settings input.
     *
     * Replaces several near-identical inline makeInput() slider calls in the
     * settings list formatter that differed only in default/min/max/step.
     *
     * @param int|string $id      the settingID (used as the field name)
     * @param string     $key     the settingKey (used as the element id)
     * @param mixed      $value   the currently stored value
     * @param string     $default the placeholder/default value
     * @param string     $min     data-slider-min
     * @param string     $max     data-slider-max
     * @param string     $step    data-slider-step
     *
     * @return string
     */
    private static function _sliderInput($id, $key, $value, $default, $min, $max, $step)
    {
        return self::makeInput(
            'form-control slider',
            $id,
            $default,
            'text',
            $key,
            $value,
            false,
            false,
            -1,
            -1,
            'data-slider-min="' . $min . '" '
            . 'data-slider-max="' . $max . '" '
            . 'data-slider-step="' . $step . '" '
            . 'data-slider-value="' . $value . '" '
            . 'data-slider-orientation="horizontal" '
            . 'data-slider-selection="before" '
            . 'data-slider-tooltip="show" '
            . 'data-slider-id="blue"'
        );
    }
    /**
     * Save updates to the fog settings information.
     *
     * @return void
     */
    /**
     * Renders the value-side input control for a single setting row.
     *
     * Shared by the server-side settings list (getSettingsList) and the
     * server-rendered category panels (_renderSettingsPanels). $row uses the
     * real DB column names (settingID, settingKey, settingValue, ...).
     *
     * @param array $row               the setting row (real column names)
     * @param array $needstobenumeric  numeric-constraint map from _settingsMeta
     * @param array $needstobecheckbox checkbox-key map from _settingsMeta
     *
     * @return string
     */
    private static function _renderSettingInput(
        array $row,
        array $needstobenumeric,
        array $needstobecheckbox
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
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_TABLE_SCROLL_MODE':
                $vals = [
                    _('Infinite scroll') => 'infinite',
                    _('Paged') => 'paged'
                ];
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
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
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_MULTICAST_DUPLEX':
                $vals = [
                    'HALF_DUPLEX' => '--half-duplex',
                    'FULL_DUPLEX' => '--full-duplex'
                ];
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
                break;
            case 'FOG_DEFAULT_LOCALE':
                $langs =& self::$foglang['Language'];
                $vals = array_flip($langs);
                $input = self::_selectInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    $vals
                );
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
                    $row['settingID'],
                    'name',
                    '',
                    false,
                    'id',
                    $row['settingKey']
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
                        Initiator::e($tz),
                        (
                            $row['settingValue'] == $tz ?
                            ' selected' :
                            ''
                        ),
                        Initiator::e($tz),
                        Initiator::e($abbr),
                        Initiator::e($offset)
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
                        'btn btn-info',
                        $row['settingKey'],
                        _('Browse')
                        . self::makeInput(
                            'd-none',
                            $row['settingID'],
                            '',
                            'file',
                            $row['settingKey'],
                            '',
                            true
                        )
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
                $input .= self::makeButton(
                    'resettoken',
                    _('Reset Token'),
                    'btn btn-warning resettoken'
                );
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
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '6',
                    '0',
                    '22',
                    '1'
                );
                break;
            case 'FOG_KERNEL_LOGLEVEL':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '4',
                    '0',
                    '7',
                    '1'
                );
                break;
            case 'FOG_INACTIVITY_TIMEOUT':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '1',
                    '1',
                    '24',
                    '1'
                );
                break;
            case 'FOG_REGENERATE_TIMEOUT':
                $input = self::_sliderInput(
                    $row['settingID'],
                    $row['settingKey'],
                    $row['settingValue'],
                    '0.50',
                    '0.25',
                    '24',
                    '0.25'
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
        return $input;
    }
    public function settingsPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('SETTINGS_POST');
        $meta = $this->_settingsMeta();
        $checkbox = $meta['checkbox'];
        $needstobenumeric = $meta['numeric'];
        $needstobeip = $meta['ip'];
        unset($findWhere, $setWhere);

        $serverFault = false;
        try {
            parse_str(
                file_get_contents('php://input'),
                $vars
            );
            $combined = $vars + $_POST + $_FILES;
            // Initialised before the loop, like the two sibling savers above.
            // The body has three `continue` paths that skip the append, and
            // $combined can be empty, so a post that changes nothing left
            // $items undefined -- and the count below reads it, which is a
            // warning rather than a silent 0. `?:` there was papering over
            // the missing initialisation; `??` would have hidden it too.
            $items = [];
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
                if ($val && $val == ($set ?? '')) {
                    continue;
                }
                if (isset($checkbox[$name])) {
                    $set = intval($set) < 1 ? 0 : 1;
                } elseif (isset($needstobenumeric[$name])) {
                    $constraint = $needstobenumeric[$name];
                    $allowsZero = ($constraint === true)
                        ? false
                        : (isset($constraint['set'])
                            ? in_array(0, $constraint['set'])
                            : ($constraint['min'] <= 0 && $constraint['max'] >= 0));
                    if ($allowsZero && !$set) {
                        $set = 0;
                    }
                    if (!is_numeric($set)) {
                        throw new Exception(
                            $name . ' ' . _('value must be numeric')
                        );
                    }
                    if ($constraint !== true) {
                        $inRange = isset($constraint['set'])
                            ? in_array($set, $constraint['set'])
                            : (floor($set) == $set
                                && $set >= $constraint['min']
                                && $set <= $constraint['max']);
                        if (!$inRange) {
                            throw new Exception(
                                $name . ' ' . _('value is not in the required range')
                            );
                        }
                    }
                } elseif (isset($needstobeip[$name])) {
                    if (!filter_var($set, FILTER_VALIDATE_IP) and $set != 0 and $set) {
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
                        $extensionCheck = strtolower(pathinfo($set, PATHINFO_EXTENSION));
                        if (!in_array($extensionCheck, $validExtensions)) {
                            throw new Exception(
                                _('Upload file extension must be, jpg, jpeg, or png')
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
        $this->jsonSend($code, $msg);
    }
    /**
     * Flushes the per-process settings cache and raises the cross-process
     * flush signal (AJAX).
     *
     * @return void
     */
    public function cacheFlushPost()
    {
        self::checkAuthAndCSRF();
        try {
            FOGBase::clearSettingsCache();
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_SUCCESS,
                [
                    'msg' => _('Settings cache flushed'),
                    'title' => _('Cache Flushed')
                ]
            );
        } catch (Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Cache Flush Failed')
                ]
            );
        }
    }
    /**
     * Reloads all settings into the cache with a single query and raises the
     * cross-process flush signal (AJAX).
     *
     * @return void
     */
    public function cacheRefreshPost()
    {
        self::checkAuthAndCSRF();
        try {
            $count = FOGBase::refreshSettingsCache();
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_SUCCESS,
                [
                    'msg' => sprintf(
                        _('Reloaded %d setting(s) into cache'),
                        $count
                    ),
                    'title' => _('Cache Refreshed')
                ]
            );
        } catch (Exception $e) {
            $this->_jsonExit(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                [
                    'error' => $e->getMessage(),
                    'title' => _('Cache Refresh Failed')
                ]
            );
        }
    }
    /**
     * Tablize the fog settings.
     *
     * @return void
     */
    public function settings()
    {
        $this->title = _('FOG Settings');

        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('FOG Settings');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo '<div class="input-group input-group-sm settings-search-box">';
        echo '<input type="text" id="settings-search" class="form-control" '
            . 'placeholder="' . _('Search settings') . '" autocomplete="off">';
        echo '<button type="button" id="settings-search-clear" '
            . 'class="btn btn-secondary" title="' . _('Clear') . '">'
            . '<i class="fa fa-times"></i></button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body" id="settings-content">';
        echo $this->_renderSettings();
        echo '</div>';
        echo '<div class="card-footer">';
        echo '<button type="button" id="settings-cache-flush" '
            . 'class="btn btn-warning">'
            . _('Flush Settings Cache')
            . '</button> ';
        echo '<button type="button" id="settings-cache-refresh" '
            . 'class="btn btn-primary">'
            . _('Refresh Settings Cache')
            . '</button>';
        // Read-only cache snapshot for this request. On the web tier static
        // state is reset per request, so these counts reflect the work done to
        // render this page (boot, auth, routing, plugins); reload to re-sample.
        $stats = self::getSettingsCacheStats();
        $flushAge = $stats['flushAgeSeconds'];
        echo '<dl class="dl-horizontal dl-spaced">';
        echo '<dt>' . _('Keys cached') . '</dt><dd>'
            . (int) $stats['keysCached'] . '</dd>';
        echo '<dt>' . _('Hits / Misses / Queries') . '</dt><dd>'
            . (int) $stats['hits'] . ' / '
            . (int) $stats['misses'] . ' / '
            . (int) $stats['dbQueries']
            . ' (' . (float) $stats['hitRatePct'] . '% '
            . _('hit rate') . ', ' . _('this request') . ')</dd>';
        echo '<dt>' . _('TTL') . '</dt><dd>'
            . (int) $stats['ttl'] . 's</dd>';
        $file = $stats['fileCache'];
        echo '<dt>' . _('Persistent file') . '</dt><dd>'
            . (!$file['enabled']
                ? _('disabled')
                : ($file['exists']
                    ? _('present') . ' (' . (int) $file['ageSeconds'] . 's '
                        . _('old') . ')'
                    : _('not built yet')))
            . '</dd>';
        echo '<dt>' . _('Last flush') . '</dt><dd>'
            . ($flushAge === null
                ? _('never')
                : (int) $flushAge . 's ' . _('ago'))
            . '</dd>';
        if (count($stats['cachedKeys']) > 0) {
            // Collapsed by default: this list can run to hundreds of keys and
            // would otherwise dominate the panel (worst on mobile). The count
            // stays visible in the summary; tap/click to expand the names.
            echo '<dt>' . _('Cached keys') . '</dt><dd>'
                . '<details class="cached-keys">'
                . '<summary>' . (int) count($stats['cachedKeys'])
                . ' ' . _('keys') . '</summary>'
                . '<small class="text-muted">'
                . Initiator::e(implode(', ', array_keys($stats['cachedKeys'])))
                . '</small></details></dd>';
        }
        echo '</dl>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Builds the category-nav + form-panel body for the settings view.
     *
     * Settings are fetched once, grouped by category, and rendered as one
     * panel per category (all server-side). The left nav and the search box
     * (client side) drive which panel/rows are visible. Reused verbatim by
     * settingsContent() so the JS can refresh the body after a save without
     * reloading the whole page.
     *
     * @return string
     */
    private function _renderSettings()
    {
        $meta = $this->_settingsMeta();
        $needstobecheckbox = $meta['checkbox'];
        $needstobenumeric = $meta['numeric'];
        $needsrefresh = $meta['refresh'];
        $refreshtip = _(
            'Changing this setting takes effect after you reload the page. '
            . 'A hard refresh (Ctrl+F5, or Cmd+Shift+R) may be required.'
        );

        $table = self::getClass('SettingManager')->getTable();
        $sql = 'SELECT `settingID`, `settingKey`, `settingDesc`, '
            . '`settingValue`, `settingCategory` FROM `' . $table . '` '
            . 'ORDER BY `settingCategory` ASC, `settingKey` ASC';
        $rows = self::$DB->query($sql)
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $byCat = [];
        foreach ((array) $rows as $row) {
            $cat = trim((string) $row['settingCategory']);
            if ($cat === '') {
                $cat = _('Uncategorized');
            }
            $byCat[$cat][] = $row;
        }
        ksort($byCat, SORT_NATURAL | SORT_FLAG_CASE);

        ob_start();
        echo '<div class="row settings-layout">';

        // Left category nav.
        echo '<div class="col-md-3 col-sm-4 settings-nav-col">';
        echo '<ul class="nav nav-pills flex-column" id="settings-nav">';
        $first = true;
        foreach ($byCat as $cat => $catRows) {
            echo '<li class="settings-nav-item' . ($first ? ' active' : '') . '">'
                . '<a href="#" data-cat="' . Initiator::e($cat) . '">'
                . Initiator::e($cat)
                . ' <span class="badge">' . count($catRows) . '</span>'
                . '</a></li>';
            $first = false;
        }
        echo '</ul>';
        echo '</div>';

        // Right form panels.
        echo '<div class="col-md-9 col-sm-8 settings-panel-col">';
        $first = true;
        foreach ($byCat as $cat => $catRows) {
            echo '<div class="settings-panel' . ($first ? ' active' : '') . '" '
                . 'data-cat="' . Initiator::e($cat) . '">';
            // Doubles as a section heading on desktop and an accordion toggle
            // on mobile (see fog.about.settings.js / fog-default-ui.scss).
            echo '<h4 class="settings-panel-title" '
                . 'data-cat="' . Initiator::e($cat) . '">'
                . '<span>' . Initiator::e($cat) . '</span>'
                . '<i class="fa fa-chevron-down settings-panel-caret"></i>'
                . '</h4>';
            echo '<div class="settings-panel-body">';
            foreach ($catRows as $row) {
                $desc = trim((string) $row['settingDesc']);
                $wantsrefresh = isset($needsrefresh[$row['settingKey']]);
                $input = self::_renderSettingInput(
                    $row,
                    $needstobenumeric,
                    $needstobecheckbox
                );
                // Search haystack: key + description + value. Value is capped
                // so a setting holding a long blob can't bloat the attribute.
                // "refresh reload" lets the search box surface the flagged ones.
                $haystack = strtolower(
                    $row['settingKey'] . ' ' . $desc . ' '
                    . substr((string) $row['settingValue'], 0, 200)
                    . ($wantsrefresh ? ' refresh reload hard refresh' : '')
                );
                // One tooltip per label: the description, with the reload note
                // appended for flagged settings. Keeping it on the label (not a
                // nested icon) avoids two overlapping tooltips on the same row.
                $tip = $desc;
                if ($wantsrefresh) {
                    $tip .= ($tip !== '' ? '  —  ' : '') . $refreshtip;
                }
                echo '<div class="form-group settings-row" '
                    . 'data-search="'
                    . Initiator::e($haystack)
                    . '">';
                echo '<label class="col-form-label settings-label" for="'
                    . Initiator::e($row['settingKey']) . '"';
                if ($tip !== '') {
                    echo ' data-bs-toggle="tooltip" data-bs-placement="top" title="'
                        . Initiator::e($tip) . '"';
                }
                echo '>' . Initiator::e($row['settingKey']);
                if ($wantsrefresh) {
                    // Visual marker only (no own tooltip); the label tooltip
                    // above already carries the reload note.
                    echo ' <i class="fa fa-refresh text-muted settings-refresh-note"'
                        . ' aria-hidden="true"></i>';
                }
                echo '</label>';
                echo '<div class="settings-control">' . $input . '</div>';
                echo '</div>';
            }
            echo '</div>'; // .settings-panel-body
            echo '</div>'; // .settings-panel
            $first = false;
        }
        echo '<div class="settings-noresults d-none text-muted">'
            . _('No settings match your search.') . '</div>';
        echo '</div>';

        echo '</div>';
        return ob_get_clean();
    }
    /**
     * AJAX fragment: the settings body only (nav + panels).
     *
     * Used by the settings JS to refresh values/derived fields after a save
     * without a full page reload.
     *
     * @return void
     */
    public function settingsContent()
    {
        echo $this->_renderSettings();
        exit;
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
        $logtype = _('error');
        $logparse = function ($log) use (
            &$files,
            &$StorageNode,
            &$logtype
        ) {
            $str = sprintf(
                _('%s %s log (%s)'),
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
                $logtype = _('access');
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
                    Initiator::e(base64_encode($ip[$nodename])),
                    Initiator::e($file),
                    (
                        isset($_POST['logtype']) && $value == $_POST['logtype'] ?
                        ' selected' :
                        ''
                    ),
                    Initiator::e($value)
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
                Initiator::e($value),
                (
                    $value == filter_input(
                        INPUT_POST,
                        'n',
                        FILTER_SANITIZE_NUMBER_INT
                    ) ?
                    ' selected' :
                    ''
                ),
                Initiator::e($value)
            );
            unset($value);
        }
        unset($vals);
        echo '</select>';
        $lineSelector = ob_get_clean();

        $this->title = _('FOG Log Viewer');

        // One self-relabelling toggle, not a pause/resume pair -- pausing the
        // live tail destroys nothing so Pause never belonged on the left, and
        // only ever one of the two was pressable. Labels are the shared
        // "Pause/Resume Reload" pair so this button reads identically to the
        // task and multicast panes. Sole right-side button, so primary.
        $buttons = self::makeReloadToggle(
            'logreload-toggle',
            'btn btn-primary float-end'
        );

        echo self::makeFormTag(
            '',
            'logviewer-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '<hr/>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'logToView',
            _('File')
        );
        echo $logSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'linesToView',
            _('Lines')
        );
        echo $lineSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
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
        echo '<div class="card-body" id="logsGoHere">';
        echo '</div>';
        echo '<div class="card-footer">';
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

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'import',
                _('Import Database')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'btn btn-info',
                'import',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'dbFile',
                    '',
                    'file',
                    'import',
                    '',
                    true
                )
            )
            . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                'dbfiledisp',
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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'importdb',
            _('Import'),
            'btn btn-warning float-start'
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
            '',
            'import-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        );
        echo $this->_box(
            $this->title,
            $rendered,
            ['footer' => $buttons]
        );
        echo '</form>';
    }
    /**
     * Process import of config data
     *
     * @return void
     */
    public function configPost()
    {
        self::checkAuthAndCSRF();
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
                $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                    [
                        'title' => _('Export Success'),
                        'msg' => _('Export Complete'),
                        '_filename' => $backup_name,
                        '_content' => $data
                    ]
                ));
            } else {
                if ($_FILES['dbFile']['error'] > 0) {
                    throw new UploadException($_FILES['dbFile']['error']);
                }

                // Basic size sanity (e.g., 10 MB cap; adjust as you like)
                if (!isset($_FILES['dbFile']['size']) || $_FILES['dbFile']['size'] > (10 * 1024 * 1024)) {
                    throw new Exception(_('Uploaded file too large.'));
                }

                // Must be an uploaded file
                if (!is_uploaded_file($_FILES['dbFile']['tmp_name'])) {
                    throw new Exception(_('Invalid upload.'));
                }

                // Move to a safe temp file we control
                $dest = sys_get_temp_dir() . DS . 'fog_import_' . bin2hex(random_bytes(8)) . '.sql';
                if (!move_uploaded_file($_FILES['dbFile']['tmp_name'], $dest)) {
                    throw new Exception(_('Failed to move uploaded file.'));
                }

                // Quick sniff: must look like SQL dump (CREATE/INSERT or mysqldump header)
                $head = file_get_contents($dest, false, null, 0, 4096);
                if ($head === false || !preg_match('/(CREATE\s+TABLE|INSERT\s+INTO|mysqldump)/i', $head)) {
                    @unlink($dest);
                    throw new Exception(_('Not a recognizable SQL dump.'));
                }

                // Now import
                try {
                    $result = self::getClass('Schema')->importdb($dest);
                } finally {
                    @unlink($dest); // cleanup regardless
                }
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
        $this->jsonSend($code, $msg);
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

        $meta = $this->_settingsMeta();
        $needstobecheckbox = $meta['checkbox'];
        $needstobenumeric = $meta['numeric'];
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
            // Only the value field carries the rendered input column; binding
            // it to settingValue lets the global search match values too.
            if ($common !== 'value') {
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => 'inputValue',
                'formatter' => function ($d, $row) use (
                    $needstobenumeric,
                    $needstobecheckbox
                ) {
                    return self::_renderSettingInput(
                        $row,
                        $needstobenumeric,
                        $needstobecheckbox
                    );
                }
            ];
            unset($real);
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                'settingID',
                $columns,
                $sqlStr,
                $filterStr,
                $totalStr
            )
        ));
    }
}
