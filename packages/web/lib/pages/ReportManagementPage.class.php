<?php
class ReportManagementPage extends FOGPage {
    public $node = 'report';
    public function __construct() {
        $this->name = 'Report Management';
        parent::__construct($this->name);
        $this->menu = array(
            'home' => $this->foglang['Home'],
            'equip-loan' => $this->foglang['EquipLoan'],
            'host-list' => $this->foglang['HostList'],
            'imaging-log' => $this->foglang['ImageLog'],
            'inventory' => $this->foglang['Inventory'],
            'pend-mac' => $this->foglang['PendingMACs'],
            'snapin-log' => $this->foglang['SnapinLog'],
            'user-track' => $this->foglang['LoginHistory'],
            'vir-hist' => $this->foglang['VirusHistory'],
        );
        $reportlink = "?node={$this->node}&sub=file&f=";
        $dh = opendir($_SESSION['FOG_REPORT_DIR']);
        if ($dh) {
            while (!(($f=readdir($dh)) === false)) {
                if (is_file($_SESSION['FOG_REPORT_DIR'].$f) && substr($f,strlen($f) - strlen('.php')) === '.php') $this->menu = array_merge($this->menu, array($reportlink.base64_encode($f) => substr($f,0,strlen($f) - 4)));
            }
        }
        $this->menu = array_merge($this->menu,array('upload'=>$this->foglang['UploadRprts']));
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->pdffile = '<i class="fa fa-file-pdf-o fa-2x"></i>';
        $this->csvfile = '<i class="fa fa-file-excel-o fa-2x"></i>';
        $_SESSION['foglastreport'] = null;
        $this->ReportMaker = $this->getClass('ReportMaker');
    }
    public function home() {
        $this->index();
    }
    public function upload() {
        // Title
        $this->title = _('Upload FOG Reports');
        echo '<div class="hostgroup">'._('This section allows you to upload user defined reports that may not be part of the base FOG package.  The report files should end in .php').'</div><p class="titleBottomLeft">'._('Upload a FOG report').'</p><form method="post" action="'.$this->formAction.'" enctype="multipart/form-data"><input type="file" name="report" /><span class="lightColor">Max Size: '.ini_get('post_max_size').'</span><p><input type="submit" value="'._('Upload File').'" /></p></form>';
    }
    public function index() {
        $this->title = _('About FOG Reports');
        echo '<p>'._('FOG reports exist to give you information about what is going on with your FOG system.  To view a report, select an item from the menu on the left-hand side of this page.').'</p>';
    }
    public function file() {
        $path = rtrim($this->getSetting('FOG_REPORT_DIR'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename(base64_decode($_REQUEST['f']));
        if (!file_exists($path)) $this->fatalError('Report file does not exist! Path: %s', array($path));
        require_once($path);
    }
    public function imaging_log() {
        $this->title = _('FOG Imaging Log - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $AllDates = array_merge($this->DB->query("SELECT DATE_FORMAT(`ilStartTime`,'%Y-%m-%d') start FROM `imagingLog` WHERE DATE_FORMAT(`ilStartTime`,'%Y-%m-%d') != '0000-00-00' GROUP BY start ORDER BY start DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'),$this->DB->query("SELECT DATE_FORMAT(`ilFinishTime`,'%Y-%m-%d') finish FROM `imagingLog` WHERE DATE_FORMAT(`ilFinishTime`,'%Y-%m-%d') != '0000-00-00' GROUP BY finish ORDER BY finish DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'));
        foreach($AllDates AS $i => &$Date) {
            $tmp = array_shift($Date);
            if (!$this->validDate($tmp)) {
                unset($tmp);
                continue;
            }
            $Dates[] = $tmp;
            unset($tmp);
        }
        unset($Date);
        $Dates = array_unique($Dates);
        if ($Dates) {
            foreach($Dates AS $i => &$Date) {
                $dates1 .= '<option value="'.$Date.'">'.$Date.'</option>';
                $dates2 = $dates1;
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
                    'field'=>$field,
                    'input'=>$input,
                );
            }
            unset($input);
            echo '<form method="post" action="'.$this->formAction.'">';
            $this->render();
            echo '</form>';
        } else $this->render();
    }
    public function imaging_log_post() {
        $this->title = _('FOG Imaging Log');
        echo '<h2><a href="export.php?type=csv&filename=ImagingLog" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=ImagingLog" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
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
        $date1 = $_REQUEST['date1'];
        $date2 = $_REQUEST['date2'];
        if ($date1 > $date2) {
            $date1 = $_REQUEST['date2'];
            $date2 = $_REQUEST['date1'];
        }
        $date2 = $this->nice_date($date2)->modify('+1 day')->format('Y-m-d');
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
        $imgTypes = array(
            'up' => _('Upload'),
            'down' => _('Download'),
        );
        foreach((array)$csvHead AS $i => &$csvHeader) $this->ReportMaker->addCSVCell($csvHeader);
        unset($csvHeader);
        $this->ReportMaker->endCSVLine();
        $ImagingLogs = $this->getClass('ImagingLogManager')->find(array('start'=>null,'finish'=>null),'OR','',''," BETWEEN '$date1' AND '$date2'",'','','',false);
        foreach((array)$ImagingLogs AS $i => &$ImagingLog) {
            if (!$ImagingLog->isValid()) continue;
            $start = $this->nice_date($ImagingLog->get('start'));
            $end = $this->nice_date($ImagingLog->get('finish'));
            if (!$this->validDate($start) || !$this->validDate($end)) {
                unset($ImagingLog,$start,$end);
                continue;
            }
            $diff = $this->diff($start,$end);
            $Host = $this->getClass('Host',$ImagingLog->get('hostID'));
            if (!$Host->isValid()) {
                unset($ImagingLog,$Host);
                continue;
            }
            $hostName = $Host->get('name');
            $hostId = $Host->get('id');
            $hostMac = $Host->get('mac');
            $hostDesc = $Host->get('description');
            unset($Host);
            $Task = $this->getClass('Task',@max($this->getSubObjectIDs('Task',array('checkInTime'=>$ImagingLog->get('start'),'hostID'=>$ImagingLog->get('hostID')))));
            $createdBy = ($Task->isValid() ? $Task->get('createdBy') : $_SESSION['FOG_USERNAME']);
            unset($Task);
            $Image= $this->getClass('Image',@max($this->getSubObjectIDs('Image',array('name'=>$ImagingLog->get('image')))));
            $imgName = $Image->get('name');
            $imgPath = $Image->get('path');
            unset($Image);
            $imgType = $imgTypes[$ImagingLog->get('type')];
            if (!$imgType) $imgType = $ImagingLog->get('type');
            unset($ImagingLog);
            $this->data[] = array(
                'createdBy'=>$createdBy,
                'host_name'=>$hostName,
                'start_date'=>$start->format('Y-m-d'),
                'start_time'=>$start->format('H:i:s'),
                'end_date'=>$end->format('Y-m-d'),
                'end_time'=>$end->format('H:i:s'),
                'duration'=>$diff,
                'image_name'=>$imgName,
                'type'=>$imgType,
            );
            $this->ReportMaker->addCSVCell($createdBy);
            $this->ReportMaker->addCSVCell($hostId);
            $this->ReportMaker->addCSVCell($hostName);
            $this->ReportMaker->addCSVCell($hostMac);
            $this->ReportMaker->addCSVCell($hostDesc);
            $this->ReportMaker->addCSVCell($imgName);
            $this->ReportMaker->addCSVCell($imgPath);
            $this->ReportMaker->addCSVCell($start->format('Y-m-d'));
            $this->ReportMaker->addCSVCell($start->format('H:i:s'));
            $this->ReportMaker->addCSVCell($end->format('Y-m-d'));
            $this->ReportMaker->addCSVCell($end->format('H:i:s'));
            $this->ReportMaker->addCSVCell($diff);
            $this->ReportMaker->addCSVCell($imgType);
            $this->ReportMaker->endCSVLine();
        }
        unset($ImagingLogIDs,$id);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(0);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function host_list() {
        $this->title = _('Host Listing Export');
        echo '<h2>'.'<a href="export.php?type=csv&filename=HostList" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=HostList" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
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
        foreach((array)$csvHead AS $csvHeader => &$classGet) $this->ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
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
        $Hosts = $this->getClass('HostManager')->find();
        foreach($Hosts AS $i => &$Host) {
            if (!$Host->isValid()) continue;
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
            foreach ((array)$csvHead AS $head => &$classGet) {
                if ($head == _('Image ID')) $this->ReportMaker->addCSVCell($imgID);
                else if ($head == _('Image Name')) $this->ReportMaker->addCSVCell($imgName);
                else if ($head == _('Image Desc')) $this->ReportMaker->addCSVCell($imgDesc);
                else if ($head == _('AD Join')) $this->ReportMaker->addCSVCell(($Host->get(useAD) == 1 ? _('Yes') : _('No')));
                else $this->ReportMaker->addCSVCell($Host->get($classGet));
            }
            unset($Host,$classGet);
            $this->ReportMaker->endCSVLine();
        }
        unset($id);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function inventory() {
        $this->title = _('Full Inventory Export');
        echo '<h2>'.'<a href="export.php?type=csv&filename=InventoryReport" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=InventoryReport" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        $csvHead = array(
            _('Host ID')=>'id',
            _('Host name')=>'name',
            _('Host MAC')=>'mac',
            _('Host Desc')=>'description',
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
        foreach((array)$csvHead AS $csvHeader => &$classGet) $this->ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
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
        $Hosts = $this->getClass('HostManager')->find();
        foreach($Hosts AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            if (!$Host->get('inventory')->isValid()) continue;
            $Image = $Host->getImage();
            $this->data[] = array(
                'host_name'=>$Host->get('name'),
                'host_mac'=>$Host->get('mac'),
                'memory'=>$Host->get('inventory')->getMem(),
                'sysprod'=>$Host->get('inventory')->get('sysproduct'),
                'sysser'=>$Host->get('inventory')->get('sysserial'),
            );
            foreach((array)$csvHead AS $head => &$classGet) {
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
                    $this->ReportMaker->addCSVCell($Host->get('inventory')->getMem());
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->get('inventory')->get($classGet));
                    break;
                }
            }
            unset($classGet);
            $this->ReportMaker->endCSVLine();
        }
        unset($id,$HostIDs);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function pend_mac() {
        if ($_REQUEST['aprvall'] == 1) {
            $this->getClass('MACAddressAssociationManager')->update('','',array('pending'=>0));;
            $this->setMessage(_('All Pending MACs approved.'));
            $this->redirect('?node=report&sub=pend-mac');
        }
        $this->title = _('Pending MAC Export');
        echo '<h2><a href="export.php?type=csv&filename=PendingMACsList" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=PendingMACsList" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a><br />';
        if ($_SESSION['Pending-MACs']) {
            echo '<a href="?node=report&sub=pend-mac&aprvall=1">'._('Approve All Pending MACs for all hosts?').'</a>';
        }
        echo '</h2>';
        $csvHead = array(
            _('Host ID'),
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Desc'),
            _('Host Pending MAC'),
        );
        foreach((array)$csvHead AS $csvHeader => &$classGet) $this->ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
        $this->ReportMaker->endCSVLine();
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
        $PendingMACs = $this->getClass('MACAddressAssociationManager')->find(array('pending'=>1));
        foreach ((array)$this->getSubObjectIDs('MACAddressAssociation',array('pending'=>1),'mac') AS $i => &$PendingMAC) {
            $PendingMAC = $this->getClass('MACAddress',$PendingMAC);
            if (!$PendingMAC->isValid()) continue;
            $Host = $PendingMAC->getHost();
            if (!$Host->isValid()) continue;
            $hostID = $Host->get('id');
            $hostName = $Host->get('name');
            $hostMac = $Host->get('mac');
            $hostDesc = $Host->get('description');
            $hostPend = $this->getClass('MACAddress',$PendingMAC)->__toString();
            unset($Host,$PendingMAC);
            $this->data[] = array(
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
            unset($hostID,$hostName,$hostMac,$hostDesc,$hostPend);
            unset($Host,$PendingMAC);
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function vir_hist() {
        $this->title = _('FOG Virus Summary');
        echo '<h2>'.'<a href="export.php?type=csv&filename=VirusHistory" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=VirusHistory" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2><form method="post" action="'.$this->formAction.'" /><h2><a href="#"><input onclick="this.form.submit()" type="checkbox" class="delvid" name="delvall" id="delvid" value="all" /><label for="delvid">('._('clear all history').')</label></a></h2></form>';
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
        foreach((array)$csvHead AS $csvHeader => &$classGet) $this->ReportMaker->addCSVCell($csvHeader);
        unset($classGet);
        $this->ReportMaker->endCSVLine();
        $Viruses = $this->getClass('VirusManager')->find();
        foreach($Viruses AS $i => &$Virus) {
            if (!$Virus->isValid()) continue;
            $Host = $this->getClass('HostManager')->getHostByMacAddresses($Virus->get('hostMAC'));
            if (!$Host->isValid()) continue;
            $hostName = $Host->get('name');
            unset($Host);
            $virusName = $Virus->get('name');
            $virusFile = $Virus->get('file');
            $virusMode = ($Virus->get('mode') == 'q' ? _('Quarantine') : _('Report'));
            $virusDate = $this->nice_date($Virus->get('date'));
            $this->data[] = array(
                'host_name'=>$hostName,
                'vir_id'=>$id,
                'vir_name'=>$virusName,
                'vir_file'=>$virusFile,
                'vir_mode'=>$virusMode,
                'vir_date'=>$this->formatTime($virusDate),
            );
            foreach((array)$csvHead AS $head => &$classGet) {
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
            }
            unset($classGet,$Virus);
            $this->ReportMaker->endCSVLine();
        }
        unset($Virus);
        $this->ReportMaker->appendHTML($this->__toString());
        echo '<form method="post" action="'.$this->formAction.'">';
        $this->ReportMaker->outputReport(false);
        echo '</form>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function vir_hist_post() {
        if ($_REQUEST['delvall'] == 'all') {
            $this->getClass('VirusManager')->destroy();
            $this->setMessage(_("All Virus' cleared"));
            $this->redirect($this->formAction);
        }
        if (is_numeric($_REQUEST['delvid'])) {
            $this->getClass('Virus',$_REQUEST['delvid'])->destroy();
            $this->setMessage(_('Virus cleared'));
            $this->redirect($this->formAction);
        }
    }
    public function user_track() {
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
        $fields = array(
            _('Enter a username to search for') => '${user_sel}',
            _('Enter a hostname to search for') => '${host_sel}',
            '&nbsp;' => '<input type="submit" value="'._('Search').'" />',
        );
        $UserNames = $this->getSubObjectIDs('UserTracking','','username');
        $HostNames = $this->getSubObjectIDs('Host','','name');
        asort($UserNames);
        asort($HostNames);
        if ($UserNames) {
            $UserNames = array_unique($UserNames);
            foreach($UserNames AS $i => &$Username) {
                if ($Username) $userSel .= '<option value="'.$Username.'">'.$Username.'</option>';
            }
            unset($Username);
            $userSelForm = '<select name="usersearch"><option value="">- '._('Please select an option').' -</option>'.$userSel.'</select>';
        }
        if ($HostNames) {
            foreach($HostNames AS $i => &$Hostname) $hostSel .= '<option value="'.$Hostname.'">'.$Hostname.'</option>';
            unset($Hostname);
            $hostSelForm = '<select name="hostsearch"><option value="">- '._('Please select an option').' -</option>'.$hostSel.'</option>';
        }
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
                'user_sel'=>$userSelForm,
                'host_sel'=>$hostSelForm,
            );
        }
        unset($input);
        echo '<form method="post" action="'.$this->formAction.'">';
        $this->render();
        echo '</form>';
    }
    public function user_track_post() {
        $this->title = _('Results Found for user and/or hostname search');
        $this->headerData = array(
            _('Host/User name'),
            _('Username'),
        );
        $this->templates = array(
            '<a href="?node='.$this->node.'&sub=user-track-disp&hostID=${host_id}&userID=${user_id}">${hostuser_name}</a>',
            '${user_name}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $hostsearch = str_replace('*','%','%'.trim($_REQUEST['hostsearch']).'%');
        $usersearch = str_replace('*','%','%'.trim($_REQUEST['usersearch']).'%');
        if (trim($_REQUEST['hostsearch']) && !trim($_REQUEST['usersearch'])) {
            $Hosts = $this->getClass('HostManager')->find(array('name'=>$hostsearch));
            foreach ((array)$Hosts AS $i => &$Host) {
                if (!$Host->isValid()) continue;
                $hostName = $Host->get('name');
                unset($Host);
                $this->data[] = array(
                    'host_id'=>$id,
                    'hostuser_name'=>$hostName,
                    'user_id'=>base64_encode('%'),
                    'user_name'=>'',
                );
            }
            unset($id,$Hosts);
        } else if (!trim($_REQUEST['hostsearch']) && trim($_REQUEST['usersearch'])) {
            $ids = $this->getSubObjectIDs('UserTracking',array('username'=>$usersearch),array('id','hostID'));
            $lastUser = '';
            $Users = $this->getClass('UserTrackingManager')->find(array('id'=>$ids['id']));
            $Hosts = $this->getClass('HostManager')->find(array('id'=>$ids['hostID']));
            foreach ($Hosts AS $i => &$Host) {
                if (!$Host->isValid()) $ids['hostID'] = array_diff((array)$Host->get('id'),(array)$ids['hostID']);
                unset($Host);
            }
            unset($Hosts);
            foreach((array)$Users AS $i => &$User) {
                if (!$User->isValid()) continue;
                if (!count($ids['hostID'])) continue;
                $Username = trim($User->get('username'));
                unset($User);
                if ($lastUser != $Username) {
                    $this->data[] = array(
                        'host_id'=>0,
                        'hostuser_name'=>$Username,
                        'user_id'=>base64_encode($Username),
                        'user_name'=>'',
                    );
                }
                $lastUser = $Username;
                unset($Username);
            }
            unset($Users,$Hosts,$lastUser);
        } else if (trim($_REQUEST['hostsearch']) && trim($_REQUEST['usersearch'])) {
            $HostIDs = $this->getSubObjectIDs('Host',array('name'=>$hostsearch));
            $Users = $this->getClass('UserTrackingManager')->find(array('username'=>$usersearch,'hostID'=>$HostIDs));
            foreach((array)$Users AS $i => &$User) {
                if (!$User->isValid()) continue;
                $Host = $this->getClass('Host',$User->get('hostID'));
                if (!$Host->isValid()) {
                    unset($Host);
                    continue;
                }
                $hostID = $Host->get('id');
                $hostName = $Host->get('name');
                $userName = $User->get('name');
                unset($Host,$User);
                $this->data[] = array(
                    'host_id'=>$hostID,
                    'hostuser_name'=>$hostName,
                    'user_id'=>base64_encode($userName),
                    'user_name'=>$userName,
                );
                unset($userName,$hostName,$hostID);
            }
            unset($HostIDs,$Users);
        } else if (!$hostsearch && !$usersearch) $this->redirect('?node='.$this->node.'sub=user-track');
        $this->render();
    }
    public function user_track_disp() {
        $this->title = _('FOG User Login History Summary - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $_REQUEST['userID'] = trim(base64_decode($_REQUEST['userID']));
        $_REQUEST['hostID'] = trim($_REQUEST['hostID']);
        if ($_REQUEST['userID'] && !$_REQUEST['hostID']) $UserSearchDates = $this->getSubObjectIDs('UserTracking',array('username'=>$_REQUEST['userID']),'datetime');
        else if (!$_REQUEST['userID'] && $_REQUEST['hostID']) $UserSearchDates = $this->getSubObjectIDs('UserTracking',array('hostID'=>$_REQUEST['hostID']),'datetime');
        else if ($_REQUEST['userID'] && $_REQUEST['hostID']) $UserSearchDates = $this->getSubObjectIDs('UserTracking',array('username'=>$_REQUEST['userID'],'hostID'=>$_REQUEST['hostID']),'datetime');
        foreach((array)$UserSearchDates AS $i => &$DateTime) {
            if (!$this->validDate($DateTime)) continue;
            $Dates[] = $this->formatTime($DateTime,'Y-m-d');
        }
        unset($DateTime);
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
                    'field'=>$field,
                    'input'=>$input,
                );
            }
            unset($input);
            echo '<form method="post" action="'.$this->formAction.'">';
            $this->render();
            echo '</form>';
        }
        else $this->render();
    }
    public function user_track_disp_post() {
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
        echo '<h2><a href="export.php?type=csv&filename=UserTrackingList" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=UserTrackingList" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
        $date1 = $_REQUEST['date1'];
        $date2 = $_REQUEST['date2'];
        if ($date1 > $date2) {
            $date1 = $_REQUEST['date2'];
            $date2 = $_REQUEST['date1'];
        }
        $date2 = date('Y-m-d',strtotime($date2.'+1 day'));
        $UserToSearch = base64_decode($_REQUEST['userID']);
        $compare = "BETWEEN '$date1' AND '$date2'";
        $UserTrackers = $this->getClass('UserTrackingManager')->find(array('datetime' => '','username' => '%'.$UserToSearch.'%','hostID'=>($_REQUEST['hostID'] ? $_REQUEST['hostID'] : '%')),'','','',$compare);
        foreach((array)$UserTrackers AS $i => &$User) {
            if (!$User->isValid()) continue;
            $Host = $this->getClass('Host',$User->get('hostID'));
            if (!$Host->isValid()) {
                unset($Host);
                continue;
            }
            $date = $this->nice_date($User->get('datetime'));
            $logintext = ($User->get('action') == 1 ? 'Login' : ($User->get('action') == 0 ? 'Logout' : ($User->get('action') == 99 ? 'Service Start' : 'N/A')));
            $this->data[] = array(
                'action'=>$logintext,
                'username'=>$User->get('username'),
                'hostname'=>$Host->get('name'),
                'time'=>$this->formatTime($User->get('datetime')),
                'desc'=>$User->get('description'),
            );
            $this->ReportMaker->addCSVCell($logintext);
            $this->ReportMaker->addCSVCell($User->get('username'));
            $this->ReportMaker->addCSVCell($Host->get('name'));
            $this->ReportMaker->addCSVCell($Host->get('mac'));
            $this->ReportMaker->addCSVCell($Host->get('description'));
            $this->ReportMaker->addCSVCell($this->formatTime($User->get('datetime')));
            $this->ReportMaker->addCSVCell($User->get('description'));
            $this->ReportMaker->endCSVLine();
            unset($User,$Host,$date,$logintext);
        }
        unset($UserTrackers);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function snapin_log() {
        $this->title = _('FOG Snapin Log - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $SnapinLogs = $this->getClass('SnapinTaskManager')->find();
        $datesold = array();
        $datesnew = array();
        foreach ($SnapinLogs AS $i => &$SnapinLog) {
            if (!$SnapinLog->isValid()) continue;
            $tmp1 = $SnapinLog->get('checkin');
            $tmp2 = $SnapinLog->get('complete');
            if (!$this->validDate($tmp1) || !$this->validDate($tmp2)) {
                unset($SnapinLog,$tmp1,$tmp2);
                continue;
            }
            $datesold[] = $this->formatTime($tmp1,'Y-m-d');
            $datesnew[] = $this->formatTime($tmp2,'Y-m-d');
            unset($tmp1,$tmp2,$SnapinLog);
        }
        unset($id,$SnapinLogIDs);
        $Dates = array_merge($datesold,$datesnew);
        unset($datesold,$datesnew);
        if ($Dates) {
            $Dates = array_unique($Dates);
            rsort($Dates);
            foreach((array)$Dates AS $i => &$Date) {
                $dates1 .= '<option value="'.$Date.'">'.$Date.'</option>';
                $dates2 .= '<option value="'.$Date.'">'.$Date.'</option>';
            }
            unset($Date,$Dates);
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
                        'field'=>$field,
                        'input'=>$input,
                    );
                }
                unset($input);
                echo '<form method="post" action="'.$this->formAction.'">';
                $this->render();
                echo '</form>';
            } else $this->render();
        } else $this->render();
    }
    public function snapin_log_post() {
        $this->title = _('FOG Snapin Log');
        echo '<h2><a href="export.php?type=csv&filename=SnapinLog" alt="Export CSV" title="Export CSV" target="_blank">'.$this->csvfile.'</a> <a href="export.php?type=pdf&filename=SnapinLog" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
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
        $date2 = date('Y-m-d',strtotime($date2.'+1 day'));
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
        foreach((array)$csvHead AS $i => &$csvHeader) $this->ReportMaker->addCSVCell($csvHeader);
        unset($csvHeader);
        $this->ReportMaker->endCSVLine();
        $SnapinTasks = $this->getClass('SnapinTaskManager')->find(array('checkin'=>'','complete'=>''),'OR','','',"BETWEEN '$date1' AND '$date2'",'','','',false);
        foreach($SnapinTasks AS $i => &$SnapinTask) {
            if (!$SnapinTask->isValid()) continue;
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                unset($Snapin);
                continue;
            }
            $SnapinJob = $SnapinTask->getSnapinJob();
            if (!$SnapinJob->isValid()) {
                unset($Snapin,$SnapinJob);
                continue;
            }
            $Host = $SnapinJob->getHost();
            if (!$Host->isValid()) {
                unset($Host,$Snapin,$SnapinJob);
                continue;
            }
            $TaskCheckinDate = $this->formatTime($SnapinTask->get('checkin'),'Y-m-d');
            $TaskCheckinTime = $this->nice_date($SnapinTask->get('complete'),'H:i:s');
            $hostID = $Host->get('id');
            $hostName = $Host->get('name');
            $hostMac = $Host->get('mac');
            $snapinID = $Snapin->get('id');
            $snapinName = $Snapin->get('name');
            $snapinDesc = $Snapin->get('description');
            $snapinFile = $Snapin->get('file');
            $snapinArgs = $Snapin->get('args');
            $snapinRw = $Snapin->get('runWith');
            $snapinRwa = $Snapin->get('runWithArgs');
            $snapinState = $SnapinTask->get('stateID');
            $snapinReturn = $SnapinTask->get('return');
            $snapinDetail = $SnapinTask->get('detail');
            $snapinCreateDate = $this->formatTime($Snapin->get(createdTime),'Y-m-d');
            $snapinCreateTime = $this->formatTime($Snapin->get(createdTime),'H:i:s');
            $jobCreateDate = $this->formatTime($SnapinJob->get(createdTime),'Y-m-d');
            $jobCreateTime = $this->formatTime($SnapinJob->get(createdTime),'H:i:s');
            $this->data[] = array(
                'snap_name'=>$snapinName,
                'snap_state'=>$snapinState,
                'snap_return'=>$snapinReturn,
                'snap_detail'=>$snapinDetail,
                'snap_create'=>$snapinCreateDate,
                'snap_time'=>$snapinCreateTime,
            );
            $this->ReportMaker->addCSVCell($hostID);
            $this->ReportMaker->addCSVCell($hostName);
            $this->ReportMaker->addCSVCell($hostMac);
            $this->ReportMaker->addCSVCell($snapinID);
            $this->ReportMaker->addCSVCell($snapinName);
            $this->ReportMaker->addCSVCell($snapinDesc);
            $this->ReportMaker->addCSVCell($snapinFile);
            $this->ReportMaker->addCSVCell($snapinArgs);
            $this->ReportMaker->addCSVCell($snapinRw);
            $this->ReportMaker->addCSVCell($snapinRwa);
            $this->ReportMaker->addCSVCell($snapinState);
            $this->ReportMaker->addCSVCell($snapinReturn);
            $this->ReportMaker->addCSVCell($snapinDetail);
            $this->ReportMaker->addCSVCell($snapinCreateDate);
            $this->ReportMaker->addCSVCell($snapinCreateTime);
            $this->ReportMaker->addCSVCell($jobCreateDate);
            $this->ReportMaker->addCSVCell($jobCreateTime);
            $this->ReportMaker->addCSVCell($TaskCheckinDate);
            $this->ReportMaker->addCSVCell($TaskCheckinTime);
            $this->ReportMaker->endCSVLine();
            unset($Host,$Snapin,$SnapinJob,$SnapinTask,$hostID,$hostName,$hostMac);
            unset($snapinID,$snapinName,$snapinDesc,$snapinFile,$snapinArgs,$snapinRw,$snapinRwa,$snapinState,$snapinReturn,$snapinDetail,$snapinCreateDate,$snapinCreateTime,$jobCreateDate,$jobCreateTime,$TaskCheckinDate,$TaskCheckinTime);
        }
        unset($SnapinTasks);
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function equip_loan() {
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
        $fields = array(
            _('Select User') => '${users}',
            '&nbsp;' => '<input type="submit" value="'._('Create Report').'" />',
        );
        $Inventorys = $this->getClass('InventoryManager')->find();
        foreach($Inventorys AS $i => &$Inventory) {
            if (!($Inventory->isValid() && $Inventory->get('primaryUser'))) continue;
            $useropt .= '<option value="'.$Inventory->get('id').'">'.$Inventory->get('primaryUser').'</option>';
            unset($Inventory);
        }
        unset($Inventorys);
        if ($useropt) {
            $selForm = '<select name="user" size= "1">'.$useropt.'</select>';
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                    'users'=>$selForm,
                );
            }
            unset($input);
            echo '<form method="post" action="'.$this->formAction.'">';
            $this->render();
            echo '</form>';
        }
        else $this->render();
    }
    public function equip_loan_post() {
        $Inventory = $this->getClass('Inventory',$_REQUEST['user']);
        if (!$Inventory->isValid()) {
            $this->title = _('FOG Equipment Loan Form');
            echo '<h2><a href="export.php?type=pdf&filename='.$Inventory->get(primaryuser).'EquipmentLoanForm" alt="Export PDF" title="Export PDF" target="_blank">'.$this->pdffile.'</a></h2>';
            $this->ReportMaker->appendHTML("<!-- "._("FOOTER CENTER")." \"" . '$PAGE' . " "._("of")." " . '$PAGES' . " - "._("Printed").": " . $this->nice_date()->format("D M j G:i:s T Y") . "\" -->" );
            $this->ReportMaker->appendHTML("<center><h2>"._("[YOUR ORGANIZATION HERE]")."</h2></center>" );
            $this->ReportMaker->appendHTML("<center><h3>"._("[sub-unit here]")."</h3></center>" );
            $this->ReportMaker->appendHTML("<center><h2><u>"._("PC Check-Out Agreement")."</u></h2></center>" );
            $this->ReportMaker->appendHTML("<h4><u>"._("Personal Information")."</u></h4>");
            $this->ReportMaker->appendHTML("<h4><b>"._("Name").": </b><u>".$Inventory->get('primaryUser')."</u></h4>");
            $this->ReportMaker->appendHTML("<h4><b>"._("Location").": </b><u>"._("Your Location Here")."</u></h4>");
            $this->ReportMaker->appendHTML("<h4><b>"._("Home Address").": </b>__________________________________________________________________</h4>");
            $this->ReportMaker->appendHTML("<h4><b>"._("City / State / Zip").": </b>__________________________________________________________________</h4>");
            $this->ReportMaker->appendHTML("<h4><b>"._("Extension").":</b>_________________ &nbsp;&nbsp;&nbsp;<b>"._("Home Phone").":</b> (__________)_____________________________</h4>" );
            $this->ReportMaker->appendHTML( "<h4><u>"._("Computer Information")."</u></h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Serial Number / Service Tag").": </b><u>" . $Inventory->get('sysserial')." / ".$Inventory->get('caseasset')."_____________________</u></h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Barcode Numbers").": </b><u>" . $Inventory->get('other1') . "   " . $Inventory->get('other2') . "</u>________________________</h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Date of Checkout").": </b>____________________________________________</h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Notes / Miscellaneous / Included Items").": </b></h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>_____________________________________________________________________________________________</b></h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>_____________________________________________________________________________________________</b></h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>_____________________________________________________________________________________________</b></h4>" );
            $this->ReportMaker->appendHTML( "<hr />" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Releasing Staff Initials").": </b>_____________________     "._("(To be released only by XXXXXXXXX)")."</h4>" );
            $this->ReportMaker->appendHTML( "<h4>"._("I have read, understood, and agree to all the Terms and Condidtions on the following pages of this document.")."</h4>" );
            $this->ReportMaker->appendHTML( "<br />" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Signed").": </b>X _____________________________  "._("Date").": _________/_________/20_______</h4>" );
            $this->ReportMaker->appendHTML( _("<!-- "._("NEW PAGE")." -->") );
            $this->ReportMaker->appendHTML( "<!-- "._("FOOTER CENTER")." \"" . '$PAGE' . " "._("of")." " . '$PAGES' . " - "._("Printed").": " .$this->nice_date()->format("D M j G:i:s T Y") . "\" -->" );
            $this->ReportMaker->appendHTML( "<center><h3>"._("Terms and Conditions")."</h3></center>" );
            $this->ReportMaker->appendHTML( "<hr />" );
            $this->ReportMaker->appendHTML( "<h4>"._("Your terms and conditions here")."</h4>" );
            $this->ReportMaker->appendHTML( "<h4><b>"._("Signed").": </b>"._("X")." _____________________________  "._("Date").": _________/_________/20_______</h4>" );
            echo '<p>'._('Your form is ready.').'</p>';
            $_SESSION['foglastreport'] = serialize($this->ReportMaker);
        }
    }
}
