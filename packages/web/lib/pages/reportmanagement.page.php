<?php
/**
 * Displays 'reports' for the admins.
 *
 * PHP version 7.4+
 *
 * @category ReportManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Base\FOGPage;

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
        @natcasesort($files);
        return $files;
    }

    /**
     * The reports that are AGGREGATIONS rather than row dumps.
     *
     * The report menu was one flat alphabetical list in which "Imaging
     * Report", which is charts over a window, sat between "Host List" and
     * "Hosts and Users", which are tables. Those are two different kinds of
     * screen and presenting them as one kind is the thing that reads as
     * unfinished (ADR 0030).
     *
     * A LIST HERE RATHER THAN A CONVENTION on the filename, because the
     * convention does not hold: `history_report` ends in `_report` and is a
     * row dump with a redirect on it, and `run_history` does not and is an
     * aggregation. Naming them is honest about that; inferring it would be
     * wrong twice on the files that ship today.
     *
     * NOT read by loading the classes. The menu is built from file names
     * precisely so that rendering a sidebar does not load fourteen classes,
     * and a `const REPORT_GROUP` on each would give that up for a label.
     *
     * Anything not named here -- an uploaded report, a plugin's -- lands
     * under Lists, which is what a FOG report has always been. A plugin that
     * disagrees can move its entry with the SUB_MENULINK_DATA hook, the
     * same seam it used to add the entry.
     *
     * @var array
     */
    const AGGREGATIONS = [
        'audit report',
        'fleet report',
        'hardware report',
        'imaging report',
        'run history',
        'snapin report',
        'storage report'
    ];

    /**
     * The report list, split into its two kinds.
     *
     * @return array group label => ordered list of report names
     */
    public static function groupedReports()
    {
        $groups = [
            'reports' => [],
            'lists' => []
        ];
        foreach (self::loadCustomReports() as $report) {
            $key = in_array(strtolower($report), self::AGGREGATIONS, true)
                ? 'reports'
                : 'lists';
            $groups[$key][] = $report;
        }

        return $groups;
    }
    /**
     * Never called at runtime. The report submenu labels are built
     * dynamically from the report filenames, so xgettext cannot see
     * them in the rendering code. Listing them literally here keeps
     * their msgids in messages.pot when the pre-commit hook
     * regenerates it from source.
     *
     * @return void
     */
    private static function _reportNamesForTranslation()
    {
        _('File Deleter');
        _('Fleet Report');
        _('Hardware Report');
        _('Storage Report');
        _('Audit Report');
        _('History Report');
        _('Imaging Report');
        _('Host List');
        _('Hosts And Users');
        _('Pending Mac List');
        _('Product Keys');
        _('Run History');
        _('Snapin List');
        _('Snapin Report');
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
        $this->name = _('Report Management');
        parent::__construct($this->name);
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
            'btn btn-primary float-end'
        );

        $labelClass = 'col-sm-3 col-form-label';

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
                'btn btn-info',
                'import',
                _('Browse')
                . self::makeInput(
                    'd-none',
                    'report',
                    '',
                    'file',
                    'import',
                    '',
                    true
                )
            )
            . self::makeInput(
                'form-control filedisp',
                '',
                '',
                'text',
                'reportfiledisp',
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
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'import-form',
            $this->formAction,
            'post',
            'multipart/form-data',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '<p class="form-text">';
        echo _(
            'This section allows you to upload user '
            . 'defined reports that may not be a part of '
            . 'the base FOG install.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ReportManagement', 'ReportManagement');
