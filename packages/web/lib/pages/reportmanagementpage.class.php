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
        $dirpath = $_SESSION['FOG_REPORT_DIR'];
        $strlen = -strlen('.report.php');
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            $dirpath,
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
        $getNiceNameReports = function ($element) use ($strlen) {
            return str_replace(
                '_',
                ' ',
                substr(
                    basename($element[0]),
                    0,
                    $strlen
                )
            );
        };
        return array_map(
            $getNiceNameReports,
            (array) $files
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
            'equiploan' => self::$foglang['EquipLoan'],
            'hostlist' => self::$foglang['HostList'],
            'imaginglog' => self::$foglang['ImageLog'],
            'inventory' => self::$foglang['Inventory'],
            'pendmac' => self::$foglang['PendingMACs'],
            'snapinlog' => self::$foglang['SnapinLog'],
            'usertrack' => self::$foglang['LoginHistory'],
            'virhist' => self::$foglang['VirusHistory'],
        );
        $reportlink = "?node={$this->node}&sub=file&f=";
        array_map(
            function (&$report) use (&$reportlink) {
                $this->menu = self::fastmerge(
                    (array) $this->menu,
                    array(
                        sprintf(
                            '%s%s',
                            $reportlink,
                            base64_encode($report)
                        ) => implode(
                            ' ',
                            array_map(
                                function (&$item) {
                                    return ucfirst($item);
                                },
                                explode(
                                    ' ',
                                    strtolower($report)
                                )
                            )
                        )
                    )
                );
            },
            (array) self::_loadCustomReports()
        );
        $this->menu = self::fastmerge(
            (array) $this->menu,
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
     * Works with a file.
     *
     * @return void
     */
    public function file()
    {
        array_map(
            function ($className) {
                self::getClass($className);
            },
            (array) preg_replace(
                '#[[:space:]]#',
                '_',
                base64_decode($_REQUEST['f'])
            )
        );
    }
    /**
     * Initial presentation for imaging log.
     *
     * @return void
     */
    public function imaginglog()
    {
        $this->title = _('FOG Imaging Log - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $start = self::$DB->query(
            "SELECT DATE_FORMAT(MIN(`ilStartTime`),'%Y-%m-%d') start FROM "
            . "`imagingLog`"
        )->fetch(
            MYSQLI_NUM,
            'fetch'
        )->get('start');
        $start = self::niceDate($start);
        $end = self::niceDate();
        if (!self::validDate($start)) {
            return $this->render();
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
            printf('<form method="post" action="%s">', $this->formAction);
            $this->render();
            echo '</form>';
        } else {
            $this->render();
        }
    }
    /**
     * Present based on the selections of dates.
     *
     * @return void
     */
    public function imaginglogPost()
    {
        $this->title = _('FOG Imaging Log');
        printf(
            $this->reportString,
            'ImagingLog',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'ImagingLog',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $this->headerData = array(
            _('Engineer'),
            _('Host'),
            _('Start'),
            _('End'),
            _('Duration'),
            _('Image'),
            _('Type'),
        );
        $this->templates = array(
            '${createdBy}',
            '${host_name}',
            '<small>${start_date} ${start_time}</small>',
            '<small>${end_date} ${end_time}</small>',
            '${duration}',
            '${image_name}',
            '${type}',
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
        $date2 = self::niceDate($date2)
            ->modify('+1 day')
            ->format('Y-m-d');
        $csvHead = array(
            _('Engineer'),
            _('Storage Group'),
            _('Storage Node'),
            _('Host ID'),
            _('Host Name'),
            _('Host MAC'),
            _('Host Desc'),
            _('Image Name'),
            _('Image Path'),
            _('Start Date'),
            _('Start Time'),
            _('End Date'),
            _('End Time'),
            _('Duration'),
            _('Deploy/Capture'),
        );
        $imgTypes = array(
            'up' => _('Capture'),
            'down' => _('Deploy'),
        );
        foreach ((array)$csvHead as &$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        ini_set('display_errors', true);
        $date1 = self::niceDate($date1);
        $date2 = self::niceDate($date2);
        foreach ((array)self::getClass('ImagingLogManager')
            ->find() as &$ImagingLog
        ) {
            $Host = $ImagingLog->getHost();
            $start = $ImagingLog->get('start');
            $end = $ImagingLog->get('finish');
            if (!self::validDate($start)
                || !self::validDate($end)
            ) {
                continue;
            }
            $diff = $this->diff($start, $end);
            $start = self::niceDate($start);
            $end = self::niceDate($end);
            if ($start < $date1
                || $start > $date2
            ) {
                continue;
            }
            $hostName = $Host->get('name');
            $hostId = $Host->get('id');
            $hostMac = $Host->get('mac');
            $hostDesc = $Host->get('description');
            unset($Host);
            $typeName = $ImagingLog->get('type');
            if (in_array($typeName, array('up', 'down'))) {
                $typeName = $imgTypes[$typeName];
            }
            $createdBy = (
                $ImagingLog->get('createdBy') ?
                $ImagingLog->get('createdBy') :
                $_SESSION['FOG_USERNAME']
            );
            $Image = self::getClass('Image')
                ->set('name', $ImagingLog->get('image'))
                ->load('name');
            if ($Image->isValid()) {
                $imgName = $Image->get('name');
                $imgPath = $Image->get('path');
            } else {
                $imgName = $ImagingLog->get('image');
                $imgPath = 'N/A';
            }
            unset($Image);
            unset($ImagingLog);
            $this->data[] = array(
                'createdBy' => $createdBy,
                'group_name' => $groupName,
                'node_name' => $nodeName,
                'host_name' => $hostName,
                'start_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'end_date' => $end->format('Y-m-d'),
                'end_time' => $end->format('H:i:s'),
                'duration' => $diff,
                'image_name' => $imgName,
                'type' => $typeName,
            );
            $this->ReportMaker
                ->addCSVCell($createdBy)
                ->addCSVCell($groupName)
                ->addCSVCell($nodeName)
                ->addCSVCell($hostId)
                ->addCSVCell($hostName)
                ->addCSVCell($hostMac)
                ->addCSVCell($hostDesc)
                ->addCSVCell($imgName)
                ->addCSVCell($imgPath)
                ->addCSVCell($start->format('Y-m-d'))
                ->addCSVCell($start->format('H:i:s'))
                ->addCSVCell($end->format('Y-m-d'))
                ->addCSVCell($end->format('H:i:s'))
                ->addCSVCell($diff)
                ->addCSVCell($typeName)
                ->endCSVLine();
        }
        unset($ImagingLogIDs, $id);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(0);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Host listing.
     *
     * @return void
     */
    public function hostlist()
    {
        $this->title = _('Host Listing Export');
        printf(
            $this->reportString,
            'HostList',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'HostList',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $csvHead = array(
            _('Host ID') => 'id',
            _('Host Name') => 'name',
            _('Host Desc') => 'description',
            _('Host MAC') => 'mac',
            _('Host Created') => 'createdTime',
            _('Image ID') => 'id',
            _('Image Name') => 'name',
            _('Image Desc') => 'description',
            _('AD Join') => 'useAD',
            _('AD OU') => 'ADOU',
            _('AD Domain') => 'ADDomain',
            _('Kernel') => 'kernel',
            _('HD Device') => 'kernelDevice',
            _('OS Name') => 'name',
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Hostname'),
            _('Host MAC'),
            _('Image Name'),
        );
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${image_name}',
        );
        foreach ((array)self::getClass('HostManager')
            ->find() as &$Host
        ) {
            $Image = $Host->getImage();
            $imgID = $Image->get('id');
            $imgName = $Image->get('name');
            $imgDesc = $Image->get('description');
            unset($Image);
            $this->data[] = array(
                'host_name'=>$Host->get('name'),
                'host_mac'=>$Host->get('mac'),
                'image_name'=>$imgName,
            );
            foreach ((array)$csvHead as $head => &$classGet) {
                switch ($head) {
                case _('Image ID'):
                    $this->ReportMaker->addCSVCell($imgID);
                    break;
                case _('Image Name'):
                    $this->ReportMaker->addCSVCell($imgName);
                    break;
                case _('Image Desc'):
                    $this->ReportMaker->addCSVCell($imgDesc);
                    break;
                case _('AD Join'):
                    $this->ReportMaker->addCSVCell(
                        (
                            $Host->get('useAD') == 1 ?
                            _('Yes') :
                            _('No')
                        )
                    );
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->get($classGet));
                    break;
                }
                unset($classGet);
            }
            unset($Host);
            $this->ReportMaker->endCSVLine();
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(0);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Presents and reports all Inventory data for hosts.
     *
     * @return void
     */
    public function inventory()
    {
        $this->title = _('Full Inventory Export');
        printf(
            $this->reportString,
            'InventoryReport',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'InventoryReport',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        array_walk(
            self::$inventoryCsvHead,
            function (&$classGet, &$csvHeader) {
                $this->ReportMaker->addCSVCell($csvHeader);
                unset($classGet, $csvHeader);
            }
        );
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Memory'),
            _('System Product'),
            _('System Serial'),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        foreach ((array)self::getClass('HostManager')
            ->find() as &$Host
        ) {
            $Image = $Host->getImage();
            $Inventory = $Host->get('inventory');
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'memory' => $Inventory->getMem(),
                'sysprod' => $Inventory->get('sysproduct'),
                'sysser' => $Inventory->get('sysserial'),
            );
            foreach (self::$inventoryCsvHead as $head => &$classGet) {
                switch ($head) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell($Host->get('id'));
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($Host->get('name'));
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell($Host->get('mac'));
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell($Host->get('description'));
                    break;
                case _('Memory'):
                    $this->ReportMaker->addCSVCell($Inventory->getMem());
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Inventory->get($classGet));
                    break;
                }
                unset($classGet, $head);
            }
            $this->ReportMaker->endCSVLine();
            unset($Inventory, $Host);
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Shows any pending mac addresses.
     *
     * @return void
     */
    public function pendmac()
    {
        if ($_REQUEST['aprvall'] == 1) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    '',
                    '',
                    array(
                        'pending' => 0
                    )
                );
            $this->setMessage(_('All Pending MACs approved.'));
            $this->redirect('?node=report&sub=pendmac');
        }
        $this->title = _('Pending MAC Export');
        printf(
            $this->reportString,
            'PendingMACsList',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'PendingMACsList',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        if ($_SESSION['Pending-MACs']) {
            printf(
                '<a href="?node=report&sub=pendmac&aprvall=1">%s</a>',
                _('Approve All Pending MACs for all hosts')
            );
        }
        echo '</h2>';
        $csvHead = array(
            _('Host ID'),
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Desc'),
            _('Host Pending MAC'),
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
        }
        unset($classGet);
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Pending MAC'),
        );
        $this->templates = array(
            '<input type="checkbox" name="pendmac[]" value='
            . '"${id}" class="toggle-action"/>',
            '${host_name}',
            '${host_mac}',
            '${host_pend}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false'
            ),
            array(),
            array(),
            array(),
        );
        foreach ((array)self::getClass('MACAddressAssociationmanager')
            ->find(
                array('pending' => 1)
            ) as &$Pending
        ) {
            $PendingMAC = new MACAddress($Pending->get('mac'));
            $Host = $Pending->getHost();
            if (!$Host->isValid()) {
                continue;
            }
            $hostID = $Host->get('id');
            $hostName = $Host->get('name');
            $hostMac = $Host->get('mac');
            $hostDesc = $Host->get('description');
            $hostPend = $PendingMAC->__toString();
            unset($Host, $PendingMAC);
            $this->data[] = array(
                'id' => $Pending->get('id'),
                'host_name' => $hostName,
                'host_mac' => $hostMac,
                'host_pend' => $hostPend,
            );
            $this->ReportMaker->addCSVCell($hostID);
            $this->ReportMaker->addCSVCell($hostName);
            $this->ReportMaker->addCSVCell($hostMac);
            $this->ReportMaker->addCSVCell($hostDesc);
            $this->ReportMaker->addCSVCell($hostPend);
            $this->ReportMaker->endCSVLine();
            unset($hostID, $hostName, $hostMac, $hostDesc, $hostPend);
            unset($Host, $PendingMAC);
        }
        if (count($this->data) > 0) {
            printf(
                '<form method="post" action="%s">',
                $this->formAction
            );
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        if (count($this->data) > 0) {
            printf(
                '<p class="c"><input name="approvependmac" type='
                . '"submit" value="%s"/>&nbsp;&nbsp;<input name='
                . '"delpendmac" type="submit" value="%s"/></p></form>',
                _('Approve selected pending macs'),
                _('Delete selected pending macs')
            );
        }
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Approves pending macs
     *
     * @return void
     */
    public function pendmacPost()
    {
        if (isset($_REQUEST['approvependmac'])) {
            self::getClass('MACAddressAssociationManager')->update(
                array('id' => $_REQUEST['pendmac']),
                '',
                array('pending' => 0)
            );
        }
        if (isset($_REQUEST['delpendmac'])) {
            self::getClass('MACAddressAssociationManager')->destroy(
                array('id' => $_REQUEST['pendmac'])
            );
        }
        $appdel = (
            isset($_REQUEST['approvependmac']) ?
            _('approved') : _('deleted')
        );
        $this->setMessage(
            sprintf(
                '%s %s %s',
                _('All pending macs'),
                $appdel,
                _('successfully')
            )
        );
        $this->redirect("?node=$this->node");
    }
    /**
     * Display virus history.
     *
     * @return void
     */
    public function virhist()
    {
        $this->title = _('FOG Virus Summary');
        printf(
            $this->reportString,
            'VirusHistory',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'VirusHistory',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        printf(
            '<form method="post" action="%s"><h2><a href="#">'
            . '<input onclick="this.form.submit()" type='
            . '"checkbox" class="delvid" name="delvall" id='
            . '"delvid" value="all"/><label for="delvid">(%s)'
            . '</label></a></h2></form>',
            $this->formAction,
            _('clear all history')
        );
        $csvHead = array(
            _('Host Name') => 'name',
            _('Virus Name') => 'name',
            _('File') => 'file',
            _('Mode') => 'mode',
            _('Date') => 'date'
        );
        $this->headerData = array(
            _('Host name'),
            _('Virus Name'),
            _('File'),
            _('Mode'),
            _('Date'),
            _('Clear')
        );
        $this->templates = array(
            '${host_name}',
            '<a href="http://www.google.com/search?q=${vir_name}">${vir_name}</a>',
            '${vir_file}',
            '${vir_mode}',
            '${vir_date}',
            sprintf(
                '<input type="checkbox" onclick="this.form.submit()" class='
                . '"delvid" value="${vir_id}" id="vir${vir_id}" name='
                . '"delvid"/><label for="for${vir_id}" class='
                . '"icon icon-hand" title="%s ${vir_name}">'
                . '<i class="fa fa-minus-circle link"></i></label>',
                _('Delete')
            )
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array('class' => 'filter-false')
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        foreach ((array)self::getClass('VirusManager')
            ->find() as &$Virus
        ) {
            $Host = self::getClass('HostManager')
                ->getHostByMacAddresses($Virus->get('mac'));
            if (!$Host->isValid()) {
                continue;
            }
            $hostName = $Host->get('name');
            unset($Host);
            $virusName = $Virus->get('name');
            $virusFile = $Virus->get('file');
            $virusMode = (
                $Virus->get('mode') == 'q' ?
                _('Quarantine') :
                _('Report')
            );
            $virusDate = self::niceDate($Virus->get('date'));
            $this->data[] = array(
                'host_name' => $hostName,
                'vir_id' => $id,
                'vir_name' => $virusName,
                'vir_file' => $virusFile,
                'vir_mode' => $virusMode,
                'vir_date' => self::formatTime(
                    $virusDate,
                    'Y-m-d H:i:s'
                ),
            );
            foreach ((array)$csvHead as $head => &$classGet) {
                switch ($head) {
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($hostName);
                    break;
                case _('Mode'):
                    $this->ReportMaker->addCSVCell($virusMode);
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Virus->get($classGet));
                    break;
                }
                unset($classGet);
            }
            unset($Virus);
            $this->ReportMaker->endCSVLine();
        }
        unset($Virus);
        $this->ReportMaker->appendHTML($this->__toString());
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->ReportMaker->outputReport(false);
        echo '</form>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    /**
     * Clear/remove particular history.
     *
     * @return void
     */
    public function virhistPost()
    {
        if ($_REQUEST['delvall'] == 'all') {
            self::getClass('VirusManager')->destroy();
            $this->setMessage(_("All Virus' cleared"));
            $this->redirect($this->formAction);
        } elseif (is_numeric($_REQUEST['delvid'])) {
            self::getClass('Virus', $_REQUEST['delvid'])->destroy();
            $this->setMessage(_('Virus cleared'));
            $this->redirect($this->formAction);
        }
    }
    /**
     * User tracking log search page.
     *
     * @return void
     */
    public function usertrack()
    {
        $this->title = _('FOG User Login History Summary - Search');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $trackCount = self::getClass('UserTrackingManager')
            ->count();
        if ($trackCount < 1) {
            return $this->render();
        }
        $UserNames = self::getSubObjectIDs('UserTracking', '', 'username');
        $HostNames = self::getSubObjectIDs('Host', '', 'name');
        natcasesort($UserNames);
        $UserNames = array_values(array_filter(array_unique((array)$UserNames)));
        natcasesort($HostNames);
        $HostNames = array_values(array_filter(array_unique((array)$HostNames)));
        if (count($UserNames) > 0) {
            ob_start();
            foreach ((array)$UserNames as &$Username) {
                if ($Username) {
                    printf(
                        '<option value="%s">%s</option>',
                        $Username,
                        $Username
                    );
                }
                unset($Username);
            }
            $userSelForm = sprintf(
                '<select name="usersearch">'
                . '<option value="">- %s -</option>'
                . '%s'
                . '</select>',
                _('Please select an option'),
                ob_get_clean()
            );
        }
        if (count($HostNames) > 0) {
            ob_start();
            foreach ((array)$HostNames as &$Hostname) {
                if ($Hostname) {
                    printf(
                        '<option value="%s">%s</option>',
                        $Hostname,
                        $Hostname
                    );
                }
                unset($Hostname);
            }
            $hostSelForm = sprintf(
                '<select name="hostsearch">'
                . '<option value="">- %s -</option>'
                . '%s'
                . '</select>',
                _('Please select an option'),
                ob_get_clean()
            );
        }
        $fields = array(
            _('Enter a username to search for') => $userSelForm,
            _('Enter a hostname to search for') => $hostSelForm,
            '&nbsp;' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Search')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Submit the searched requests and return the info.
     *
     * @return void
     */
    public function usertrackPost()
    {
        $this->title = _('Results Found for user and/or hostname search');
        $this->headerData = array(
            _('Host/User name'),
            _('Username'),
        );
        $this->templates = array(
            sprintf(
                '<a href="?node=%s&sub=usertrackdisp&hostID='
                . '${host_id}&userID=${user_id}">${hostuser_name}</a>',
                $this->node
            ),
            '${user_name}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $hostsearch = str_replace(
            '*',
            '%',
            sprintf(
                '%%%s%%',
                trim($_REQUEST['hostsearch'])
            )
        );
        $usersearch = str_replace(
            '*',
            '%',
            sprintf(
                '%%%s%%',
                trim($_REQUEST['usersearch'])
            )
        );
        if (trim($_REQUEST['hostsearch'])
            && !trim($_REQUEST['usersearch'])
        ) {
            foreach ((array)self::getClass('HostManager')
                ->find(
                    array('name' => $hostsearch)
                ) as &$Host
            ) {
                $this->data[] = array(
                    'host_id' => $Host->get('id'),
                    'hostuser_name' => $Host->get('name'),
                    'user_id' => base64_encode('%'),
                    'user_name' => '',
                );
                unset($Host);
            }
        } elseif (!trim($_REQUEST['hostsearch'])
            && trim($_REQUEST['usersearch'])
        ) {
            $ids = self::getSubObjectIDs(
                'UserTracking',
                array(
                    'username' => $usersearch
                ),
                array(
                    'id',
                    'hostID'
                )
            );
            $hostIDs = self::getSubObjectIDs(
                'Host',
                array('id' => $ids['hostID'])
            );
            foreach ((array)self::getClass('UserTrackingManager')
                ->find(
                    array(
                        'id' => $ids['id'],
                        'hostID' => $hostIDs
                    )
                ) as &$User
            ) {
                $Username = trim($User->get('username'));
                unset($User);
                if ($lastUser != $Username) {
                    $this->data[] = array(
                        'host_id' => 0,
                        'hostuser_name' => $Username,
                        'user_id' => base64_encode($Username),
                        'user_name' => '',
                    );
                }
                $lastUser = $Username;
                unset($Username);
            }
            unset($lastUser);
        } elseif (trim($_REQUEST['hostsearch'])
            && trim($_REQUEST['usersearch'])
        ) {
            $hostIDs = self::getSubObjectIDs(
                'Host',
                array('name' => $hostsearch)
            );
            foreach ((array)self::getClass('UserTrackingManager')
                ->find(
                    array('username' => $usersearch, 'hostID' => $hostIDs)
                ) as &$User
            ) {
                $Host = new Host($User->get('hostID'));
                $userName = $User->get('username');
                $this->data[] = array(
                    'host_id' => $Host->get('id'),
                    'hostuser_name' => $Host->get('name'),
                    'user_id' => base64_encode($userName),
                    'user_name' => $userName,
                );
                unset($userName, $Host, $User);
            }
            unset($HostIDs);
        } elseif (!$hostsearch && !$usersearch) {
            $this->redirect(
                sprintf(
                    '?node=%s&sub=usertrack',
                    $this->node
                )
            );
        }
        $this->render();
    }
    /**
     * Actually display more refined info pertinent to dates.
     *
     * @return void
     */
    public function usertrackdisp()
    {
        $this->title = _('FOG User Login History Summary - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $userid = trim(base64_decode($_REQUEST['userID']));
        $hostid = trim($_REQUEST['hostID']);
        if ($userid
            && !$hostid
        ) {
            $UserSearchDates = self::getSubObjectIDs(
                'UserTracking',
                array('username' => $userid),
                'datetime'
            );
        } elseif (!$userid
            && $hostid
        ) {
            $UserSearchDates = self::getSubObjectIDs(
                'UserTracking',
                array('hostID' => $hostid),
                'datetime'
            );
        } elseif ($userid
            && $hostid
        ) {
            $UserSearchDates = self::getSubObjectIDs(
                'UserTracking',
                array(
                    'username' => $userid,
                    'hostID' => $hostid
                ),
                'datetime'
            );
        }
        foreach ((array)$UserSearchDates as &$DateTime) {
            if (!self::validDate($DateTime)) {
                continue;
            }
            $Dates[] = self::formatTime($DateTime, 'Y-m-d');
            unset($DateTime);
        }
        unset($DateTime);
        if ($Dates) {
            $Dates = array_unique($Dates);
            rsort($Dates);
            ob_start();
            foreach ((array)$Dates as $i => &$Date) {
                printf(
                    '<option value="%s">%s</option>',
                    $Date,
                    $Date
                );
                unset($Date);
            }
            unset($Dates);
            $dates = ob_get_clean();
            $fields = array(
                _('Select Start Date') => sprintf(
                    '<select name="date1" size="1">%s</select>',
                    $dates
                ),
                _('Select End Date') => sprintf(
                    '<select name="date2" size="1">%s</select>',
                    $dates
                ),
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
            }
            unset($input);
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
     * Finally display the chosen data.
     *
     * @return void
     */
    public function usertrackdispPost()
    {
        $this->title = _('FOG User Login History Summary');
        $this->headerData = array(
            _('Action'),
            _('Username'),
            _('Hostname'),
            _('Time'),
            _('Description'),
        );
        $this->templates = array(
            '${action}',
            '${username}',
            '${hostname}',
            '${time}',
            '${desc}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        $this->ReportMaker->addCSVCell(_('Action'));
        $this->ReportMaker->addCSVCell(_('Username'));
        $this->ReportMaker->addCSVCell(_('Hostname'));
        $this->ReportMaker->addCSVCell(_('Host MAC'));
        $this->ReportMaker->addCSVCell(_('Host Desc'));
        $this->ReportMaker->addCSVCell(_('Time'));
        $this->ReportMaker->addCSVCell(_('Description'));
        $this->ReportMaker->endCSVLine();
        printf(
            $this->reportString,
            'UserTrackingList',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'UserTrackingList',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        $date1 = $_REQUEST['date1'];
        $date2 = $_REQUEST['date2'];
        if ($date1 > $date2) {
            $date1 = $_REQUEST['date2'];
            $date2 = $_REQUEST['date1'];
        }
        $date1 = self::niceDate($date1);
        $date2 = self::niceDate('+1 day');
        $userID = trim(base64_decode($_REQUEST['userID']));
        $hostID = trim($_REQUEST['hostID']);
        if (isset($hostID)
            && is_numeric($hostID)
            && $hostID > 0
        ) {
            $Host = new Host($hostID);
            if (!$userID) {
                $Users =& self::getClass('UserTrackingManager')
                    ->find(
                        array('id' => $Host->get('users'))
                    );
            } else {
                $Users =& self::getClass('UserTrackingManager')
                    ->find(
                        array(
                            'id' => $Host->get('users'),
                            'username' => $userID
                        )
                    );
            }
        } else {
            $Host = new Host(0);
            if (!$userID) {
                $Users =& self::getClass('UserTrackingManager')
                    ->find();
            } else {
                $Users =& self::getClass('UserTrackingManager')
                    ->find(
                        array('username' => $userID)
                    );
            }
        }
        foreach ((array)$Users as &$User) {
            if (!$Host->isValid()) {
                $Host = new Host($User->get('hostID'));
            }
            if (!$Host->isValid()) {
                continue;
            }
            $date = self::niceDate($User->get('datetime'));
            if ($date < $date1
                || $date > $date2
            ) {
                continue;
            }
            $logintext = (
                $User->get('action') == 1 ?
                'Login' :
                (
                    $User->get('action') == 0 ?
                    'Logout' :
                    (
                        $User->get('action') == 99 ?
                        'Service Start' :
                        'N/A'
                    )
                )
            );
            $this->data[] = array(
                'action'=>$logintext,
                'username'=>$User->get('username'),
                'hostname'=>$Host->get('name'),
                'time'=> self::formatTime($User->get('datetime'), 'Y-m-d H:i:s'),
                'desc'=>$User->get('description'),
            );
            $this->ReportMaker->addCSVCell($logintext);
            $this->ReportMaker->addCSVCell($User->get('username'));
            $this->ReportMaker->addCSVCell($Host->get('name'));
            $this->ReportMaker->addCSVCell($Host->get('mac'));
            $this->ReportMaker->addCSVCell($Host->get('description'));
            $this->ReportMaker->addCSVCell(
                self::formatTime(
                    $User->get('datetime'),
                    'Y-m-d H:i:s'
                )
            );
            $this->ReportMaker->addCSVCell($User->get('description'));
            $this->ReportMaker->endCSVLine();
            unset($User, $date, $logintext);
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
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
            return $this->render();
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
            _('Snapin Name'),
            _('State'),
            _('Return Code'),
            _('Return Desc'),
            _('Create Date'),
            _('Create Time'),
        );
        $this->templates = array(
            '${snap_name}',
            '${snap_state}',
            '${snap_return}',
            '${snap_detail}',
            '${snap_create}',
            '${snap_time}',
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
                || !self::validDate($end)
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
    /**
     * Present selector for equipment load form printing.
     *
     * @return void
     */
    public function equiploan()
    {
        $this->title = _('FOG Equipment Loan Form');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        ob_start();
        foreach ((array)self::getClass('InventoryManager')
            ->find() as &$Inventory
        ) {
            if (!$Inventory->get('primaryUser')) {
                continue;
            }
            printf(
                '<option value="%s">%s</option>',
                $Inventory->get('id'),
                $Inventory->get('primaryUser')
            );
            unset($Inventory);
        }
        $fields = array(
            _('Select User') => sprintf(
                '<select name="user" size="1">'
                . '<option value="">- %s -</option>'
                . '%s'
                . '</select>',
                _('Please select an option'),
                ob_get_clean()
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Create Report')
            )
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input);
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Present our form based on the selected data.
     *
     * @return void
     */
    public function equiploanPost()
    {
        $Inventory = new Inventory($_REQUEST['user']);
        if (!$Inventory->isValid()) {
            return;
        }
        $this->title = _('FOG Equipment Loan Form');
        printf(
            '<h2>'
            . '<div id="exportDiv"></div>'
            . '<a id="pdfsub" href="export.php?type='
            . 'pdf&filename=%sEquipmentLoanForm" alt='
            . '"%s" title="%s" target="_blank">%s</a></h2>',
            $Inventory->get('primaryUser'),
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
        list(
            $coname,
            $subname,
            $tos
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_COMPANY_NAME',
                    'FOG_COMPANY_SUBNAME',
                    'FOG_COMPANY_TOS'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $this->ReportMaker->appendHTML(
            sprintf(
                '<!-- FOOTER CENTER "$PAGE %s $PAGES - %s: %s" -->'
                . '<p class="c"><h3>%s</h3></p><hr/><p class="c">'
                . '<h2>%s</h2></p><p class="c"><h3>%s</h3></p>'
                . '<p class="c"><h2><u>%s</u></h2></p>'
                . '<p class="c"><h4><u>%s</u></h4></p>'
                . '<h4><b>%s: </b><u>%s</u></h4><h4>'
                . '<b>%s: </b><u>%s</u></h4><h4>'
                . '<b>%s: </b>%s</h4><h4><b>%s: </b>'
                . '%s</h4><h4><b>%s: </b>%s</h4><h4>'
                . '<b>%s: </b>%s</h4><p class="c">'
                . '<h4><u>%s</u></h4></p><h4><b>%s: </b>'
                . '<u>%s</u></h4><h4><b>%s: </b><u>%s</u>'
                . '</h4><h4><b>%s: </b><u>%s</u></h4>'
                . '<p class="c"><h4><b>%s / %s / %s</b></h4></p>'
                . '<p class="c"><h4><b>%s</b></h4></p>'
                . '<p class="c"><h4><b>%s</b></h4></p>'
                . '<p class="c"><h4><b>%s</b></h4></p>'
                . '<br/><hr/><h4><b>%s: </b>%s</h4>'
                . '<p class="c"><h4>(%s %s)</h4></p>'
                . '<p class="c"><h4>%s</h4></p><h4><b>%s: </b>%s</h4>'
                . '<h4><b>%s: </b>%s</h4>'
                . '<!-- NEW PAGE -->'
                . '<!-- FOOTER CENTER "$PAGE %s $PAGES - %s: %s" -->'
                . '<p class="c"><h3>%s</h3></p><hr/><h4>%s</h4>'
                . '<h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4>',
                _('of'),
                _('Printed'),
                self::formatTime('', 'D M j G:i:s T Y'),
                _('Equipment Loan'),
                $coname,
                $subname,
                _('PC Check-out Agreement'),
                _('Personal Information'),
                _('Name'),
                $Inventory->get('primaryUser'),
                _('Location'),
                _('Your Location Here'),
                str_pad(_('Home Address'), 25),
                str_repeat('_', 65),
                str_pad(_('City/State/Zip'), 25),
                str_repeat('_', 65),
                str_pad(_('Extension'), 25),
                str_repeat('_', 65),
                str_pad(_('Home Phone'), 25),
                str_repeat('_', 65),
                _('Computer Information'),
                str_pad(
                    sprintf(
                        '%s / %s',
                        _('Serial Number'),
                        _('Service Tag')
                    ),
                    25
                ),
                str_pad(
                    sprintf(
                        '%s / %s',
                        $Inventory->get('sysserial'),
                        $Inventory->get('caseasset')
                    ),
                    65,
                    '_'
                ),
                str_pad(
                    _('Barcode Numbers'),
                    25
                ),
                str_pad(
                    sprintf(
                        '%s %s',
                        $Inventory->get('other1'),
                        $Inventory->get('other2')
                    ),
                    65,
                    '_'
                ),
                str_pad(
                    _('Date of checkout'),
                    25
                ),
                str_repeat('_', 65),
                _('Notes'),
                _('Miscellaneous'),
                _('Included Items'),
                str_repeat('_', 75),
                str_repeat('_', 75),
                str_repeat('_', 75),
                str_pad(_('Releasing Staff Initials'), 25),
                str_repeat('_', 65),
                _('To be released only by'),
                str_repeat('_', 20),
                sprintf(
                    '%s, %s, %s %s %s.',
                    _('I have read'),
                    _('understood'),
                    _('and agree to all the'),
                    _('Terms and Conditions'),
                    _('on the following pages of this document')
                ),
                str_pad(_('Signed'), 25),
                str_repeat('_', 65),
                str_pad(_('Date'), 25),
                str_repeat('_', 65),
                _('of'),
                _('Printed'),
                self::formatTime('', 'D M j G:i:s T Y'),
                _('Terms and Conditions'),
                $tos,
                str_pad(_('Signed'), 25),
                str_repeat('_', 65),
                str_pad(_('Date'), 25),
                str_repeat('_', 65)
            )
        );
        printf('<p>%s</p>', _('Your form is ready.'));
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
