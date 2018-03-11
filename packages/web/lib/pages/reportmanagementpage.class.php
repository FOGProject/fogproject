<?php
/**
 * Displays 'reports' for the admins.
 *
 * PHP version 5
 *
 * @category ReportManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays 'reports' for the admins.
 *
 * @category ReportManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ReportManagementPage extends FOGPage
{
    /**
     * The node this page displays from.
     *
     * @var string
     */
    public $node = 'report';
    /**
     * Loads custom reports.
     *
     * @return array
     */
    public static function loadCustomReports()
    {
        $regext = sprintf(
            '#^.+%sreports%s.*\.report\.php$#',
            DS,
            DS
        );
        $dirpath = sprintf(
            '%sreports%s',
            DS,
            DS
        );
        $strlen = -strlen('.report.php');
        $plugins = '';
        $fileitems = function ($element) use ($dirpath, &$plugins) {
            preg_match(
                sprintf(
                    "#^($plugins.+%splugins%s)(?=.*$dirpath).*$#",
                    DS,
                    DS
                ),
                $element[0],
                $match
            );

            return $match[0];
        };
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            BASEPATH,
            FileSystemIterator::SKIP_DOTS
        );
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        $files = iterator_to_array($RegexIterator, false);
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        $plugins = '?!';
        $tFiles = array_map($fileitems, (array)$files);
        $fFiles = array_filter($tFiles);
        $normalfiles = array_values($fFiles);
        unset($tFiles, $fFiles);
        $plugins = '?=';
        $grepString = sprintf(
            '#%s(%s)%s#',
            DS,
            implode(
                '|',
                self::$pluginsinstalled
            ),
            DS
        );
        $tFiles = array_map($fileitems, (array)$files);
        $fFiles = preg_grep($grepString, $tFiles);
        $pluginfiles = array_values($fFiles);
        unset($tFiles, $fFiles, $files);
        $files = array_merge(
            $normalfiles,
            $pluginfiles
        );
        $getNiceNameReports = function ($element) use ($strlen) {
            return str_replace(
                '_',
                ' ',
                substr(
                    basename($element),
                    0,
                    $strlen
                )
            );
        };
        $data = array_map(
            $getNiceNameReports,
            (array)$files
        );
        natcasesort($data);
        return $data;
    }
    /**
     * Initializes the report page.
     *
     * @param string $name The name if other than this.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        set_time_limit(0);
        $this->name = 'Report Management';
        parent::__construct($this->name);
        $_SESSION['foglastreport'] = null;
        $this->ReportMaker = self::getClass('ReportMaker');
    }
    /**
     * Presents when the user hit's the home link.
     *
     * @return void
     */
    public function home()
    {
        $this->index();
    }
    /**
     * Allows the user to upload new reports if they created one.
     *
     * @return void
     */
    public function upload()
    {
        $this->title = _('Import Reports');

        $buttons = self::makeButton(
            'import-send',
            _('Import'),
            'btn btn-primary'
        );

        $fields = [
            self::makeLabel(
                'col-sm-2 control-label',
                'import',
                _('Import Report')
                . '<br/>('
                . _('Max Size')
                . ': '
                . ini_get('post_max_size')
            ) => '<div class="input-group">'
            . self::makeLabel(
                'input-group-btn',
                '',
                '<span class="btn btn-info">'
                . _('Browse')
                . self::makeInput(
                    'hidden',
                    'report',
                    '',
                    'file',
                    'import'
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
        ];
        self::$HookManager->processEvent(
            'IMPORT_REPORT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'report' => &$this->ReportMaker
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
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '<p class="help-block">';
        echo _(
            'This section allows you to upload user '
            . 'defined reports that may not be a part of '
            . 'the base FOG install.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * The actual index presentation.
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('About FOG Reports');
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo _('FOG Reports exist to give you information about what')
            . ' '
            . _('is going on with your FOG System')
            . '. '
            . _('To view a report, select an item from the menu')
            . '.';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
