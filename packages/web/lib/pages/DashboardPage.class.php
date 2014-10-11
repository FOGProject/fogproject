<<<<<<< HEAD
<?php
/**	Class Name: DashboardPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages
    Description: This is an extension of the FOGPage Class
    This class controls the Dashboard/Home page for fog.
	It creates the elements dynamically when a person first logs
	into FOG.
    It, provides the overview elements:
	System overview, Activity, Disk usage, and bandwidth
	information.

	Useful for:
	One stop shop of overall server usage/activity.
*/
class DashboardPage extends FOGPage
{
	// Base variables
	var $name = 'Dashboard';
	var $node = 'home';
	var $id = 'id';
	// Pages
	/** index()
		The first page displayed especially when a user logs in.
	*/
	public function index()
	{
		$SystemUptime = $this->FOGCore->SystemUptime();
		$fields = array(
			_('Username') => $this->FOGUser ? $this->FOGUser->get('name') : '',
			_('Web Server') => $this->FOGCore->getSetting('FOG_WEB_HOST'),
			_('TFTP Server') => $this->FOGCore->getSetting('FOG_TFTP_HOST'),
			_('Load Average') => $SystemUptime['load'],
			_('Uptime') => $SystemUptime['uptime'],
		);

		$this->templates = array(
			'${field}',
			'${fielddata}',
		);
		$this->attributes = array(
			array(),
			array(),
		);
		print "\n\t\t\t".'<ul id="dashboard-boxes">';
		print "\n\t\t\t<li>";
		print "\n\t\t\t<h4>"._('System Overview').'</h4>';
		foreach ((array)$fields AS $field => $fielddata)
		{
			$this->data[] = array(
				'field' => $field,
				'fielddata' => $fielddata,
			);
		}
		$this->HookManager->processEvent('DashboardData', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		print "\n\t\t\t</li>";
		print "\n\t\t\t<li>";
		print "\n\t\t\t<h4>"._('System Activity').'</h4>';
		print "\n\t\t\t".'<div class="graph pie-graph" id="graph-activity"></div>';
		print "\n\t\t\t</li>";
		print "\n\t\t\t<li>";
		print "\n\t\t\t<h4>"._('Disk Information').'</h4>';
		print "\n\t\t\t".'<div id="diskusage-selector">';
		foreach ((array)$this->FOGCore->getClass('StorageNodeManager')->find(array('isEnabled' => 1,'isGraphEnabled' => 1)) AS $StorageNode)
			$options[] = "\n\t\t\t".'<option value="'.$StorageNode->get('id').'">'.$StorageNode->get('name').($StorageNode->get('isMaster') == '1' ? ' *' : '').'</option>';
		$options ? print "\n\t\t\t".'<select name="storagesel" style="whitespace: no-wrap; width: 100px; position: relative; top: 100px;">'.implode("\n",$options).'</select>' : null;
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<a href="?node=hwinfo"><div class="graph pie-graph" id="graph-diskusage"></div></a>';
		print "\n\t\t\t</li>";
		print "\n\t\t\t</ul>";
		print "\n\t\t\t<h3>"._('Imaging over the last 30 days').'</h3>';
		print "\n\t\t\t".'<div id="graph-30day" class="graph"></div>';
		print "\n\t\t\t".'<h3 id="graph-bandwidth-title">'.$this->foglang['Bandwidth'].'- <span>'.$this->foglang['Transmit'].'</span><!-- (<span>2 Minutes</span>)--></h3>';
		print "\n\t\t\t".'<div id="graph-bandwidth-filters">';
		print "\n\t\t\t".'<div>';
		print "\n\t\t\t".'<a href="#" id="graph-bandwidth-filters-transmit" class="l active">'.$this->foglang['Transmit'].'</a>';
		print "\n\t\t\t".'<a href="#" id="graph-bandwidth-filters-receive" class="l">'.$this->foglang['Receive'].'</a>';
		print "\n\t\t\t".'</div>';
		print "\n\t\t\t".'<div class="spacer"></div>';
		print "\n\t\t\t<!--";
		print "\n\t\t\t<div>";
		print "\n\t\t\t".'<a href="#" rel="3600" class="r">'._('1 Hour').'</a>';
		print "\n\t\t\t".'<a href="#" rel="1800" class="r">'._('30 Minutes').'</a>';
		print "\n\t\t\t".'<a href="#" rel="600" class="r">'._('10 Minutes').'</a>';
		print "\n\t\t\t".'<a href="#" rel="120" class="r active">'._('2 Minutes').'</a>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t-->";
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<div id="graph-bandwidth" class="graph"></div>';
		$DateTimeStart = new DateTime(date('Y-m-d',strtotime('-30 days')), (!ini_get('date.timezone') ? new DateTimeZone('GMT') : new DateTimeZone(ini_get('date.timezone'))));
		$DateTimeEnd = new DateTime(date('Y-m-d'), (!ini_get('date.timezone') ? new DateTimeZone('GMT') : new DateTimeZone(ini_get('date.timezone'))));
		$DateTimeEnd = $DateTimeEnd->modify('+1 day');
		$DatePeriod = new DatePeriod($DateTimeStart, new DateInterval('P1D'), $DateTimeEnd);
		foreach($DatePeriod AS $Date)
		{
			$keyword = '%'.$Date->format('Y-m-d').'%';
			$ImagingLogs = $this->FOGCore->getClass('ImagingLogManager')->count(array('start' => $keyword, 'type' => array('up','down')));
			$Graph30dayData[] = '["'.($Date->getTimestamp()*1000).'", '.$ImagingLogs.']';
		}
		
		$ActivityActive = 0;
       	$ActivityQueued = 0;
  		$ActivitySlots = 0;
  		$ActivityTotalClients = 0;
		foreach( $this->FOGCore->getClass('StorageNodeManager')->find(array('isEnabled' => 1)) AS $StorageNode ) {
		    if ( $StorageNode && $StorageNode->isValid() ) {
           		$ActivityActive += $StorageNode->getUsedSlotCount();
	        	$ActivityQueued += $StorageNode->getQueuedSlotCount();
	        	$ActivityTotalClients += $StorageNode->get('maxClients');

    		}
		}
   		$ActivitySlots = $ActivityTotalClients -  $ActivityActive - $ActivityQueued;		    		
   		
		$StorageNode = current($this->FOGCore->getClass('StorageNodeManager')->find(array('isMaster' => 1, 'isEnabled' => 1)));

		print "\n\t\t\t".'<div class="fog-variable" id="ActivityActive">'.$ActivityActive.'</div>';
		print "\n\t\t\t".'<div class="fog-variable" id="ActivityQueued">'.$ActivityQueued.'</div>';
		print "\n\t\t\t".'<div class="fog-variable" id="ActivitySlots">'.($ActivitySlots < 0 ? 0 : $ActivitySlots).'</div>';
		print "\n\t\t\t<!-- Variables -->";
		print "\n\t\t\t".'<div class="fog-variable" id="Graph30dayData">['.implode(', ', (array)$Graph30dayData).']</div>';
	}
	/** bandwidth()
		Display's the bandwidth bar on the dashboard page.
	*/
	public function bandwidth()
	{
		// Loop each storage node -> grab stats
		foreach ((array)$this->FOGCore->getClass('StorageNodeManager')->find(array('isEnabled' => 1, 'isGraphEnabled' => 1)) AS $StorageNode)
		{
			// TODO: Need to move interface to per storage group server
			$URL = sprintf('http://%s/%s?dev=%s', rtrim($StorageNode->get('ip'), '/'), ltrim($this->FOGCore->getSetting("FOG_NFS_BANDWIDTHPATH"), '/'), $StorageNode->get('interface'));
			// Fetch bandwidth stats from remote server
			if ($fetchedData = $this->FOGCore->fetchURL($URL))
			{
				// Legacy client
				if (preg_match('/(.*)##(.*)/U', $fetchedData, $match))
					$data[$StorageNode->get('name')] = array('rx' => $match[1], 'tx' => $match[2]);
				else
					$data[$StorageNode->get('name')] = json_decode($fetchedData, true);
			}
		}
		print json_encode((array)$data);
	}
}
=======
<?php
/**	Class Name: DashboardPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages
    Description: This is an extension of the FOGPage Class
    This class controls the Dashboard/Home page for fog.
	It creates the elements dynamically when a person first logs
	into FOG.
    It, provides the overview elements:
	System overview, Activity, Disk usage, and bandwidth
	information.

	Useful for:
	One stop shop of overall server usage/activity.
*/
class DashboardPage extends FOGPage
{
	// Base variables
	var $name = 'Dashboard';
	var $node = 'home';
	var $id = 'id';
	// Pages
	/** index()
		The first page displayed especially when a user logs in.
	*/
	public function index()
	{
		$SystemUptime = $this->FOGCore->SystemUptime();
		$fields = array(
			_('Username') => $this->FOGUser ? $this->FOGUser->get('name') : '',
			_('Web Server') => $this->FOGCore->getSetting('FOG_WEB_HOST'),
			_('TFTP Server') => $this->FOGCore->getSetting('FOG_TFTP_HOST'),
			_('Load Average') => $SystemUptime['load'],
			_('Uptime') => $SystemUptime['uptime'],
		);

		$this->templates = array(
			'${field}',
			'${fielddata}',
		);
		$this->attributes = array(
			array(),
			array(),
		);
		print "\n\t\t\t".'<ul id="dashboard-boxes">';
		print "\n\t\t\t<li>";
		print "\n\t\t\t<h4>"._('System Overview').'</h4>';
		foreach ((array)$fields AS $field => $fielddata)
		{
			$this->data[] = array(
				'field' => $field,
				'fielddata' => $fielddata,
			);
		}
		$this->HookManager->processEvent('DashboardData', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		print "\n\t\t\t</li>";
		print "\n\t\t\t<li>";
		print "\n\t\t\t<h4>"._('System Activity').'</h4>';
		print "\n\t\t\t".'<div class="graph pie-graph" id="graph-activity"></div>';
		print "\n\t\t\t</li>";
		print "\n\t\t\t<li>";
		print "\n\t\t\t<h4>"._('Disk Information').'</h4>';
		print "\n\t\t\t".'<div id="diskusage-selector">';
		foreach ((array)$this->getClass('StorageNodeManager')->find(array('isEnabled' => 1,'isGraphEnabled' => 1)) AS $StorageNode)
			$options[] = "\n\t\t\t".'<option value="'.$StorageNode->get('id').'">'.$StorageNode->get('name').($StorageNode->get('isMaster') == '1' ? ' *' : '').'</option>';
		$options ? print "\n\t\t\t".'<select name="storagesel" style="whitespace: no-wrap; width: 100px; position: relative; top: 100px;">'.implode("\n",$options).'</select>' : null;
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<a href="?node=hwinfo"><div class="graph pie-graph" id="graph-diskusage"></div></a>';
		print "\n\t\t\t</li>";
		print "\n\t\t\t</ul>";
		print "\n\t\t\t<h3>"._('Imaging over the last 30 days').'</h3>';
		print "\n\t\t\t".'<div id="graph-30day" class="graph"></div>';
		print "\n\t\t\t".'<h3 id="graph-bandwidth-title">'.$this->foglang['Bandwidth'].'- <span>'.$this->foglang['Transmit'].'</span><!-- (<span>2 Minutes</span>)--></h3>';
		print "\n\t\t\t".'<div id="graph-bandwidth-filters">';
		print "\n\t\t\t".'<div>';
		print "\n\t\t\t".'<a href="#" id="graph-bandwidth-filters-transmit" class="l active">'.$this->foglang['Transmit'].'</a>';
		print "\n\t\t\t".'<a href="#" id="graph-bandwidth-filters-receive" class="l">'.$this->foglang['Receive'].'</a>';
		print "\n\t\t\t".'</div>';
		print "\n\t\t\t".'<div class="spacer"></div>';
		print "\n\t\t\t<!--";
		print "\n\t\t\t<div>";
		print "\n\t\t\t".'<a href="#" rel="3600" class="r">'._('1 Hour').'</a>';
		print "\n\t\t\t".'<a href="#" rel="1800" class="r">'._('30 Minutes').'</a>';
		print "\n\t\t\t".'<a href="#" rel="600" class="r">'._('10 Minutes').'</a>';
		print "\n\t\t\t".'<a href="#" rel="120" class="r active">'._('2 Minutes').'</a>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t-->";
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<div id="graph-bandwidth" class="graph"></div>';
		$DatePeriod = new DatePeriod($this->nice_date()->modify('-29 days'), new DateInterval('P1D'), $this->nice_date()->modify('+1 day'));
		foreach($DatePeriod AS $Date)
		{
			$keyword = '%'.$Date->format('Y-m-d').'%';
			$ImagingLogs = $this->getClass('ImagingLogManager')->count(array('start' => $keyword, 'type' => array('up','down')));
			$Graph30dayData[] = '["'.(1000*$Date->getTimestamp()).'", '.$ImagingLogs.']';
		}
		$ActivityActive = 0;
       	$ActivityQueued = 0;
  		$ActivitySlots = 0;
  		$ActivityTotalClients = 0;
		foreach($this->getClass('StorageNodeManager')->find(array('isEnabled' => 1)) AS $StorageNode)
		{
		    if ($StorageNode && $StorageNode->isValid())
			{
           		$ActivityActive += $StorageNode->getUsedSlotCount();
	        	$ActivityQueued += $StorageNode->getQueuedSlotCount();
	        	$ActivityTotalClients += $StorageNode->get('maxClients');
    		}
		}
   		$ActivitySlots = $ActivityTotalClients -  $ActivityActive - $ActivityQueued;		    		
		$StorageNode = current($this->getClass('StorageNodeManager')->find(array('isMaster' => 1, 'isEnabled' => 1)));
		print "\n\t\t\t".'<div class="fog-variable" id="ActivityActive">'.$ActivityActive.'</div>';
		print "\n\t\t\t".'<div class="fog-variable" id="ActivityQueued">'.$ActivityQueued.'</div>';
		print "\n\t\t\t".'<div class="fog-variable" id="ActivitySlots">'.($ActivitySlots < 0 ? 0 : $ActivitySlots).'</div>';
		print "\n\t\t\t<!-- Variables -->";
		print "\n\t\t\t".'<div class="fog-variable" id="Graph30dayData">['.implode(', ', (array)$Graph30dayData).']</div>';
	}
	/** bandwidth()
		Display's the bandwidth bar on the dashboard page.
	*/
	public function bandwidth()
	{
		// Loop each storage node -> grab stats
		$StorageNode = new StorageNode($_REQUEST['nodeid']);
		$URL = sprintf('http://%s/%s?dev=%s', rtrim($StorageNode->get('ip'), '/'), ltrim($this->FOGCore->getSetting("FOG_NFS_BANDWIDTHPATH"), '/'), $StorageNode->get('interface'));
		// Fetch bandwidth stats from remote server
		if ($fetchedData = $this->FOGCore->fetchURL($URL))
		{
			// Legacy client
			if (preg_match('/(.*)##(.*)/U', $fetchedData, $match))
				$data = array('rx' => $match[1], 'tx' => $match[2]);
			else
				$data = json_decode($fetchedData, true);
		}
		print json_encode((array)$data);
	}
}
>>>>>>> dev-branch
