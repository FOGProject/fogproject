<?php
/**
 * Plugin management page
 *
 * PHP version 5
 *
 * @category PluginManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin management page
 *
 * @category PluginManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PluginManagementPage extends FOGPage
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
            _('Location'),
            _('Activated'),
            _('Installed')
        ];
        $this->templates = [
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
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
            . self::makeTabUpdateURL(
                'plugin-activate'
            )
            . '" ';

        $install = ' method="post" action="'
            . self::makeTabUpdateURL(
                'plugin-install'
            )
            . '" ';

        $deactivate = ' method="post" action="'
            . self::makeTabUpdateURL(
                'plugin-deactivate'
            )
            . '" ';

        $remove = ' method="post" action="'
            . self::makeTabUpdateURL(
                'plugin-remove'
            )
            . '" ';

        $activateBtn = self::makeButton(
            'activate',
            _('Activate selected'),
            'btn btn-primary',
            $activate
        );

        $installBtn = self::makeButton(
            'install',
            _('Install selected'),
            'btn btn-success',
            $install
        );

        $deactivateBtn = self::makeButton(
            'deactivate',
            _('Deactivate selected'),
            'btn btn-warning',
            $deactivate
        );

        $removeBtn = self::makeButton(
            'remove',
            _('Remove selected'),
            'btn btn-danger'
        );

        $buttons = '<div class="btn-group">'
            . $activateBtn
            . $installBtn
            . $deactivateBtn
            . $removeBtn
            . '</div>';

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
        echo '</div>';
    }
}
