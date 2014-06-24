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
		$Hosts = $this->FOGCore->getClass('HostManager')->find();
		$report = new ReportMaker();
		$report->appendHTML('<table cellpadding="0" cellspacing="0" border="0" width="100%">');
		$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Hostname</b></td><td><b>MAC</b></td><td><b>Registered</b></td></tr>');
		$report->addCSVCell('Hostname');
		$report->addCSVCell('MAC');
		$report->addCSVCell('Registered');
		$report->endCSVLine();
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
				$report->appendHTML('<tr bgcolor="#BDBDBD"><td><b>Username</b></td><td><b>Action</b></td><td><b>Time</b></td></tr>');
				$report->addCSVCell('Username');
				$report->addCSVCell('Action');
				$report->addCSVCell('Time');
				$report->endCSVLine();
				$cnt1 = 0;
				foreach ($Host->get('users') AS $User)
				{
					$bg1 = ($cnt1++ % 2 == 0 ? "#E7E7E7" : '');
					$logintext = ($User->get('action') == 1 ? 'Login' : ($User->get('action') == 0 ? 'Logout' : ($User->get('action') == 99 ? 'Service Start' : 'N/A')));
					if ($logintext == 'Login' || $logintext == 'Logout')
					{
						$report->appendHTML('<tr bgcolor="'.$bg1.'"><td>'.$User->get('username').'</td><td>'.$logintext.'</td><td>'.$this->FOGCore->formatTime($User->get('datetime')).'</td></tr>');
						$report->addCSVCell($User->get('username'));
						$report->addCSVCell($logintext);
						$report->addCSVCell($this->FOGCore->formatTime($User->get('datetime')));
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
			else
				$report->endCSVLine();
		}
		$report->appendHTML('</table>');
		$report->outputReport(ReportMaker::FOG_REPORT_HTML);
		$_SESSION['foglastreport'] = serialize($report);
	}
}
$HostUsers = new HostUsers();
