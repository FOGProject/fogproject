<?php
class HostUsers extends FOGBase
{
	public function __construct()
	{
		parent::__construct();
		$this->makeReport();
	}
	private function makeReport()
	{
		print '<h2>'._('FOG Hosts and Users Login').'<a href="export.php?type=csv" target="_blank"><img class="noBorder" src="images/csv.png" /></a><a href="export.php?type=pdf" target="_blank"><img class="noBorder" src="images/pdf.png" /></a></h2>';
		$report = new ReportMaker();
		$report->appendHTML('<table cellpadding="0" cellspacing="0" border="0" width="100%">');
		$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td><td><b>MAC</b></td><td><b>Registered</b></td></tr>');
		$report->addCSVCell('Hostname');
		$report->addCSVCell('MAC');
		$report->addCSVCell('Registered');
		$report->endCSVLine();
		$Hosts = $this->FOGCore->getClass('HostManager')->find('','','','','','name');
		$cnt = 0;
		foreach ($Hosts AS $Host)
		{
			$bg = ($cnt++ % 2 == 0 ? "#E7E7E7" : '');
			$report->appendHTML('<tr bgcolor="'.$bg.'"><td>'.$Host->get('name').'</td><td>'.$Host->get('mac').'</td><td>'.$Host->get('createdTime').'</td></tr>');
			$report->addCSVCell($Host->get('name'));
			$report->addCSVCell($Host->get('mac'));
			$report->addCSVCell($Host->get('createdTime'));
			if ($Host->get('users'))
			{
				$report->endCSVLine();
				$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Username</b></td><td><b>Action</b></td><td><b>Date</b></td><td><b>Time</b></td></tr>');
				$report->addCSVCell('Username');
				$report->addCSVCell('Action');
				$report->addCSVCell('Time');
				$report->endCSVLine();
				$cnt1 = 0;
				$Users = $this->getClass('UserTrackingManager')->find(array('hostID' => $Host->get('id'),'action' => array(null,0,1)),'',array('username','datetime'),'DESC','','username');
				foreach ($Users AS $User)
				{
					if ($User->get('username') != 'Array')
					{
						$bg1 = ($cnt1++ % 2 == 0 ? "#E7E7E7" : '');
						$logintext = ($User->get('action') == 1 ? 'Login' : 'Logout' );
						$report->appendHTML('<tr bgcolor="'.$bg1.'"><td>'.$User->get('username').'</td><td>'.$logintext.'</td><td>'.$User->get('date').'</td><td>'.$User->get('datetime').'</td></tr>');
						$report->addCSVCell($User->get('username'));
						$report->addCSVCell($logintext);
						$report->addCSVCell($User->get('date'));
						$report->addCSVCell($User->get('datetime'));
						$report->endCSVLine();
					}
				}
				$report->appendHTML('<table cellpadding="0" cellspacing="0" border="0" width="100%">');
				$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td><td><b>MAC</b></td><td><b>Registered</b></td></tr>');
				$report->addCSVCell('Hostname');
				$report->addCSVCell('MAC');
				$report->addCSVCell('Registered');
				$report->endCSVLine();
			}
		}
		$report->appendHTML('</table>');
		$report->outputReport(ReportMaker::FOG_REPORT_HTML);
		$_SESSION['foglastreport'] = serialize($report);
	}
}
$HostUsers = new HostUsers();
