<?php
class Hosts_and_Users extends ReportManagementPage {
	public function __construct($name = '') {
		$this->name = 'Hosts and Users';
		$this->node = 'report';
		parent::__construct($this->name);
		$this->index();
	}
	public function index() {
		$this->title =_('FOG Hosts and Users Login');
        printf($this->reportString,
            'Hosts_and_Users',
            _('Export CSV'),
            _('Export CSV'),
            self::$csvfile,
            'Hosts_and_Users',
            _('Export PDF'),
            _('Export PDF'),
            self::$pdffile
        );
		$report = self::getClass('ReportMaker');
		$report->appendHTML('<table cellpadding="0" cellspacing="0" border="0" width="100%">');
		$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td><td><b>MAC</b></td><td><b>Registered</b></td></tr>');
		$report->addCSVCell('Hostname');
		$report->addCSVCell('MAC');
		$report->addCSVCell('Registered');
		$report->endCSVLine();
		$cnt = 0;
        array_map(function(&$Host) use (&$report) {
            if (!$Host->isValid()) return;
			$bg = ($cnt++ % 2 == 0 ? "#E7E7E7" : '');
            $report->appendHTML(sprintf('<tr bgcolor="%s"><td>%s</td><td>%s</td><td>%s</td></tr>',$bg,$Host->get('name'),$Host->get('mac'),$Host->get('createdTime')));
			$report->addCSVCell($Host->get('name'));
			$report->addCSVCell($Host->get('mac'));
            $report->addCSVCell($Host->get('createdTime'));
            if (!count($Host->get('users'))) {
                $report->endCSVLine();
                return;
            }
            $report->endCSVLine();
            $report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Username</b></td><td><b>Action</b></td><td><b>Date</b></td><td><b>Time</b></td></tr>');
            $report->addCSVCell('Username');
            $report->addCSVCell('Action');
            $report->addCSVCell('Time');
            $report->endCSVLine();
            $cnt1 = 0;
            array_map(function(&$User) use (&$report) {
                if (!$User->isValid()) return;
                if ($User->get('username') == 'Array') return;
                $bg1 = ($cnt1++ % 2 == 0 ? "#E7E7E7" : '');
                $logintext = ($User->get('action') == 1 ? 'Login' : 'Logout' );
                $report->appendHTML(sprintf('<tr bgcolor="%s"><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',$bg1,$User->get('username'),$logintext,$User->get('date'),$User->get('datetime')));
                $report->addCSVCell($User->get('username'));
                $report->addCSVCell($logintext);
                $report->addCSVCell($User->get('date'));
                $report->addCSVCell($User->get('datetime'));
                $report->endCSVLine();
				$report->appendHTML('<table cellpadding="0" cellspacing="0" border="0" width="100%">');
				$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td><td><b>MAC</b></td><td><b>Registered</b></td></tr>');
				$report->addCSVCell('Hostname');
				$report->addCSVCell('MAC');
				$report->addCSVCell('Registered');
				$report->endCSVLine();
			},(array)self::getClass('UserTrackingManager')->find(array('id'=>$Host->get('users'),'action'=>array(null,0,1)),'','datetime','DESC','','username'));
		},(array)self::getClass('HostManager')->find('','','','','','name'));
		$report->appendHTML('</table>');
		$report->outputReport(0);
		$_SESSION['foglastreport'] = serialize($report);
	}
}
