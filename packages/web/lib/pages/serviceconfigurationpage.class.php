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
        $disps = [
            'FOG_CLIENT_DISPLAYMANAGER_R',
            'FOG_CLIENT_DISPLAYMANAGER_X',
            'FOG_CLIENT_DISPLAYMANAGER_Y'
        ];
        list(
            $r,
            $x,
            $y
        ) = self::getSubObjectIDs(
            'Service',
            ['name' => $disps],
            'value'
        );
        unset($disps);
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
            . '/>',
            '<label class="col-sm-2 control-label" for="width">'
            . _('Default Width')
            . '<br/>('
            . _('in pixels')
            . ')</label>' => '<input type="number" class="form-control" '
            . 'name="width" value="'
            . $x
            . '" id="width"/>',
            '<label class="col-sm-2 control-label" for="height">'
            . _('Default Height')
            . '<br/>('
            . _('in pixels')
            . ')'
            . '</label>' => '<input type="number" class="form-control" '
            . 'name="height" value="'
            . $y
            . '" id="height"/>',
            '<label class="col-sm-2 control-label" for="refresh">'
            . _('Default Refresh Rate')
            . '<br/>('
            . _('in Hz')
            . ')</label>' => '<input type="number" class="form-control" '
            . 'name="refresh" value="'
            . $r
            . '" id="refresh"/>'
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
        echo '<form id="displaymanagerupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $width = (int)filter_input(INPUT_POST, 'width');
            $height = (int)filter_input(INPUT_POST, 'height');
            $refresh = (int)filter_input(INPUT_POST, 'refresh');
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            self::setSetting('FOG_CLIENT_DISPLAYMANAGER_R', $refresh);
            self::setSetting('FOG_CLIENT_DISPLAYMANAGER_X', $width);
            self::setSetting('FOG_CLIENT_DISPLAYMANAGER_Y', $height);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        $tme = self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN');
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
            . '/>',
            '<label class="col-sm-2 control-label" for="updatetme">'
            . _('Auto Log Out Time')
            . '<br/>('
            . _('in minutes')
            . ')<br/>('
            . _('Active only at 5 minutes')
            . ')</label>' => '<input type="number" class="form-control" '
            . 'name="tme" value="'
            . $tme
            . '" id="updatetme"/>'
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
        echo '<form id="autologoutupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $tme = (int)filter_input(INPUT_POST, 'tme');
            if ($tme < 5) {
                $tme = 0;
            }
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            self::setSetting('FOG_CLIENT_AUTOLOGOFF_MIN', $tme);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="snapinclientupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="hostregisterupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="hostnamechangerupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="printermanagerupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="taskrebootupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="usertrackerupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
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
        echo '<form id="powermanagementupdate-form" class="form-horizontal" novalidate>';
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
        if (isset($_POST['update'])) {
            $isen = (int)isset($_POST['isEnabled']);
            $isdef = (int)isset($_POST['isDefault']);
            $Service->set('value', $isen);
            $Module->set('isDefault', $isdef);
            if (!$Service->save()) {
                throw new Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new Exception(_('Unable to update module default setting'));
            }
        }
    }
    /**
     * Redirects search to edit
     *
     * @return void
     */
    public function search()
    {
        $this->edit();
    }
    /**
     * Redirects index page to edit
     *
     * @return void
     */
    public function index()
    {
        $this->edit();
    }
    /**
     * Redirect index page updates.
     *
     * @return void
     */
    public function indexPost()
    {
        $this->editPost();
    }
    /**
     * Redirect list page updates
     *
     * @return void
     */
    public function listPost()
    {
        $this->editPost();
    }
    /**
     * The home elements.
     *
     * @return void
     */
    public function edit()
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

        echo self::tabFields($tabData, false);
    }
    /**
     * Updates the contents as needed.
     *
     * @return void
     */
    public function editPost()
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
            $code = 201;
            $hook = 'SERVICE_UPDATE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Module update success!'),
                    'title' => _('Module Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = 500;
            $hook = 'SERVICE_UPDATE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
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
}
