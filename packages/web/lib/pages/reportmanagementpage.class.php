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
    private static function _loadCustomReports()
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
        $tFiles = array_map($fileitems, (array) $files);
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
        $tFiles = array_map($fileitems, (array) $files);
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
        $this->menu = array(
            'home' => self::$foglang['Home']
        );
        $reportlink = "?node={$this->node}&sub=file&f=";
        foreach (self::_loadCustomReports() as &$report) {
            $item = array();
            foreach (explode(' ', strtolower($report)) as &$rep) {
                $item[] = ucfirst(trim($rep));
                unset($rep);
            }
            $item = implode(' ', $item);
            $this->menu = self::fastmerge(
                (array)$this->menu,
                array(
                    sprintf(
                        '%s%s',
                        $reportlink,
                        base64_encode($report)
                    ) => $item
                )
            );
            unset($report, $item);
        }
        $this->menu = self::fastmerge(
            (array)$this->menu,
            array('upload' => self::$foglang['UploadRprts'])
        );
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes
                )
            );
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
        $this->title = _('Upload FOG Reports');
        printf(
            '<div class="hostgroup">%s</div>'
            . '<p class="titleBottomLeft">%s</p>'
            . '<form method="post" action="%s" enctype="multipart/form-data">'
            . '<input type="file" name="report"/>'
            . '<span class="lightColor">%s: %s</span>'
            . '<p><input type="submit" value="%s"/></p></form>',
            _(
                'This section allows you to upload user '
                . 'defined reports that may not be part of '
                . 'the base FOG package. The report files '
                . 'should end in .php'
            ),
            _('Upload a FOG Report'),
            $this->formAction,
            _('Max Size'),
            ini_get('post_max_size'),
            _('Upload File')
        );
    }
    /**
     * The actual index presentation.
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('About FOG Reports');
        printf(
            '<p>%s</p>',
            _(
                'FOG Reports exist to give you information '
                . 'about what is going on with your FOG System. '
                . 'To view a report, select an item from the menu '
                . 'on the left-hand side of this page.'
            )
        );
    }
}
