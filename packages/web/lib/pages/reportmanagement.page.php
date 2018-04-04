<?php
/**
 * Displays 'reports' for the admins.
 *
 * PHP version 5
 *
 * @category ReportManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays 'reports' for the admins.
 *
 * @category ReportManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ReportManagement extends FOGPage
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
        $extension = '.report.php';
        $files = self::fileitems(
            $extension,
            'reports'
        );
        $strlen = -strlen($extension);
        foreach ($files as $i => &$file) {
            $files[$i] = str_replace(
                '_',
                ' ',
                substr(basename($file), 0, $strlen)
            );
            unset($file);
        }
        natcasesort($files);
        return $files;
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

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'import',
                _('Import Report')
                . '<br/>('
                . _('Max Size')
                . ': '
                . ini_get('post_max_size')
                . ')'
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
}
