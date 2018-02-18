<?php
/**
 * Configure global level module/services.
 * These are things like hostname changer, display, etc...
 *
 * PHP version 5
 *
 * @category ServiceConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Configure global level module/services.
 * These are things like hostname changer, display, etc...
 *
 * @category ServiceConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ServiceConfigurationPage extends FOGPage
{
    /**
     * The global module status storage
     *
     * @var array
     */
    private static $_moduleName = [];
    /**
     * The global entry points storage
     *
     * @var array
     */
    private static $_modNames = [];
    /**
     * The node this page works off of.
     *
     * @var string
     */
    public $node = 'service';
    /**
     * Initializes the service page.
     *
     * @param string $name The name to start with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Service Configuration';
        parent::__construct($this->name);
        self::$_moduleName = self::getGlobalModuleStatus();
        self::$_modNames = self::getGlobalModuleStatus(true);
    }
    /**
     * Presents the home for this page.
     *
     * @return void
     */
    public function serviceHome()
    {
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo _('This will allow you to configure how services');
        echo ' ';
        echo _('function on client computers.');
        echo _('The settings tend to be global which affects all hosts.');
        echo _('If you are looking to configure settings for a specific host');
        echo ', ';
        echo _('please see the hosts service settings section.');
        echo _('To get started please select an item from the menu.');
        echo '<hr/>';
        echo _('Use the following link to go to the client page.');
        echo ' ';
        echo _('There you can download utilities such as FOG Prep');
        echo ' ';
        echo _('and the FOG client.');
        echo '<br/>';
        echo '<a href="../management/index.php?node=client">';
        echo _('Click Here');
        echo '</a>';
        echo '</div>';
        echo '</div>';

    }
    /**
     * Presents the displaymanager page.
     *
     * @return void
     */
    public function serviceDisplaymanager()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-displaymanager" ';

        $buttons = self::makeButton(
            'displaymanager-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','display manager');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="isdmEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="isdmEnabled"'
            . (
                self::$_moduleName['displaymanager'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="isdmDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="isdmDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODULE_DISPLAYMANAGER_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Display Manager -->';
        echo '<div class="box-group" id="displaymanager">';
        echo '<div class="box box-solid">';
        echo '<div id="updatedisplaymanager" class="">';
        echo '<div class="box-body">';
        echo '<form id="displayupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['displaymanager']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the display manager elements.
     *
     * @return void
     */
    public function serviceDisplaymanagerPost()
    {
        Route::search('module','display manager');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['displaymanager'])
            ->load('name');
    }
    /**
     * Presents the autologout page.
     *
     * @return void
     */
    public function serviceAutologout()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-autologout" ';

        $buttons = self::makeButton(
            'autologout-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','auto log out');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="isaloEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="isaloEnabled"'
            . (
                self::$_moduleName['autologout'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="isaloDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="isaloDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODULE_AUTOLOGOUT_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Auto Logout -->';
        echo '<div class="box-group" id="autologout">';
        echo '<div class="box box-solid">';
        echo '<div id="updateautologout" class="">';
        echo '<div class="box-body">';
        echo '<form id="autologoutupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['autologout']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the autologout elements.
     *
     * @return void
     */
    public function serviceAutologoutPost()
    {
        Route::search('module','auto log out');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['autologout'])
            ->load('name');
    }
    /**
     * Presents the snapin client page.
     *
     * @return void
     */
    public function serviceSnapinclient()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-snapinclient" ';

        $buttons = self::makeButton(
            'snapinclient-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','snapins');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="isscEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="isscEnabled"'
            . (
                self::$_moduleName['snapinclient'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="isscDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="isscDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODUL_SNAPINCLIENT_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Snapin Client -->';
        echo '<div class="box-group" id="snapinclient">';
        echo '<div class="box box-solid">';
        echo '<div id="updatesnapinclient" class="">';
        echo '<div class="box-body">';
        echo '<form id="snapinclientupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['snapinclient']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the snapinclient elements.
     *
     * @return void
     */
    public function serviceSnapinclientPost()
    {
        Route::search('module','snapins');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['snapinclient'])
            ->load('name');
    }
    /**
     * Presents the host register page.
     *
     * @return void
     */
    public function serviceHostregister()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-hostregister" ';

        $buttons = self::makeButton(
            'hostregister-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','host registration');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="ishrEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="ishrEnabled"'
            . (
                self::$_moduleName['hostregister'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="ishrDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="ishrDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODUL_HOSTREGISTER_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Host Register -->';
        echo '<div class="box-group" id="hostregister">';
        echo '<div class="box box-solid">';
        echo '<div id="updatehostregister" class="">';
        echo '<div class="box-body">';
        echo '<form id="hostregisterupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['hostregister']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the Host register elements.
     *
     * @return void
     */
    public function serviceHostregisterPost()
    {
        Route::search('module','host registration');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['hostregister'])
            ->load('name');
    }
    /**
     * Presents the hostname changer page.
     *
     * @return void
     */
    public function serviceHostnamechanger()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-hostnamechanger" ';

        $buttons = self::makeButton(
            'hostnamechanger-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','hostname changer');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="ishcEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="ishcEnabled"'
            . (
                self::$_moduleName['hostnamechanger'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="ishcDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="ishcDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODUL_HOSTNAMECHANGER_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Hostname Changer -->';
        echo '<div class="box-group" id="hostnamechanger">';
        echo '<div class="box box-solid">';
        echo '<div id="updatehostnamechanger" class="">';
        echo '<div class="box-body">';
        echo '<form id="hostnamechangerupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['hostnamechanger']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the Host name changer elements.
     *
     * @return void
     */
    public function serviceHostnamechangerPost()
    {
        Route::search('module','hostname changer');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['hostnamechanger'])
            ->load('name');
    }
    /**
     * Presents the printer manager page.
     *
     * @return void
     */
    public function servicePrintermanager()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-printermanager" ';

        $buttons = self::makeButton(
            'printermanager-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','printer manager');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="ispmEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="ispmEnabled"'
            . (
                self::$_moduleName['printermanager'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="ispmDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="ispmDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODULE_PRINTERMANAGER_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Printer Manager -->';
        echo '<div class="box-group" id="printermanager">';
        echo '<div class="box box-solid">';
        echo '<div id="updateprintermanager" class="">';
        echo '<div class="box-body">';
        echo '<form id="printermanagerupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['printermanager']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the printer manager elements.
     *
     * @return void
     */
    public function servicePrintermanagerPost()
    {
        Route::search('module','printer manager');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['printermanager'])
            ->load('name');
    }
    /**
     * Presents the task reboot page.
     *
     * @return void
     */
    public function serviceTaskreboot()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-taskreboot" ';

        $buttons = self::makeButton(
            'taskreboot-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','task reboot');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="istrEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="istrEnabled"'
            . (
                self::$_moduleName['taskreboot'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="istrDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="istrDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODULE_TASKREBOOT_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Task Reboot -->';
        echo '<div class="box-group" id="taskreboot">';
        echo '<div class="box box-solid">';
        echo '<div id="updatetaskreboot" class="">';
        echo '<div class="box-body">';
        echo '<form id="taskrebootupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['taskreboot']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the task reboot elements.
     *
     * @return void
     */
    public function serviceTaskrebootPost()
    {
        Route::search('module','task reboot');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['taskreboot'])
            ->load('name');
    }
    /**
     * Presents the user tracker page.
     *
     * @return void
     */
    public function serviceUsertracker()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-usertracker" ';

        $buttons = self::makeButton(
            'usertracker-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','user tracker');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="isutEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="isutEnabled"'
            . (
                self::$_moduleName['usertracker'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="isutDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="isutDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODULE_USERTRACKER_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- User Tracker -->';
        echo '<div class="box-group" id="usertracker">';
        echo '<div class="box box-solid">';
        echo '<div id="updateusertracker" class="">';
        echo '<div class="box-body">';
        echo '<form id="usertrackerupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['usertracker']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the user tracker elements.
     *
     * @return void
     */
    public function serviceUsertrackerPost()
    {
        Route::search('module','user tracker');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['usertracker'])
            ->load('name');
    }
    /**
     * Presents the powermanagement page.
     *
     * @return void
     */
    public function servicePowermanagement()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=service-powermanagement" ';

        $buttons = self::makeButton(
            'powermanagement-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        Route::search('module','power management');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = $Modules->modules[0];
        unset($Modules);
        $fields = [
            '<label class="col-sm-2 control-label" for="isprmEnabled">'
            . _('Module Enabled')
            . '</label>' => '<input type="checkbox" name="isEnabled" '
            . 'id="isprmEnabled"'
            . (
                self::$_moduleName['powermanagement'] ?
                ' checked' :
                ''
            )
            . '/>',
            '<label class="col-sm-2 control-label" for="isprmDefault">'
            . _('Enabled as Default')
            . '</label>' => '<input type="checkbox" name="isDefault" '
            . 'id="isprmDefault"'
            . (
                $Module->isDefault ?
                ' checked' :
                ''
            )
            . '/>'
        ];
        self::$HookManager->processEvent(
            'MODULE_POWERMANAGEMENT_FIELDS',
            [
                'fields' => &$fields,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<!-- Power Management -->';
        echo '<div class="box-group" id="powermanagement">';
        echo '<div class="box box-solid">';
        echo '<div id="updatepowermanagement" class="">';
        echo '<div class="box-body">';
        echo '<form id="powermanagementupdate-form" class="form-horizontal">';
        echo '<input type="hidden" name="name" value="'
            . self::$_modNames['powermanagement']
            . '"/>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the power management elements.
     *
     * @return void
     */
    public function servicePowermanagementPost()
    {
        Route::search('module','power management');
        $Modules = json_decode(
            Route::getData()
        );
        $Module = self::getClass('Module', $Modules->modules[0]->id);
        $Service = self::getClass('Service')
            ->set('name', self::$_modNames['powermanagement'])
            ->load('name');
    }
    /**
     * The home elements.
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('Global Module Settings');

        $tabData = [];

        // Home
        $tabData[] = [
            'name' => _('Home'),
            'id' => 'service-home',
            'generator' => function() {
                $this->serviceHome();
            }
        ];

        // Loop the client module options
        $moduleName = self::getGlobalModuleStatus();
        $modNames = self::getGlobalModuleStatus(true);
        $notWhere = [
            'clientupdater',
            'dircleanup',
            'greenfog',
            'usercleanup'
        ];

        Route::listem('module');
        $Modules = json_decode(
            Route::getData()
        );
        $Modules = $Modules->data;
        foreach ($Modules as $Module) {
            if (in_array($Module->shortName, $notWhere)) {
                continue;
            }
            $tabData[] = [
                'name' => $Module->name,
                'id' => 'service-' . $Module->shortName,
                'generator' => function() use ($Module) {
                    $func = 'service' . ucfirst($Module->shortName);
                    $this->{$func}();
                }
            ];
        }

        echo self::tabFields($tabData);
    }
    /**
     * Updates the contents as needed.
     *
     * @return void
     */
    public function indexPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'SERVICE_EDIT_POST'
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'service-displaymanager':
                $this->serviceDisplaymanagerPost();
                break;
            case 'service-autologout':
                $this->serviceAutologoutPost();
                break;
            case 'service-snapinclient':
                $this->serviceSnapinclientPost();
                break;
            case 'service-hostregister':
                $this->serviceHostregisterPost();
                break;
            case 'service-hostnamechanger':
                $this->serviceHostnamechangerPost();
                break;
            case 'service-printermanager':
                $this->servicePrintermanagerPost();
                break;
            case 'service-taskreboot':
                $this->serviceTaskrebootPost();
                break;
            case 'service-usertracker':
                $this->serviceUsertrackerPost();
                break;
            case 'service-powermanagement':
                $this->servicePowermanagementPost();
                break;
            }
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'SERVICE_UPDATE_FAIL';
            $msg = json_encode(
                [
                    'error' => _('Module update failed!'),
                    'title' => _('Module Update Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager->processEvent(
            $hook
        );
        echo $msg;
        exit;
    }
    /**
     * Display the edit page.
     *
     * @return void
     */
    public function editBackup()
    {
        echo '<div class="tab-content">';
        $this->home();
        $moduleName = self::getGlobalModuleStatus();
        $modNames = self::getGlobalModuleStatus(true);
        Route::listem('module');
        $Modules = json_decode(
            Route::getData()
        );
        $Modules = $Modules->modules;
        foreach ((array)$Modules as &$Module) {
            unset(
                $this->data,
                $this->span,
                $this->headerData,
                $this->attributes,
                $this->templates
            );
            $this->attributes = array(
                array(
                    'class' => 'col-xs-4'
                ),
                array(
                    'class' => 'col-xs-4'
                ),
                array(
                    'class' => 'col-xs-4'
                )
            );
            $this->templates = array(
                '${field}',
                '${input}',
                '${span}',
            );
            $fields = array(
                sprintf(
                    '<label for="'
                    . $Module->shortName
                    . 'main">%s %s?</label>',
                    $Module->name,
                    _('Enabled')
                ) => sprintf(
                    '<input type="checkbox" name="en" id="%smain"%s/>',
                    $Module->shortName,
                    (
                        $moduleName[$Module->shortName] ?
                        ' checked' :
                        ''
                    ),
                    $Module->shortName
                ),
                sprintf(
                    '<label for="'
                    . $Module->shortName
                    . 'def'
                    . '">%s</label>',
                    (
                        $moduleName[$Module->shortName] ?
                        sprintf(
                            '%s %s?',
                            $Module->name,
                            _('Enabled as default')
                        ) :
                        ''
                    )
                ) => sprintf(
                    '%s',
                    (
                        $moduleName[$Module->shortName] ?
                        sprintf(
                            '<input type="checkbox" name="defen" id="%sdef"%s/>',
                            $Module->shortName,
                            (
                                $Module->isDefault ?
                                ' checked' :
                                ''
                            ),
                            $Module->shortName
                        ) :
                        ''
                    )
                )
            );
            $this->span = array(
                'span',
                sprintf(
                    '<i class="icon fa fa-question hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="%s"></i>',
                    $Module->description
                )
            );
            $rendered = self::formFields($fields);
            $this->span = array(
                'span',
                '<button type="submit" name="updatestatus" class='
                . '"btn btn-info btn-block" id="update'
                . $Module->shortName
                . '"/>'
                . _('Update')
                . '</button>'
            );
            $fields = array(
                '<label for="update'
                . $Module->shortName
                . '"/>'
                . _('Make Changes?')
                . '</label>' => '<input type="hidden" name="name" value="'
                . $modNames[$Module->shortName]
                . '"/>'
            );
            $rendered = self::formFields($fields);
            echo '<!-- '
                . $Module->name
                . ' -->';
            echo '<div class="tab-pane fade" id="'
                . $Module->shortName
                . '">';
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo $Module->name;
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo '<form class="form-horizontal" method="post" action="'
                . '?node=service&sub=edit&tab='
                . $Module->shortName
                . '" enctype="multipart/form-data">';
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Service Status');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo $Module->description;
            $this->render(12);
            echo '</div>';
            echo '</div>';
            switch ($Module->shortName) {
            case 'autologout':
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Current settings');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="updatetme">';
                echo _('Default log out time (in minutes)');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<div class="input-group">';
                echo '<input type="text" name="tme" value='
                    . '"'
                    . self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN')
                    . '" class="form-control" id="updatetme"/>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="updatedefaults">';
                echo _('Make Changes?');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button name="updatedefaults" id="updatedefaults" class='
                    . '"btn btn-info btn-block" type="submit">';
                echo _('Update');
                echo '</button>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                break;
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
            case 'snapinclient':
                self::$HookManager
                    ->processEvent(
                        'SNAPIN_CLIENT_SERVICE',
                        array(
                            'page' => &$this
                        )
                    );
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                break;
            case 'clientupdater':
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Current settings');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                self::getClass('FOGConfigurationPage')->clientupdater(false);
                echo '</div>';
                echo '</div>';
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                break;
            case 'dircleanup':
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                $this->headerData = array(
                    _('Delete'),
                    _('Path')
                );
                $this->attributes = array(
                    array(
                        'width' => 16,
                        'class' => 'filter-false'
                    ),
                    array()
                );
                $this->templates = array(
                    '<input type="checkbox" name="delid[]" value="${dir_id}"/>',
                    '${dir_path}'
                );
                Route::listem('dircleaner');
                $dircleanups = json_decode(
                    Route::getData()
                );
                $dircleanups = $dircleanups->dircleaners;
                foreach ((array)$dircleanups as &$DirCleanup) {
                    $this->data[] = array(
                        'dir_id' => $DirCleanup->id,
                        'dir_path' => $DirCleanup->path
                    );
                    unset($DirCleanup);
                }
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Current settings');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo _('NOTICE');
                echo ': ';
                echo _('This module is only used on the old client.');
                echo _('The old client iswhat was distributed with');
                echo ' ';
                echo _('FOG 1.2.0 and earlier.');
                echo ' ';
                echo _('This module did not work past Windows XP');
                echo ' ';
                echo _('due to UAC introduced in Vista and up.');
                echo '<hr/>';
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Directories');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                $this->render(12);
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="adddir">';
                echo _('Add Directory');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<div class="input-group">';
                echo '<input class="form-control" id="adddir" name="adddir" '
                    . 'type="text"/>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="deletedc">';
                echo _('Delete Selected Items');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button class="btn btn-danger btn-block" name='
                    . '"deletedc" type="submit" id="deletedc">';
                echo _('Delete');
                echo '</button>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="updatedc">';
                echo _('Make Changes');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button class="btn btn-info btn-block" name='
                    . '"adddc" type="submit" id="updatedc">';
                echo _('Add');
                echo '</button>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
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
                break;
            case 'displaymanager':
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                $this->attributes = array(
                    array('class' => 'col-xs-4'),
                    array('class' => 'col-xs-8 form-group')
                );
                $this->templates = array(
                    '${field}',
                    '${input}'
                );
                $disps = array(
                    'FOG_CLIENT_DISPLAYMANAGER_R',
                    'FOG_CLIENT_DISPLAYMANAGER_X',
                    'FOG_CLIENT_DISPLAYMANAGER_Y'
                );
                list(
                    $r,
                    $x,
                    $y
                ) = self::getSubObjectIDs(
                    'Service',
                    array('name' => $disps),
                    'value'
                );
                unset($disps);
                $fields = array(
                    '<label for="width">'
                    . _('Default Width')
                    . '</label>' => '<div class="input-group">'
                    . '<input type="text" class="form-control" name="width" '
                    . 'value="'
                    . $x
                    . '" id="width"/>'
                    . '</div>',
                    '<label for="height">'
                    . _('Default Height')
                    . '</label>' => '<div class="input-group">'
                    . '<input type="text" class="form-control" name="height" '
                    . 'value="'
                    . $y
                    . '" id="height"/>'
                    . '</div>',
                    '<label for="refresh">'
                    . _('Default Refresh Rate')
                    . '</label>' => '<div class="input-group">'
                    . '<input type="text" class="form-control" name="refresh" '
                    . 'value="'
                    . $r
                    . '" id="refresh"/>'
                    . '</div>',
                    '<label for="updatescreen">'
                    . _('Make Changes?')
                    . '</label>' => '<button type="submit" class='
                    . '"btn btn-info btn-block" name='
                    . '"updatescreen" id="updatescreen">'
                    . _('Update')
                    . '</button>'
                );
                $rendered = self::formFields($fields);
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Current settings');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                $this->render(12);
                echo '</div>';
                echo '</div>';
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                break;
            case 'greenfog':
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                $this->headerData = array(
                    _('Delete'),
                    _('Time'),
                    _('Action')
                );
                $this->attributes = array(
                    array(
                        'class' => 'filter-false',
                        'width' => 16
                    ),
                    array(),
                    array()
                );
                $this->templates = array(
                    '<input type="checkbox" name="gfrem${gf_id}" value="${gf_id}"/>',
                    '${gf_time}',
                    '${gf_action}'
                );
                $actionSel = array(
                    's' => _('Shutdown'),
                    'r' => _('Reboot')
                );
                $selAction = filter_input(INPUT_POST, 'style');
                $actSel = self::selectForm(
                    'style',
                    $actionSel,
                    $selAction,
                    true
                );
                Route::listem('greenfog');
                $GreenFogs = json_encode(
                    Route::getData()
                );
                $GreenFogs = $GreenFogs->greenfogs;
                foreach ((array)$GreenFogs as &$GreenFog) {
                    $gftime = self::niceDate(
                        $GreenFog->hour
                        . ':'
                        . $GreenFog->min
                    )->format('H:i');
                    $this->data[] = array(
                        'gf_time' => $gftime,
                        'gf_action' => (
                            $GreenFog->action == 'r' ?
                            _('Reboot') :
                            (
                                $GreenFog->action == 's' ?
                                _('Shutdown') :
                                _('N/A')
                            )
                        ),
                        'gf_id' => $GreenFog->id
                    );
                    unset($GreenFog);
                }
                unset($GreenFogs);
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Current settings');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo _('NOTICE')
                    . ': ';
                echo _('This module is only used on the old client.');
                echo ' ';
                echo _('The old client was distributed with FOG 1.2.0 and earlier.');
                echo ' ';
                echo _('This module has since been replaced with Power Management.');
                echo '<hr/>';
                $this->render(12);
                echo '<div class="form-group">';
                echo '<label class="col-xs-4 control-label">';
                echo _('New Event');
                echo '</label>';
                echo '<div class="col-xs-2">';
                echo '<div class="input-group">';
                echo '<input type="number" class="form-control" name="h" '
                    . 'maxlength="2" placeholder="HH"/>';
                echo '</div>';
                echo '</div>';
                echo '<div class="col-xs-2">';
                echo '<div class="input-group">';
                echo '<input type="number" class="form-control" name="m" '
                    . 'maxlength="2" placeholder="MM"/>';
                echo '</div>';
                echo '</div>';
                echo '<div class="col-xs-4">';
                echo '<div class="input-group">';
                echo $actSel;
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="col-xs-4 control-label" for="deleteevent">';
                echo _('Delete Selected Items');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button type="submit" class='
                    . '"btn btn-danger btn-block" name='
                    . '"deleteevent" id="deleteevent">';
                echo _('Delete');
                echo '</button>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="col-xs-4 control-label" for="addevent">';
                echo _('Add Event');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button type="submit" class='
                    . '"btn btn-info btn-block" name='
                    . '"addevent" id="addevent">';
                echo _('Add');
                echo '</button>';
                echo '</div>';
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
                break;
            case 'usercleanup':
                unset(
                    $this->data,
                    $this->form,
                    $this->headerData,
                    $this->templates,
                    $this->attributes
                );
                $this->headerData = array(
                    _('Delete'),
                    _('User')
                );
                $this->attributes = array(
                    array(
                        'class' => 'filter-false',
                        'width' => 16
                    ),
                    array()
                );
                $this->templates = array(
                    '${input}',
                    '${user_name}'
                );
                Route::listem('usercleanup');
                $UserCleanups = json_decode(
                    Route::getData()
                );
                $UserCleanups = $UserCleanups->usercleanups;
                foreach ((array)$UserCleanups as &$UserCleanup) {
                    $this->data[] = array(
                        'user_name' => $UserCleanup->name,
                        'input' => (
                            $UserCleanup->id < 7 ?
                            '' :
                            '<input type="checkbox" name="delid[]" '
                            . 'id="rmuser${user_id}" value="${user_id}"/>'
                        ),
                        'user_id' => $UserCleanup->id
                    );
                    unset($UserCleanup);
                }
                unset($UserCleanups);
                echo '<div class="panel panel-info">';
                echo '<div class="panel-heading text-center">';
                echo '<h4 class="title">';
                echo _('Current settings');
                echo '</h4>';
                echo '</div>';
                echo '<div class="panel-body">';
                echo _('NOTICE')
                    . ': ';
                echo _('This module is only used on the old client.');
                echo ' ';
                echo _('The old client was distributed with FOG 1.2.0 and earlier.');
                echo ' ';
                echo _('This module did not work past Windows XP due to UAC.');
                echo '<hr/>';
                $this->render(12);
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="adduser">';
                echo _('Add User');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<div class="input-group">';
                echo '<input class="form-control" id="adduser" name="adduser" '
                    . 'type="text"/>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="deleteuc">';
                echo _('Delete Selected Items');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button class="btn btn-danger btn-block" name='
                    . '"deleteuc" type="submit" id="deleteuc">';
                echo _('Delete');
                echo '</button>';
                echo '</div>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label class="control-label col-xs-4" for="updateuc">';
                echo _('Make Changes');
                echo '</label>';
                echo '<div class="col-xs-8">';
                echo '<button class="btn btn-info btn-block" name='
                    . '"adddc" type="submit" id="updateuc">';
                echo _('Add');
                echo '</button>';
                echo '</div>';
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
                break;
            }
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($Module);
        }
    }
    /**
     * Actually change the items.
     *
     * @return void
     */
    public function editPost()
    {
        $name = filter_input(INPUT_POST, 'name');
        $Service = self::getClass('Service')
            ->set('name', $name)
            ->load('name');
        $Module = self::getClass('Module')
            ->set('shortName', $tab)
            ->load('shortName');
        self::$HookManager
            ->processEvent(
                'SERVICE_EDIT_POST',
                array('Service' => &$Service)
            );
        $onoff = isset($_POST['en']);
        $defen = isset($_POST['defen']);
        try {
            if (isset($_POST['updatestatus'])) {
                if ($Service) {
                    $Service->set('value', $onoff)->save();
                }
                if ($Module) {
                    $Module->set('isDefault', $defen)->save();
                }
            }
            global $tab;
            switch ($tab) {
            case 'autologout':
                $tme = (int)filter_input(INPUT_POST, 'tme');
                if (isset($_POST['updatedefaults'])) {
                    self::getClass('Service')
                        ->set('name', 'FOG_CLIENT_AUTOLOGOFF_MIN')
                        ->load('name')
                        ->set('value', $tme)
                        ->save();
                }
                break;
            case 'snapinclient':
                self::$HookManager
                    ->processEvent(
                        'SNAPIN_CLIENT_SERVICE_POST'
                    );
                break;
            case 'dircleanup':
                if (isset($_POST['adddc'])) {
                    $adddir = filter_input(INPUT_POST, 'adddir');
                    $Service->addDir($adddir);
                }
                if (isset($_POST['deletedc'])) {
                    $dcids = filter_input_array(
                        INPUT_POST,
                        array(
                            'delid' => array(
                                'flags' => FILTER_REQUIRE_ARRAY
                            )
                        )
                    );
                    $dcids = $dcids['delid'];
                    $Service->remDir($dcids);
                }
                break;
            case 'displaymanager':
                if (isset($_POST['updatescreen'])) {
                    $r = (int)filter_input(INPUT_POST, 'refresh');
                    $x = (int)filter_input(INPUT_POST, 'width');
                    $y = (int)filter_input(INPUT_POST, 'height');
                    $Service->setDisplay(
                        $x,
                        $y,
                        $r
                    );
                }
                break;
            case 'greenfog':
                if (isset($_POST['addevent'])) {
                    $h = filter_input(INPUT_POST, 'h');
                    $m = filter_input(INPUT_POST, 'm');
                    $a = filter_input(INPUT_POST, 'style');
                    if ($h < 0 || $h > 23) {
                        throw new Exception(
                            _('Must be 0 through 23 for hours in a day.')
                        );
                    }
                    if ($m < 0 || $h > 59) {
                        throw new Exception(
                            _('Must be 0 through 59 for minutes in an hour.')
                        );
                    }
                    if (!in_array($a, array('r', 's'))) {
                        throw new Exception(
                            _('Either reboot or shutdown action must be used.')
                        );
                    }
                    $Service->setGreenFog(
                        $h,
                        $m,
                        $a
                    );
                }
                if (isset($_POST['delid'])) {
                    $delid = filter_input_array(
                        INPUT_POST,
                        array(
                            'delid' => array(
                                'flags' => FILTER_REQUIRE_ARRAY
                            )
                        )
                    );
                    $delid = $delid['delid'];
                    $Service->remGF($delid);
                }
                break;
            case 'usercleanup':
                $addUser = filter_input(INPUT_POST, 'adduser');
                if (!empty($addUser)) {
                    $Service->addUser($addUser);
                }
                if (isset($_POST['delid'])) {
                    $delid = filter_input_array(
                        INPUT_POST,
                        array(
                            'delid' => array(
                                'flags' => FILTER_REQUIRE_ARRAY
                            )
                        )
                    );
                    $delid = $delid['delid'];
                    $Service->remUser($delid);
                }
                break;
            case 'clientupdater':
                self::getClass('FOGConfigurationPage')->clientupdaterPost();
                break;
            }
            if (!$Service->save()) {
                throw new Exception(_('Service update failed'));
            }
            $hook = 'SERVICE_EDIT_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Module updated!'),
                    'title' => _('Module Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SERVICE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Module Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Service' => &$Service)
            );
        unset($Service);
        echo $msg;
        exit;
    }
    /**
     * Redirect search call to index.
     *
     * @return void
     */
    public function search()
    {
        $this->index();
    }
}
