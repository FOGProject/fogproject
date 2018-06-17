<?php
/**
 * Plugin management page
 *
 * PHP version 5
 *
 * @category PluginManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin management page
 *
 * @category PluginManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PluginManagement extends FOGPage
{
    /**
     * The node that uses this item
     *
     * @var string
     */
    public $node = 'plugin';
    /**
     * Initialize the plugin page
     *
     * @param string $name the name of the page.
     *
     * @return false;
     */
    public function __construct($name = '')
    {
        $this->name = 'Plugin Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Plugin Name'),
            _('Description'),
            _('Location'),
            _('Activated'),
            _('Installed')
        ];
        $this->attributes = [
            [],
            [],
            [],
            ['width' => 5],
            ['width' => 5]
        ];
    }
    /**
     * The index page.
     *
     * @return void
     */
    public function index()
    {
        if (self::$ajax) {
            header('Content-type: application/json');
            Route::listem('plugin');
            echo Route::getData();
            exit;
        }
        $this->title = _('List All Plugins');

        $activate = ' method="post" action="'
            . '../management/index.php?node=plugin&sub=activate'
            . '" ';

        $install = ' method="post" action="'
            . '../management/index.php?node=plugin&sub=install'
            . '" ';

        $deactivate = ' method="post" action="'
            . '../management/index.php?node=plugin&sub=deactivate'
            . '" ';

        $remove = ' method="post" action="'
            . '../management/index.php?node=plugin&sub=remove'
            . '" ';

        // Activate/Deactivate Plugins
        $buttons = '<div class="btn-group pull-right">';
        $buttons .= self::makeSplitButton(
            'activate',
            _('Activate selected'),
            [
                [
                    'id' => 'deactivate',
                    'text' => _('Deactivate selected'),
                    'prop' => $deactivate
                ]
            ],
            'right',
            'primary',
            $activate
        );
        $buttons .= '</div>';

        // Install/Uninstall Plugins
        $buttons .= '<div class="btn-group pull-left">';
        $buttons .= self::makeSplitButton(
            'install',
            _('Install selected'),
            [
                [
                    'id' => 'remove',
                    'text' => _('Uninstall selected'),
                    'prop' => $remove
                ]
            ],
            'left',
            'success',
            $install
        );
        $buttons .= '</div>';

        echo '<div class="box box-solid">';
        echo '<div id="plugins" class="">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('List All Plugins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Just a place holder
     *
     * @return void
     */
    public function activate()
    {
    }
    /**
     * Actually perform activation.
     *
     * @return void
     */
    public function activatePost()
    {
        header('Content-type: application/json');
        $plugins = filter_input_array(
            INPUT_POST,
            [
                'plugins' => [
                    'flags' => FILTER_REQUIRE_ARRAY
                ]
            ]
        );
        $plugins = $plugins['plugins'];
        self::$HookManager->processEvent('PLUGIN_ACTIVATE_POST');

        $serverFault = false;
        try {
            $ids = ['id' => $plugins];
            $state = ['state' => 1];
            $PluginManager = self::getClass('PluginManager');
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new Exception(_('Activate plugins failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_ACTIVATE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => (
                        count($plugins ?: []) == 1 ?
                        _('Plugin activated!') :
                        _('Plugins activated!')
                    ),
                    'title' => _('Plugin Activate Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_ACTIVATE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Activate Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Redirect to index.
     *
     * @return void
     */
    public function install()
    {
    }
    /**
     * Actually perform installation.
     *
     * @return void
     */
    public function installPost()
    {
        header('Content-type: application/json');
        $plugins = filter_input_array(
            INPUT_POST,
            [
                'plugins' => [
                    'flags' => FILTER_REQUIRE_ARRAY
                ]
            ]
        );
        $plugins = $plugins['plugins'];
        self::$HookManager->processEvent('PLUGIN_INSTALL_POST');

        $serverFault = false;
        try {
            $ids = ['id' => $plugins];
            $state = ['state' => 1];
            $install = ['installed' => 1];
            $PluginManager = self::getClass('PluginManager');
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new Exception(_('Activate plugins failed!'));
            }
            Route::listem(
                'plugin',
                [
                    'id' => $plugins,
                    'installed' => ['',0,'0']
                ]
            );
            $Plugins = json_decode(
                Route::getData()
            );
            foreach ($Plugins->data as &$Plugin) {
                $installPlugin = self::getClass(
                    $Plugin->name
                    . 'Manager'
                );
                if (!method_exists($installPlugin, 'install')) {
                    $serverFault = true;
                    throw new Exception(
                        _('Unable to install, no method exists for ')
                        . $Plugin->name
                    );
                }
                if (!$installPlugin->install($Plugin->name)) {
                    throw new Exception(
                        _('Failed to install ')
                        . $Plugin->name
                    );
                }
            }
            if (!$PluginManager->update($ids, '', $install)) {
                $serverFault = true;
                throw new Exception(_('Install plugins failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_INSTALL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => (
                        count($plugins ?: []) == 1 ?
                        _('Plugin installed!') :
                        _('Plugins installed!')
                    ),
                    'title' => _('Plugin Install Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_INSTALL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Install Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Just a place holder
     *
     * @return void
     */
    public function deactivate()
    {
    }
    /**
     * Actually perform deactivation.
     *
     * @return void
     */
    public function deactivatePost()
    {
        header('Content-type: application/json');
        $plugins = filter_input_array(
            INPUT_POST,
            [
                'plugins' => [
                    'flags' => FILTER_REQUIRE_ARRAY
                ]
            ]
        );
        $plugins = $plugins['plugins'];
        self::$HookManager->processEvent('PLUGIN_DEACTIVATE_POST');

        $serverFault = false;
        try {
            $ids = ['id' => $plugins];
            $state = ['state' => 0];
            $PluginManager = self::getClass('PluginManager');
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new Exception(_('Deactivate plugins failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_DEACTIVATE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Plugin deactivated!'),
                    'title' => _('Plugin Deactivate Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_DEACTIVATE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Deactivate Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Just a placeholder.
     *
     * @return void
     */
    public function remove()
    {
    }
    /**
     * Actually perform uninstall.
     *
     * @return void
     */
    public function removePost()
    {
        header('Content-type: application/json');
        $plugins = filter_input_array(
            INPUT_POST,
            [
                'plugins' => [
                    'flags' => FILTER_REQUIRE_ARRAY
                ]
            ]
        );
        $plugins = $plugins['plugins'];
        self::$HookManager->processEvent('PLUGIN_UNINSTALL_POST');

        $serverFault = false;
        try {
            $ids = ['id' => $plugins];
            $state = ['state' => 0];
            $install = ['installed' => 0];
            $PluginManager = self::getClass('PluginManager');
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new Exception(_('Deactivate plugins failed!'));
            }
            Route::listem(
                'plugin',
                [
                    'id' => $plugins,
                    'installed' => 1
                ]
            );
            $Plugins = json_decode(
                Route::getData()
            );
            foreach ($Plugins->data as &$Plugin) {
                $installPlugin = self::getClass(
                    $Plugin->name
                    . 'Manager'
                );
                if (!method_exists($installPlugin, 'uninstall')) {
                    $serverFault = true;
                    throw new Exception(
                        _('Unable to uninstall, no method exists for ')
                        . $Plugin->name
                    );
                }
                if (!$installPlugin->uninstall()) {
                    throw new Exception(
                        _('Failed to uninstall ')
                        . $Plugin->name
                    );
                }
            }
            if (!$PluginManager->update($ids, '', $install)) {
                $serverFault = true;
                throw new Exception(_('Uninstall plugins failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_UNINSTALL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => (
                        count($plugins ?: []) == 1 ?
                        _('Plugin uninstalled!') :
                        _('Plugins uninstalled!')
                    ),
                    'title' => _('Plugin Uninstall Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_UNINSTALL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Uninstall Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
}
