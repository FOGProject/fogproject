<?php
/**
 * Configure global level module/services.
 * These are things like hostname changer, display, etc...
 *
 * PHP version 7.4+
 *
 * @category ServiceConfigurationPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Items\Module;
use FOG\Items\Setting;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
        $this->name = _('Service Configuration');
        parent::__construct($this->name);
        self::$_moduleName = self::getGlobalModuleStatus();
        self::$_modNames = self::getGlobalModuleStatus(true);
        // Loop the client module options
        $notWhere = [
            'clientupdater',
            'dircleanup',
            'usercleanup'
        ];
        $modkeys = array_keys(self::getGlobalModuleStatus());
        $where = array_diff(
            $modkeys,
            $notWhere
        );

        self::$_modules = Route::getList(
            'module',
            ['shortName' => $where]
        );
    }
    /**
     * Presents the home for this page.
     *
     * @return void
     */
    public function serviceHome()
    {
        echo '<div class="card">';
        echo '<div class="card-body">';
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
     * Renders a global module/service settings tab.
     *
     * Every service tab is a CSRF form with the same two checkboxes
     * (Module Enabled / Enabled by Default) wrapped in a box-solid; only
     * the module it targets, the field id prefix, the FIELDS hook and any
     * extra per-module fields differ.
     *
     * @param string $key         The module shortName (and tab/form slug).
     * @param string $match       The lowercased module name to match.
     * @param string $idPrefix    The checkbox id prefix (e.g. 'sc').
     * @param string $hook        The MODULE_*_FIELDS event to fire.
     * @param array  $extraFields Extra label=>input fields appended after
     *                            the enabled/default pair.
     *
     * @return void
     */
    private function _renderModuleTab(
        $key,
        $match,
        $idPrefix,
        $hook,
        array $extraFields = []
    ) {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'service-' . $key
            )
            . '" ';

        $buttons = self::makeButton(
            $key . '-update',
            _('Update'),
            'btn btn-primary float-end',
            $props
        );
        foreach (self::$_modules as &$module) {
            if ($match === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'is' . $idPrefix . 'Enabled',
                _('Module Enabled')
            ) => self::makeInput(
                '',
                'isEnabled',
                '',
                'checkbox',
                'is' . $idPrefix . 'Enabled',
                '',
                false,
                false,
                -1,
                -1,
                (self::$_moduleName[$key] ? ' checked' : '')
            ),
            self::makeLabel(
                $labelClass,
                'is' . $idPrefix . 'Default',
                _('Enabled by Default')
            ) => self::makeInput(
                '',
                'isDefault',
                '',
                'checkbox',
                'is' . $idPrefix . 'Default',
                '',
                false,
                false,
                -1,
                -1,
                ($Module->isDefault ? ' checked' : '')
            )
        ];
        $fields = array_merge($fields, $extraFields);

        self::$HookManager->processEvent(
            $hook,
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$Module
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            $key . 'update-form',
            self::makeTabUpdateURL(
                'service-' . $key
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo self::makeInput(
            '',
            'name_' . $Module->id,
            '',
            'hidden',
            '',
            self::$_modNames[$key]
        );
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Persists a global module/service settings tab.
     *
     * Toggles the module's global Setting value and its isDefault flag from
     * the posted checkboxes; $extra (if given) runs after both are set but
     * before either is saved, for modules with additional settings.
     *
     * @param string        $key   The module shortName (Setting name key).
     * @param string        $match The lowercased module name to match.
     * @param string        $hook  The MODULE_*_POST event to fire.
     * @param callable|null $extra Extra persistence run before the saves.
     *
     * @return void
     */
    private function _saveModuleTab($key, $match, $hook, $extra = null)
    {
        self::checkAuthAndCSRF();
        self::$HookManager->processEvent($hook);
        foreach (self::$_modules as &$module) {
            if ($match === strtolower($module->name)) {
                $Module = $module;
                break;
            }
            unset($module);
        }
        $Module = new Module($Module->id);
        $Service = (new Setting())
            ->set('name', self::$_modNames[$key])
            ->load('name');
        if (isset($_POST['update'])) {
            $Service->set('value', (int)isset($_POST['isEnabled']));
            $Module->set('isDefault', (int)isset($_POST['isDefault']));
            if (is_callable($extra)) {
                $extra();
            }
            if (!$Service->save()) {
                throw new \Exception(_('Unable to update global setting'));
            }
            if (!$Module->save()) {
                throw new \Exception(_('Unable to update module default setting'));
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
        $labelClass = 'col-sm-3 col-form-label';

        list(
            $tme,
            $warn
        ) = self::getSetting(
            [
                'FOG_CLIENT_AUTOLOGOFF_MIN',
                'FOG_CLIENT_AUTOLOGOFF_WARN'
            ]
        );

        $this->_renderModuleTab(
            'autologout',
            'auto log out',
            'alo',
            'MODULE_AUTOLOGOUT_FIELDS',
            [
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
                ),
                self::makeLabel(
                    $labelClass,
                    'updatewarn',
                    _('Warning Before Log Out')
                    . '<br/>('
                    . _('in seconds')
                    . ')<br/>('
                    . _('0 logs the user out with no warning')
                    . ')'
                ) => self::makeInput(
                    'form-control',
                    'warn',
                    '60',
                    'number',
                    'updatewarn',
                    $warn
                )
            ]
        );
    }
    /**
     * Updates the autologout elements.
     *
     * @return void
     */
    public function serviceAutologoutPost()
    {
        $this->_saveModuleTab(
            'autologout',
            'auto log out',
            'MODULE_AUTOLOGOUT_POST',
            function () {
                $tme = (int)filter_input(INPUT_POST, 'tme');
                if ($tme < 5) {
                    $tme = 0;
                }
                self::setSetting('FOG_CLIENT_AUTOLOGOFF_MIN', $tme);
                // Clamped rather than refused, and never negative. A warning
                // longer than the timeout is clamped again on the agent, to
                // half the timeout -- the point of doing it in both places
                // is that a policy which arrived wrong must not be able to
                // log a fleet off the moment anybody stops typing.
                $warn = (int)filter_input(INPUT_POST, 'warn');
                if ($warn < 0) {
                    $warn = 0;
                }
                self::setSetting('FOG_CLIENT_AUTOLOGOFF_WARN', $warn);
            }
        );
    }
    /**
     * Presents the snapin client page.
     *
     * @return void
     */
    public function serviceSnapinclient()
    {
        $this->_renderModuleTab(
            'snapinclient',
            'snapins',
            'sc',
            'MODULE_SNAPINCLIENT_FIELDS'
        );
    }
    /**
     * Updates the snapinclient elements.
     *
     * @return void
     */
    public function serviceSnapinclientPost()
    {
        $this->_saveModuleTab(
            'snapinclient',
            'snapins',
            'MODULE_SNAPINCLIENT_POST'
        );
    }
    /**
     * Presents the host register page.
     *
     * @return void
     */
    public function serviceHostregister()
    {
        $this->_renderModuleTab(
            'hostregister',
            'host registration',
            'hr',
            'MODULE_HOSTREGISTER_FIELDS'
        );
    }
    /**
     * Updates the Host register elements.
     *
     * @return void
     */
    public function serviceHostregisterPost()
    {
        $this->_saveModuleTab(
            'hostregister',
            'host registration',
            'MODULE_HOSTREGISTER_POST'
        );
    }
    /**
     * Presents the hostname changer page.
     *
     * @return void
     */
    public function serviceHostnamechanger()
    {
        $this->_renderModuleTab(
            'hostnamechanger',
            'hostname changer',
            'hc',
            'MODULE_HOSTNAMECHANGER_FIELDS'
        );
    }
    /**
     * Updates the Host name changer elements.
     *
     * @return void
     */
    public function serviceHostnamechangerPost()
    {
        $this->_saveModuleTab(
            'hostnamechanger',
            'hostname changer',
            'MODULE_HOSTNAMECHANGER_POST'
        );
    }
    /**
     * Presents the printer manager page.
     *
     * @return void
     */
    public function servicePrintermanager()
    {
        $this->_renderModuleTab(
            'printermanager',
            'printer manager',
            'pm',
            'MODULE_PRINTERMANAGER_FIELDS'
        );
    }
    /**
     * Updates the printer manager elements.
     *
     * @return void
     */
    public function servicePrintermanagerPost()
    {
        $this->_saveModuleTab(
            'printermanager',
            'printer manager',
            'MODULE_PRINTERMANAGER_POST'
        );
    }
    /**
     * Presents the task reboot page.
     *
     * @return void
     */
    public function serviceTaskreboot()
    {
        $this->_renderModuleTab(
            'taskreboot',
            'task reboot',
            'tr',
            'MODULE_TASKREBOOT_FIELDS'
        );
    }
    /**
     * Updates the task reboot elements.
     *
     * @return void
     */
    public function serviceTaskrebootPost()
    {
        $this->_saveModuleTab(
            'taskreboot',
            'task reboot',
            'MODULE_TASKREBOOT_POST'
        );
    }
    /**
     * Presents the user tracker page.
     *
     * @return void
     */
    public function serviceUsertracker()
    {
        $this->_renderModuleTab(
            'usertracker',
            'user tracker',
            'ut',
            'MODULE_USERTRACKER_FIELDS'
        );
    }
    /**
     * Updates the user tracker elements.
     *
     * @return void
     */
    public function serviceUsertrackerPost()
    {
        $this->_saveModuleTab(
            'usertracker',
            'user tracker',
            'MODULE_USERTRACKER_POST'
        );
    }
    /**
     * Presents the powermanagement page.
     *
     * @return void
     */
    public function servicePowermanagement()
    {
        $this->_renderModuleTab(
            'powermanagement',
            'power management',
            'pm',
            'MODULE_POWERMANAGEMENT_FIELDS'
        );
    }
    /**
     * Updates the power management elements.
     *
     * @return void
     */
    public function servicePowermanagementPost()
    {
        $this->_saveModuleTab(
            'powermanagement',
            'power management',
            'MODULE_POWERMANAGEMENT_POST'
        );
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
        self::checkAuthAndCSRF();
        $this->editPost();
    }
    /**
     * Redirect list page updates
     *
     * @return void
     */
    public function listPost()
    {
        self::checkAuthAndCSRF();
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
                    // A module row whose tab has not been written must not
                    // take the whole page down with it. `software` is one
                    // today. The moment the method exists this renders it.
                    if (!method_exists($this, $func)) {
                        printf(
                            '<p>%s</p>',
                            _('This module has no settings to configure.')
                        );
                        return;
                    }
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
        self::checkAuthAndCSRF();
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
        } catch (\Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'SERVICE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Module Update Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg
            ],
            $hook
        );
    }
}
