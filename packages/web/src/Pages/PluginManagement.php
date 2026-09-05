<?php
/**
 * Plugin management page
 *
 * PHP version 7.4+
 *
 * @category PluginManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Auth\Authorization;
use FOG\Base\FOGPage;
use FOG\Items\Plugin;
use FOG\Managers\PluginManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
    public $atts = [];
    /**
     * The node that uses this item
     *
     * @var string
     */
    public $node = 'plugin';
    /**
     * This grid does not select.
     *
     * A plugin is installed and uninstalled by its own row action, never by
     * ticking rows and pressing Delete.
     *
     * @var bool
     */
    public $selectable = false;
    /**
     * Initialize the plugin page
     *
     * @param string $name the name of the page.
     *
     * @return false;
     */
    public function __construct($name = '')
    {
        $this->name = _('Plugin Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Plugin Name'),
            _('Description'),
            _('Version'),
            _('Location'),
            _('Activated'),
            _('Installed')
        ];
        // Percentages, not content-driven widths. Without them the browser
        // sizes columns to their longest cell and Plugin Name wrapped while
        // the wide columns took the table. These proportions are only honored
        // because the table carries .fog-table-fixed (see registerTable()),
        // which switches it to a fixed layout.
        //
        // The five VISIBLE widths sum to 100. Description is not one of them:
        // it is the row's tooltip on every grid now, not a column, so its
        // header is emitted (DataTables needs a <th> per column) and then
        // hidden, and whatever width it carried would only be subtracted from
        // the total the other five have to share.
        $this->attributes = [
            ['width' => '25%'],
            ['width' => '0%'],
            ['width' => '15%'],
            ['width' => '30%'],
            ['width' => '15%'],
            ['width' => '15%']
        ];
    }
    /**
     * The index page.
     *
     * @return void
     */
    public function index(...$args)
    {
        if (self::$ajax) {
            header('Content-type: application/json');
            Route::listem('plugin');
            $data = json_decode(Route::getData());
            foreach ((array)$data->data as &$row) {
                $plugin = new Plugin($row->id);
                $row->needsupdate = $plugin->needsSchemaUpdate() ? 1 : 0;
                // Why a plugin can't be turned on, rendered on the row rather
                // than only raised when the activate button is pressed. The
                // reason is a property of the plugin, so an admin should be
                // able to see it without first trying and failing.
                $row->incompatible = Plugin::compatError(
                    Plugin::readManifest((string)$row->location)
                );
                // A row whose directory is gone. Rendered rather than
                // silently tolerated: it is otherwise indistinguishable from
                // a plugin that simply has no manifest, and the only route
                // out of it is the Forget action, which the admin has to know
                // to reach for.
                $row->missing = Plugin::isMissing((string)$row->location)
                    ? 1
                    : 0;
                unset($row);
            }
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($data));
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

        $update = ' method="post" action="'
            . '../management/index.php?node=plugin&sub=upgrade'
            . '" ';

        // 'forget', not 'deleteplugin'. _subToAction() maps a delete* sub to
        // the delete action, and the plugin node registers only
        // view/edit/install -- so plugin.delete would resolve to a permission
        // nobody holds and the button would be dead for everyone. Deleting a
        // row for code that is no longer there is housekeeping of the same
        // authority as uninstalling, which is plugin.edit, and that is what
        // this name resolves to.
        $forget = ' method="post" action="'
            . '../management/index.php?node=plugin&sub=forget'
            . '" ';

        // Upload, then Activate/Deactivate, in that emission order: inside a
        // float-end cluster the buttons run left to right, so the supporting
        // action is emitted first and "Activate selected" stays the one
        // primary on this side. Secondary, not primary, for the same reason.
        //
        // The button is rendered whenever the caller holds plugin.install --
        // not only when uploads are switched on -- so an admin who has the
        // permission is told what to turn on rather than left looking for a
        // button that is not there. The modal body says which half is missing.
        $uploadModal = '';
        $buttons = '';
        if (Authorization::can('plugin.install')) {
            $buttons .= self::makeButton(
                'plugin-upload',
                _('Upload plugin'),
                'btn btn-secondary float-end',
                ' type="button" '
            );
            $uploadModal = self::makeModal(
                'plugin-uploadModal',
                _('Upload plugin'),
                '<div id="plugin-upload-form"></div>'
                . '<div id="plugin-upload-preview" class="d-none"></div>',
                self::makeButton(
                    'plugin-upload-cancel',
                    _('Cancel'),
                    'btn btn-outline-secondary float-start',
                    ' type="button" data-bs-dismiss="modal" '
                )
                . self::makeButton(
                    'plugin-upload-send',
                    _('Upload'),
                    'btn btn-primary float-end',
                    ' type="button" '
                ),
                '',
                'primary',
                'modal-lg'
            );
        }

        // Activate/Deactivate Plugins
        $buttons .= '<div class="btn-group float-end">';
        $buttons .= self::makeSplitButton(
            'activate',
            _('Activate selected'),
            [
                [
                    'id' => 'deactivate',
                    'text' => _('Deactivate selected'),
                    'props' => $deactivate
                ]
            ],
            'right',
            'primary',
            $activate
        );
        $buttons .= '</div>';

        // Install/Uninstall Plugins
        $buttons .= '<div class="btn-group float-start">';
        $buttons .= self::makeSplitButton(
            'install',
            _('Install selected'),
            [
                [
                    'id' => 'remove',
                    'text' => _('Uninstall selected'),
                    'props' => $remove
                ],
                [
                    'id' => 'update',
                    'text' => _('Update selected'),
                    'props' => $update
                ],
                [
                    'id' => 'forget',
                    'text' => _('Forget selected'),
                    'props' => $forget
                ]
            ],
            'left',
            'success',
            $install
        );
        $buttons .= '</div>';

        echo '<div class="card card-primary card-outline">';
        echo '<div id="plugins" class="">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('List All Plugins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Outside the card, and this page's table is not wrapped in a form, so
        // the modal's own form is not nested inside another one.
        echo $uploadModal;
    }
    /**
     * Why archive uploads are not available, or '' when they are.
     *
     * Two independent switches, both required, because either one alone is a
     * hole. The setting is what an admin turns on in the UI; the directory
     * permission is a root act (bin/fog-plugin-uploads.sh). If the setting
     * alone were enough, the UI could grant itself a web-writable directory
     * that PHP autoloads code from, which is the thing the split exists to
     * prevent.
     *
     * @return string a human-readable reason, or ''
     */
    public static function uploadsBlocked()
    {
        if (!self::getSetting('FOG_PLUGIN_UI_INSTALL_ENABLED')) {
            return _('Plugin uploads are switched off. Turn on FOG_PLUGIN_UI_INSTALL_ENABLED in FOG Settings.');
        }
        if (!defined('FOG_PLUGIN_DIR') || !is_dir(FOG_PLUGIN_DIR)) {
            return sprintf(
                _('%s does not exist. Re-run the FOG installer.'),
                defined('FOG_PLUGIN_DIR') ? FOG_PLUGIN_DIR : _('The plugin directory')
            );
        }
        if (!is_writable(FOG_PLUGIN_DIR)) {
            return sprintf(
                _('%s is not writable by the web server. Run "bin/fog-plugin-uploads.sh enable" as root.'),
                FOG_PLUGIN_DIR
            );
        }
        return '';
    }
    /**
     * The upload form, fetched into the modal by the browser.
     *
     * Fetched rather than rendered inline so the reason an upload is
     * unavailable is worked out when the modal is opened, not when the page
     * was last loaded -- an admin who runs the enable script in another window
     * does not have to hunt for why the button still complains.
     *
     * @return void
     */
    public function installArchive()
    {
        $blocked = self::uploadsBlocked();
        if ('' !== $blocked) {
            echo '<div class="alert alert-warning mb-0">'
                . \Initiator::e($blocked)
                . '</div>';
            exit;
        }
        $fields = [
            self::makeLabel(
                'col-sm-3 col-form-label',
                'pluginarchive',
                _('Plugin archive')
                . '<br/>(' . _('Max Size') . ': ' . ini_get('post_max_size') . ')'
            ) => '<div class="input-group">'
            . self::makeLabel(
                'btn btn-info',
                'pluginarchive',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'pluginarchive',
                    '',
                    'file',
                    'pluginarchive',
                    '',
                    true
                )
            )
            . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                'pluginarchivedisp',
                '',
                false,
                false,
                -1,
                -1,
                '',
                true
            )
            . '</div>'
            . '<span class="form-text">'
            . _('A .tar.gz holding one directory named for the plugin, with config/plugin.config.php inside it.')
            . '</span>',
        ];
        echo '<p class="form-text">';
        echo _(
            'A plugin is PHP that runs on this server. Only upload one you '
            . 'trust. Nothing is installed until you have seen what the '
            . 'archive contains and confirmed it.'
        );
        echo '</p>';
        echo self::formFields($fields);
        exit;
    }
    /**
     * Accepts the upload, stages it, and describes what it holds.
     *
     * Nothing is installed here. The archive is unpacked somewhere the
     * autoloader does not look and the caller is handed a token plus the
     * manifest, so the confirmation step has something real to show rather
     * than the file name the browser supplied.
     *
     * @return void
     */
    public function installArchivePost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $serverFault = false;
        try {
            $blocked = self::uploadsBlocked();
            if ('' !== $blocked) {
                throw new \Exception($blocked);
            }
            if (!isset($_FILES['pluginarchive'])
                || !is_uploaded_file((string)$_FILES['pluginarchive']['tmp_name'])
            ) {
                throw new \Exception(_('No archive was uploaded.'));
            }
            if ($_FILES['pluginarchive']['error'] > 0) {
                throw new \Exception(
                    sprintf(
                        _('The upload failed (error %s). It may be larger than post_max_size, currently %s.'),
                        $_FILES['pluginarchive']['error'],
                        ini_get('post_max_size')
                    )
                );
            }
            $staged = Plugin::stageArchive(
                (string)$_FILES['pluginarchive']['tmp_name'],
                basename((string)$_FILES['pluginarchive']['name'])
            );
            if (isset($staged['error'])) {
                throw new \Exception($staged['error']);
            }
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $hook = 'PLUGIN_ARCHIVE_STAGED';
            $msg = json_encode($staged);
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_ARCHIVE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Upload Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Placeholder so the POST below is reachable.
     *
     * FOGPageManager::render() resolves the sub to a method and falls back to
     * index() unless a method of the BASE name exists -- it only appends
     * 'Post' after that check has passed. Without this, a POST to
     * sub=installArchiveCommit silently rendered the plugin list instead of
     * committing, with a 200 and a page body rather than any error. Same
     * reason activate(), install() and remove() are empty here.
     *
     * @return void
     */
    public function installArchiveCommit()
    {
    }
    /**
     * Moves a staged plugin into the external root.
     *
     * The plugin lands on disk not installed and not active. Discovery writes
     * its row on the next boot and the admin still has to install and activate
     * it, which keeps "the files are here" and "this code is running" two
     * separate decisions.
     *
     * @return void
     */
    public function installArchiveCommitPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $serverFault = false;
        try {
            $blocked = self::uploadsBlocked();
            if ('' !== $blocked) {
                throw new \Exception($blocked);
            }
            $token = (string)filter_input(INPUT_POST, 'token');
            $result = Plugin::commitStaged($token);
            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_ARCHIVE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => sprintf(
                        $result['upgrade']
                            ? _('%s replaced. Install it to apply any new database steps.')
                            : _('%s added. Install it to set up its database.'),
                        $result['name']
                    ),
                    'title' => _('Plugin Upload Success')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_ARCHIVE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Upload Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Stops a batch that contains a plugin which cannot run here.
     *
     * The whole batch is refused rather than the offending members quietly
     * skipped: a partial success reported as "Plugins activated!" leaves the
     * admin believing something is on that isn't, and the plugins in a batch
     * are often the ones that depend on each other.
     *
     * @param array $plugins the posted plugin ids
     *
     * @throws \Exception when any of them is blocked
     *
     * @return void
     */
    private function _refuseBlocked($plugins)
    {
        $blockers = Plugin::activationBlockers((array)$plugins);
        if (!count($blockers)) {
            return;
        }
        $reasons = [];
        foreach ($blockers as $name => $reason) {
            $reasons[] = sprintf('%s %s', $name, $reason);
        }
        throw new \Exception(implode('; ', $reasons));
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
        self::checkAuthAndCSRF();
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
            $this->_refuseBlocked($plugins);
            $ids = ['id' => $plugins];
            $state = ['state' => 1];
            $PluginManager = new PluginManager();
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new \Exception(_('Activate plugins failed!'));
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
        } catch (\Exception $e) {
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
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        self::checkAuthAndCSRF();
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
            $this->_refuseBlocked($plugins);
            $ids = ['id' => $plugins];
            $state = ['state' => 1];
            $install = ['installed' => 1];
            $PluginManager = new PluginManager();
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new \Exception(_('Activate plugins failed!'));
            }
            $Plugins = Route::getList(
                'plugin',
                [
                    'id' => $plugins,
                    'installed' => ['',0,'0']
                ]
            );
            foreach ($Plugins as &$Plugin) {
                $pluginObj = new Plugin($Plugin->id);
                if (!$pluginObj->installdb()) {
                    throw new \Exception(
                        _('Failed to install ')
                        . $Plugin->name
                    );
                }
                unset($Plugin);
            }
            if (!$PluginManager->update($ids, '', $install)) {
                $serverFault = true;
                throw new \Exception(_('Install plugins failed!'));
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
        } catch (\Exception $e) {
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
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Redirect to index.
     *
     * @return void
     */
    public function upgrade()
    {
    }
    /**
     * Apply pending schema migrations to already-installed plugins.
     *
     * Unlike install, this does not filter on install state: it runs
     * installdb() (non-destructive) for each selected plugin so a plugin
     * whose code ships newer schema() steps gets caught up without a
     * drop/recreate.
     *
     * @return void
     */
    public function upgradePost()
    {
        self::checkAuthAndCSRF();
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
        self::$HookManager->processEvent('PLUGIN_UPGRADE_POST');

        $serverFault = false;
        try {
            // Only update plugins that are actually installed (the reverse of
            // the install filter): updating a not-installed plugin is a no-op
            // for the admin's intent.
            $Plugins = Route::getList(
                'plugin',
                [
                    'id' => $plugins,
                    'installed' => 1
                ]
            );
            foreach ($Plugins as &$Plugin) {
                $pluginObj = new Plugin($Plugin->id);
                if (!$pluginObj->installdb()) {
                    throw new \Exception(
                        _('Failed to update ')
                        . $Plugin->name
                    );
                }
                unset($Plugin);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_UPGRADE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => (
                        count($plugins ?: []) == 1 ?
                        _('Plugin updated!') :
                        _('Plugins updated!')
                    ),
                    'title' => _('Plugin Update Success')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_UPGRADE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Update Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        self::checkAuthAndCSRF();
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
            $PluginManager = new PluginManager();
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new \Exception(_('Deactivate plugins failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_DEACTIVATE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Plugin deactivated!'),
                    'title' => _('Plugin Deactivate Success')
                ]
            );
        } catch (\Exception $e) {
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
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        self::checkAuthAndCSRF();
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
            // pSchema goes back to 0 with the tables it describes. It is a
            // count of applied migration steps, and uninstall() has just
            // dropped everything those steps built -- leaving it at its old
            // high-water mark made the NEXT install a no-op: applyUpdates()
            // saw "already at step N", ran nothing, recreated no tables, and
            // reported "Plugin installed!". The plugin then came up active
            // with no tables and every query against it threw
            // "Base table or view not found".
            $install = ['installed' => 0, 'schema' => 0];
            $PluginManager = new PluginManager();
            if (!$PluginManager->update($ids, '', $state)) {
                $serverFault = true;
                throw new \Exception(_('Deactivate plugins failed!'));
            }
            $Plugins = Route::getList(
                'plugin',
                [
                    'id' => $plugins,
                    'installed' => 1
                ]
            );
            foreach ($Plugins as &$Plugin) {
                $installPlugin = self::getClass(
                    $Plugin->name
                    . 'Manager'
                );
                if (!method_exists($installPlugin, 'uninstall')) {
                    $serverFault = true;
                    throw new \Exception(
                        _('Unable to uninstall, no method exists for ')
                        . $Plugin->name
                    );
                }
                if (!$installPlugin->uninstall()) {
                    throw new \Exception(
                        _('Failed to uninstall ')
                        . $Plugin->name
                    );
                }
                // Drop any role permissions scoped to this plugin's node so
                // they do not linger after the node leaves the registry.
                Authorization::purgePermissions(strtolower($Plugin->name));
            }
            if (!$PluginManager->update($ids, '', $install)) {
                $serverFault = true;
                throw new \Exception(_('Uninstall plugins failed!'));
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
        } catch (\Exception $e) {
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
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Placeholder so the dispatcher routes ?sub=forget to forgetPost()
     * instead of falling back to index().
     *
     * @return void
     */
    public function forget()
    {
    }
    /**
     * Deletes rows whose plugin code is no longer on disk.
     *
     * Discovery only ever walks directories, so a plugin whose code has been
     * deleted leaves a row nothing will ever visit again. Until now there was
     * no way to remove it: the page offers activate, deactivate, install and
     * uninstall, none of which delete a row.
     *
     * Refuses any selected plugin whose code IS present, rather than deleting
     * the row and letting the next discovery pass put it straight back. That
     * would look like a working delete and be a no-op, which is the shape of
     * bug this page has produced too many of already.
     *
     * The plugin's tables are left behind, and the message says so. They
     * cannot be dropped: the list of what to drop lives in the manager
     * class's schema(), which is exactly the code that has gone.
     *
     * @return void
     */
    public function forgetPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $plugins = filter_input_array(
            INPUT_POST,
            [
                'plugins' => [
                    'flags' => FILTER_REQUIRE_ARRAY
                ]
            ]
        );
        $plugins = array_filter(array_map('intval', (array)$plugins['plugins']));
        self::$HookManager->processEvent('PLUGIN_FORGET_POST');

        $serverFault = false;
        try {
            if (!count($plugins)) {
                throw new \Exception(_('No plugins selected.'));
            }
            $rows = Route::getList('plugin', ['id' => $plugins]);
            $present = [];
            $forget = [];
            foreach ($rows as $row) {
                if (Plugin::isMissing((string)$row->location)) {
                    $forget[(int)$row->id] = (string)$row->name;
                    continue;
                }
                $present[] = $row->name;
            }
            if (count($present)) {
                throw new \Exception(
                    sprintf(
                        _('These plugins are still installed on disk, so forgetting them would achieve nothing: %s. Uninstall them instead.'),
                        implode(', ', $present)
                    )
                );
            }
            foreach ($forget as $id => $name) {
                $plugin = new Plugin($id);
                if (!$plugin->destroy()) {
                    $serverFault = true;
                    throw new \Exception(_('Failed to forget ') . $name);
                }
                // Same cleanup uninstall does. A row can reach here still
                // marked installed -- the admin deleted the directory by hand
                // rather than uninstalling first -- and its role permissions
                // would otherwise outlive every trace of the plugin.
                Authorization::purgePermissions(strtolower($name));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'PLUGIN_FORGET_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Forgotten. Any database tables the plugin created are still there -- what to drop is described by code that is no longer installed.'),
                    'title' => (
                        count($forget) == 1 ?
                        _('Plugin Forgotten') :
                        _('Plugins Forgotten')
                    )
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'PLUGIN_FORGET_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Plugin Forget Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'Plugin' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => $msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Placeholder so the dispatcher routes ?sub=sidebar to sidebarAjax()
     * instead of falling back to index().
     *
     * @return void
     */
    public function sidebar()
    {
    }
    /**
     * Returns the rebuilt "PLUGIN OPTIONS" sidebar markup so the menu can be
     * refreshed over AJAX after install/activate/deactivate/remove without a
     * full page reload. This runs in a fresh request, so getActivePlugins()
     * and the per-plugin menu hooks reflect the new state -- the output is
     * identical to what a browser refresh would render.
     *
     * @return void
     */
    public function sidebarAjax()
    {
        $main = $hookMain = '';
        self::buildMainMenuItems($main, $hookMain);
        echo $hookMain;
        exit;
    }
}
