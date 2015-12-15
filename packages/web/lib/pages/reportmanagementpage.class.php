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
        foreach ($this->getClass('DirectoryIterator',$_SESSION['FOG_REPORT_DIR']) AS $fileInfo) {
            if ($fileInfo->isDot()) continue;
            if (!$fileInfo->isFile()) continue;
            if (!$this->endsWith($fileInfo->getFilename(),'.php')) continue;
            $this->menu = array_merge($this->menu,array(sprintf('%s%s',$reportlink,base64_encode($fileInfo->getFilename()))=>substr($fileInfo->getFilename(),0,-strlen('.php'))));
            unset($fileInfo);
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
        $this->title = _('Upload FOG Reports');
        printf('<div class="hostgroup">%s</div><p class="titleBottomLeft">%s</p><form method="post" action="%s" enctype="multipart/form-data"><input type="file" name="report"/><span class="lightColor">%s: %s</span><p><input type="submit" value="%s"/></p></form>',
            _('This section allows you to upload user defined reports that may not be part of the base FOG package. The report files should end in .php'),
            _('Upload a FOG Report'),
            $this->formAction,
            _('Max Size'),
            ini_get('post_max_size'),
            _('Upload File')
        );
    }
    public function index() {
        $this->title = _('About FOG Reports');
        printf('<p>%s</p>',_('FOG Reports exist to give you information about what is going on with your FOG System. To view a report, select an item from the menu on the left-hand side of this page.'));
    }
    public function file() {
        $path = sprintf('%s/%s',trim($this->getSetting('FOG_REPORT_DIR'),'/'),basename(base64_decode($_REQUEST['f'])));
        if (!file_exists($path)) $this->fatalError(sprintf('%s: %s',_('Report file does not exist! Path'),array($path)));
        require($path);
    }
    public function imaging_log() {
        $this->title = _('FOG Imaging Log - Select Date Range');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $AllDates = array_merge($this->DB->query("SELECT DATE_FORMAT(`ilStartTime`,'%Y-%m-%d') start FROM `imagingLog` WHERE DATE_FORMAT(`ilStartTime`,'%Y-%m-%d') != '0000-00-00' GROUP BY start ORDER BY start DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'),$this->DB->query("SELECT DATE_FORMAT(`ilFinishTime`,'%Y-%m-%d') finish FROM `imagingLog` WHERE DATE_FORMAT(`ilFinishTime`,'%Y-%m-%d') != '0000-00-00' GROUP BY finish ORDER BY finish DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'));
        foreach ((array)$AllDates AS $i => &$Date) {
            $tmp = array_shift($Date);
            if (!$this->validDate($tmp)) continue;
            $Dates[] = $tmp;
            unset($Date,$tmp);
        }
        unset($AllDates);
        $Dates = array_unique($Dates);
        rsort($Dates);
        if (count($Dates) > 0) {
            ob_start();
            foreach ((array)$Dates AS $i => &$Date) {
                printf('<option value="%s">%s</option>',$Date,$Date);
                unset($Date);
            }
            unset($Dates);
            $dates = ob_get_clean();
            $date1 = sprintf('<select name="%s" size="1">%s</select>','date1',$dates);
            $date2 = sprintf('<select name="%s" size="1">%s</select>','date2',$dates);
            $fields = array(
                _('Select Start Date') => $date1,
                _('Select End Date') => $date2,
                '&nbsp;' => sprintf('<input type="submit" value="%s"/>',_('Search for Entries')),
            );
            foreach ((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                );
                unset($input);
            }
            unset($fields);
            printf('<form method="post" action="%s">',$this->formAction);
            $this->render();
            echo '</form>';
        } else $this->render();
    }
    public function imaging_log_post() {
        $this->title = _('FOG Imaging Log');
        printf('<h2><a href="export.php?type=csv&filename=ImagingLog" alt="%s" title="%s" target="_blank">%s</a> <a href="export.php?type=pdf&filename=ImagingLog" alt="%s" title="%s" target="_blank">%s</a></h2>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
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
            '<small>${start_date}<br/>${start_time}</small>',
            '<small>${end_date}<br/>${end_time}</small>',
            '${duration}',
            '${image_name}',
            '${type}',
        );
        array_pop($this->attributes);
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
        foreach ((array)$csvHead AS $i => &$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        foreach ((array)$this->getClass('ImagingLogManager')->find(array('start'=>null,'finish'=>null),'OR','',''," BETWEEN '$date1' AND '$date2'",'','','',false) AS $i => &$ImagingLog) {
            if (!$ImagingLog->isValid()) continue;
            $start = $this->nice_date($ImagingLog->get('start'));
            $end = $this->nice_date($ImagingLog->get('finish'));
            if (!$this->validDate($start) || !$this->validDate($end)) continue;
            $diff = $this->diff($start,$end);
            $Host = $this->getClass('Host',$ImagingLog->get('hostID'));
            if (!$Host->isValid()) continue;
            $hostName = $Host->get('name');
            $hostId = $Host->get('id');
            $hostMac = $Host->get('mac');
            $hostDesc = $Host->get('description');
            unset($Host);
            $Task = $this->getClass('Task',@max($this->getSubObjectIDs('Task',array('checkInTime'=>$ImagingLog->get('start'),'hostID'=>$ImagingLog->get('hostID')))));
            $createdBy = ($ImagingLog->get('createdBy') ? $ImagingLog->get('createdBy') : $_SESSION['FOG_USERNAME']);
            unset($Task);
            $Image = $this->getClass('Image',@max($this->getSubObjectIDs('Image',array('name'=>$ImagingLog->get('image')))));
            if ($Image->isValid()) {
                $imgName = $Image->get('name');
                $imgPath = $Image->get('path');
            } else {
                $imgName = $ImagingLog->get('image');
                $imgPath = 'N/A';
            }
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
        printf('<h2><a href="export.php?type=csv&filename=HostList" alt="%s" title="%s" target="_blank">%s</a> <a href="export?type="pdf?filename=HostList" alt="%s" title="%s" target="_blank">%s</a></h2>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
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
        foreach ((array)$csvHead AS $csvHeader => &$classGet) {
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
        foreach ((array)$this->getClass('HostManager')->find() AS $i => &$Host) {
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
                    $this->ReportMaker->addCSVCell(($Host->get('useAD') == 1 ? _('Yes') : _('No')));
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
    public function inventory() {
        $this->title = _('Full Inventory Export');
        printf('<h2><a href="export.php?type=csv&filename=InventoryReport" alt="%s" title="%s" target="_blank">%s</a> <a href="export?type="pdf?filename=InventoryReport" alt="%s" title="%s" target="_blank">%s</a></h2>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
        );
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
        foreach ((array)$csvHead AS $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
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
        foreach ((array)$this->getClass('HostManager')->find() AS $i => &$Host) {
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
            foreach ((array)$csvHead AS $head => &$classGet) {
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
        printf('<h2><a href="export.php?type=csv&filename=PendingMACsList" alt="%s" title="%s" target="_blank">%s</a> <a href="export?type="pdf?filename=PendingMACsList" alt="%s" title="%s" target="_blank">%s</a><br/>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
        );
        if ($_SESSION['Pending-MACs']) printf('<a href="?node=report&sub=pend-mac&aprvall=1">%s</a>',_('Approve All Pending MACs for all hosts'));
        echo '</h2>';
        $csvHead = array(
            _('Host ID'),
            _('Host name'),
            _('Host Primary MAC'),
            _('Host Desc'),
            _('Host Pending MAC'),
        );
        foreach ((array)$csvHead AS $csvHeader => &$classGet) $this->ReportMaker->addCSVCell($csvHeader);
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
        printf('<h2><a href="export.php?type=csv&filename=VirusHistory" alt="%s" title="%s" target="_blank">%s</a> <a href="export?type="pdf?filename=VirusHistory" alt="%s" title="%s" target="_blank">%s</a></h2>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
        );
        printf('<form method="post" action="%s"><h2><a href="#"><input onclick="this.form.submit()" type="checkbox" class="delvid" name="delvall" id="delvid" value="all"/><label for="delvid">(%s)</label></a></h2></form>',
            $this->formAction,
            _('clear all history')
        );
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
            sprintf('<input type="checkbox" onclick="this.form.submit()" class="delvid" value="${vir_id}" id="vir${vir_id}" name="delvid"/><label for="for${vir_id}" class="icon icon-hand" title="%s ${vir_name}"><i class="fa fa-minus-circle link"></i></label>',_('Delete')),
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array('class'=>'filter-false'),
        );
        foreach ((array)$csvHead AS $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        foreach ((array)$this->getClass('VirusManager')->find() AS $i => &$Virus) {
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
            foreach ((array)$csvHead AS $head => &$classGet) {
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
        printf('<form method="post" action="%s">',$this->formAction);
        $this->ReportMaker->outputReport(false);
        echo '</form>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
    public function vir_hist_post() {
        if ($_REQUEST['delvall'] == 'all') {
            $this->getClass('VirusManager')->destroy();
            $this->setMessage(_("All Virus' cleared"));
            $this->redirect($this->formAction);
        } else if (is_numeric($_REQUEST['delvid'])) {
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
        $UserNames = $this->getSubObjectIDs('UserTracking','','username');
        $HostNames = $this->getSubObjectIDs('Host','','name');
        asort($UserNames);
        $UserNames = array_filter(array_unique((array)$UserNames));
        asort($HostNames);
        $HostNames = array_filter(array_unique((array)$HostNames));
        if (count($UserNames) > 0) {
            ob_start();
            foreach ((array)$UserNames AS $i => &$Username) {
                if ($Username) printf('<option value="%s">%s</option>',$Username,$Username);
                unset($Username);
            }
            $userSelForm = sprintf('<select name="usersearch"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean());
        }
        if (count($HostNames) > 0) {
            ob_start();
            foreach ((array)$HostNames AS $i => &$Hostname) {
                if ($Hostname) printf('<option value="%s">%s</option>',$Hostname,$Hostname);
                unset($Hostname);
            }
            $hostSelForm = sprintf('<select name="hostsearch"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean());
        }
        $fields = array(
            _('Enter a username to search for') => $userSelForm,
            _('Enter a hostname to search for') => $hostSelForm,
            '' => sprintf('<input type="submit" value="%s"/>',_('Search')),
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        printf('<form method="post" action="%s">',$this->formAction);
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
            sprintf('<a href="?node=%s&sub=user-track-disp&hostID=${host_id}&userID=${user_id}">${hostuser_name}</a>',$this->node),
            '${user_name}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $hostsearch = str_replace('*','%',sprintf('%%%s%%',trim($_REQUEST['hostsearch'])));
        $usersearch = str_replace('*','%',sprintf('%%%s%%',trim($_REQUEST['usersearch'])));
        if (trim($_REQUEST['hostsearch']) && !trim($_REQUEST['usersearch'])) {
            foreach ((array)$this->getClass('HostManager')->find(array('name'=>$hostsearch)) AS $i => &$Host) {
                if (!$Host->isValid()) continue;
                $this->data[] = array(
                    'host_id'=>$id,
                    'hostuser_name'=>$Host->get('name'),
                    'user_id'=>base64_encode('%'),
                    'user_name'=>'',
                );
                unset($Host);
            }
        } else if (!trim($_REQUEST['hostsearch']) && trim($_REQUEST['usersearch'])) {
            $ids = $this->getSubObjectIDs('UserTracking',array('username'=>$usersearch),array('id','hostID'));
            $lastUser = '';
            foreach ((array)$this->getClass('HostManager')->find(array('id'=>$ids['hostID'])) AS $i => &$Host) {
                if (!$Host->isValid()) $ids['hostID'] = array_diff((array)$Host->get('id'),(array)$ids['hostID']);
                unset($Host);
            }
            foreach ((array)$this->getClass('UserTrackingManager')->find(array('id'=>$ids['id'])) AS $i => &$User) {
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
            unset($lastUser);
        } else if (trim($_REQUEST['hostsearch']) && trim($_REQUEST['usersearch'])) {
            $HostIDs = $this->getSubObjectIDs('Host',array('name'=>$hostsearch));
            foreach ((array)$this->getClass('UserTrackingManager')->find(array('username'=>$usersearch,'hostID'=>$HostIDs)) AS $i => &$User) {
                if (!$User->isValid()) continue;
                $Host = $this->getClass('Host',$User->get('hostID'));
                if (!$Host->isValid()) continue;
                $userName = $User->get('name');
                unset($Host,$User);
                $this->data[] = array(
                    'host_id'=>$Host->get('id'),
                    'hostuser_name'=>$Host->get('name'),
                    'user_id'=>base64_encode($userName),
                    'user_name'=>$userName,
                );
                unset($userName,$Host,$User);
            }
            unset($HostIDs);
        } else if (!$hostsearch && !$usersearch) $this->redirect(sprintf('?node=%s&sub=user-track',$this->node));
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
        foreach ((array)$UserSearchDates AS $i => &$DateTime) {
            if (!$this->validDate($DateTime)) continue;
            $Dates[] = $this->formatTime($DateTime,'Y-m-d');
        }
        unset($DateTime);
        if ($Dates) {
            $Dates = array_unique($Dates);
            rsort($Dates);
            ob_start();
            foreach ((array)$Dates AS $i => &$Date) {
                printf('<option value="%s">%s</option>',$Date,$Date);
                unset($Date);
            }
            unset($Dates);
            $dates = ob_get_clean();
            $fields = array(
                _('Select Start Date') => sprintf('<select name="date1" size="1">%s</select>',$dates),
                _('Select End Date') => sprintf('<select name="date2" size="1">%s</select>',$dates),
                '' => sprintf('<input type="submit" value="%s"/>',_('Search for Entries')),
            );
            foreach((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                );
            }
            unset($input);
            printf('<form method="post" action="%s">',$this->formAction);
            $this->render();
            echo '</form>';
        } else $this->render();
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
        printf('<h2><a href="export.php?type=csv&filename=UserTrackingList" alt="%s" title="%s" target="_blank">%s</a> <a href="export?type="pdf?filename=UserTrackingList" alt="%s" title="%s" target="_blank">%s</a></h2>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
        );
        $date1 = $_REQUEST['date1'];
        $date2 = $_REQUEST['date2'];
        if ($date1 > $date2) {
            $date1 = $_REQUEST['date2'];
            $date2 = $_REQUEST['date1'];
        }
        $date2 = date('Y-m-d',strtotime("$date2 +1 day"));
        foreach ((array)$this->getClass('UserTrackingManager')->find(array('datetime'=>'','username'=>sprintf('%%%s%%',base64_decode($_REQUEST['userID'])),'hostID'=>($_REQUEST['hostID'] ? $_REQUEST['hostID'] : '%')),'','','',"BETWEEN '$date1' AND '$date2'",'','','',false) AS $i => &$User) {
            if (!$User->isValid()) continue;
            $Host = $this->getClass('Host',$User->get('hostID'));
            if (!$Host->isValid()) continue;
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
        $AllDates = array_merge($this->DB->query("SELECT DATE_FORMAT(`stCheckinDate`,'%Y-%m-%d') start FROM `snapinTasks` WHERE DATE_FORMAT(`stCheckinDate`,'%Y-%m-%d') != '0000-00-00' GROUP BY start ORDER BY start DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'),$this->DB->query("SELECT DATE_FORMAT(`stCompleteDate`,'%Y-%m-%d') finish FROM `snapinTasks` WHERE DATE_FORMAT(`stCompleteDate`,'%Y-%m-%d') != '0000-00-00' GROUP BY finish ORDER BY finish DESC")->fetch(MYSQLI_NUM,'fetch_all')->get('start'));
        foreach ((array)$AllDates AS $i => &$Date) {
            $tmp = array_shift($Date);
            if (!$this->validDate($tmp)) continue;
            $Dates[] = $tmp;
            unset($Date,$tmp);
        }
        unset($AllDates);
        $Dates = array_unique($Dates);
        rsort($Dates);
        if (count($Dates) > 0) {
            ob_start();
            foreach ((array)$Dates AS $i => &$Date) {
                printf('<option value="%s">%s</option>',$Date,$Date);
                unset($Date);
            }
            unset($Dates);
            $dates = ob_get_clean();
            $date1 = sprintf('<select name="%s" size="1">%s</select>','date1',$dates);
            $date2 = sprintf('<select name="%s" size="1">%s</select>','date2',$dates);
            $fields = array(
                _('Select Start Date') => $date1,
                _('Select End Date') => $date2,
                '&nbsp;' => sprintf('<input type="submit" value="%s"/>',_('Search for Entries')),
            );
            foreach ((array)$fields AS $field => &$input) {
                $this->data[] = array(
                    'field'=>$field,
                    'input'=>$input,
                );
                unset($input);
            }
            unset($fields);
            printf('<form method="post" action="%s">',$this->formAction);
            $this->render();
            echo '</form>';
        } else $this->render();
    }
    public function snapin_log_post() {
        $this->title = _('FOG Snapin Log');
        printf('<h2><a href="export.php?type=csv&filename=SnapinLog" alt="%s" title="%s" target="_blank">%s</a> <a href="export.php?type=pdf&filename=SnapinLog" alt="%s" title="%s" target="_blank">%s</a></h2>',
            _('Export CSV'),
            _('Export CSV'),
            $this->csvfile,
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
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
        $date2 = date('Y-m-d',strtotime("$date2 +1 day"));
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
        foreach((array)$csvHead AS $i => &$csvHeader) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($csvHeader);
        }
        $this->ReportMaker->endCSVLine();
        foreach ((array)$this->getClass('SnapinTaskManager')->find(array('checkin'=>null,'complete'=>null),'OR','','',"BETWEEN '$date1' AND '$date2'",'','','',false) AS $i => &$SnapinTask) {
            if (!$SnapinTask->isValid()) continue;
            $start = $this->nice_date($SnapinTask->get('checkin'));
            $end = $this->nice_date($SnapinTask->get('complete'));
            if (!$this->validDate($start) || !$this->validDate($end)) continue;
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) continue;
            $SnapinJob = $SnapinTask->getSnapinJob();
            if (!$SnapinJob->isValid()) continue;
            $Host = $SnapinJob->getHost();
            if (!$Host->isValid()) continue;
            $this->data[] = array(
                'snap_name'=>$Snapin->get('name'),
                'snap_state'=>$this->getClass('TaskState',$SnapinTask->get('stateID'))->get('name'),
                'snap_return'=>$SnapinTask->get('return'),
                'snap_detail'=>$SnapinTask->get('detail'),
                'snap_create'=>$this->formatTime($Snapin->get('createdTime'),'Y-m-d'),
                'snap_time'=>$this->formatTime($Snapin->get('createdTime'),'H:i:s'),
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
            $this->ReportMaker->addCSVCell($this->getClass('TaskState',$SnapinTask->get('stateID'))->get('name'));
            $this->ReportMaker->addCSVCell($SnapinTask->get('return'));
            $this->ReportMaker->addCSVCell($SnapinTask->get('detail'));
            $this->ReportMaker->addCSVCell($this->formatTime($Snapin->get('createdTime'),'Y-m-d'));
            $this->ReportMaker->addCSVCell($this->formatTime($Snapin->get('createdTime'),'H:i:s'));
            $this->ReportMaker->addCSVCell($this->formatTime($SnapinJob->get('createdTime'),'Y-m-d'));
            $this->ReportMaker->addCSVCell($this->formatTime($SnapinJob->get('createdTime'),'H:i:s'));
            $this->ReportMaker->addCSVCell($this->formatTime($SnapinTask->get('checkin'),'Y-m-d'));
            $this->ReportMaker->addCSVCell($this->formatTime($SnapinTask->get('checkin'),'H:i:s'));
            $this->ReportMaker->endCSVLine();
            unset($Host,$Snapin,$SnapinJob,$SnapinTask);
        }
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
        ob_start();
        foreach ((array)$this->getClass('InventoryManager')->find() AS $i => &$Inventory) {
            if (!$Inventory->isValid()) continue;
            if (!$Inventory->get('primaryUser')) continue;
            if (!($Inventory->isValid() && $Inventory->get('primaryUser'))) continue;
            printf('<option value="%s">%s</option>',$Inventory->get('id'),$Inventory->get('primaryUser'));
            unset($Inventory);
        }
        $fields = array(
            _('Select User') => sprintf('<select name="user" size="1"><option value="">- %s -</option>%s</select>',_('Please select an option'),ob_get_clean()),
            '' => sprintf('<input type="submit" value="%s"/>',_('Create Report')),
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input);
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function equip_loan_post() {
        $Inventory = $this->getClass('Inventory',$_REQUEST['user']);
        if (!$Inventory->isValid()) return;
        $this->title = _('FOG Equipment Loan Form');
        printf('<h2><a href="export.php?type=pdf&filename=%sEquipmentLoanForm" alt="%s" title="%s" target="_blank">%s</a></h2>',
            $Inventory->get('primaryUser'),
            _('Export PDF'),
            _('Export PDF'),
            $this->pdffile
        );
        $this->ReportMaker->appendHTML(sprintf('<!-- FOOTER CENTER "$PAGE %s $PAGES - %s: %s" --><p class="c"><h3>%s</h3></p><hr/><p class="c"><h2>%s</h2></p><p class="c"><h3>%s</h3></p><p class="c"><h2><u>%s</u></h2></p><p class="c"><h4><u>%s</u></h4></p><h4><b>%s: </b><u>%s</u></h4><h4><b>%s: </b><u>%s</u></h4><h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4><p class="c"><h4><u>%s</u></h4></p><h4><b>%s: </b><u>%s</u></h4><h4><b>%s: </b><u>%s</u></h4><h4><b>%s: </b><u>%s</u></h4><p class="c"><h4><b>%s / %s / %s</b></h4></p><p class="c"><h4><b>%s</b></h4></p><p class="c"><h4><b>%s</b></h4></p><p class="c"><h4><b>%s</b></h4></p><br/><hr/><h4><b>%s: </b>%s</h4><p class="c"><h4>(%s %s)</h4></p><p class="c"><h4>%s</h4></p><h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4><!-- NEW PAGE --><!-- FOOTER CENTER "$PAGE %s $PAGES - %s: %s" --><p class="c"><h3>%s</h3></p><hr/><h4>%s</h4><h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4>',
            _('of'),
            _('Printed'),
            $this->formatTime('','D M j G:i:s T Y'),
            _('Equipment Loan'),
            _('[Organization Here]'),
            _('[sub-unit here]'),
            _('PC Check-out Agreement'),
            _('Personal Information'),
            _('Name'),
            $Inventory->get('primaryUser'),
            _('Location'),
            _('Your Location Here'),
            str_pad(_('Home Address'),25),
            str_repeat('_',65),
            str_pad(_('City/State/Zip'),25),
            str_repeat('_',65),
            str_pad(_('Extension'),25),
            str_repeat('_',65),
            str_pad(_('Home Phone'),25),
            str_repeat('_',65),
            _('Computer Information'),
            str_pad(sprintf('%s / %s',_('Serial Number'),_('Service Tag')),25),
            str_pad(sprintf('%s / %s',$Inventory->get('sysserial'),$Inventory->get('caseasset')),65,'_'),
            str_pad(_('Barcode Numbers'),25),
            str_pad(sprintf('%s %s',$Inventory->get('other1'),$Inventory->get('other2')),65,'_'),
            str_pad(_('Date of checkout'),25),
            str_repeat('_',65),
            _('Notes'),
            _('Miscellaneous'),
            _('Included Items'),
            str_repeat('_',75),
            str_repeat('_',75),
            str_repeat('_',75),
            str_pad(_('Releasing Staff Initials'),25),
            str_repeat('_',65),
            _('To be released only by'),
            str_repeat('_',20),
            _('I have read, understood, and agree to all the Terms and Conditions on the following pages of this document.'),
            str_pad(_('Signed'),25),
            str_repeat('_',65),
            str_pad(_('Date'),25),
            str_repeat('_',65),
            _('of'),
            _('Printed'),
            $this->formatTime('','D M j G:i:s T Y'),
            _('Terms and Conditions'),
            _('Your terms and conditions here'),
            str_pad(_('Signed'),25),
            str_repeat('_',65),
            str_pad(_('Date'),25),
            str_repeat('_',65)
        ));
        printf('<p>%s</p>',_('Your form is ready.'));
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
