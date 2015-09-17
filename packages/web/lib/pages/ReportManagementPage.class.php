<?php
class ReportManagementPage extends FOGPage {
    public $node = 'report';
    public function __construct() {
        $this->name = 'Report Management';
        parent::__construct($this->name);
        $this->menu = array(
            'home' => $this->foglang[Home],
            'equip-loan' => $this->foglang[EquipLoan],
            'host-list' => $this->foglang[HostList],
            'imaging-log' => $this->foglang[ImageLog],
            'inventory' => $this->foglang[Inventory],
            'pend-mac' => $this->foglang[PendingMACs],
            'snapin-log' => $this->foglang[SnapinLog],
            'user-track' => $this->foglang[LoginHistory],
            'vir-hist' => $this->foglang[VirusHistory],
        );
        $reportlink = "?node={$this->node}&sub=file&f=";
        $dh = opendir($_SESSION[FOG_REPORT_DIR]);
        if ($dh) {
            while (!(($f=readdir($dh)) === false)) {
                if (is_file($_SESSION[FOG_REPORT_DIR].$f) && substr($f,strlen($f) - strlen('.php')) === '.php') $this->menu = array_merge($this->menu, array($reportlink.base64_encode($f) => substr($f,0,strlen($f) - 4)));
            }
        }
        $this->menu = array_merge($this->menu,array(upload=>$this->foglang[UploadRprts]));
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        $this->pdffile = '<i class="fa fa-file-pdf-o fa-2x"></i>';
        $this->csvfile = '<i class="fa fa-file-excel-o fa-2x"></i>';
        $_SESSION[foglastreport] = null;
    }
    /** home()
        Sub home, just redirects to index page.
     */
    public function home() {
        $this->index();
    }
    /** upload()
        Allows you to upload your own reports.
     */
    public function upload() {
        // Title
        $this->title = _('Upload FOG Reports');
        echo '<div class="hostgroup">'._('This section allows you to upload user defined reports that may not be part of the base FOG package.  The report files should end in .php').'</div><p class="titleBottomLeft">'._('Upload a FOG report').'</p><form method="post" action="'.$this->formAction.'" enctype="multipart/form-data"><input type="file" name="report" /><span class="lightColor">Max Size: '.ini_get('post_max_size').'</span><p><input type="submit" value="'._('Upload File').'" /></p></form>';
    }
    // Pages
    /** index()
        First page seen when clicking on the manager.
     */
    public function index() {
        // Set title
        $this->title = _('About FOG Reports');
        echo '<p>'._('FOG reports exist to give you information about what is going on with your FOG system.  To view a report, select an item from the menu on the left-hand side of this page.').'</p>';
    }
    /** file()
        Checks if the file actually exists, from the menu item clicked.
        Exceptions are the default reports which have been written into this
        file as opjects of the report class.
     */
    public function file() {
        $path = rtrim($this->FOGCore->getSetting(FOG_REPORT_DIR), '/') . '/' . basename(base64_decode($this->REQUEST[f]));
        if (!file_exists($path)) $this->fatalError('Report file does not exist! Path: %s', array($path));
        require_once($path);
    }
    /** imaging_log()
        Gives out the dates, if available, from imaging log.
     */
    public function imaging_log() {
        // Set title
        $this->title = _('FOG Imaging Log - Select Date Range');
        // Header Data
        unset($this->headerData);
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // Get the dates to use!
        $AllDates = array_merge($this->DB->query("SELECT DATE_FORMAT(`ilStartTime`,'%Y-%m-%d') start FROM `imagingLog` WHERE DATE_FORMAT(`ilStartTime`,'%Y-%m-%d') != '0000-00-00' GROUP BY start ORDER BY start DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'),$this->DB->query("SELECT DATE_FORMAT(`ilFinishTime`,'%Y-%m-%d') finish FROM `imagingLog` WHERE DATE_FORMAT(`ilFinishTime`,'%Y-%m-%d') != '0000-00-00' GROUP BY finish ORDER BY finish DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'));
        foreach($AllDates AS $i => &$Date)
            $Dates[] = array_shift($Date);
        unset($Date);
        $Dates = array_unique($Dates);
        if ($Dates) {
            foreach($Dates AS $i => &$Date) {
                $dates1 .= '<option value="'.$Date.'">'.$Date.'</option>';
                $dates2 .= '<option value="'.$Date.'">'.$Date.'</option>';
            }
            unset($Date);
            $date1 = '<select name="date1" size="1">'.$dates1.'</select>';
            $date2 = '<select name="date2" size="1">'.$dates2.'</select>';
            $fields = array(
                _('Select Start Date') => $date1,
                _('Select End Date') => $date2,
                '&nbsp;' => '<input type="submit" value="'._('Search for Entries').'" />',
            );
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    field=>$field,
                    input=>$input,
                );
            }
            unset($input);
            echo '<form method="post" action="'.$this->formAction.'">';
            $this->render();
            echo '</form>';
        } else $this->render();
    }
    /** imaging_log_post()
        Prints the data and gives access to download the reports.
     */
    public function imaging_log_post() {
        // Set title
        $this->title = _('FOG Imaging Log');
        // This gets the download links for which type of file you want.
        echo '<h2><a href="export.php?type=csv&filename=ImagingLog" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=ImagingLog" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        // Header Data
        $this->headerData = array(
            _('Engineer'),
            _('Host'),
            _('Start'),
            _('End'),
            _('Duration'),
            _('Image'),
            _('Type'),
            _('Clear'),
        );
        // Templates
        $this->templates = array(
            '${createdBy}',
            '${host_name}',
            '<small>${start_date}<br/>${start_time}</small>',
            '<small>${end_date}<br/>${end_time}</small>',
            '${duration}',
            '${image_name}',
            '${type}',
            '',
        );
        // Setup Report Maker for this class.
        $ReportMaker = new ReportMaker();
        // Set dates and check order is proper
        $date1 = $_REQUEST[date1];
        $date2 = $_REQUEST[date2];
        if ($date1 > $date2) {
            $date1 = $_REQUEST[date2];
            $date2 = $_REQUEST[date1];
        }
        $date2 = $this->nice_date($date2)->modify('+1 day')->format('Y-m-d');
        // This is just for the header in the CSV:
        $csvHead = array(
            _('Engineer'),
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
            _('Download/Upload'),
        );
        foreach((array)$csvHead AS $i => &$csvHeader) $ReportMaker->addCSVCell($csvHeader);
        unset($csvHeader);
        $ReportMaker->endCSVLine();
        $ImagingLogs = $this->getClass(ImagingLogManager)->find(array(start=>'nope',finish=>'nope'),'OR','','',"BETWEEN '$date1' AND '$date2'");
        foreach((array)$ImagingLogs AS $i => &$ImagingLog) {
            $start = $this->nice_date($ImagingLog->get(start));
            $end = $this->nice_date($ImagingLog->get(finish));
            // Find the host if it still exists.
            $Host = current($this->getClass(HostManager)->find(array(id=>$ImagingLog->get(hostID))));
            // Find the task matching the start time and the hostID.
            $Task = current($this->getClass(TaskManager)->find(array(checkInTime=>$ImagingLog->get(start),hostID=>$ImagingLog->get(hostID))));
            // Find the image if it still exists.
            $Image = current($this->getClass(ImageManager)->find(array(name=>$ImagingLog->get(image))));
            // Verify if the dates are valid
            $checkStart = $this->validDate($start);
            $checkEnd = $this->validDate($end);
            // Store the difference
            $diff = $this->diff($start,$end);
            $createdBy = ($Task && $Task->isValid() ? $Task->get(createdBy) : $this->FOGUser->get(name));
            $hostName = ($Host && $Host->isValid() ? $Host->get(name) : '');
            $hostId = ($Host && $Host->isValid() ? $Host->get(id) : $ImagingLog->get(hostID));
            $hostMac = ($Host && $Host->isValid() ? $Host->get(mac) : '');
            $hostDesc = ($Host && $Host->isValid() ? $Host->get(description) : '');
            $imgName = ($Image && $Image->isValid() ? $Image->get(name) : $ImagingLog->get(image));
            $imgPath = ($Image && $Image->isValid() ? $Image->get(path) : '');
            $imgType = ($ImagingLog->get(type) == 'down' ? _('Download') : ($ImagingLog->get(type) == 'up' ? _('Upload') : $ImagingLog->get(type)));
            // For the html report (PDF)
            if ($checkStart && $checkEnd) {
                $this->data[] = array(
                    createdBy=>$createdBy,
                    host_name=>$hostName,
                    start_date=>$start->format('Y-m-d'),
                    start_time=>$start->format('H:i:s'),
                    end_date=>$end->format('Y-m-d'),
                    end_time=>$end->format('H:i:s'),
                    duration=>$diff,
                    image_name=>$ImagingLog->get(image),
                    type=>$imgType,
                );
                // For the CSV
                $ReportMaker->addCSVCell($createdBy);
                $ReportMaker->addCSVCell($hostId);
                $ReportMaker->addCSVCell($hostName);
                $ReportMaker->addCSVCell($hostMac);
                $ReportMaker->addCSVCell($hostDesc);
                $ReportMaker->addCSVCell($imgName);
                $ReportMaker->addCSVCell($imgPath);
                $ReportMaker->addCSVCell($start->format('Y-m-d'));
                $ReportMaker->addCSVCell($start->format('H:i:s'));
                $ReportMaker->addCSVCell($end->format('Y-m-d'));
                $ReportMaker->addCSVCell($end->format('H:i:s'));
                $ReportMaker->addCSVCell($diff);
                $ReportMaker->addCSVCell($imgType);
                $ReportMaker->endCSVLine();
            }
        }
        unset($ImagingLog);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        $ReportMaker->outputReport(false);
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** host_list()
        Display's the host list for both CSV and PDF Reports.
     */
    public function host_list() {
        // Setup Report Maker for this object.
        $ReportMaker = $this->getClass(ReportMaker);
        // Set Title
        $this->title = _('Host Listing Export');
        // This gets the download links for which type of file you want.
        echo '<h2>'.'<a href="export.php?type=csv&filename=HostList" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=HostList" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        // CSV Header row:
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
        foreach((array)$csvHead AS $csvHeader => &$classGet) $ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
        $ReportMaker->endCSVLine();
        // Header Data
        $this->headerData = array(
            _('Hostname'),
            _('Host MAC'),
            _('Image Name'),
        );
        // Templates
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${image_name}',
        );
        // Find hosts
        $Hosts = $this->getClass(HostManager)->find();
        // Store the data
        foreach($Hosts AS $i => &$Host) {
            $Image = $Host->getImage();
            $imgID = $Image->isValid() ? $Image->get(id) : '';
            $imgName = $Image->isValid() ? $Image->get(name) : '';
            $imgDesc = $Image->isValid() ? $Image->get(description) : '';
            $this->data[] = array(
                host_name=>$Host->get(name),
                host_mac=>$Host->get(mac),
                image_name=>$imgName,
            );
            // The below lines create the csv.
            foreach ((array)$csvHead AS $head => &$classGet) {
                if ($head == _('Image ID')) $ReportMaker->addCSVCell($imgID);
                else if ($head == _('Image Name')) $ReportMaker->addCSVCell($imgName);
                else if ($head == _('Image Desc')) $ReportMaker->addCSVCell($imgDesc);
                else if ($head == _('AD Join')) $ReportMaker->addCSVCell(($Host->get(useAD) == 1 ? _('Yes') : _('No')));
                else $ReportMaker->addCSVCell($Host->get($classGet));
            }
            unset($classGet);
            $ReportMaker->endCSVLine();
        }
        unset($Host);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        $ReportMaker->outputReport(false);
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** inventory()
        Returns all VALID inventory stuff.
     */
    public function inventory() {
        // Setup Report Maker for this object.
        $ReportMaker = new ReportMaker();
        // Set Title
        $this->title = _('Full Inventory Export');
        // This gets the download links for which type of file you want.
        echo '<h2>'.'<a href="export.php?type=csv&filename=InventoryReport" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=InventoryReport" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        $csvHead = array(
            _('Host ID')=>'id',
            _('Host name')=>'name',
            _('Host MAC')=>'mac',
            _('Host Desc')=>'description',
            _('Image ID')=>'id',
            _('Image Name')=>'name',
            _('Image Desc')=>'description',
            _('OS Name')=>'name',
            _('Inventory ID')=>'id',
            _('Inventory Desc')=>'description',
            _('Primary User')=>'primaryUser',
            _('Other Tag 1')=>'other1',
            _('Other Tag 2')=>'other2',
            _('System Manufacturer')=>'sysman',
            _('System Product')=>'sysproduct',
            _('System Version')=>'sysversion',
            _('System Serial')=>'sysserial',
            _('System Type')=>'systype',
            _('BIOS Version')=>'biosversion',
            _('BIOS Vendor')=>'biosvendor',
            _('BIOS Date')=>'biosdate',
            _('MB Manufacturer')=>'mbman',
            _('MB Name')=>'mbproductname',
            _('MB Version')=>'mbversion',
            _('MB Serial')=>'mbserial',
            _('MB Asset')=>'mbasset',
            _('CPU Manufacturer')=>'cpuman',
            _('CPU Version')=>'cpuversion',
            _('CPU Speed')=>'cpucurrent',
            _('CPU Max Speed')=>'cpumax',
            _('Memory')=>'mem',
            _('HD Model')=>'hdmodel',
            _('HD Firmware')=>'hdfirmware',
            _('HD Serial')=>'hdserial',
            _('Chassis Manufacturer')=>'caseman',
            _('Chassis Version')=>'casever',
            _('Chassis Serial')=>'caseser',
            _('Chassis Asset')=>'caseasset',
        );
        foreach((array)$csvHead AS $csvHeader => &$classGet) $ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
        $ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('OS name'),
            _('Memory'),
            _('System Product'),
            _('System Serial'),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${os_name}',
            '${memory}',
            '${sysprod}',
            '${sysser}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        // All hosts
        $Hosts = $this->getClass(HostManager)->find('','','','','','name');
        // Loop through each of the hosts.
        foreach($Hosts AS $i => &$Host) {
            if ($Host->isValid()) {
                // Find the image information
                if ($Host->get(imageID)) $Image = $Host->getImage();
                // Find the current inventory for this host
                $Inventory = $Host->get(inventory);
                // If found print data
                if($Inventory) {
                    $this->data[] = array(
                        host_name=>$Host->get(name),
                        host_mac=>$Host->get(mac),
                        os_name=>$Host->getImage()->getOS(),
                        memory=>$Inventory->getMem(),
                        sysprod=>$Inventory->get(sysproduct),
                        sysser=>$Inventory->get(sysserial),
                    );
                    foreach((array)$csvHead AS $head => &$classGet) {
                        if ($head == _('Host ID')) $ReportMaker->addCSVCell($Host->get(id));
                        else if ($head == _('Host name')) $ReportMaker->addCSVCell($Host->get(name));
                        else if ($head == _('Host MAC')) $ReportMaker->addCSVCell($Host->get(mac));
                        else if ($head == _('Host Desc')) $ReportMaker->addCSVCell($Host->get(description));
                        else if ($head == _('Image ID')) $ReportMaker->addCSVCell($Image && $Image->isValid() ? $Image->get(id) : '');
                        else if ($head == _('Image Name')) $ReportMaker->addCSVCell($Image && $Image->isValid() ? $Image->get(name) : '');
                        else if ($head == _('Image Desc')) $ReportMaker->addCSVCell($Image && $Image->isValid() ? $Image->get(description) : '');
                        else if ($head == _('Memory')) $ReportMaker->addCSVCell($Inventory->getMem());
                        else $ReportMaker->addCSVCell($Inventory->get($classGet));
                    }
                    unset($classGet);
                    $ReportMaker->endCSVLine();
                }
            }
        }
        unset($Host);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        $ReportMaker->outputReport(false);
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** pend_mac()
        Pending MAC's report.
     */
    public function pend_mac() {
        // Get all the pending mac hosts.
        $Hosts = $this->getClass(HostManager)->find();
        // Approves All Pending MACs for all hosts.
        if ($_REQUEST[aprvall] == 1) {
            foreach($Hosts AS $i => &$Host) $Host->addPendtoAdd()->save();
            unset($Host);
            $this->FOGCore->setMessage(_('All Pending MACs approved.'));
            $this->FOGCore->redirect('?node=report&sub=pend-mac');
        }
        // Setup Report Maker for this object.
        $ReportMaker = new ReportMaker();
        // Set Title
        $this->title = _('Pending MAC Export');
        // This gets the download links for which type of file you want.
        echo '<h2><a href="export.php?type=csv&filename=PendingMACsList" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=PendingMACsList" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a><br /><a href="?node=report&sub=pend-mac&aprvall=1">'._('Approve All Pending MACs for all hosts?').'</a></h2>';
        // CSV Header
        $csvHead = array(
            _('Host ID'),
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Desc'),
            _('Host Pending MAC'),
        );
        foreach((array)$csvHead AS $csvHeader => &$classGet) $ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
        $ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Pending MAC'),
        );
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${host_pend}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
        );
        foreach((array)$Hosts AS $i => &$Host) {
            foreach($Host->get(pendingMACs) AS $i => &$MAC) {
                if ($MAC && $MAC->isValid()) {
                    $this->data[] = array(
                        host_name=>$Host->get(name),
                        host_mac=>$Host->get(mac),
                        host_pend=>$MAC,
                    );
                    $ReportMaker->addCSVCell($Host->get(id));
                    $ReportMaker->addCSVCell($Host->get(name));
                    $ReportMaker->addCSVCell($Host->get(mac));
                    $ReportMaker->addCSVCell($Host->get(description));
                    $ReportMaker->addCSVCell($MAC);
                    $ReportMaker->endCSVLine();
                }
            }
            unset($MAC);
        }
        unset($Host);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        $ReportMaker->outputReport(false);
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** vir_hist()
        Prints the virus history report.
     */
    public function vir_hist() {
        // Setup Report Maker for this object.
        $ReportMaker = $this->getClass(ReportMaker);
        // Set Title
        $this->title = _('FOG Virus Summary');
        // This gets the download links for which type of file you want.
        echo '<h2>'.'<a href="export.php?type=csv&filename=VirusHistory" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=VirusHistory" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2><form method="post" action="'.$this->formAction.'" /><h2><a href="#"><input onclick="this.form.submit()" type="checkbox" class="delvid" name="delvall" id="delvid" value="all" /><label for="delvid">('._('clear all history').')</label></a></h2></form>';
        // CSV Header
        $csvHead = array(
            _('Host Name')=>'name',
            _('Virus Name')=>'name',
            _('File')=>'file',
            _('Mode')=>'mode',
            _('Date')=>'date',
        );
        $this->headerData = array(
            _('Host name'),
            _('Virus Name'),
            _('File'),
            _('Mode'),
            _('Date'),
            _('Clear'),
        );
        $this->templates = array(
            '${host_name}',
            '<a href="http://www.google.com/search?q=${vir_name}">${vir_name}</a>',
            '${vir_file}',
            '${vir_mode}',
            '${vir_date}',
            '<input type="checkbox" onclick="this.form.submit()" class="delvid" value="${vir_id}" id="vir${vir_id}" name="delvid" /><label for="vir${vir_id}" class="icon icon-hand" title="'._('Delete').' ${vir_name}"><i class="fa fa-minus-circle fa-1x link"></i></label>',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array('class'=>'filter-false'),
        );
        foreach((array)$csvHead AS $csvHeader => &$classGet) $ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
        $ReportMaker->endCSVLine();
        // Find all viruses
        $Viruses = $this->getClass(VirusManager)->find();
        foreach($Viruses AS $i => &$Virus) {
            $Host = $this->getClass(HostManager)->getHostByMacAddresses($Virus->get(hostMAC));
            $this->data[] = array(
                host_name=>$Host && $Host->isValid() ? $Host->get(name) : '',
                vir_id=>$Virus->get(id),
                vir_name=>$Virus->get(name),
                vir_file=>$Virus->get('file'),
                vir_mode=>$Virus->get(mode) == 'q' ? _('Quarantine') : _('Report'),
                vir_date=>$Virus->get('date'),
            );
            foreach((array)$csvHead AS $head => &$classGet) {
                if ($head == _('Host name')) $ReportMaker->addCSVCell($Host ? $Host->get(name) : '');
                else if ($head == _('Mode')) $ReportMaker->addCSVCell($Virus->get(mode) == 'q' ? _('Quarantine') : _('Report'));
                else $ReportMaker->addCSVCell($Virus->get($classGet));
            }
            unset($classGet);
            $ReportMaker->endCSVLine();
        }
        unset($Virus);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        echo '<form method="post" action="'.$this->formAction.'">';
        $ReportMaker->outputReport(false);
        echo '</form>';
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** vir_hist_post()
        Deletes the selected element. All virus' or just the particular one.
     */
    public function vir_hist_post() {
        if ($_REQUEST[delvall] == 'all') {
            $Viruses = $this->getClass(VirusManager)->find();
            foreach($Viruses AS $i => &$Virus) $Virus->destroy();
            unset($Virus);
            $this->FOGCore->setMessage(_("All Virus' cleared"));
            $this->FOGCore->redirect($this->formAction);
        }
        if (is_numeric($_REQUEST[delvid])) {
            $this->getClass(Virus,$_REQUEST[delvid])->destroy();
            $this->FOGCore->setMessage(_('Virus cleared'));
            $this->FOGCore->redirect($this->formAction);
        }
    }
    /** user_track()
        User Login Report.  Can search by Username, Hostname, or filter a specific user to specific
        hostname if there's any matches. Will call other functions.
     */
    public function user_track() {
        // Set Title
        $this->title = _('FOG User Login History Summary - Search');
        // Header data
        unset($this->headerData);
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Fields
        $fields = array(
            _('Enter a username to search for') => '${user_sel}',
            _('Enter a hostname to search for') => '${host_sel}',
            '&nbsp;' => '<input type="submit" value="'._('Search').'" />',
        );
        $UserNames = $this->getClass(UserTrackingManager)->find('','','','','','username','','username');
        $HostNames = $this->getClass(HostManager)->find('','','','','','name','','name');
        asort($UserNames);
        asort($HostNames);
        if($UserNames) {
            $UserNames = array_unique($UserNames);
            foreach($UserNames AS $i => &$Username) {
                if($Username) $userSel .= '<option value="'.$Username.'">'.$Username.'</option>';
            }
            unset($Username);
            $userSelForm = '<select name="usersearch"><option value="">- '._('Please select an option').' -</option>'.$userSel.'</select>';
        }
        if ($HostNames) {
            foreach($HostNames AS $i => &$Hostname)
                $hostSel .= '<option value="'.$Hostname.'">'.$Hostname.'</option>';
            unset($Hostname);
            $hostSelForm = '<select name="hostsearch"><option value="">- '._('Please select an option').' -</option>'.$hostSel.'</option>';
        }
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                field=>$field,
                input=>$input,
                user_sel=>$userSelForm,
                host_sel=>$hostSelForm,
            );
        }
        unset($input);
        echo '<form method="post" action="'.$this->formAction.'">';
        $this->render();
        echo '</form>';
    }
    /** user_track_post()
        Looks up the user/host user&&host matches.
     */
    public function user_track_post() {
        // Set title
        $this->title = _('Results Found for user and/or hostname search');
        // Header Row
        $this->headerData = array(
            _('Host/User name'),
            _('Username'),
        );
        // Templates
        $this->templates = array(
            '<a href="?node='.$this->node.'&sub=user-track-disp&hostID=${host_id}&userID=${user_id}">${hostuser_name}</a>',
            '${user_name}',
        );
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // search setup
        $hostsearch = str_replace('*','%','%'.trim($_REQUEST[hostsearch]).'%');
        $usersearch = str_replace('*','%','%'.trim($_REQUEST[usersearch]).'%');
        if (trim($_REQUEST[hostsearch]) && !trim($_REQUEST[usersearch])) {
            $Hostnames = $this->getClass(HostManager)->find(array(name=>$hostsearch),'','','','','','','name');
            $Hostnames = array_unique($Hostnames);
            asort($Hostnames);
            foreach((array)$Hostnames AS $i => &$Hostname) {
                $Host = current($this->getClass(HostManager)->find(array(name=>$Hostname)));
                if ($Host && $Host->isValid()) {
                    $this->data[] = array(
                        host_id=>$Host->get(id),
                        hostuser_name=>$Host->get(name),
                        user_id=>base64_encode('%'),
                        user_name=>'',
                    );
                }
            }
            unset($Hostname);
        }
        else if (!trim($_REQUEST[hostsearch]) && trim($_REQUEST[usersearch])) {
            $Usernames = $this->getClass(UserTrackingManager)->find(array(username=>$usersearch),'','','','','','','username');
            $HostIDs = $this->getClass(UserTrackingManager)->find(array(username=>$usersearch),'','','','','','','hostID');
            $Usernames = array_unique($Usernames);
            $HostIDs = array_unique($HostIDs);
            asort($Usernames);
            asort($HostIDs);
            foreach((array)$Usernames AS $i => &$Username) {
                $Hosts = $this->getClass(HostManager)->find(array(id=>$HostIDs));
                if($Hosts) {
                    $this->data[] = array(
                        host_id=>0,
                        hostuser_name=>$Username,
                        user_id=>base64_encode($Username),
                        user_name=>'',
                    );
                }
            }
            unset($Username);
        }
        else if (trim($_REQUEST[hostsearch]) && trim($_REQUEST[usersearch])) {
            $Usernames = $this->getClass(UserTrackingManager)->find(array(username=>$usersearch,action=>1),'','','','','','','username');
            $HostIDs = $this->getClass(UserTrackingManager)->find(array(username=>$usersearch,action=>1),'','','','','','','hostID');
            $Usernames = array_unique($Usernames);
            $HostIDs = array_unique($HostIDs);
            asort($Usernames);
            asort($HostIDs);
            $Hosts = $this->getClass(HostManager)->find(array(id=>$HostIDs,name=>$hostsearch));
            foreach((array)$Hosts AS $i => &$Host) {
                $User = current($this->getClass(UserTrackingManager)->find(array(hostID=>$Host->get(id),username=>$Usernames)));
                $this->data[] = array(
                    host_id=>$Host->get(id),
                    hostuser_name=>$Host->get(name),
                    user_id=>base64_encode($User->get(username)),
                    user_name=>$User->get(username),
                );
            }
            unset($Host);
        }
        else if (!$hostsearch && !$usersearch) $this->FOGCore->redirect('?node='.$this->node.'sub=user-track');
        $this->render();
    }
    /** user_track_disp()
        Display's the date range selection for what host/user is found.
     */
    public function user_track_disp() {
        // Set title.
        $this->title = _('FOG User Login History Summary - Select Date Range');
        // Header Data
        unset($this->headerData);
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (base64_decode($_REQUEST[userID]) && !$_REQUEST[hostID]) $UserSearchDates = $this->getClass(UserTrackingManager)->find(array(username=>base64_decode($_REQUEST[userID])));
        if (!base64_decode($_REQUEST[userID]) && $_REQUEST[hostID]) $UserSearchDates = $this->getClass(UserTrackingManager)->find(array(hostID=>$_REQUEST[hostID]));
        if (base64_decode($_REQUEST[userID]) && $_REQUEST[hostID]) $UserSearchDates = $this->getClass(UserTrackingManager)->find(array(username=>base64_decode($_REQUEST[userID]),hostID=>$_REQUEST[hostID]));
        foreach((array)$UserSearchDates AS $i => &$User) $Dates[] = $this->nice_date($User->get(datetime))->format('Y-m-d');
        unset($User);
        if ($Dates) {
            $Dates = array_unique($Dates);
            rsort($Dates);
            foreach((array)$Dates AS $i => &$Date) {
                $dates1 .= '<option value="'.$Date.'">'.$Date.'</option>';
                $dates2 .= '<option value="'.$Date.'">'.$Date.'</option>';
            }
            unset($Date);
            $date1 = '<select name="date1" size="1">'.$dates1.'</select>';
            $date2 = '<select name="date2" size="1">'.$dates2.'</select>';
            $fields = array(
                _('Select Start Date') => $date1,
                _('Select End Date') => $date2,
                '&nbsp;' => '<input type="submit" value="'._('Search for Entries').'" />',
            );
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    field=>$field,
                    input=>$input,
                );
            }
            unset($input);
            echo '<form method="post" action="'.$this->formAction.'">';
            $this->render();
            echo '</form>';
        }
        else $this->render();
    }
    /** user_track_disp_post()
        Display the actual report.
     */
    public function user_track_disp_post() {
        // Setup Report Maker for this object.
        $ReportMaker = $this->getClass(ReportMaker);
        // Set Title
        $this->title = _('FOG User Login History Summary');
        // Header data
        $this->headerData = array(
            _('Action'),
            _('Username'),
            _('Hostname'),
            _('Time'),
            _('Description'),
        );
        // Templates
        $this->templates = array(
            '${action}',
            '${username}',
            '${hostname}',
            '${time}',
            '${desc}',
        );
        // Attributes
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        $ReportMaker->addCSVCell(_('Action'));
        $ReportMaker->addCSVCell(_('Username'));
        $ReportMaker->addCSVCell(_('Hostname'));
        $ReportMaker->addCSVCell(_('Host MAC'));
        $ReportMaker->addCSVCell(_('Host Desc'));
        $ReportMaker->addCSVCell(_('Time'));
        $ReportMaker->addCSVCell(_('Description'));
        $ReportMaker->endCSVLine();
        // This gets the download links for which type of file you want.
        echo '<h2><a href="export.php?type=csv&filename=UserTrackingList" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=UserTrackingList" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        // Set dates and check order is proper
        $date1 = $_REQUEST[date1];
        $date2 = $_REQUEST[date2];
        if ($date1 > $date2) {
            $date1 = $_REQUEST[date2];
            $date2 = $_REQUEST[date1];
        }
        $date2 = date('Y-m-d',strtotime($date2.'+1 day'));
        // Get all the User Trackers Based on info found.
        $UserTracker = $this->getClass(UserTracking);
        $UserToSearch = base64_decode($_REQUEST[userID]);
        $compare = "BETWEEN '$date1' AND '$date2' AND ".$UserTracker->databaseFields[username].(preg_match('#%#',$UserToSearch) ? " LIKE '$UserToSearch'" : "='$UserToSearch'");
        if ($_REQUEST[hostID])
            $compare .= ' AND '.$UserTracker->databaseFields[hostID]."='".$_REQUEST[hostID]."'";;
        $UserTrackers = $this->getClass(UserTrackingManager)->find(array(datetime => ''),'','','',$compare);
        foreach((array)$UserTrackers AS $i => &$User) {
            $date = $this->nice_date($User->get(datetime));
            $logintext = ($User->get(action) == 1 ? 'Login' : ($User->get(action) == 0 ? 'Logout' : ($User->get(action) == 99 ? 'Service Start' : 'N/A')));
            $Host = current($this->getClass(HostManager)->find(array(id=>$User->get(hostID))));
            $this->data[] = array(
                action=>$logintext,
                username=>$User->get(username),
                hostname=>$Host && $Host->isValid() ? $Host->get(name) : '',
                'time'=>$this->FOGCore->formatTime($User->get(datetime)),
                desc=>$User->get(description),
            );
            $ReportMaker->addCSVCell($logintext);
            $ReportMaker->addCSVCell($User->get(username));
            $ReportMaker->addCSVCell($Host && $Host->isValid() ? $Host->get(name) : '');
            $ReportMaker->addCSVCell($Host && $Host->isValid() ? $Host->get(mac) : '');
            $ReportMaker->addCSVCell($Host && $Host->isValid() ? $Host->get(description) : '');
            $ReportMaker->addCSVCell($this->FOGCore->formatTime($User->get(datetime)));
            $ReportMaker->addCSVCell($User->get(description));
            $ReportMaker->endCSVLine();
        }
        unset($User);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        $ReportMaker->outputReport(false);
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** snapin_log()
        Returns all snapins deployed between specified dates.
     */
    public function snapin_log() {
        // Set title
        $this->title = _('FOG Snapin Log - Select Date Range');
        // Header Data
        unset($this->headerData);
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // Get the dates to use!
        $SnapinLogs = $this->getClass(SnapinTaskManager)->find();
        foreach ($SnapinLogs AS $i => &$SnapinLog) {
            $datesold[] = $this->nice_date($SnapinLog->get(checkin))->format('Y-m-d');
            $datesnew[] = $this->nice_date($SnapinLog->get(complete))->format('Y-m-d');
        }
        unset($SnapinLog);
        $Dates = array_merge($datesold,$datesnew);
        if ($Dates) {
            $Dates = array_unique($Dates);
            rsort($Dates);
            foreach((array)$Dates AS $i => &$Date) {
                $dates1 .= '<option value="'.$Date.'">'.$Date.'</option>';
                $dates2 .= '<option value="'.$Date.'">'.$Date.'</option>';
            }
            unset($Date);
            if(($dates1 || $dates2) && ($dates1 && $dates2)) {
                $date1 = '<select name="date1" size="1">'.$dates1.'</select>';
                $date2 = '<select name="date2" size="1">'.$dates2.'</select>';
                $fields = array(
                    _('Select Start Date') => $date1,
                    _('Select End Date') => $date2,
                    '&nbsp;' => '<input type="submit" value="'._('Search for Entries').'" />',
                );
                foreach((array)$fields AS $field => &$input) {
                    $this->data[] = array(
                        field=>$field,
                        input=>$input,
                    );
                }
                unset($input);
                echo '<form method="post" action="'.$this->formAction.'">';
                $this->render();
                echo '</form>';
            }
            else
                $this->render();
        }
        else
            $this->render();
    }
    /** snapin_log_post()
        Display's the dates to filter through.
     */
    public function snapin_log_post() {
        // Set title
        $this->title = _('FOG Snapin Log');
        // This gets the download links for which type of file you want.
        echo '<h2><a href="export.php?type=csv&filename=SnapinLog" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=SnapinLog" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        // Header Data
        $this->headerData = array(
            _('Snapin Name'),
            _('State'),
            _('Return Code'),
            _('Return Desc'),
            _('Create Date'),
            _('Create Time'),
        );
        // Templates
        $this->templates = array(
            '${snap_name}',
            '${snap_state}',
            '${snap_return}',
            '${snap_detail}',
            '${snap_create}',
            '${snap_time}',
        );
        // Setup Report Maker for this class.
        $ReportMaker = new ReportMaker();
        // Set dates and check order is proper
        $date1 = $_REQUEST[date1];
        $date2 = $_REQUEST[date2];
        if ($date1 > $date2) {
            $date1 = $_REQUEST[date2];
            $date2 = $_REQUEST[date1];
        }
        $date2 = date('Y-m-d',strtotime($date2.'+1 day'));
        // This is just for the header in the CSV:
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
        foreach((array)$csvHead AS $i => &$csvHeader) $ReportMaker->addCSVCell($csvHeader);
        unset($csvHeader);
        $ReportMaker->endCSVLine();
        // Find all snapin tasks
        $SnapinTasks = $this->getClass(SnapinTaskManager)->find(array(checkin=>'',complete=>''),'OR','','',"BETWEEN '$date1' AND '$date2'");
        foreach($SnapinTasks AS $i => &$SnapinTask) {
            $SnapinCheckin1 = $this->nice_date($SnapinTask->get(checkin));
            $SnapinCheckin2 = $this->nice_date($SnapinTask->get(complete));
            // Get the Task based on create date thru complete date
            // Get the snapin
            $Snapin = $SnapinTask->getSnapin();
            // Get the Job
            $SnapinJob = $SnapinTask->getSnapinJob();
            // Get the Host
            $Host = $SnapinJob->getHost();
            $hostID = $Host->get(id);
            $hostName = $Host->isValid() ? $Host->get(name) : '';
            $hostMac = $Host->isValid() ? $Host->get(mac) : '';
            $snapinID = $Snapin->get(id);
            $snapinName = $Snapin->isValid() ? $Snapin->get(name) : '';
            $snapinDesc = $Snapin->isValid() ? $Snapin->get(description) : '';
            $snapinFile = $Snapin->isValid() ? $Snapin->get(file) : '';
            $snapinArgs = $Snapin->isValid() ? $Snapin->get(args) : '';
            $snapinRw = $Snapin->isValid() ? $Snapin->get(runWith) : '';
            $snapinRwa = $Snapin->isValid() ? $Snapin->get(runWithArgs) : '';
            $snapinState = $SnapinTask->get(stateID);
            $snapinReturn = $SnapinTask->get('return');
            $snapinDetail = $SnapinTask->get(detail);
            $snapinCreateDate = $Snapin->isValid() ? $this->formatTime($Snapin->get(createdTime),'Y-m-d') : '';
            $snapinCreateTime = $Snapin->isValid() ? $this->formatTime($Snapin->get(createdTime),'H:i:s') : '';
            $jobCreateDate = $this->formatTime($SnapinJob->get(createdTime),'Y-m-d');
            $jobCreateTime = $this->formatTime($SnapinJob->get(createdTime),'H:i:s');
            $TaskCheckinDate = $SnapinCheckin1->format('Y-m-d');
            $TaskCheckinTime = $SnapinCheckin2->format('H:i:s');
            $this->data[] = array(
                snap_name=>$snapinName,
                snap_state=>$snapinState,
                snap_return=>$snapinReturn,
                snap_detail=>$snapinDetail,
                snap_create=>$snapinCreateDate,
                snap_time=>$snapinCreateTime,
            );
            $ReportMaker->addCSVCell($hostID);
            $ReportMaker->addCSVCell($hostName);
            $ReportMaker->addCSVCell($HostMac);
            $ReportMaker->addCSVCell($snapinID);
            $ReportMaker->addCSVCell($snapinName);
            $ReportMaker->addCSVCell($snapinDesc);
            $ReportMaker->addCSVCell($snapinFile);
            $ReportMaker->addCSVCell($snapinArgs);
            $ReportMaker->addCSVCell($snapinRw);
            $ReportMaker->addCSVCell($snapinRwa);
            $ReportMaker->addCSVCell($snapinState);
            $ReportMaker->addCSVCell($snapinReturn);
            $ReportMaker->addCSVCell($snapinDetail);
            $ReportMaker->addCSVCell($snapinCreateDate);
            $ReportMaker->addCSVCell($snapinCreateTime);
            $ReportMaker->addCSVCell($jobCreateDate);
            $ReportMaker->addCSVCell($jobCreateTime);
            $ReportMaker->addCSVCell($TaskCheckinDate);
            $ReportMaker->addCSVCell($TaskCheckinTime);
            $ReportMaker->endCSVLine();
        }
        unset($SnapinTask);
        // This is for the pdf.
        $ReportMaker->appendHTML($this->process());
        $ReportMaker->outputReport(false);
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
    /** equip_loan()
        Equipment Loan stuff.
     */
    public function equip_loan() {
        // Set title
        $this->title = _('FOG Equipment Loan Form');
        // Header data
        unset($this->headerData);
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Select User') => '${users}',
            '&nbsp;' => '<input type="submit" value="'._('Create Report').'" />',
        );
        // Get data (other users) from inventory.
        $InventoryUsers = $this->getClass(InventoryManager)->find();
        // Create the select field.
        foreach($InventoryUsers AS $i => &$Inventory) {
            if ($Inventory->get(primaryUser))
                $useropt .= '<option value="'.$Inventory->get(id).'">'.$Inventory->get(primaryUser).'</option>';
        }
        unset($Inventory);
        if ($useropt) {
            $selForm = '<select name="user" size= "1">'.$useropt.'</select>';
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    field=>$field,
                    input=>$input,
                    users=>$selForm,
                );
            }
            unset($input);
            echo '<form method="post" action="'.$this->formAction.'">';
            $this->render();
            echo '</form>';
        }
        else $this->render();
    }
    /** equip_loan_post()
        Display the form for printing/pdf output.
     */
    public function equip_loan_post() {
        $Inventory = $this->getClass(Inventory,$_REQUEST[user]);
        // Set title
        $this->title = _('FOG Equipment Loan Form');
        // This gets the download links for which type of file you want.
        echo '<h2><a href="export.php?type=pdf&filename='.$Inventory->get(primaryuser).'EquipmentLoanForm" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        // Report Maker
        $ReportMaker = $this->getClass(ReportMaker);
        // Get the current Inventory based on what was selected.
        // Title Information
        $ReportMaker->appendHTML("<!-- "._("FOOTER CENTER")." \"" . '$PAGE' . " "._("of")." " . '$PAGES' . " - "._("Printed").": " . $this->nice_date()->format("D M j G:i:s T Y") . "\" -->" );
        $ReportMaker->appendHTML("<center><h2>"._("[YOUR ORGANIZATION HERE]")."</h2></center>" );
        $ReportMaker->appendHTML("<center><h3>"._("[sub-unit here]")."</h3></center>" );
        $ReportMaker->appendHTML("<center><h2><u>"._("PC Check-Out Agreement")."</u></h2></center>" );
        // Personal Information
        $ReportMaker->appendHTML("<h4><u>"._("Personal Information")."</u></h4>");
        $ReportMaker->appendHTML("<h4><b>"._("Name").": </b><u>".$Inventory->get('primaryUser')."</u></h4>");
        $ReportMaker->appendHTML("<h4><b>"._("Location").": </b><u>"._("Your Location Here")."</u></h4>");
        $ReportMaker->appendHTML("<h4><b>"._("Home Address").": </b>__________________________________________________________________</h4>");
        $ReportMaker->appendHTML("<h4><b>"._("City / State / Zip").": </b>__________________________________________________________________</h4>");
        $ReportMaker->appendHTML("<h4><b>"._("Extension").":</b>_________________ &nbsp;&nbsp;&nbsp;<b>"._("Home Phone").":</b> (__________)_____________________________</h4>" );
        // Computer Information
        $ReportMaker->appendHTML( "<h4><u>"._("Computer Information")."</u></h4>" );
        $ReportMaker->appendHTML( "<h4><b>"._("Serial Number / Service Tag").": </b><u>" . $Inventory->get('sysserial')." / ".$Inventory->get('caseasset')."_____________________</u></h4>" );
        $ReportMaker->appendHTML( "<h4><b>"._("Barcode Numbers").": </b><u>" . $Inventory->get('other1') . "   " . $Inventory->get('other2') . "</u>________________________</h4>" );
        $ReportMaker->appendHTML( "<h4><b>"._("Date of Checkout").": </b>____________________________________________</h4>" );
        $ReportMaker->appendHTML( "<h4><b>"._("Notes / Miscellaneous / Included Items").": </b></h4>" );
        $ReportMaker->appendHTML( "<h4><b>_____________________________________________________________________________________________</b></h4>" );
        $ReportMaker->appendHTML( "<h4><b>_____________________________________________________________________________________________</b></h4>" );
        $ReportMaker->appendHTML( "<h4><b>_____________________________________________________________________________________________</b></h4>" );
        $ReportMaker->appendHTML( "<hr />" );
        $ReportMaker->appendHTML( "<h4><b>"._("Releasing Staff Initials").": </b>_____________________     "._("(To be released only by XXXXXXXXX)")."</h4>" );
        $ReportMaker->appendHTML( "<h4>"._("I have read, understood, and agree to all the Terms and Condidtions on the following pages of this document.")."</h4>" );
        $ReportMaker->appendHTML( "<br />" );
        $ReportMaker->appendHTML( "<h4><b>"._("Signed").": </b>X _____________________________  "._("Date").": _________/_________/20_______</h4>" );
        $ReportMaker->appendHTML( _("<!-- "._("NEW PAGE")." -->") );
        $ReportMaker->appendHTML( "<!-- "._("FOOTER CENTER")." \"" . '$PAGE' . " "._("of")." " . '$PAGES' . " - "._("Printed").": " .$this->nice_date()->format("D M j G:i:s T Y") . "\" -->" );
        $ReportMaker->appendHTML( "<center><h3>"._("Terms and Conditions")."</h3></center>" );
        $ReportMaker->appendHTML( "<hr />" );
        $ReportMaker->appendHTML( "<h4>"._("Your terms and conditions here")."</h4>" );
        $ReportMaker->appendHTML( "<h4><b>"._("Signed").": </b>"._("X")." _____________________________  "._("Date").": _________/_________/20_______</h4>" );
        echo '<p>'._('Your form is ready.').'</p>';
        $_SESSION[foglastreport] = serialize($ReportMaker);
    }
}
