<?php
class HostUsers extends ReportManagementPage {
	public function __construct($name = '') {
		$this->name = 'Hosts and Users';
		$this->node = 'report';
		parent::__construct($this->name);
		$this->index();
	}
	public function index() {
		$this->title =_('FOG Hosts and Users Login');
		echo '<p class="c"><a href="export.php?type=csv" target="_blank"><i class="fa fa-file-excel-o fa-2x"></i></a><a href="export.php?type=pdf" target="_blank"><i class="fa fa-file-pdf-o fa-2x"></i></a></p><br/>';
		$report = $this->getClass('ReportMaker');
		$report->appendHTML('<table cellpadding="0" cellspacing="0" border="0" width="100%">');
		$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td><td><b>MAC</b></td><td><b>Registered</b></td></tr>');
		$report->addCSVCell('Hostname');
		$report->addCSVCell('MAC');
		$report->addCSVCell('Registered');
		$report->endCSVLine();
		$cnt = 0;
		foreach ($this->getClass('HostManager')->find('','','','','','name') AS $i => &$Host) {
            if (!$Host->isValid()) continue;
			$bg = ($cnt++ % 2 == 0 ? "#E7E7E7" : '');
            $report->appendHTML(sprintf('<tr bgcolor="%s"><td>%s</td><td>%s</td><td>%s</td></tr>',$bg,$Host->get('name'),$Host->get('mac'),$Host->get('createdTime')));
			$report->addCSVCell($Host->get('name'));
			$report->addCSVCell($Host->get('mac'));
            $report->addCSVCell($Host->get('createdTime'));
            if (!count($Host->get('users'))) {
                $report->endCSVLine();
                continue;
            }
            $report->endCSVLine();
            $report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Username</b></td><td><b>Action</b></td><td><b>Date</b></td><td><b>Time</b></td></tr>');
            $report->addCSVCell('Username');
            $report->addCSVCell('Action');
            $report->addCSVCell('Time');
            $report->endCSVLine();
            $cnt1 = 0;
            foreach ((array)$this->getClass('UserTrackingManager')->find(array('id'=>$Host->get('users'),'action'=>array(null,0,1)),'','datetime','DESC','','username') AS $i => $User) {
                if (!$User->isValid()) continue;
                if ($User->get('username') == 'Array') continue;
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
			}
		}
		$report->appendHTML('</table>');
		$report->outputReport(0);
		$_SESSION['foglastreport'] = serialize($report);
	}
}
$HostUsers = new HostUsers();
