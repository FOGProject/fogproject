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
            array('upload' => _('Import Reports'))
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
        $this->title = _('Import FOG Reports');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->data[] = array(
            'field' => '<label for="import">'
            . _('Import Report?')
            . '<br/>'
            . _('Max Size')
            . ': '
            . ini_get('post_max_size')
            . '</label>',
            'input' => '<div class="input-group">'
            . '<label class="input-group-btn">'
            . '<span class="btn btn-info">'
            . _('Browse')
            . '<input type="file" class="hidden" name='
            . '"report" id="import"/>'
            . '</span>'
            . '</label>'
            . '<input type="text" class="form-control filedisp" readonly/>'
            . '</div>'
        );
        $this->data[] = array(
            'field' => '<label for="importbtn">'
            . _('Import Report?')
            . '</label>',
            'input' => '<button type="submit" name="importbtn" class="'
            . 'btn btn-info btn-block" id="importbtn">'
            . _('Import')
            . '</button>'
        );
        self::$HookManager->processEvent(
            'IMPORT_REPORT',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo _('This section allows you to uploade user')
            . ' '
            . _('defined reports that may not be a part of')
            . ' '
            . _('the base FOG install')
            . '.';
        echo '<hr/>';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
