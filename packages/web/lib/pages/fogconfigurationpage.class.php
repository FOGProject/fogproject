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
        $this->menu = array(
            'home' => self::$foglang['Home'],
            'license' => self::$foglang['License'],
            'kernelUpdate' => self::$foglang['KernelUpdate'],
            'pxemenu' => self::$foglang['PXEBootMenu'],
            'customizepxe' => self::$foglang['PXEConfiguration'],
            'newMenu' => self::$foglang['NewMenu'],
            'clientupdater' => self::$foglang['ClientUpdater'],
            'maclist' => self::$foglang['MACAddrList'],
            'settings' => self::$foglang['FOGSettings'],
            'logviewer' => self::$foglang['LogViewer'],
            'config' => self::$foglang['ConfigSave'],
            'http://www.sf.net/projects/freeghost' => self::$foglang['FOGSFPage'],
            'https://fogproject.org' => self::$foglang['FOGWebPage'],
            'https://github.com/fogproject/fogproject.git' => _(
                'FOG Project on Github'
            ),
            'https://github.com/fogproject/fog-client.git' => _(
                'FOG Client on Github'
            ),
            'https://wiki.fogproject.org/wiki/index.php' => _('FOG Wiki'),
            'https://forums.fogproject.org' => _('FOG Forums'),
            'https://www.paypal.com/cgi-bin/webscr?i'
            . 'item_name=Donation+to+FOG+-+A+Free+Cloning+'
            . 'Solution&cmd=_donations&business=fogproject'
            . '.org%40gmail.com' => _('Donate to FOG'),
            );
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes'=>&$this->notes
                )
            );
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
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body placehere" vers="'
            . FOG_VERSION
            . '">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Kernel Versions');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        Route::listem('storagenode');
        $StorageNodes = json_decode(
            Route::getData()
        );
        $StorageNodes = $StorageNodes->storagenodes;
        $urls = array();
        foreach ((array)$StorageNodes as &$StorageNode) {
            $url = $urls[] = filter_var(
                sprintf(
                    '%s://%s/fog/status/kernelvers.php',
                    self::$httpproto,
                    $StorageNode->ip
                ),
                FILTER_SANITIZE_URL
            );
            if (!self::getClass('StorageNode', $StorageNode->id)->get('online')) {
                continue;
            }
            echo '<a id="'
                . $StorageNode->name
                . '" class="expand_trigger btn btn-info btn-block"'
                . 'href="#'
                . $StorageNode->name
                . '">';
            echo '<h4 class="title kernversionupdate">';
            echo $StorageNode->name;
            echo ' ';
            echo _('FOG Version');
            echo ': ()';
            echo '</h4>';
            echo '</a>';
            echo '<div id="'
                . $StorageNode->name
                . '" class="hidefirst">';
            echo '<pre class="kernvers" urlcall="';
            echo $url;
            echo '">';
            echo '</pre>';
            echo '</div>';
            unset($StorageNode);
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display the fog license information
     *
     * @return void
     */
    public function license()
    {
        $this->title = _('FOG License Information');
        $file = './languages/'
            . self::$locale
            . '.UTF-8/gpl-3.0.txt';
        $contents = file_get_contents($file);
        $contents = preg_replace('!\r?\n!', '<br/>', $contents);
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('GNU Gneral Public License');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body text-justify">';
        echo $contents;
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
        $url = 'https://fogproject.org/kernels/kernelupdate_bootstrap.php';
        $htmlData = self::$FOGURLRequests->process($url);
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Kernel Update');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
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
        echo '</div>';
        echo '<div class="panel-body">';
        echo $htmlData[0];
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
            $url = 'https://fogproject.org/kernels/kernelupdate_bootstrap.php';
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
            echo '<div class="col-xs-9">';
            echo '<div class="kerninfo">';
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Downloading Kernel');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo '<i class="fa fa-cog fa-2x fa-spin"></i>';
            echo ' ';
            echo _('Starting process');
            echo '</div>';
            echo '</div>';
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
            $formstr = "?node={$node}&sub=kernelUpdate";
            echo '<div class="col-xs-9">';
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Save Kernel');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo '<form class="form-horizontal" method="post" action="';
            $formstr;
            echo '">';
            echo '<input type="hidden" name="file" value="';
            echo $tmpFile;
            echo '"/>';
            echo '<div class="col-xs-4">';
            echo '<label class="control-label" for="dstName">';
            echo _('Kernel Name');
            echo '</label>';
            echo '</div>';
            echo '<div class="col-xs-8">';
            echo '<div class="input-group">';
            echo '<input class="form-control" type="text" name="dstName" id='
                . '"dstName" value="'
                . $tmpArch
                . '"/>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-4">';
            echo '<label class="control-label" for="install">';
            echo _('Install Kernel');
            echo '</label>';
            echo '</div>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" class="btn btn-info btn-block" id='
                . '"install" name="install">';
            echo _('Install');
            echo '</button>';
            echo '</div>';
            echo '</form>';
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
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-6 form-group'),
            array('class' => 'col-xs-2')
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${span}',
        );
        $ServicesToSee = array(
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
        );
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
            array(
                'name' => $ServicesToSee
            ),
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
            $this->data[] = array(
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
            );
            unset($input, $field);
        };
        // Menu based changes.
        $fields = array(
            '<label for="mainColors">'
            . _('Main Colors')
            . '</label>' => array(
                '<div class="input-group">'
                . '<textarea id="mainColors" name="mainColors" class="form-control">'
                . $mainColors
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the color settings of the main menu items')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="hostValid">'
            . _('Valid Host Colors')
            . '<label>' => array(
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
            ),
            '<label for="hostInvalid">'
            . _('Invalid Host Colors')
            . '</label>' => array(
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
            ),
            '<label for="mainCpairs">'
            . _('Main pairings')
            . '</label>' => array(
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
            ),
            '<label for="mainFallback">'
            . _('Main fallback pairings')
            . '</label>' => array(
                '<div class="input-group">'
                . '<textarea class="form-control" id="mainFallback" name='
                . '"mainFallback">'
                . $mainFallback
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the pairings as a fallback')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="hostCpairs">'
            . _('Host pairings')
            . '</label>' => array(
                '<div class="input-group">'
                . '<textarea class="form-control" id="hostCPairs" name="hostCpairs">'
                . $hostCpairs
                . '</textarea>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the pairings after host checks')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="timeout">'
            . _('Menu Timeout')
            . ' ('
            . _('in seconds')
            . ')'
            . '</label>' => array(
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
            ),
            '<label for="bgfile">'
            . _('Menu Background File')
            . '</label>' => array(
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
            ),
            '<label for="menuSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="menuSet" class="'
            . 'btn btn-info btn-block" name="updatemenuset">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'IPXE_MENU_SETTINGS_FIELDS',
            array(
                'fields' => &$fields
            )
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_MENU_SETTINGS',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
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
            . '">';
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
        $this->data = array();
        $fields = array(
            '<label for="nomenu">'
            . _('No Menu')
            . '</label>' => array(
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
            ),
            '<label for="hidemenu">'
            . _('Hide Menu')
            . '</label>' => array(
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
            ),
            '<label for="hidetimeout">'
            . _('Hide Menu Timeout')
            . '</label>' => array(
                '<div class="input-group">'
                . '<input type="text" id="hidetimeout" name="hidetimeout" '
                . 'value="'
                . $hideTimeout
                . '" class="form-control"/>'
                . '</div>',
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the timeout value for the hidden menu system')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="keysequence">'
            . _('Boot Key Sequence')
            . '</label>' => array(
                $bootKeys,
                '<i class="fa fa-question hand" title="'
                . _('Option sets the ipxe keysequence to enter to gain menu')
                . ' '
                . _('access to the hidden menu system')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="hideSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="hideSet" class="'
            . 'btn btn-info btn-block" name="updatehideset">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'IPXE_HIDENOMENU_SETTINGS_FIELDS',
            array(
                'fields' => &$fields
            )
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_HIDENOMENU_SETTINGS',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
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
        $this->data = array();
        $fields = array(
            '<label for="bootTypeExit">'
            . _('Exit to Hard Drive Type')
            . '</label>' => array(
                $exitNorm,
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the legacy boot exit method ipxe will use')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="efiBootTypeExit">'
            . _('Exit to Hard Drive Type(EFI)')
            . '</label>' => array(
                $exitEfi,
                '<i class="fa fa-question hand" title="'
                . _('Option specifies the efi boot exit method ipxe will use')
                . '." data-toggle="tooltip" data-placement="right"></i>'
            ),
            '<label for="exitSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="exitSet" class="'
            . 'btn btn-info btn-block" name="updatebootexit">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'IPXE_EXITTYPE_SETTINGS_FIELDS',
            array(
                'fields' => &$fields
            )
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_EXITTYPE_SETTINGS',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
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
        $this->data = array();
        $fields = array(
            '<label for="advlog">'
            . _('Advanced Menu Login')
            . '</label>' => array(
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
            ),
            '<label for="advtext">'
            . _('Advanced menu command')
            . '</label>' => array(
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
            ),
            '<label for="advSet">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" id="advSet" class="'
            . 'btn btn-info btn-block" name="updateadvset">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'IPXE_ADVANCED_SETTINGS_FIELDS',
            array(
                'fields' => &$fields
            )
        );
        array_walk($fields, $fieldsToData);
        self::$HookManager->processEvent(
            'IPXE_ADVANCED_SETTINGS',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
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
                $ServicesToEdit = array(
                    'FOG_IPXE_MAIN_COLOURS' => $mainColors,
                    'FOG_IPXE_VALID_HOST_COLOURS' => $hostValid,
                    'FOG_IPXE_INVALID_HOST_COLOURS' => $hostInvalid,
                    'FOG_IPXE_MAIN_CPAIRS' => $mainCpairs,
                    'FOG_IPXE_MAIN_FALLBACK_CPAIRS' => $mainFallback,
                    'FOG_IPXE_HOST_CPAIRS' => $hostCpairs,
                    'FOG_PXE_MENU_TIMEOUT' => $timeout,
                    'FOG_IPXE_BG_FILE' => $bgfile
                );
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
                $ServicesToEdit = array(
                    'FOG_NO_MENU' => $noMenu,
                    'FOG_PXE_MENU_HIDDEN' => $hideMenu,
                    'FOG_PXE_HIDDENMENU_TIMEOUT' => $hidetimeout,
                    'FOG_KEY_SEQUENCE' => $keysequence
                );
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
                $ServicesToEdit = array(
                    'FOG_BOOT_EXIT_TYPE' => $bootTypeExit,
                    'FOG_EFI_BOOT_EXIT_TYPE' => $efiBootTypeExit
                );
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
                $ServicesToEdit = array(
                    'FOG_ADVANCED_MENU_LOGIN' => $advmenulogin,
                    'FOG_PXE_ADVANCED' => $adv,
                );
            }
            ksort($ServicesToEdit);
            $ids = self::getSubObjectIDs(
                'Service',
                array(
                    'name' => array_keys($ServicesToEdit)
                )
            );
            $items = array();
            $iteration = 0;
            foreach ($ServicesToEdit as $key => &$value) {
                $items[] = array($ids[$iteration], $key, $value);
                $iteration++;
                unset($value);
            }
            if (count($items) > 0) {
                self::getClass('ServiceManager')
                    ->insertBatch(
                        array(
                            'id',
                            'name',
                            'value'
                        ),
                        $items
                    );
            }
            $msg = json_encode(
                array(
                    'msg' => _('iPXE Settings updated successfully!'),
                    'title' => _('iPXE Update Success')
                )
            );
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Update Fail')
                )
            );
        }
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
        $this->templates = array(
            '${field}',
            '${input}',
        );
        Route::listem('pxemenuoptions');
        $Menus = json_decode(
            Route::getData()
        );
        $Menus = $Menus->pxemenuoptionss;
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
            $fields = array(
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
            );
            $fields = array_filter($fields);
            array_walk($fields, $this->fieldsToData);
            self::$HookManager->processEvent(
                sprintf(
                    'BOOT_ITEMS_%s',
                    $divTab
                ),
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center expand_trigger '
                . 'hand" id="pxeItem_'
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
                . '">';
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
                    array(
                        'id' => $menuid
                    ),
                    '',
                    array(
                        'name' => $menu_item,
                        'description' => $menu_desc,
                        'params' => $menu_params,
                        'regMenu' => $menu_regmenu,
                        'args' => $menu_options,
                        'default' => $menu_default,
                        'hotkey' => $menu_hotkey,
                        'keysequence' => $menu_key
                    )
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
                        array(
                            'id' => $MenuIDs
                        ),
                        '',
                        array(
                            'default' => '0'
                        )
                    );
            }
            unset($MenuIDs);
            $DefMenuIDs = self::getSubObjectIDs(
                'PXEMenuOptions',
                array('default' => 1)
            );
            if (!count($DefMenuIDs)) {
                self::getClass('PXEMenuOptions', 1)
                    ->set('default', 1)
                    ->save();
            }
            unset($DefMenuIDs);
            $msg = json_encode(
                array(
                    'msg' => _("$menu_item successfully updated!"),
                    'title' => _('iPXE Item Update Success')
                )
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
                    array(
                        'msg' => $menuname->get('name')
                        . ' '
                        . _('successfully removed!'),
                        'title' => _('iPXE Item Remove Success')
                    )
                );
            }
            $countDefault = self::getClass('PXEMenuOptionsManager')
                ->count(
                    array(
                        'default' => 1
                    )
                );
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
        $this->templates = array(
            '${field}',
            '${input}',
        );
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
        $fields = array(
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
        );
        $fields = array_filter($fields);
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'BOOT_ITEMS_ADD',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
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
            . '">';
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
                        array(
                            'default' => 0
                        )
                    );
            }
            $Menu = self::getClass('PXEMenuOptions')
                ->set('name', $menu_item)
                ->set('description', $menu_desc)
                ->set('params', $menu_params)
                ->set('regMenu', $menu_regmenu)
                ->set('args', $menu_options)
                ->set('default', $menu_default);
            if (!$Menu->save()) {
                throw new Exception(_('iPXE Item create failed!'));
            }
            $countDefault = self::getClass('PXEMenuOptionsManager')
                ->count(
                    array(
                        'default' => 1
                    )
                );
            if ($countDefault == 0 || $countDefault > 1) {
                self::getClass('PXEMenuOptions', 1)
                    ->set('default', 1)
                    ->save();
            }
            $hook = 'MENU_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('iPXE Item added!'),
                    'title' => _('iPXE Item Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'MENU_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Item Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array(
                    'Menu' => &$Menu
                )
            );
        unset($Menu);
        echo $msg;
        exit;
    }
    /**
     * Client updater element from config page.
     *
     * @param bool $formNeeded Is the element needing form?
     *
     * @return void
     */
    public function clientupdater($formNeeded = true)
    {
        $this->title = _('FOG Client Service Updater');
        $this->headerData = array(
            _('Delete'),
            _('Module Name'),
            _('Module MD5'),
            _('Module Type'),
        );
        $this->templates = array(
            '<input type="checkbox" name="delcu[]" class='
            . '"delid" id="delcuid${client_id}" value='
            . '"${client_id}"/>',
            '<input type="hidden" name="name" value='
            . '"FOG_CLIENT_CLIENTUPDATER_ENABLED"/>${name}',
            '${module}',
            '${type}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(
                'class' => 'filter-false'
            )
        );
        Route::listem('clientupdater');
        $ClientUpdates = json_decode(
            Route::getData()
        );
        $ClientUpdates = $ClientUpdates->clientupdaters;
        foreach ((array)$ClientUpdates as &$ClientUpdate) {
            $this->data[] = array(
                'name' => $ClientUpdate->name,
                'module' => $ClientUpdate->md5,
                'type' => $ClientUpdate->type,
                'client_id' => $ClientUpdate->id,
                'id' => $ClientUpdate->id,
            );
            unset($ClientUpdate);
        }
        self::$HookManager
            ->processEvent(
                'CLIENT_UPDATE',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
        if ($formNeeded) {
            echo '<div class="col-xs-9">';
            echo '<form class="form-horizontal" method="post" action="'
                . $this->formAction
                . '&tab=clientupdater" enctype="multipart/form-data">';
        }
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Current files');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo _('NOTICE');
        echo ': ';
        echo _('The below items are only used for the old client.');
        echo ' ';
        echo _('Old clients are the clients that came with FOG');
        echo ' ';
        echo _('Version 1.2.0 and earlier');
        echo '<hr/>';
        echo _('This section allows you to update the modules and');
        echo ' ';
        echo _('config files that run on the client computers.');
        echo ' ';
        echo _('The clients will checkin with the server from time');
        echo ' ';
        echo _('to time to see if a new module is published.');
        echo ' ';
        echo _('If a new module is published the client will');
        echo ' ';
        echo _('download the module and use it on the next');
        echo ' ';
        echo _('time the service is started.');
        echo '<hr/>';
        $this->render(12);
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $fields = array(
            '<label for="file">'
            . _('Upload file')
            . ' '
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . '</label>' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden" multiple name='
            . '"module[]" id="file"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp" readonly/>'
            . '</div>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'CLIENT_UPDATE',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render(12);
        echo '<div class="form-group">';
        echo '<label class="control-label col-xs-4" for="deletecu">';
        echo _('Delete Selected Items');
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<button class="btn btn-danger btn-block" type="submit" id="deletecu" '
            . 'name="deletecu">';
        echo _('Delete');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label class="control-label col-xs-4" for="upload">';
        echo _('Make Changes');
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<button class="btn btn-info btn-block" type="submit" id="upload" '
            . 'name="upload">';
        echo _('Update');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        echo '</div>';
        echo '</div>';
        if ($formNeeded) {
            echo '</form>';
            echo '</div>';
        }
    }
    /**
     * Submits the changes as needed.
     *
     * @return void
     */
    public function clientupdaterPost()
    {
        try {
            if (isset($_POST['deletecu'])) {
                $delcus = filter_input_array(
                    INPUT_POST,
                    array(
                        'delcu' => array(
                            'flags' => FILTER_REQUIRE_ARRAY
                        )
                    )
                );
                $delcus = $delcus['delcu'];
                self::getClass('ClientUpdaterManager')
                    ->destroy(array('id' => $delcus));
                echo json_encode(
                    array(
                        'msg' => _('Item removed successfully!'),
                        'title' => _('Client Modules Change Success')
                    )
                );
                exit;
            }
            if (isset($_POST['upload'])) {
                if (count($_FILES['module']['tmp_name']) < 1) {
                    throw new Exception(_('No file uploaded!'));
                }
                $error = $_FILES['module']['error'];
                foreach ((array)$error as &$err) {
                    if ($err > 0) {
                        throw new UploadException($err);
                    }
                    unset($err);
                }
                $tmpfiles = $_FILES['module']['tmp_name'];
                foreach ((array)$tmpfiles as $index => &$tmp_name) {
                    if (!file_exists($tmp_name)) {
                        continue;
                    }
                    if (!($md5 = md5_file($tmp_name))) {
                        continue;
                    }
                    $filename = basename(
                        $_FILES['module']['name'][$index]
                    );
                    $fp = fopen(
                        $tmp_name,
                        'rb'
                    );
                    $content = fread(
                        $fp,
                        self::getFilesize($tmp_name)
                    );
                    fclose($fp);
                    $finfo = new finfo(FILEINFO_MIME);
                    $f = $finfo->file($tmp_name);
                    self::getClass('ClientUpdater')
                        ->set('name', $filename)
                        ->load('name')
                        ->set('md5', $md5)
                        ->set('type', $f)
                        ->set('file', $content)
                        ->save();
                }
                global $tab;
                self::redirect(
                    $this->formAction
                );
            }
            $msg = json_encode(
                array(
                    'msg' => _('Modules added/updated successfully!'),
                    'title' => _('Client Modules Change Success')
                )
            );
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Client Modules Change Fail')
                )
            );
        }
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
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body text-center">';
        echo _(
            'This section allows you to import known mac address makers '
            . 'into the FOG database for easier identification'
        );
        echo '<br/>';
        echo '<div class="row">';
        echo _('Current Records');
        echo ': ';
        echo self::getMACLookupCount();
        echo '</div>';
        echo '<br/>';
        echo '<div class="row">';
        echo '<a href="http://standards-oui.ieee.org/oui.txt">';
        echo 'http://standards-oui.ieee.org/oui.txt';
        echo '</a>';
        echo '</div>';
        echo '<br/>';
        echo '<div class="row">';
        echo '<div id="delete"></div>';
        echo '<div id="update"></div>';
        echo '<div class="form-group col-xs-offset-4 col-xs-2">';
        echo '<button class="macButtons btn btn-danger btn-block" type='
            . '"button" id="macButtonDel">';
        echo _('Delete MACs');
        echo '</button>';
        echo '</div>';
        echo '<div class="form-group col-xs-2">';
        echo '<button class="macButtons btn btn-info btn-block" type='
            . '"button" id="macButtonUp">';
        echo _('Update MACs');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
        if (isset($_GET['update'])) {
            self::clearMACLookupTable();
            $url = 'http://standards-oui.ieee.org/oui.txt';
            if (($fh = fopen($url, 'rb')) === false) {
                throw new Exception(_('Could not read temp file'));
            }
            $items = array();
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
                $items[] = array(
                    $mac,
                    $mak
                );
            }
            fclose($fh);
            if (count($items) > 0) {
                list(
                    $first_id,
                    $affected_rows
                ) = self::getClass('OUIManager')
                ->insertBatch(
                    array(
                        'prefix',
                        'name'
                    ),
                    $items
                );
                $imported += $affected_rows;
                unset($items);
            }
            unset($first_id);
        }
        if (isset($_GET['clear'])) {
            self::clearMACLookupTable();
        }
        self::resetRequest();
        self::redirect('?node=about&sub=maclist');
    }
    /**
     * The fog settings
     *
     * @return void
     */
    public function settings()
    {
        $ServiceNames = array(
            'FOG_REGISTRATION_ENABLED',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_QUICKREG_AUTOPOP',
            'FOG_CLIENT_AUTOUPDATE',
            'FOG_CLIENT_AUTOLOGOFF_ENABLED',
            'FOG_CLIENT_CLIENTUPDATER_ENABLED',
            'FOG_CLIENT_DIRECTORYCLEANER_ENABLED',
            'FOG_CLIENT_DISPLAYMANAGER_ENABLED',
            'FOG_CLIENT_GREENFOG_ENABLED',
            'FOG_CLIENT_HOSTREGISTER_ENABLED',
            'FOG_CLIENT_HOSTNAMECHANGER_ENABLED',
            'FOG_CLIENT_POWERMANAGEMENT_ENABLED',
            'FOG_CLIENT_PRINTERMANAGER_ENABLED',
            'FOG_CLIENT_SNAPIN_ENABLED',
            'FOG_CLIENT_TASKREBOOT_ENABLED',
            'FOG_CLIENT_USERCLEANUP_ENABLED',
            'FOG_CLIENT_USERTRACKER_ENABLED',
            'FOG_ADVANCED_STATISTICS',
            'FOG_CHANGE_HOSTNAME_EARLY',
            'FOG_DISABLE_CHKDSK',
            'FOG_HOST_LOOKUP',
            'FOG_CAPTUREIGNOREPAGEHIBER',
            'FOG_USE_ANIMATION_EFFECTS',
            'FOG_USE_LEGACY_TASKLIST',
            'FOG_USE_SLOPPY_NAME_LOOKUPS',
            'FOG_PLUGINSYS_ENABLED',
            'FOG_FORMAT_FLAG_IN_GUI',
            'FOG_NO_MENU',
            'FOG_ALWAYS_LOGGED_IN',
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_TASK_FORCE_REBOOT',
            'FOG_EMAIL_ACTION',
            'FOG_FTP_IMAGE_SIZE',
            'FOG_KERNEL_DEBUG',
            'FOG_ENFORCE_HOST_CHANGES',
            'FOG_LOGIN_INFO_DISPLAY',
            'MULTICASTGLOBALENABLED',
            'SCHEDULERGLOBALENABLED',
            'PINGHOSTGLOBALENABLED',
            'IMAGESIZEGLOBALENABLED',
            'IMAGEREPLICATORGLOBALENABLED',
            'SNAPINREPLICATORGLOBALENABLED',
            'SNAPINHASHGLOBALENABLED',
            'FOG_QUICKREG_IMG_WHEN_REG',
            'FOG_QUICKREG_PROD_KEY_BIOS',
            'FOG_TASKING_ADV_SHUTDOWN_ENABLED',
            'FOG_TASKING_ADV_WOL_ENABLED',
            'FOG_TASKING_ADV_DEBUG_ENABLED',
            'FOG_API_ENABLED',
            'FOG_IMAGE_LIST_MENU',
            'FOG_REAUTH_ON_DELETE',
            'FOG_REAUTH_ON_EXPORT'
        );
        self::$HookManager
            ->processEvent(
                'SERVICE_NAMES',
                array(
                    'ServiceNames' => &$ServiceNames
                )
            );
        $this->title = _('FOG System Settings');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" enctype="multipart/form-data">';
        echo _('This section allows you to customize or alter')
            . ' '
            . _('the way in which FOG operates')
            . '. '
            . _('Please be very careful changing any of the following settings')
            . ', '
            . _('as they can cause issues that are difficult to troubleshoot')
            . '.';
        echo '<hr/>';
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-6 form-group'),
            array('class' => 'col-xs-2'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${span}',
        );
        echo '<div class="col-xs-12">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center trigger_expand hand">';
        echo '<h4 class="title">';
        echo _('Expand All');
        echo '</h4>';
        echo '</div>';
        echo '</div>';
        $catset = false;
        Route::listem('service', 'category', true);
        $Services = json_decode(
            Route::getData()
        );
        $Services = $Services->services;
        $divTab = false;
        foreach ((array)$Services as &$Service) {
            $curcat = $Service->category;
            if (!$divTab) {
                $divTab = preg_replace(
                    '#[^\w\-]#',
                    '_',
                    $Service->category
                );
            }
            if ($curcat != $catset) {
                if ($catset !== false) {
                    $this->data[] = array(
                        'field' => '<label for='
                        . '"'
                        . $divTab
                        . '">'
                        . _('Make Changes?')
                        . '</label>',
                        'input' => '<button class="'
                        . 'btn btn-info btn-block" type="submit" '
                        . 'name="'
                        . $divTab
                        . '" id="'
                        . $divTab
                        . '">'
                        . _('Update')
                        . '</button>',
                        'span' => ''
                    );
                    $this->render(12);
                    unset($this->data);
                    echo '</div>';
                    echo '</div>';
                    $divTab = preg_replace(
                        '#[^\w\-]#',
                        '_',
                        $Service->category
                    );
                }
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center expand_trigger '
                    . 'hand" id="'
                    . $divTab
                    . '">';
                echo '<h4 class="title">';
                echo $Service->category;
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body hidefirst" id="'
                    . $divTab
                    . '">';
            }
            switch ($Service->name) {
            case 'FOG_PIGZ_COMP':
                $type = '<div class="col-xs-8">'
                    . '<div class="rangegen pigz"></div>'
                    . '</div>'
                    . '<div class="col-xs-4">'
                    . '<div class="input-group">'
                    . '<input type="text" name="${service_id}" class="form-control '
                    . 'showVal pigz" maxsize="2" value="${service_value}" id='
                    . '"${service_name}" readonly/>'
                    . '</div>'
                    . '</div>';
                break;
            case 'FOG_KERNEL_LOGLEVEL':
                $type = '<div class="col-xs-8">'
                    . '<div class="rangegen loglvl"></div>'
                    . '</div>'
                    . '<div class="col-xs-4">'
                    . '<div class="input-group">'
                    . '<input type="text" name="${service_id}" class="form-control '
                    . 'showVal loglvl" maxsize="2" value="${service_value}" id='
                    . '"${service_name}" readonly/>'
                    . '</div>'
                    . '</div>';
                break;
            case 'FOG_INACTIVITY_TIMEOUT':
                $type = '<div class="col-xs-8">'
                    . '<div class="rangegen inact"></div>'
                    . '</div>'
                    . '<div class="col-xs-4">'
                    . '<div class="input-group">'
                    . '<input type="text" name="${service_id}" class="form-control '
                    . 'showVal inact" maxsize="2" value="${service_value}" id='
                    . '"${service_name}" readonly/>'
                    . '</div>'
                    . '</div>';
                break;
            case 'FOG_REGENERATE_TIMEOUT':
                $type = '<div class="col-xs-8">'
                    . '<div class="rangegen regen"></div>'
                    . '</div>'
                    . '<div class="col-xs-4">'
                    . '<div class="input-group">'
                    . '<input type="text" name="${service_id}" class="form-control '
                    . 'showVal regen" maxsize="5" value="${service_value}" id='
                    . '"${service_name}" readonly/>'
                    . '</div>'
                    . '</div>';
                break;
            case 'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT':
                $vals = array(
                    _('Partclone Gzip') => 0,
                    _('Partclone Gzip Split 200MiB') => 2,
                    _('Partclone Uncompressed') => 3,
                    _('Partclone Uncompressed Split 200MiB') => 4,
                    _('Partclone Zstd') => 5,
                    _('Partclone Zstd Split 200MiB') => 6
                );
                ob_start();
                foreach ((array)$vals as $view => &$value) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        $value,
                        (
                            $Service->value == $value ?
                            ' selected' :
                            ''
                        ),
                        $view
                    );
                    unset($value);
                }
                unset($vals);
                $type = '<div class="input-group">'
                    . '<select name="${service_id}" '
                    . 'autocomplete="off" '
                    . 'class="form-control" id="${service_name}">'
                    . ob_get_clean()
                    . '</select>'
                    . '</div>';
                break;
            case 'FOG_VIEW_DEFAULT_SCREEN':
                $screens = array('SEARCH','LIST');
                ob_start();
                foreach ((array)$screens as &$viewop) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        strtolower($viewop),
                        (
                            $Service->value == strtolower($viewop) ?
                            ' selected' :
                            ''
                        ),
                        $viewop
                    );
                    unset($viewop);
                }
                unset($screens);
                $type = '<div class="input-group">'
                    . '<select name="${service_id}" '
                    . 'autocomplete="off" '
                    . 'class="form-control" id="${service_name}">'
                    . ob_get_clean()
                    . '</select>'
                    . '</div>';
                break;
            case 'FOG_MULTICAST_DUPLEX':
                $duplexTypes = array(
                    'HALF_DUPLEX' => '--half-duplex',
                    'FULL_DUPLEX' => '--full-duplex',
                );
                ob_start();
                foreach ((array)$duplexTypes as $types => &$val) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        $val,
                        (
                            $Service->value == $val ?
                            ' selected' :
                            ''
                        ),
                        $types
                    );
                    unset($val);
                }
                $type = '<div class="input-group">'
                    . '<select name="${service_id}" '
                    . 'autocomplete="off" '
                    . 'class="form-control" id="${service_name}">'
                    . ob_get_clean()
                    . '</select>'
                    . '</div>';
                break;
            case 'FOG_BOOT_EXIT_TYPE':
            case 'FOG_EFI_BOOT_EXIT_TYPE':
                $type = '<div class="input-group">'
                    . Service::buildExitSelector(
                        $Service->id,
                        $Service->value,
                        false,
                        $Service->name
                    )
                    . '</div>';
                break;
            case 'FOG_DEFAULT_LOCALE':
                $locale = self::getSetting('FOG_DEFAULT_LOCALE');
                ob_start();
                $langs =& self::$foglang['Language'];
                foreach ($langs as $lang => &$humanreadable) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        $lang,
                        (
                            $locale == $lang
                            || $locale == self::$foglang['Language'][$lang] ?
                            ' selected' :
                            ''
                        ),
                        $humanreadable
                    );
                    unset($humanreadable);
                }
                $type = '<div class="input-group">'
                    . '<select name="${service_id}" '
                    . 'autocomplete="off" '
                    . 'class="form-control" id="${service_name}">'
                    . ob_get_clean()
                    . '</select>'
                    . '</div>';
                break;
            case 'FOG_QUICKREG_IMG_ID':
                $type = '<div class="input-group">'
                    . self::getClass('ImageManager')->buildSelectBox(
                        $Service->value,
                        $Service->id
                    )
                    . '</div>';
                break;
            case 'FOG_QUICKREG_GROUP_ASSOC':
                $type = '<div class="input-group">'
                    . self::getClass('GroupManager')->buildSelectBox(
                        $Service->value,
                        $Service->id
                    )
                    . '</div>';
                break;
            case 'FOG_KEY_SEQUENCE':
                $type = '<div class="input-group">'
                    . self::getClass('KeySequenceManager')
                    ->buildSelectBox(
                        $Service->value,
                        $Service->id
                    )
                    . '</div>';
                break;
            case 'FOG_QUICKREG_OS_ID':
                $ImageName = _('No image specified');
                if ($Service->value > 0) {
                    $ImageName = self::getClass(
                        'Image',
                        $Service->value
                    )->get('name');
                }
                $type = '<p id="${service_name}">'
                    . $ImageName
                    . '</p>';
                break;
            case 'FOG_TZ_INFO':
                $dt = self::niceDate('now', $utc);
                $tzIDs = DateTimeZone::listIdentifiers();
                ob_start();
                echo '<div class="input-group">';
                echo '<select name="${service_id}" class="form-control" '
                    . 'id="${service_name}">';
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
                            $Service->value == $tz ?
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
                echo '</div>';
                $type = ob_get_clean();
                break;
            case ('FOG_API_TOKEN' === $Service->name ||
                (preg_match('#pass#i', $Service->name)
                && !preg_match('#(valid|min)#i', $Service->name))):
                $type = '<div class="input-group">';
                switch ($Service->name) {
                case 'FOG_API_TOKEN':
                    $type .= '<input type="password" name="${service_id}" value="'
                        . '${service_base64val}" autocomplete="off" class='
                        . '"form-control token"'
                        . 'id="${service_name}" readonly/>'
                        . '<div class="input-group-btn">'
                        . '<button class='
                        . '"btn btn-warning resettoken" type="button">'
                        . _('Reset Token')
                        . '</button>'
                        . '</div>';
                    break;
                case 'FOG_STORAGENODE_MYSQLPASS':
                    $type .= '<input type="text" name="${service_id}" value="'
                        . '${service_value}" autocomplete="off" class='
                        . '"form-control" id="${service_name}"/>';
                    break;
                case 'FOG_AD_DEFAULT_PASSWORD':
                    $type .= '<input name="${service_id}" type="password" value="'
                        . ($Service->value ? '********************************' : '')
                        . '" autocomplete="off" class='
                        . '"form-control" id="${service_name}"/>';
                break;
                default:
                    $type .= '<input type="password" name="${service_id}" value="'
                        . '${service_value}" autocomplete="off" class='
                        . '"form-control" id="${service_name}"/>';
                }
                $type .= '</div>';
                break;
            case (in_array($Service->name, $ServiceNames)):
                $type = '<input type="checkbox" name="${service_id}" value="1" '
                    . 'id="${service_name}"'
                    . (
                        $Service->value ?
                        ' checked' :
                        ''
                    )
                    . '/>';
                break;
            case 'FOG_COMPANY_TOS':
            case 'FOG_AD_DEFAULT_OU':
                $type = '<div class="input-group">'
                    . '<textarea name="${service_id}" class='
                    . '"form-control" id="${service_name}">'
                    . '${service_value}'
                    . '</textarea>'
                    . '</div>';
                break;
            case 'FOG_CLIENT_BANNER_IMAGE':
                $type = '<div class="input-group">'
                    . '<label class="input-group-btn">'
                    . '<span class="btn btn-info">'
                    . _('Browse')
                    . '<input type="file" class="hidden" name='
                    . '"${service_id}" id="${service_name}"/>'
                    . '</span>'
                    . '</label>'
                    . '<input type="text" class="form-control filedisp" '
                    . 'value="${service_value}" readonly/>'
                    . '<input type="hidden" class="filedisp" '
                    . 'value="${service_value}" name="banner"/>'
                    . '</div>';
                break;
            case 'FOG_CLIENT_BANNER_SHA':
                $type = '<div class="input-group">'
                    . '<input class="form-control" name="${service_id}" type='
                    . '"text" value="${service_value}" id="${service_name}"'
                    . ' readonly/>'
                    . '</div>';
                break;
            case 'FOG_COMPANY_COLOR':
                $type = '<div class="input-group">'
                    . '<input name="${service_id}" type="text" maxlength="6" value='
                    . '"${service_value}" id="${service_name}" class='
                    . '"jscolor {required:false} {refine: false} form-control"/>'
                    . '</div>';
                break;
            default:
                $type = '<div class="input-group">'
                    . '<input id="${service_name}" type="text" name="${service_id}" '
                    . 'value="${service_value}" autocomplete="off" '
                    . 'class="form-control">'
                    . '</div>';
                break;
            }
            $this->data[] = array(
                'field' => '<label for="${service_name}">'
                . '${label_name}'
                . '</label>',
                'input' => (
                    count(
                        explode(
                            chr(10),
                            $Service->value
                        )
                    ) <= 1 ?
                    $type :
                    '<div class="input-group">'
                    . '<textarea name="${service_id}" '
                    . 'class="form-control" id="${service_name}">'
                    . '${service_value}'
                    . '</textarea>'
                    . '</div>'
                ),
                'span' => '<i class="icon fa fa-question hand" title='
                . '"${service_desc}" data-toggle='
                . '"tooltip" data-placement="right"></i>',
                'id' => $Service->id,
                'service_id' => $Service->id,
                'service_name' => $Service->name,
                'label_name' => str_replace(
                    array('FOG_', '_'),
                    array('', ' '),
                    $Service->name
                ),
                'service_value' => $Service->value,
                'service_base64val' => base64_encode($Service->value),
                'service_desc' => $Service->description,
            );
            self::$HookManager
                ->processEvent(
                    sprintf(
                        'CLIENT_UPDATE_%s',
                        $divTab
                    ),
                    array(
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            $catset = $Service->category;
            unset($options, $Service);
        }
        $this->data[] = array(
            'field' => '<label for='
            . '"'
            . $divTab
            . '">'
            . _('Make Changes?')
            . '</label>',
            'input' => '<button class="'
            . 'btn btn-info btn-block" type="submit" '
            . 'name="'
            . $divTab
            . '" id="'
            . $divTab
            . '">'
            . _('Update')
            . '</button>',
            'span' => ''
        );
        $this->render(12);
        unset($this->data);
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
        $checkbox = array(0,1);
        $regenrange = range(0, 24, .25);
        array_shift($regenrange);
        $needstobenumeric = array(
            // API System
            'FOG_API_ENABLED' => $checkbox,
            // FOG Boot Settings
            'FOG_PXE_MENU_TIMEOUT' => true,
            'FOG_PXE_MENU_HIDDEN' => $checkbox,
            'FOG_PIGZ_COMP' => range(0, 22),
            'FOG_KEY_SEQUENCE' => range(1, 35),
            'FOG_NO_MENU' => $checkbox,
            'FOG_ADVANCED_MENU_LOGIN' => $checkbox,
            'FOG_KERNEL_DEBUG' => $checkbox,
            'FOG_PXE_HIDDENMENU_TIMEOUT' => true,
            'FOG_REGISTRATION_ENABLED' => $checkbox,
            'FOG_KERNEL_LOGLEVEL' => range(0, 7),
            'FOG_WIPE_TIMEOUT' => true,
            'FOG_IMAGE_LIST_MENU' => $checkbox,
            // FOG Email Settings
            'FOG_EMAIL_ACTION' => $checkbox,
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
            'FOG_QUICKREG_AUTOPOP' => $checkbox,
            'FOG_QUICKREG_IMG_ID' => self::fastmerge(
                (array)0,
                self::getSubObjectIDs('Image')
            ),
            'FOG_QUICKREG_SYS_NUMBER' => true,
            'FOG_QUICKREG_GROUP_ASSOC' => self::fastmerge(
                (array)0,
                self::getSubObjectIDs('Group')
            ),
            'FOG_QUICKREG_PROD_KEY_BIOS' => $checkbox,
            // FOG Service
            'FOG_CLIENT_CHECKIN_TIME' => true,
            'FOG_CLIENT_MAXSIZE' => true,
            'FOG_GRACE_TIMEOUT' => true,
            'FOG_CLIENT_AUTOUPDATE' => $checkbox,
            // FOG Service - Auto Log Off
            'FOG_CLIENT_AUTOLOGOFF_ENABLED' => $checkbox,
            'FOG_CLIENT_AUTOLOGOFF_MIN' => true,
            // FOG Service - Client Updater
            'FOG_CLIENT_CLIENTUPDATER_ENABLED' => $checkbox,
            // FOG Service - Directory Cleaner
            'FOG_CLIENT_DIRECTORYCLEANER_ENABLED' => $checkbox,
            // FOG Service - Display manager
            'FOG_CLIENT_DISPLAYMANAGER_ENABLED' => $checkbox,
            'FOG_CLIENT_DISPLAYMANAGER_X' => true,
            'FOG_CLIENT_DISPLAYMANAGER_Y' => true,
            'FOG_CLIENT_DISPLAYMANAGER_R' => true,
            // FOG Service - Green Fog
            'FOG_CLIENT_GREENFOG_ENABLED' => $checkbox,
            // FOG Service - Host Register
            'FOG_CLIENT_HOSTREGISTER_ENABLED' => $checkbox,
            'FOG_QUICKREG_MAX_PENDING_MACS' => true,
            // FOG Service - Hostname Changer
            'FOG_CLIENT_HOSTNAMECHANGER_ENABLED' => $checkbox,
            // FOG Service - Power Management
            'FOG_CLIENT_POWERMANAGEMENT_ENABLED' => $checkbox,
            // FOG Service - Printer Manager
            'FOG_CLIENT_PRINTERMANAGER_ENABLED' => $checkbox,
            // FOG Service - Snapins
            'FOG_CLIENT_SNAPIN_ENABLED' => $checkbox,
            // FOG Service - Task Reboot
            'FOG_CLIENT_TASKREBOOT_ENABLED' => $checkbox,
            'FOG_TASK_FORCE_ENABLED' => $checkbox,
            // FOG Service - User Cleanup
            'FOG_CLIENT_USERCLEANUP_ENABLED' => $checkbox,
            // FOG Service - User Tracker
            'FOG_CLIENT_USERTRACKER_ENABLED' => $checkbox,
            // FOG View Settings
            'FOG_DATA_RETURNED' => true,
            // General Settings
            'FOG_USE_SLOPPY_NAME_LOOKUPS' => $checkbox,
            'FOG_CAPTURERESIZEPCT' => true,
            'FOG_CHECKIN_TIMEOUT' => true,
            'FOG_CAPTUREIGNOREPAGEHIBER' => $checkbox,
            'FOG_USE_ANIMATION_EFFECTS' => $checkbox,
            'FOG_USE_LEGACY_TASKLIST' => $checkbox,
            'FOG_HOST_LOOKUP' => $checkbox,
            'FOG_ADVANCED_STATISTICS' => $checkbox,
            'FOG_DISABLE_CHKDSK' => $checkbox,
            'FOG_CHANGE_HOSTNAME_EARLY' => $checkbox,
            'FOG_FORMAT_FLAG_IN_GUI' => $checkbox,
            'FOG_MEMORY_LIMIT' => true,
            'FOG_SNAPIN_LIMIT' => true,
            'FOG_FTP_IMAGE_SIZE' => $checkbox,
            'FOG_FTP_PORT' => range(1, 65535),
            'FOG_FTP_TIMEOUT' => true,
            'FOG_BANDWIDTH_TIME' => true,
            'FOG_URL_BASE_CONNECT_TIMEOUT' => true,
            'FOG_URL_BASE_TIMEOUT' => true,
            'FOG_URL_AVAILABLE_TIMEOUT' => true,
            'FOG_TASKING_ADV_SHUTDOWN_ENABLED' => $checkbox,
            'FOG_TASKING_ADV_WOL_ENABLED' => $checkbox,
            'FOG_TASKING_ADV_DEBUG_ENABLED' => $checkbox,
            'FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT' => self::fastmerge(
                (array)0,
                range(2, 6)
            ),
            'FOG_REAUTH_ON_DELETE' => $checkbox,
            'FOG_REAUTH_ON_EXPORT' => $checkbox,
            // Login Settings
            'FOG_ALWAYS_LOGGED_IN' => $checkbox,
            'FOG_INACTIVITY_TIMEOUT' => range(1, 24),
            'FOG_REGENERATE_TIMEOUT' => $regenrange,
            // Multicast Settings
            'FOG_UDPCAST_STARTINGPORT' => range(1, 65535),
            'FOG_MULTICASE_MAX_SESSIONS' => true,
            'FOG_UDPCAST_MAXWAIT' => true,
            'FOG_MULTICAST_PORT_OVERRIDE' => range(0, 65535),
            // Plugin System
            'FOG_PLUGINSYS_ENABLED' => $checkbox,
            // Proxy Settings
            'FOG_PROXY_PORT' => range(0, 65535),
            // User Management
            'FOG_USER_MINPASSLENGTH' => true,
        );
        $needstobeip = array(
            // Multicast Settings
            'FOG_MULTICAST_ADDRESS' => true,
            'FOG_MULTICAST_RENDEZVOUS' => true,
            // Proxy Settings
            'FOG_PROXY_IP' => true,
        );
        unset($findWhere, $setWhere);
        Route::listem('service', 'id', true);
        $Services = json_decode(
            Route::getData()
        );
        $Services = $Services->services;
        try {
            foreach ((array)$Services as $index => &$Service) {
                $divTab = preg_replace(
                    '#[^\w\-]#',
                    '_',
                    $Service->category
                );
                if (!isset($_POST[$divTab])) {
                    continue;
                }
                $key = trim(
                    $Service->id
                );
                $val = trim(
                    $Service->value
                );
                $name = trim(
                    $Service->name
                );
                $set = filter_var($_POST[$key]);
                if (isset($needstobenumeric[$name])) {
                    if ($needstobenumeric[$name] === true
                        && !is_numeric($set)
                    ) {
                        $set = 0;
                    }
                    if ($needstobenumeric[$name] !== true
                        && !in_array($set, $needstobenumeric[$name])
                    ) {
                        $set = 0;
                    }
                }
                if (isset($needstobeip[$name])
                    && !filter_var($set, FILTER_VALIDATE_IP)
                ) {
                    $set = '';
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
                        $set = 128;
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
                $items[] = array($key, $name, $set);
                unset($Service, $index);
            }
            if (count($items) > 0) {
                self::getClass('ServiceManager')
                    ->insertBatch(
                        array(
                            'id',
                            'name',
                            'value'
                        ),
                        $items
                    );
            }
            $msg = json_encode(
                array(
                    'msg' => _('Settings successfully stored!'),
                    'title' => _('Settings Update Success')
                )
            );
            if (isset($_POST['Rebranding'])) {
                echo '<div class="col-xs-9">';
                echo '<div class="panel panel-success">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Service Setting Update Success');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo _('Rebranding element has been successfully updated!');
                echo '</div>';
                echo '</div>';
                echo '</div>';
                return;
            }
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Settings Update Fail')
                )
            );
            if (isset($_POST['Rebranding'])) {
                echo '<div class="col-xs-9">';
                echo '<div class="panel panel-warning">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Service Setting Update Failed');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo $e->getMessage();
                echo '</div>';
                echo '</div>';
                echo '</div>';
                return;
            }
        }
        if (isset($_POST['Rebranding'])) {
            self::redirect(
                $this->formAction
            );
        }
        echo $msg;
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
        $StorageGroups = $StorageGroups->storagegroups;
        foreach ((array)$StorageGroups as &$StorageGroup) {
            if (count($StorageGroup->enablednodes) < 1) {
                continue;
            }
            $nodeIDs = self::getSubObjectIDs(
                'StorageNode',
                array(
                    'id' => $StorageGroup->enablednodes,
                    'isMaster' => 1
                )
            );
            if (count($nodeIDs ?: []) != 1) {
                continue;
            }
            Route::indiv(
                'storagenode',
                array_shift($nodeIDs),
                'logfiles'
            );
            $StorageNode = json_decode(
                Route::getData()
            );
            if (!$StorageNode->isEnabled) {
                continue;
            }
            $fogfiles = json_decode(
                json_encode($StorageNode->logfiles),
                true
            );
            try {
                $apacheerrlog = preg_grep(
                    '#(error\.log$|.*error_log$)#i',
                    $fogfiles
                );
                $apacheacclog = preg_grep(
                    '#(access\.log$|.*access_log$)#i',
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
                $files[$StorageNode->name] = array(
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
                );
                $logtype = 'error';
                $logparse = function (&$log) use (&$files, $StorageNode, &$logtype) {
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
                $files[$StorageNode->name] = array(
                    $e->getMessage() => null,
                );
            }
            $ip[$StorageNode->name] = $StorageNode->ip;
            self::$HookManager
                ->processEvent(
                    'LOG_VIEWER_HOOK',
                    array(
                        'files' => &$files,
                        'StorageNode' => &$StorageNode
                    )
                );
            unset($StorageGroup);
        }
        unset($StorageGroups);
        ob_start();
        foreach ((array)$files as $nodename => &$filearray) {
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
        $logOpts = ob_get_clean();
        $vals = array(
            20,
            50,
            100,
            200,
            400,
            500,
            1000
        );
        ob_start();
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
        $lineOpts = ob_get_clean();
        $this->title = _('FOG Log Viewer');
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<div class="col-xs-4">';
        echo '<label class="control-label" for="logToView">';
        echo _('File') .': ';
        echo '</label>';
        echo '<select name="logtype" class="form-control" id="logToView">';
        echo $logOpts;
        echo '</select>';
        echo '</div>';
        echo '<div class="col-xs-4">';
        echo '<label class="control-label" for="linesToView">';
        echo _('Lines') .': ';
        echo '</label>';
        echo '<select name="n" class="form-control" id="linesToView">';
        echo $lineOpts;
        echo '</select>';
        echo '</div>';
        echo '<div class="col-xs-2">';
        echo '<div class="checkbox">';
        echo '<label for="reverse">';
        echo '<input type="checkbox" name="reverse" id="reverse"/>';
        echo _('Reverse the file: (newest on top)');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-xs-2">';
        echo '<button type="button" id="logpause" class="btn btn-info btn-block">';
        echo _('Pause');
        echo '</button>';
        echo '</div>';
        echo '<div class="col-xs-12">';
        echo '<div id="logsGoHere"></div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Present the config screen.
     *
     * @return void
     */
    public function config()
    {
        self::$HookManager->processEvent('IMPORT');
        $this->title = _('Configuration Import/Export');
        $report = self::getClass('ReportMaker');
        $_SESSION['foglastreport'] = serialize($report);
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->data[] = array(
            'field' => '<label for="export">'
            . _('Export Database?')
            . '</label>',
            'input' => '<div class="hiddeninitially" id="exportDiv"></div>'
            . '<button type="submit" name="export" class="'
            . 'btn btn-info btn-block" id="export">'
            . _('Export')
            . '</button>'
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Export Database');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action='
            . '"export.php?type=sql">';
        $this->render(12);
        $this->data = array();
        $this->data[] = array(
            'field' => '<label for="import">'
            . _('Import Database?')
            . '<br/>'
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . '</label>',
            'input' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden" name='
            . '"dbFile" id="import"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp" readonly/>'
            . '</div>'
        );
        $this->data[] = array(
            'field' => '<label for="importbtn">'
            . _('Import Database?')
            . '</label>',
            'input' => '<button type="submit" name="importbtn" class="'
            . 'btn btn-info btn-block" id="importbtn">'
            . _('Import')
            . '</button>'
        );
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Import Database');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" enctype="multipart/form-data">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Process import of config data
     *
     * @return void
     */
    public function configPost()
    {
        self::$HookManager->processEvent('IMPORT_POST');
        $Schema = self::getClass('Schema');
        try {
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
            echo '<div class="col-xs-9">';
            if ($result === true) {
                echo '<div class="panel panel-success">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Import Successful');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo _('Database imported and added successfully!');
                echo '</div>';
                echo '</div>';
            } else {
                $origres = $result;
                $result = $Schema->importdb($original);
                unlink($original);
                unset($original);
                echo '<div class="panel panel-warning">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Import Failed');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo _('There were errors during import!');
                echo '<br/>';
                echo '<br/>';
                echo '<pre>';
                echo $origres;
                echo '</pre>';
                if ($result === true) {
                    echo '<div class="panel panel-success">';
                    echo '<div class="panel-heading text-center">';
                    echo _('Database Reverted');
                    echo '</div>';
                    echo '<div class="panel-body">';
                    echo _('Database changes reverted!');
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="panel panel-danger">';
                    echo '<div class="panel-heading text-center">';
                    echo '<h4 class="title">';
                    echo _('Database Failure');
                    echo '</h4>';
                    echo '</div>';
                    echo '<div class="panel-body">';
                    echo _('Errors on revert detected!');
                    echo '<br/>';
                    echo '<br/>';
                    echo '<pre>';
                    echo $result;
                    echo '</pre>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
}
