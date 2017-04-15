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
        $regext = '#^.+/reports/.*\.report\.php$#';
        $dirpath = '/reports/';
        $strlen = -strlen('.report.php');
        $plugins = '';
        $fileitems = function ($element) use ($dirpath, &$plugins) {
            preg_match(
                "#^($plugins.+/plugins/)(?=.*$dirpath).*$#",
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
            '#/(%s)/#',
            implode(
                '|',
                self::$pluginsinstalled
            )
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
        return array_map(
            $getNiceNameReports,
            (array)$files
        );
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
        $this->name = 'Report Management';
        parent::__construct($this->name);
        $this->menu = array(
            'home' => self::$foglang['Home'],
            'snapinlog' => self::$foglang['SnapinLog'],
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
    /**
     * Display selector for snapin log.
     *
     * @return void
     */
    public function snapinlog()
    {
        $this->title = _('FOG Snapin Log - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $start = self::$DB->query(
            "SELECT DATE_FORMAT(MIN(`stCheckinDate`),'%Y-%m-%d') start FROM "
            . "`snapinTasks`"
        )->fetch(
            MYSQLI_NUM,
            'fetch'
        )->get('start');
        $start = self::niceDate($start);
        $end = self::niceDate();
        if (!self::validDate($start)) {
            $start = self::niceDate()->modify('-2 years');
        }
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end);
        $dates = iterator_to_array($daterange);
        foreach ((array)$dates as &$date) {
            $Dates[] = $date->format('Y-m-d');
            unset($Date);
        }
        unset($dates);
        rsort($Dates);
        if (count($Dates) > 0) {
            ob_start();
            foreach ((array)$Dates as &$Date) {
                printf(
                    '<option value="%s">%s</option>',
                    $Date,
                    $Date
                );
                unset($Date);
            }
            unset($Dates);
            $dates = ob_get_clean();
            $date1 = sprintf(
                '<select name="%s" size="1">%s</select>',
                'date1',
                $dates
            );
            $date2 = sprintf(
                '<select name="%s" size="1">%s</select>',
                'date2',
                $dates
            );
            $fields = array(
                _('Select Start Date') => $date1,
                _('Select End Date') => $date2,
                '&nbsp;' => sprintf(
                    '<input type="submit" value="%s"/>',
                    _('Search for Entries')
                ),
            );
            foreach ((array)$fields as $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                );
                unset($input);
            }
            unset($fields);
            printf(
                '<form method="post" action="%s">',
                $this->formAction
            );
            $this->render();
            echo '</form>';
        } else {
            $this->render();
        }
    }
    /**
     * Display filtered snapin log history.
     *
     * @return void
     */
    public function snapinlogPost()
    {
        $this->title = _('FOG Snapin Log');
        printf(
            $this->reportString,
            'SnapinLog',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'SnapinLog',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->headerData = array(
            _('Host Name'),
            _('Snapin Name'),
            _('State'),
            _('Return Code'),
            _('Return Desc'),
            _('Create Date'),
            _('Create Time'),
        );
        $this->templates = array(
            '${host_name}<br/><small>'
            . _('Started/Checked in')
            . ': ${checkin}<br/>'
            . _('Completed')
            . ': ${complete}</small>',
            '${snap_name}',
            '${snap_state}',
            '${snap_return}',
            '${snap_detail}',
            '${snap_create}',
            '${snap_time}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array()
        );
        $date1 = $_REQUEST['date1'];
        $date2 = $_REQUEST['date2'];
        if ($date1 > $date2) {
            $date1 = $_REQUEST['date2'];
            $date2 = $_REQUEST['date1'];
        }
        $date2 = date('Y-m-d', strtotime("$date2 +1 day"));
        $csvHead = array(
            _('Host ID'),
            _('Host Name'),
            _('Host MAC'),
            _('Snapin ID'),
            _('Snapin Name'),
            _('Snapin Description'),
            _('Snapin File'),
            _('Snapin Args'),
            _('Snapin Run With'),
            _('Snapin Run With Args'),
            _('Snapin State'),
            _('Snapin Return Code'),
            _('Snapin Return Detail'),
            _('Snapin Creation Date'),
            _('Snapin Creation Time'),
            _('Job Create Date'),
            _('Job Create Time'),
            _('Task Checkin Date'),
            _('Task Checkin Time'),
            _('Task Complete Date'),
            _('Task Complete Time')
        );
        foreach ((array)$csvHead as $i => &$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        $date1 = self::niceDate($date1);
        $date2 = self::niceDate($date2);
        foreach ((array)self::getClass('SnapinTaskManager')
            ->find() as &$SnapinTask
        ) {
            $start = self::niceDate($SnapinTask->get('checkin'));
            $end = self::niceDate($SnapinTask->get('complete'));
            if (!self::validDate($start)
                && !self::validDate($end)
            ) {
                continue;
            }
            if ($start < $date1
                || $start > $date2
            ) {
                continue;
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                continue;
            }
            $SnapinJob = $SnapinTask->getSnapinJob();
            if (!$SnapinJob->isValid()) {
                continue;
            }
            $Host = $SnapinJob->getHost();
            if (!$Host->isValid()) {
                continue;
            }
            $State = new TaskState($SnapinTask->get('stateID'));
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'checkin' => $SnapinTask->get('checkin'),
                'complete' => $SnapinTask->get('complete'),
                'snap_name' => $Snapin->get('name'),
                'snap_state' => $State->get('name'),
                'snap_return' => $SnapinTask->get('return'),
                'snap_detail' => $SnapinTask->get('detail'),
                'snap_create' => self::formatTime(
                    $Snapin->get('createdTime'),
                    'Y-m-d'
                ),
                'snap_time'=> self::formatTime(
                    $Snapin->get('createdTime'),
                    'H:i:s'
                )
            );
            $this->ReportMaker->addCSVCell($Host->get('id'));
            $this->ReportMaker->addCSVCell($Host->get('name'));
            $this->ReportMaker->addCSVCell($Host->get('mac')->__toString());
            $this->ReportMaker->addCSVCell($Snapin->get('id'));
            $this->ReportMaker->addCSVCell($Snapin->get('name'));
            $this->ReportMaker->addCSVCell($Snapin->get('description'));
            $this->ReportMaker->addCSVCell($Snapin->get('file'));
            $this->ReportMaker->addCSVCell($Snapin->get('args'));
            $this->ReportMaker->addCSVCell($Snapin->get('runWith'));
            $this->ReportMaker->addCSVCell($Snapin->get('runWithArgs'));
            $this->ReportMaker->addCSVCell($State->get('name'));
            $this->ReportMaker->addCSVCell($SnapinTask->get('return'));
            $this->ReportMaker->addCSVCell($SnapinTask->get('detail'));
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $Snapin->get('createdTime'),
                    'Y-m-d'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $Snapin->get('createdTime'),
                    'H:i:s'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $SnapinJob->get('createdTime'),
                    'Y-m-d'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $SnapinJob->get('createdTime'),
                    'H:i:s'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $SnapinTask->get('checkin'),
                    'Y-m-d'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $SnapinTask->get('checkin'),
                    'H:i:s'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $SnapinTask->get('complete'),
                    'Y-m-d'
                )
            );
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $SnapinTask->get('complete'),
                    'H:i:s'
                )
            );
            $this->ReportMaker->endCSVLine();
            unset(
                $Host,
                $Snapin,
                $SnapinJob,
                $SnapinTask,
                $State
            );
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
