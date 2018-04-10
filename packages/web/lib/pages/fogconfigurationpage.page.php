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
    public function index()
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
            if (!$StorageNode->online) {
                continue;
            }
            $url = filter_var(
                sprintf(
                    '%s://%s/fog/status/kernelvers.php',
                    self::$httpproto,
                    $StorageNode->ip
                ),
                FILTER_SANITIZE_URL
            );
            $id = str_replace(' ', '_', $StorageNode->name);
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
            echo '<pre class="kernvers" urlcall="'
                . $url
                . '">';
            echo '</pre>';
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
        echo '<div class="box-footer">';
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
        echo _('Kernel Versions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $renderNodes;
        echo '</div>';
        echo '<div class="box-footer">';
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
        echo '<div class="box-footer">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Post our kernel download.
     *
     * @return void
     */
    public function kernel()
    {
        $this->kernelUpdatePost();
    }
    /**
     * Show the kernel update page.
     *
     * @return void
     */
    public function kernelUpdate()
    {
        $this->title = _('Kernel Update');

        $this->headerData = [
            _('Version'),
            _('Architecture'),
            _('Type'),
            _('Date')
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
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
        echo $this->render(12);
        echo '</div>';
        echo '<div class="box-footer">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Download the form.
     *
     * @return void
     */
    public function kernelUpdatePost()
    {
        global $node;
        global $sub;
        if (!isset($_POST['install']) && $sub == 'kernelUpdate') {
            $url = 'https://fogproject.org/kernels/kernelupdate_bootstrap_fog2.php';
            $htmlData = self::$FOGURLRequests->process($url);
            echo $htmlData[0];
        } elseif (isset($_POST['install'])) {
            $_SESSION['allow_ajax_kdl'] = true;
            $dstName = filter_input(INPUT_POST, 'dstName');
            $_SESSION['dest-kernel-file'] = trim(
                basename(
                    $dstName
                )
            );
            $_SESSION['tmp-kernel-file'] = sprintf(
                '%s%s%s%s',
                DS,
                trim(
                    sys_get_temp_dir(),
                    DS
                ),
                DS,
                basename($_SESSION['dest-kernel-file'])
            );
            $file = filter_input(INPUT_GET, 'file');
            $_SESSION['dl-kernel-file'] = base64_decode(
                $file
            );
            if (file_exists($_SESSION['tmp-kernel-file'])) {
                unlink($_SESSION['tmp-kernel-file']);
            }
            echo '<!-- Kernel Information -->';
            echo '<div class="box-group" id="kernel-update-form">';
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
            echo _('Downloading Kernel');
            echo '</p>';
            echo '</div>';
            echo '</div>';
            echo '<div class="box-body">';
            echo '<i class="fa fa-cog fa-2x fa-spin"></i>';
            echo ' ';
            echo _('Starting process');
            echo '</div>';
            echo '<div class="box-footer">';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            $file = filter_input(INPUT_GET, 'file');
            $arch = filter_input(INPUT_GET, 'arch');
            $tmpFile = basename(
                $file
            );
            $tmpArch = (
                $arch == 64 ?
                'bzImage' :
                'bzImage32'
            );

            $fields = [
                self::makeLabel(
                    'col-sm-3 control-label',
                    'dstName',
                    _('Kernel Name')
                ) => self::makInput(
                    'form-control kernelname-input',
                    'dstName',
                    'bzImage',
                    'text',
                    'dstName',
                    $tmpArch,
                    true
                )
            ];
            self::$HookManager->processEvent(
                'KERNEL_UPDATE_FIELDS',
                ['fields' => &$fields]
            );

            $rendered = self::formFields($fields);
            unset($fields);

            $props = ' method="post" action="'
                . $formstr
                . '" ';

            $buttons = self::makeButton(
                'install',
                _('Save Kernel'),
                'btn btn-warning',
                $props
            );

            echo '<!-- Kernel Information -->';
            echo '<div class="box-group" id="kernel-update-form">';
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
            echo $rendered;
            echo '</div>';
            echo '<div class="box-footer">';
            echo $buttons;
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
    /**
     * Display the ipxe menu configurations.
     *
     * @return void
     */
    public function pxemenu()
    {
        $this->title = _('FOG PXE Boot Menu Configuration');
        unset($this->headerData);
        $this->attributes = [
            [],
            [],
            []
        ];
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
            'FOG_PXE_MENU_TIMEOUT',
        ];
        list(
            $advLogin,
            $exitNorm,
            $exitEfi,
            $bgfile,
            $hostCpairs,
            $hostInvalid,
            $mainColors,
            $mainCpairs,
            $mainFallback,
            $hostValid,
            $bootKeys,
            $noMenu,
            $advanced,
            $hideTimeout,
            $hidChecked,
            $timeout
        ) = self::getSubObjectIDs(
            'Service',
            ['name' => $ServicesToSee],
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $advLogin = $advLogin ? ' checked' : '';
        $exitNorm = Service::buildExitSelector(
            'bootTypeExit',
            $exitNorm,
            false,
            'bootTypeExit'
        );
        $exitEfi = Service::buildExitSelector(
            'efiBootTypeExit',
            $exitEfi,
            false,
            'efiBootTypeExit'
        );
        $bootKeys = self::getClass('KeySequenceManager')
            ->buildSelectBox($bootKeys);
        $noMenu = (
            $noMenu ?
            ' checked' :
            ''
        );
        $hidChecked = (
            $hidChecked ?
            ' checked' :
            ''
        );
        $fieldsToData = function (&$input, &$field) {
            $this->data[] = [
                'field' => $field,
                'input' => (
                    is_array($input) ?
                    $input[0] :
                    $input
                ),
                'span' => (
                    is_array($input) ?
                    $input[1] :
                    ''
                )
            ];
            unset($input, $field);
        };
        // Menu based changes.
        $fields = [
            '<label for="mainColors">'
            . _('Main Colors')
            . '</label>' => [
                '<div class="input-group">'
                . '<textarea id="mainColors" name="mainColors" class="form-control">'
                . $mainColors
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the color settings of the main menu items')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="hostValid">'
            . _('Valid Host Colors')
            . '<label>' => [
                '<div class="input-group">'
                . '<textarea id="hostValid" class="form-control" name="hostValid">'
                . $hostValid
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the color text on the menu if the host')
                . ' '
                . _('is valid')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="hostInvalid">'
            . _('Invalid Host Colors')
            . '</label>' => [
                '<div class="input-group">'
                . '<textarea name="hostInvalid" class="form-control" id='
                . '"hostInvalid">'
                . $hostInvalid
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the color text on the menu if the host')
                . ' '
                . _('is invalid')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="mainCpairs">'
            . _('Main pairings')
            . '</label>' => [
                '<div class="input-group">'
                . '<textarea id="mainCpairs" name="mainCpairs" class='
                . '"form-control">'
                . $mainCpairs
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the pairings of colors to')
                . ' '
                . _('present and where/how they need to display')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="mainFallback">'
            . _('Main fallback pairings')
            . '</label>' => [
                '<div class="input-group">'
                . '<textarea class="form-control" id="mainFallback" name='
                . '"mainFallback">'
                . $mainFallback
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the pairings as a fallback')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="hostCpairs">'
            . _('Host pairings')
            . '</label>' => [
                '<div class="input-group">'
                . '<textarea class="form-control" id="hostCPairs" name="hostCpairs">'
                . $hostCpairs
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the pairings after host checks')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="timeout">'
            . _('Menu Timeout')
            . ' ('
            . _('in seconds')
            . ')'
            . '</label>' => [
                '<div class="input-group">'
                . '<input type="text" id="timeout" name="timeout" value="'
                . $timeout
                . '" class="form-control"/>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the menu timeout')
                . '. '
                . _('This is set in seconds and causes the default option')
                . ' '
                . _('to be booted if no keys are pressed when the menu is')
                . ' '
                . _('open')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="bgfile">'
            . _('Menu Background File')
            . '</label>' => [
                '<div class="input-group">'
                . '<input type="text" id="bgfile" name="bgfile" value="'
                . $bgfile
                . '" class="form-control"/>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the background file to use')
                . ' '
                . _('for the menu background')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="menuSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="menuSet" class="'
            . 'btn btn-info btn-block" name="updatemenuset">'
            . _('Update')
            . '</button>'
        ];
        self::$HookManager->processEvent(
            'IPXE_MENU_SETTINGS_FIELDS',
            ['fields' => &$fields]
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_MENU_SETTINGS',
            [
                'data' => &$this->data,
                'attributes' => &$this->attributes
            ]
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('iPXE Menu Settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center expand_trigger hand" id='
            . '"menusettings">';
        echo '<h4 class="title">';
        echo _('Menu colors, pairings, settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body hidefirst" id="menusettings">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        $this->data = [];
        $fields = [
            '<label for="nomenu">'
            . _('No Menu')
            . '</label>' => [
                '<input type="checkbox" id="nomenu" name="nomenu"'
                . $noMenu
                . '/>',
                '<i class="fa fa-question hand" title="'
                . _('Option sets if there will even')
                . ' '
                . _('be the presence of a menu')
                . ' '
                . _('to the client systems')
                . '. '
                . _('If there is not a task set')
                . ', '
                . _('it boots to the first device')
                . ', '
                . _('if there is a task')
                . ', '
                . _('it performs that task')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="hidemenu">'
            . _('Hide Menu')
            . '</label>' => [
                '<input type="checkbox" id="hidemenu" name="hidemenu"'
                . $hidChecked
                . '/>',
                '<i class="fa fa-question hand" title="'
                . _('Option sets the key sequence')
                . '. '
                . _('If none is specified')
                . ', '
                . _('ESC is defaulted')
                . '. '
                . _('Login with the FOG credentials and you will see the menu')
                . '. '
                . _('Otherwise it will just boot like normal')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="hidetimeout">'
            . _('Hide Menu Timeout')
            . '</label>' => [
                '<div class="input-group">'
                . '<input type="text" id="hidetimeout" name="hidetimeout" '
                . 'value="'
                . $hideTimeout
                . '" class="form-control"/>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the timeout value for the hidden menu system')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="keysequence">'
            . _('Boot Key Sequence')
            . '</label>' => [
                $bootKeys,
                '<i class="fa fa-question hand" title="'
                . _('Option sets the ipxe keysequence to enter to gain menu')
                . ' '
                . _('access to the hidden menu system')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="hideSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="hideSet" class="'
            . 'btn btn-info btn-block" name="updatehideset">'
            . _('Update')
            . '</button>'
        ];
        self::$HookManager->processEvent(
            'IPXE_HIDENOMENU_SETTINGS_FIELDS',
            ['fields' => &$fields]
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_HIDENOMENU_SETTINGS',
            [
                'data' => &$this->data,
                'attributes' => &$this->attributes
            ]
        );
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center expand_trigger hand" id="'
            . 'menuhidesettings">';
        echo '<h4 class="title">';
        echo _('Menu Hide/No Menu settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body hidefirst" id="menuhidesettings">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        $this->data = [];
        $fields = [
            '<label for="bootTypeExit">'
            . _('Exit to Hard Drive Type')
            . '</label>' => [
                $exitNorm,
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the legacy boot exit method ipxe will use')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="efiBootTypeExit">'
            . _('Exit to Hard Drive Type(EFI)')
            . '</label>' => [
                $exitEfi,
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the efi boot exit method ipxe will use')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="exitSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="exitSet" class="'
            . 'btn btn-info btn-block" name="updatebootexit">'
            . _('Update')
            . '</button>'
        ];
        self::$HookManager->processEvent(
            'IPXE_EXITTYPE_SETTINGS_FIELDS',
            ['fields' => &$fields]
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_EXITTYPE_SETTINGS',
            [
                'data' => &$this->data,
                'attributes' => &$this->attributes
            ]
        );
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center expand_trigger hand" id="'
            . 'menuexitsettings">';
        echo '<h4 class="title">';
        echo _('Boot Exit settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body hidefirst" id="menuexitsettings">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        $this->data = [];
        $fields = [
            '<label for="advlog">'
            . _('Advanced Menu Login')
            . '</label>' => [
                '<input type="checkbox" id="advlog" name="advmenulogin"'
                . $advLogin
                . '/>',
                '<i class="fa fa-question hand" title="'
                . _('Option below enforces a login system')
                . ' '
                . _('for the advanced menu parameters')
                . '. '
                . _('If off')
                . ', '
                . _('no login will appear')
                . '. '
                . _('If on')
                . ', '
                . _('it will enforce login to gain access to the advanced')
                . ' '
                . _('menu system')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="advtext">'
            . _('Advanced menu command')
            . '</label>' => [
                '<div class="input-group">'
                . '<textarea id="advtext" class="form-control" name="adv">'
                . $advanced
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Add any custom text you would like')
                . ' '
                . _('the advanced menu to use')
                . '. '
                . _('This is ipxe script commands to operate with')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ],
            '<label for="advSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="advSet" class="'
            . 'btn btn-info btn-block" name="updateadvset">'
            . _('Update')
            . '</button>'
        ];
        self::$HookManager->processEvent(
            'IPXE_ADVANCED_SETTINGS_FIELDS',
            ['fields' => &$fields]
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_ADVANCED_SETTINGS',
            [
                'data' => &$this->data,
                'attributes' => &$this->attributes
            ]
        );
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center expand_trigger hand" id="'
            . 'advancedmenusettings">';
        echo '<h4 class="title">';
        echo _('Advanced Menu settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body hidefirst" id="advancedmenusettings">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Stores the changes made.
     *
     * @return void
     */
    public function pxemenuPost()
    {
        try {
            if (isset($_POST['updatemenuset'])) {
                $mainColors = filter_input(
                    INPUT_POST,
                    'mainColors'
                );
                $hostValid = filter_input(
                    INPUT_POST,
                    'hostValid'
                );
                $hostInvalid = filter_input(
                    INPUT_POST,
                    'hostInvalid'
                );
                $mainCpairs = filter_input(
                    INPUT_POST,
                    'mainCpairs'
                );
                $mainFallback = filter_input(
                    INPUT_POST,
                    'mainFallback'
                );
                $hostCpairs = filter_input(
                    INPUT_POST,
                    'hostCpairs'
                );
                $timeout = trim(
                    filter_input(INPUT_POST, 'timeout')
                );
                $timeoutt = (is_numeric($timeout) &&  $timeout >= 0);
                if (!$timeoutt) {
                    throw new Exception(_('Invalid Timeout Value'));
                }
                $bgfile = filter_input(
                    INPUT_POST,
                    'bgfile'
                );
                $ServicesToEdit = [
                    'FOG_IPXE_MAIN_COLOURS' => $mainColors,
                    'FOG_IPXE_VALID_HOST_COLOURS' => $hostValid,
                    'FOG_IPXE_INVALID_HOST_COLOURS' => $hostInvalid,
                    'FOG_IPXE_MAIN_CPAIRS' => $mainCpairs,
                    'FOG_IPXE_MAIN_FALLBACK_CPAIRS' => $mainFallback,
                    'FOG_IPXE_HOST_CPAIRS' => $hostCpairs,
                    'FOG_PXE_MENU_TIMEOUT' => $timeout,
                    'FOG_IPXE_BG_FILE' => $bgfile
                ];
            }
            if (isset($_POST['updatehideset'])) {
                $noMenu = (int)isset($_POST['nomenu']);
                $hideMenu = (int)isset($_POST['hidemenu']);
                $hidetimeout = trim(
                    filter_input(INPUT_POST, 'hidetimeout')
                );
                $hidetimeoutt = (is_numeric($hidetimeout) && $hidetimeout >= 0);
                if (!$hidetimeoutt) {
                    throw new Exception(_('Invalid Timeout Value'));
                }
                $keysequence = filter_input(
                    INPUT_POST,
                    'keysequence'
                );
                $ServicesToEdit = [
                    'FOG_NO_MENU' => $noMenu,
                    'FOG_PXE_MENU_HIDDEN' => $hideMenu,
                    'FOG_PXE_HIDDENMENU_TIMEOUT' => $hidetimeout,
                    'FOG_KEY_SEQUENCE' => $keysequence
                ];
            }
            if (isset($_POST['updatebootexit'])) {
                $bootTypeExit = filter_input(
                    INPUT_POST,
                    'bootTypeExit'
                );
                $efiBootTypeExit = filter_input(
                    INPUT_POST,
                    'efiBootTypeExit'
                );
                $ServicesToEdit = [
                    'FOG_BOOT_EXIT_TYPE' => $bootTypeExit,
                    'FOG_EFI_BOOT_EXIT_TYPE' => $efiBootTypeExit
                ];
            }
            if (isset($_POST['updateadvset'])) {
                $advmenulogin = filter_input(
                    INPUT_POST,
                    'advmenulogin'
                );
                $adv = filter_input(
                    INPUT_POST,
                    'adv'
                );
                $ServicesToEdit = [
                    'FOG_ADVANCED_MENU_LOGIN' => $advmenulogin,
                    'FOG_PXE_ADVANCED' => $adv,
                ];
            }
            ksort($ServicesToEdit);
            $ids = self::getSubObjectIDs(
                'Service',
                ['name' => array_keys($ServicesToEdit)]
            );
            $items = [];
            $iteration = 0;
            foreach ($ServicesToEdit as $key => &$value) {
                $items[] = [$ids[$iteration], $key, $value];
                $iteration++;
                unset($value);
            }
            if (count($items) > 0) {
                self::getClass('ServiceManager')
                    ->insertBatch(
                        [
                            'id',
                            'name',
                            'value'
                        ],
                        $items
                    );
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('iPXE Settings updated successfully!'),
                    'title' => _('iPXE Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Update Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Saves/updates the pxe customizations.
     *
     * @return void
     */
    public function customizepxe()
    {
        $this->title = self::$foglang['PXEMenuCustomization'];
        Route::listem('pxemenuoptions');
        $Menus = json_decode(
            Route::getData()
        );
        $Menus = $Menus->data;
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo _('This item allows you to edit all of the iPXE Menu items as you');
        echo ' ';
        echo _('see fit');
        echo '. ';
        echo _('Mind you');
        echo ', ';
        echo _('iPXE syntax is very finicky when it comes to editing');
        echo '. ';
        echo _('If you need help understanding what items are needed please');
        echo ' ';
        echo _('see the forums or lookup the commands and scripts available');
        echo ' ';
        echo _('from');
        echo ' ';
        echo '<a href="http://ipxe.org">';
        echo 'ipxe.org';
        echo '</a>';
        echo '.';
        echo '<hr/>';
        foreach ((array)$Menus as &$Menu) {
            $divTab = preg_replace(
                '#[^\w\-]#',
                '_',
                $Menu->name
            );
            $menuid = in_array(
                $Menu->id,
                range(1, 13)
            );
            $menuDefault = (
                $Menu->default ?
                ' checked' :
                ''
            );
            $hotKey = (
                $Menu->hotkey ?
                ' checked' :
                ''
            );
            $keySeq = $Menu->keysequence;
            $fields = [
                '<label for="menu_item'
                . $divTab
                . '">'
                . _('Menu Item')
                . '</label>' => '<div class="input-group">'
                . '<input type="text" class="form-control" value="'
                . $Menu->name
                . '" name="menu_item" id="menu_item'
                . $divTab
                . '"/>'
                . '</div>',
                '<label for="menu_description'
                . $divTab
                . '">'
                . _('Description')
                . '</label>' => '<div class="input-group">'
                . '<textarea name="menu_description" id="menu_description'
                . $divTab
                . '" class="form-control">'
                . $Menu->description
                . '</textarea>'
                . '</div>',
                '<label for="menu_params'
                . $divTab
                . '">'
                . _('Parameters')
                . '</label>' => '<div class="input-group">'
                . '<textarea name="menu_params" id="menu_params'
                . $divTab
                . '" class="form-control">'
                . $Menu->params
                . '</textarea>'
                . '</div>',
                '<label for="menu_options'
                . $divTab
                . '">'
                . _('Boot Options')
                . '</label>' => '<div class="input-group">'
                . '<input type="text" name="menu_options" id="'
                . 'menu_options'
                . $divTab
                . '" value="'
                . $Menu->args
                . '" class="form-control"/>'
                . '</div>',
                '<label for="menudef'
                . $divTab
                . '">'
                . _('Default Item')
                . '</label>' => '<input type="checkbox" name="menu_default" id="'
                . 'menudef'
                . $divTab
                . '"'
                . $menuDefault
                . '/>',
                '<label for="hotkey'
                . $divTab
                . '">'
                . _('Hot Key Enabled')
                . '</label>' => '<input type="checkbox" name="hotkey" id="hotkey'
                . $divTab
                . '"'
                . $hotKey
                . '/>',
                '<label for="menu_hotkey'
                . $divTab
                . '">'
                . _('Hot Key to use')
                . '</label>' => '<div class="input-group">'
                . '<input type="text" name="keysequence" value="'
                . $keySeq
                . '" class="form-control" id="menu_hotkey'
                . $divTab
                . '"/>'
                . '</div>',
                '<label for="menu_regsel'
                . $divTab
                . '">'
                . _('Menu Show with')
                . '</label>' => '<div class="input-group">'
                . self::getClass(
                    'PXEMenuOptionsManager'
                )->regSelect(
                    $Menu->regMenu,
                    'menu_regsel'
                    . $divTab
                )
                . '</div>',
                '<label for="menu_id'
                . $divTab
                . '">'
                . _('Make Changes?')
                . '</label>'
                . '<input type="hidden" name="menu_id" value="'
                . $Menu->id
                . '"/>' => '<button name="saveform" type="submit" class="'
                . 'btn btn-info btn-block" id="menu_id'
                . $divTab
                . '">'
                . self::$foglang['Submit']
                . '</button>',
                (
                    !$menuid ?
                    '<label for="menu_del'
                    . $divTab
                    . '">'
                    . _('Delete Menu Item')
                    . '</label>'
                    . '<input type="hidden" name="rmid" value="'
                    . $Menu->id
                    . '"/>' :
                    ''
                ) => (
                    !$menuid ?
                    '<button name="delform" type="submit" class="'
                    . 'btn btn-danger btn-block" id="menu_del'
                    . $divTab
                    . '">'
                    . self::$foglang['Delete']
                    . '</button>' :
                    ''
                ),
            ];
            $fields = array_filter($fields);
            array_walk($fields, $this->fieldsToData);
            self::$HookManager->processEvent(
                sprintf(
                    'BOOT_ITEMS_%s',
                    $divTab
                ),
                [
                    'data' => &$this->data,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                ]
            );
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center '
                . 'expand_trigger hand" id="pxeItem_'
                . $divTab
                . '">';
            echo '<h4 class="title">';
            echo $Menu->name;
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body hidefirst" id="pxeItem_'
                . $divTab
                . '">';
            echo '<form class="form-horizontal" method="post" action="'
                . $this->formAction
                . '" novalidate>';
            echo $this->render(12);
            echo '</form>';
            echo '</div>';
            echo '</div>';
            unset(
                $this->data,
                $Menu
            );
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Saves the actual customizations
     *
     * @return void
     */
    public function customizepxePost()
    {
        $menuid = filter_input(
            INPUT_POST,
            'menu_id'
        );
        if (isset($_POST['saveform'])) {
            $menu_item = filter_input(
                INPUT_POST,
                'menu_item'
            );
            $menu_desc = filter_input(
                INPUT_POST,
                'menu_description'
            );
            $menu_params = filter_input(
                INPUT_POST,
                'menu_params'
            );
            $menu_options = filter_input(
                INPUT_POST,
                'menu_options'
            );
            $menu_regmenu = filter_input(
                INPUT_POST,
                'menu_regmenu'
            );
            $menu_hotkey = (int)isset($_POST['hotkey']);
            $menu_key = filter_input(
                INPUT_POST,
                'keysequence'
            );
            $menu_default = (int)isset($_POST['menu_default']);
            self::getClass('PXEMenuOptionsManager')
                ->update(
                    ['id' => $menuid],
                    '',
                    [
                        'name' => $menu_item,
                        'description' => $menu_desc,
                        'params' => $menu_params,
                        'regMenu' => $menu_regmenu,
                        'args' => $menu_options,
                        'default' => $menu_default,
                        'hotkey' => $menu_hotkey,
                        'keysequence' => $menu_key
                    ]
                );
            if ($menu_default) {
                $MenuIDs = self::getSubObjectIDs('PXEMenuOptions');
                natsort($MenuIDs);
                $MenuIDs = array_unique(
                    array_diff(
                        $MenuIDs,
                        (array)$menuid
                    )
                );
                natsort($MenuIDs);
                self::getClass('PXEMenuOptionsManager')
                    ->update(
                        ['id' => $MenuIDs],
                        '',
                        ['default' => '0']
                    );
            }
            unset($MenuIDs);
            $DefMenuIDs = self::getSubObjectIDs(
                'PXEMenuOptions',
                ['default' => 1]
            );
            if (!count($DefMenuIDs)) {
                self::getClass('PXEMenuOptions', 1)
                    ->set('default', 1)
                    ->save();
            }
            unset($DefMenuIDs);
            $code = 201;
            $msg = json_encode(
                [
                    'msg' => _("$menu_item successfully updated!"),
                    'title' => _('iPXE Item Update Success')
                ]
            );
        }
        if (isset($_POST['delform'])) {
            $rmid = filter_input(
                INPUT_POST,
                'rmid'
            );
            $menuname = self::getClass(
                'PXEMenuOptions',
                $rmid
            );
            if ($menuname->destroy()) {
                $msg = json_encode(
                    [
                        'msg' => $menuname->get('name')
                        . ' '
                        . _('successfully removed!'),
                        'title' => _('iPXE Item Remove Success')
                    ]
                );
            }
            $countDefault = self::getClass('PXEMenuOptionsManager')
                ->count(['default' => 1]);
            if ($countDefault == 0
                || $countDefault > 1
            ) {
                self::getClass('PXEMenuOptions', 1)
                    ->set('default', 1)
                    ->save();
            }
        }
        echo $msg;
        exit;
    }
    /**
     * Form presented to create a new menu.
     *
     * @return void
     */
    public function newMenu()
    {
        $this->title = _('Create New iPXE Menu Entry');
        $menu_item = filter_input(
            INPUT_POST,
            'menu_item'
        );
        $menu_desc = filter_input(
            INPUT_POST,
            'menu_description'
        );
        $menu_params = filter_input(
            INPUT_POST,
            'menu_params'
        );
        $menu_options = filter_input(
            INPUT_POST,
            'menu_options'
        );
        $menu_regmenu = filter_input(
            INPUT_POST,
            'menu_regmenu'
        );
        $menu_hotkey = (
            isset($_POST['hotkey']) ?
            ' checked' :
            ''
        );
        $menu_key = filter_input(
            INPUT_POST,
            'keysequence'
        );
        $menu_default = (
            isset($_POST['menu_default']) ?
            ' checked' :
            ''
        );
        $fields = [
            '<label for="menu_item">'
            . _('Menu Item')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="form-control" value="'
            . '" name="menu_item" id="menu_item'
            . $menu_item
            . '"/>'
            . '</div>',
            '<label for="menu_description">'
            . _('Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="menu_description" id="menu_description'
            . '" class="form-control">'
            . $menu_desc
            . '</textarea>'
            . '</div>',
            '<label for="menu_params">'
            . _('Parameters')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="menu_params" id="menu_params'
            . '" class="form-control">'
            . $menu_params
            . '</textarea>'
            . '</div>',
            '<label for="menu_options">'
            . _('Boot Options')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="menu_options" id="'
            . 'menu_options'
            . '" value="'
            . $menu_options
            . '" class="form-control"/>'
            . '</div>',
            '<label for="menudef">'
            . _('Default Item')
            . '</label>' => '<input type="checkbox" name="menu_default" id="'
            . 'menudef'
            . '"'
            . $menu_default
            . '/>',
            '<label for="hotkey">'
            . _('Hot Key Enabled')
            . '</label>' => '<input type="checkbox" name="hotkey" id="hotkey'
            . '"'
            . $menu_hotkey
            . '/>',
            '<label for="menu_hotkey">'
            . _('Hot Key to use')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="keysequence" value="'
            . $menu_key
            . '" class="form-control" id="menu_hotkey'
            . '"/>'
            . '</div>',
            '<label for="menu_regsel">'
            . _('Menu Show with')
            . '</label>' => '<div class="input-group">'
            . self::getClass(
                'PXEMenuOptionsManager'
            )->regSelect(
                $menu_regmenu,
                'menu_regsel'
            )
            . '</div>',
            '<label for="menu_id'
            . '">'
            . _('Make Changes?')
            . '</label>'
            . '<input type="hidden" name="menu_id" value="'
            . '"/>' => '<button name="saveform" type="submit" class="'
            . 'btn btn-info btn-block" id="menu_id'
            . '">'
            . self::$foglang['Submit']
            . '</button>',
        ];
        $fields = array_filter($fields);
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'BOOT_ITEMS_ADD',
                [
                    'data' => &$this->data,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                ]
            );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('New iPXE Menu');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Creates the new Menu items.
     *
     * @return void
     */
    public function newMenuPost()
    {
        $menu_item = filter_input(
            INPUT_POST,
            'menu_item'
        );
        $menu_desc = filter_input(
            INPUT_POST,
            'menu_description'
        );
        $menu_params = filter_input(
            INPUT_POST,
            'menu_params'
        );
        $menu_options = filter_input(
            INPUT_POST,
            'menu_options'
        );
        $menu_regmenu = filter_input(
            INPUT_POST,
            'menu_regmenu'
        );
        $menu_hotkey = (int)isset($_POST['hotkey']);
        $menu_key = filter_input(
            INPUT_POST,
            'keysequence'
        );
        $menu_default = (int)isset($_POST['menu_default']);
        try {
            if (!$menu_item) {
                throw new Exception(_('Menu Item or title cannot be blank'));
            }
            if (!$menu_desc) {
                throw new Exception(_('A description needs to be set'));
            }
            if ($menu_default) {
                self::getClass('PXEMenuOptionsManager')
                    ->update(
                        '',
                        '',
                        ['default' => 0]
                    );
            }
            $Menu = self::getClass('PXEMenuOptions')
                ->set('name', $menu_item)
                ->set('description', $menu_description)
                ->set('params', $menu_params)
                ->set('regMenu', $menu_regmenu)
                ->set('args', $menu_options)
                ->set('default', $menu_default);
            if (!$Menu->save()) {
                throw new Exception(_('iPXE Item create failed!'));
            }
            $countDefault = self::getClass('PXEMenuOptionsManager')
                ->count(['default' => 1]);
            if ($countDefault == 0 || $countDefault > 1) {
                $PXEMenuOptions = self::getClass(
                    'PXEMenuOptions',
                    1
                )->set('default', 1);
                if (!$PXEMenuOptions->save()) {
                    $serverFault = true;
                    throw new Exception(_('Menu item failed!'));
                }
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'MENU_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('iPXE Item added!'),
                    'title' => _('iPXE Item Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'MENU_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Item Create Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager
            ->processEvent(
                $hook,
                ['Menu' => &$Menu]
            );
        unset($Menu);
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
            'btn btn-outline pull-right'
        );
        $buttons .= self::makeButton(
            'deletemacs',
            _('Delete MAC List'),
            'btn btn-danger pull-right'
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
        echo '<a href="http://standards.ieee.org/regauth/oui/oui.txt">';
        echo 'http://standards.ieee.org/regauth/oui/oui.txt';
        echo '</a>';
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo _('Current Records');
        echo ': ';
        echo '<span id="lookupcount">' . self::getMACLookupCount() . '</span>';
        echo '</div>';
        echo '<div class="box-footer">';
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
            $url = 'http://linuxnet.ca/ieee/oui.txt';
            if (($fh = fopen($url, 'rb')) === false) {
                throw new Exception(_('Could not read temp file'));
            }
            $items = [];
            $start = 18;
            $imported = 0;
            $pat = '#^([0-9a-fA-F]{2}[:-]){2}([0-9a-fA-F]{2}).*$#';
            while (($line = fgets($fh, 4096)) !== false) {
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
            fclose($fh);
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
        $viewvals = [-1, 10, 25, 50, 100];
        array_shift($regenrange);
        $checkbox = [
            'FOG_ENFORCE_HOST_CHANGES' => true,
            'FOG_API_ENABLED' => true,
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
            'FOG_PLUGINSYS_ENABLED' => true
        ];
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
            'IMAGEREPSLEEPTIME' => true,
            'MULTICASESLEEPTIME' => true,
            // FOG Quick Registration
            'FOG_QUICKREG_IMG_ID' => self::fastmerge(
                (array)0,
                self::getSubObjectIDs('Image')
            ),
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => self::fastmerge(
                (array)0,
                self::getSubObjectIDs('Group')
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
        $pName = filter_input(INPUT_POST, 'category');
        Route::listem(
            'service',
            ['settingCategory' => $pName]
        );
        $Services = json_decode(
            Route::getData()
        );

        $serverFault = false;
        try {
            parse_str(
                file_get_contents('php://input'),
                $vars
            );
            foreach ($vars as $key => &$val) {
                Route::indiv('service', $key);
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
                    switch ($needstobenumeric[$name]) {
                    case ($needstobenumeric[$name] === true):
                        if (!is_numeric($set)) {
                            throw new Exception(
                                $name . ' ' . _('value must be numeric')
                            );
                        }
                        break;
                    default:
                        if (!is_numeric($set)) {
                            throw new Exception(
                                $name . ' ' . _('value must be numeric')
                            );
                        }
                        if (!in_array($set, $needstobenumeric[$name])) {
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
                        '/[^-\w\.]+/',
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
                    } else {
                        self::setSetting('FOG_CLIENT_BANNER_SHA', $hash);
                    }
                    break;
                }
                $items[] = [$key, $name, $set];
                unset($Service);
            }
            if (count($items) > 0) {
                $ServiceMan = self::getClass('ServiceManager');
                $insert_fields = [
                    'id',
                    'name',
                    'value'
                ];
                if (!$ServiceMan->insertBatch($insert_fields, $items)) {
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

        $buttons = self::makeButton(
            'service-send',
            _('Save'),
            'btn btn-primary pull-right'
        );

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('FOG Settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'settings-table', $buttons);
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
        $logparse = function (&$log) use (
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
            Route::listem(
                'storagenode',
                [
                    'ngmID' => $StorageGroup->enablednodes,
                    'ngmIsEnabled' => 1,
                    'ngmIsMasterNode' => 1
                ]
            );
            $StorageNodes = json_decode(
                Route::getData()
            );
            if (count($StorageNodes->data) ?: [] < 1) {
                continue;
            }
            foreach ($StorageNodes->data as &$StorageNode) {
                break;
            }
            $fogfiles = json_decode(
                json_encode($StorageNode->logfiles),
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
                $multicastlog = preg_grep(
                    '#(multicast.log$)#i',
                    $fogfiles
                );
                $multicastlog = array_shift($multicastlog);
                $schedulerlog = preg_grep(
                    '#(fogscheduler.log$)#i',
                    $fogfiles
                );
                $schedulerlog = array_shift($schedulerlog);
                $imgrepliclog = preg_grep(
                    '#(fogreplicator.log$)#i',
                    $fogfiles
                );
                $imgrepliclog = array_shift($imgrepliclog);
                $imagesizelog = preg_grep(
                    '#(fogimagesize.log$)#i',
                    $fogfiles
                );
                $imagesizelog = array_shift($imagesizelog);
                $snapinreplog = preg_grep(
                    '#(fogsnapinrep.log$)#i',
                    $fogfiles
                );
                $snapinreplog = array_shift($snapinreplog);
                $snapinhashlog = preg_grep(
                    '#(fogsnapinhash.log$)#i',
                    $fogfiles
                );
                $snapinhashlog = array_shift($snapinhashlog);
                $pinghostlog = preg_grep(
                    '#(pinghosts.log$)#i',
                    $fogfiles
                );
                $pinghostlog = array_shift($pinghostlog);
                $svcmasterlog = preg_grep(
                    '#(servicemaster.log$)#i',
                    $fogfiles
                );
                $svcmasterlog = array_shift($svcmasterlog);
                $imgtransferlogs = preg_grep(
                    '#(fogreplicator.log.transfer)#i',
                    $fogfiles
                );
                $snptransferlogs = preg_grep(
                    '#(fogsnapinrep.log.transfer)#i',
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
        echo '<select name="logtype" class="select2" id="logToView">';
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
                        $value == $_POST['logtype'] ?
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
                    $value == $_POST['n'] ?
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
            'btn btn-success'
        );
        $buttons .= self::makeButton(
            'logpause',
            _('Pause'),
            'btn btn-warning'
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
        echo '<div class="btn-group">';
        echo $buttons;
        echo '</div>';
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
                    'report',
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
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'importdb',
            _('Import'),
            'btn btn-warning'
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
        echo '<div class="btn-group">';
        echo $buttons;
        echo '</div>';
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
                unlink($tmpfile);
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
                    ENT_QUOTES,
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
                if (!$result) {
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
            'FOG_IMAGE_LIST_MENU' => true,
            'FOG_REAUTH_ON_DELETE' => true,
            'FOG_REAUTH_ON_EXPORT' => true
        ];
        self::$HookManager->processEvent(
            'NEEDSTOBECHECKBOX',
            ['needstobecheckbox' => &$needstobecheckbox]
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
            'IMAGEREPSLEEPTIME' => true,
            'MULTICASESLEEPTIME' => true,
            // FOG Quick Registration
            'FOG_QUICKREG_IMG_ID' => self::fastmerge(
                (array)0,
                self::getSubObjectIDs('Image')
            ),
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => self::fastmerge(
                (array)0,
                self::getSubObjectIDs('Group')
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
        $serviceMan = self::getClass('ServiceManager');
        $table = $serviceMan->getTable();
        $dbcolumns = $serviceMan->getColumns();
        $sqlStr = $serviceMan->getQueryStr();
        $filterStr = $serviceMan->getFilterStr();
        $totalStr = $serviceMan->getTotalStr();
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
                        $input = self::getClass('ImageManager')->buildSelectBox(
                            $row['settingValue'],
                            $row['settingID']
                        );
                        break;
                    case 'FOG_QUICKREG_GROUP_ASSOC':
                        $input = self::getClass('GroupManager')->buildSelectBox(
                            $row['settingValue'],
                            $row['settingID']
                        );
                        break;
                    case 'FOG_KEY_SEQUENCE':
                        $input = self::getClass('KeySequenceManager')
                            ->buildSelectBox(
                                $row['settingValue'],
                                $row['settingID']
                            );
                        break;
                    case 'FOG_BOOT_EXIT_TYPE':
                    case 'FOG_EFI_BOOT_EXIT_TYPE':
                        $input = Service::buildExitSelector(
                            $row['settingID'],
                            $row['settingValue'],
                            false,
                            $row['settingKey']
                        );
                        break;
                    case 'FOG_TZ_INFO':
                        $dt = self::niceDate('now', $utc);
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
                            true
                        );
                        break;
                    case 'FOG_QUICKREG_OS_ID':
                        $ImageName = _('No image specified');
                        if ($row['settingValue'] > 0) {
                            $ImageName = self::getClass(
                                'Image',
                                $row['settingValue']
                            )->get('name');
                        }
                        $input = '<p id="'
                            . $row['settingKey']
                            . '">'
                            . $ImageName
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
                        break;
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
}
