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
     * The actual modules themselves
     *
     * @var array
     */
    private static $_modules = [];
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
        // Loop the client module options
        $notWhere = [
            'clientupdater',
            'dircleanup',
            'greenfog',
            'usercleanup'
        ];
        $modkeys = array_keys(self::getGlobalModuleStatus());
        $where = array_diff(
            $modkeys,
            $notWhere
        );

        Route::listem(
            'module',
            ['shortName' => $where]
        );
        $Modules = json_decode(
            Route::getData()
        );
        self::$_modules = $Modules->data;
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
            . self::makeTabUpdateURL(
                'service-displaymanager'
            )
            . '" ';

        $buttons = self::makeButton(
            'displaymanager-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('display manager' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $disps = [
            'FOG_CLIENT_DISPLAYMANAGER_R',
            'FOG_CLIENT_DISPLAYMANAGER_X',
            'FOG_CLIENT_DISPLAYMANAGER_Y'
        ];
        list(
            $r,
            $x,
            $y
        ) = self::getSetting($disps);
        unset($disps);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'isdmEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isdmEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['displaymanager'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'isdmDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'isdmDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'width',
                _('Default Width')
                . '<br/>('
                . _('in pixels')
                . ')'
            ) => self::makeInput(
                'form-control',
                'width',
                '1024',
                'number',
                'width',
                $x
            ),
            self::makeLabel(
                $labelClass,
                'height',
                _('Default Height')
                . '<br/>('
                . _('in pixels')
                . ')'
            ) => self::makeInput(
                'form-control',
                'height',
                '768',
                'number',
                'height',
                $y
            ),
            self::makeLabel(
                $labelClass,
                'refresh',
                _('Default Refresh Rate')
                . '<br/>('
                . _('in Hz')
                . ')'
            ) => self::makeInput(
                'form-control',
                'refresh',
                '60',
                'number',
                'refresh',
                $r
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_DISPLAYMANAGER_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'displaymanagerupdate-form',
            self::makeTabUpdateURL(
                'service-displaymanager'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['displaymanager']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the display manager elements.
     *
     * @return void
     */
    public function serviceDisplaymanagerPost()
    {
        self::$HookManager->processEvent('MODULE_DISPLAYMANAGER_POST');
        foreach (self::$_modules as &$module) {
            if ('display manager' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Setting = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-autologout'
            )
            . '" ';

        $buttons = self::makeButton(
            'autologout-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('auto log out' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $tme = self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN');
        $fields = [
            self::makeLabel(
                $labelClass,
                'isaloEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isaloEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['autologout'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'isaloDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'isaloDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'updatetme',
                _('Auto Log Out Time')
                . '<br/>('
                . _('in minutes')
                . ')<br/>('
                . _('Active at 5 minutes or more')
                . ')'
            ) => self::makeInput(
                'form-control',
                'tme',
                '5',
                'number',
                'updatetme',
                $tme
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_AUTOLOGOUT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'autologoutupdate-form',
            self::makeTabUpdateURL(
                'service-autologout'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['autologout']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the autologout elements.
     *
     * @return void
     */
    public function serviceAutologoutPost()
    {
        self::$HookManager->processEvent('MODULE_AUTOLOGOUT_POST');
        foreach (self::$_modules as &$module) {
            if ('auto log out' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-snapinclient'
            )
            . '" ';

        $buttons = self::makeButton(
            'snapinclient-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('snapins' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'isscEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isscEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['snapinclient'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'isscDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'isscDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_SNAPINCLIENT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'snapinclientupdate-form',
            self::makeTabUpdateURL(
                'service-snapinclient'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['snapinclient']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the snapinclient elements.
     *
     * @return void
     */
    public function serviceSnapinclientPost()
    {
        self::$HookManager->processEvent('MODULE_SNAPINCLIENT_POST');
        foreach (self::$_modules as &$module) {
            if ('snapins' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-hostregister'
            )
            . '" ';

        $buttons = self::makeButton(
            'hostregister-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('host registration' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ishrEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'ishrEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['hostregister'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'ishrDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'ishrDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_HOSTREGISTER_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'hostregisterupdate-form',
            self::makeTabUpdateURL(
                'service-hostregister'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['hostregister']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the Host register elements.
     *
     * @return void
     */
    public function serviceHostregisterPost()
    {
        self::$HookManager->processEvent('MODULE_HOSTREGISTER_POST');
        foreach (self::$_modules as &$module) {
            if ('host registration' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-hostnamechanger'
            )
            . '" ';

        $buttons = self::makeButton(
            'hostnamechanger-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('hostname changer' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ishcEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'ishcEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['hostnamechanger'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'ishcDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'ishcDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_HOSTNAMECHANGER_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'hostnamechangerupdate-form',
            self::makeTabUpdateURL(
                'service-hostnamechanger'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['hostnamechanger']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the Host name changer elements.
     *
     * @return void
     */
    public function serviceHostnamechangerPost()
    {
        self::$HookManager->processEvent('MODULE_HOSTNAMECHANGER_POST');
        foreach (self::$_modules as &$module) {
            if ('hostname changer' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-printermanager'
            )
            . '" ';

        $buttons = self::makeButton(
            'printermanager-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('printer manager' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ispmEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'ispmEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['printermanager'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'ispDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'ispDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_PRINTERMANAGER_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'printermanagerupdate-form',
            self::makeTabUpdateURL(
                'service-printermanager'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['printermanager']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the printer manager elements.
     *
     * @return void
     */
    public function servicePrintermanagerPost()
    {
        self::$HookManager->processEvent('MODULE_PRINTERMANAGER_POST');
        foreach (self::$_modules as &$module) {
            if ('printer manager' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-taskreboot'
            )
            . '" ';

        $buttons = self::makeButton(
            'taskreboot-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('task reboot' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'istrEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'istrEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['taskreboot'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'istrDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'istrDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_TASKREBOOT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'taskrebootupdate-form',
            self::makeTabUpdateURL(
                'service-taskreboot'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['taskreboot']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the task reboot elements.
     *
     * @return void
     */
    public function serviceTaskrebootPost()
    {
        self::$HookManager->processEvent('MODULE_TASKREBOOT_POST');
        foreach (self::$_modules as &$module) {
            if ('task reboot' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-usertracker'
            )
            . '" ';

        $buttons = self::makeButton(
            'usertracker-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('user tracker' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'isutEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'isutEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['usertracker'] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'isutDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'isutDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_USERTRACKER_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'usertrackerupdate-form',
            self::makeTabUpdateURL(
                'service-usertracker'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['usertracker']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the user tracker elements.
     *
     * @return void
     */
    public function serviceUsertrackerPost()
    {
        self::$HookManager->processEvent('MODULE_USERTRACKER_POST');
        foreach (self::$_modules as &$module) {
            if ('user tracker' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
            . self::makeTabUpdateURL(
                'service-powermanagement'
            )
            . '" ';

        $buttons = self::makeButton(
            'powermanagement-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ('power management' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ispmEnabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'ispmEnabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName['powermanagement'] ? 'checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'ispmDefault',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'ispmDefault',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? 'checked' : '')
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_POWERMANAGEMENT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'powermanagementupdate-form',
            self::makeTabUpdateURL(
                'service-powermanagement'
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo self::makeInput(
            '',
            'name_'.$Module->id,
            '',
            'hidden',
            '',
            self::$_modNames['powermanagement']
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the power management elements.
     *
     * @return void
     */
    public function servicePowermanagementPost()
    {
        self::$HookManager->processEvent('MODULE_POWERMANAGEMENT_POST');
        foreach (self::$_modules as &$module) {
            if ('power management' === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = self::getClass(
            'Module',
            $Module->id
        );
        $Service = self::getClass('Setting')
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
     * Redirects index page to edit
     *
     * @return void
     */
    public function index(...$args)
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
            'generator' => function () {
                $this->serviceHome();
            }
        ];

        foreach (self::$_modules as $Module) {
            $tabData[] = [
                'name' => $Module->name,
                'id' => 'service-' . $Module->shortName,
                'generator' => function () use ($Module) {
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
                case 'service-autologout':
                    $this->serviceAutologoutPost();
                    break;
                case 'service-displaymanager':
                    $this->serviceDisplaymanagerPost();
                    break;
                case 'service-hostnamechanger':
                    $this->serviceHostnamechangerPost();
                    break;
                case 'service-hostregister':
                    $this->serviceHostregisterPost();
                    break;
                case 'service-powermanagement':
                    $this->servicePowermanagementPost();
                    break;
                case 'service-printermanager':
                    $this->servicePrintermanagerPost();
                    break;
                case 'service-snapinclient':
                    $this->serviceSnapinclientPost();
                    break;
                case 'service-taskreboot':
                    $this->serviceTaskrebootPost();
                    break;
                case 'service-usertracker':
                    $this->serviceUsertrackerPost();
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'SERVICE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Module update success!'),
                    'title' => _('Module Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'SERVICE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Module Update Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager->processEvent(
            $hook,
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg
            ]
        );
        echo $msg;
        exit;
    }
}
