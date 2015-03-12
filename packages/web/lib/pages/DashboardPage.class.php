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
		print "\n\t\t\t<div>";
		print "\n\t\t\t".'<a href="#" rel="3600" class="r">'._('1 Hour').'</a>';
		print "\n\t\t\t".'<a href="#" rel="1800" class="r">'._('30 Minutes').'</a>';
		print "\n\t\t\t".'<a href="#" rel="600" class="r">'._('10 Minutes').'</a>';
		print "\n\t\t\t".'<a href="#" rel="120" class="r active">'._('2 Minutes').'</a>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<div id="graph-bandwidth" class="graph"></div>';
		$DateStart = '%'.date('Y-m-d',strtotime('-30 days')).'%';
		$DateEnd = '%'.date('Y-m-d',time()).'%';
		for ($i = 0; $i < 30; $i++)
			$DatePeriod[] = date('Y-m-d',strtotime('-'.$i.' days'));
		$ImagingLogs = $this->getClass('ImagingLogManager')->find(array('start' => null,'finish' => null,'type' => array('up','down')),'','','',"BETWEEN '".trim($DateStart,'%')."' AND '".trim($DateEnd,'%')."'");
		foreach($DatePeriod AS $Date)
		{
			$Date = $this->nice_date($Date);
			$count = 0;
			foreach($ImagingLogs AS $ImagingLog)
			{
				if ($ImagingLog && $ImagingLog->isValid() && date('Y-m-d',strtotime($ImagingLog->get('start'))) == $Date->format('Y-m-d'))
					$count++;
			}
			$Graph30dayData[] = '["'.(1000*$Date->getTimestamp()).'", '.$count.']';
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
		$Nodes = $this->getClass('StorageNodeManager')->find(array('isGraphEnabled' => 1));
		// Loop each storage node -> grab stats
		foreach($Nodes AS $StorageNode)
			$URL[] = sprintf('http://%s/%s?dev=%s', $this->FOGCore->resolveHostname($StorageNode->get('ip')), ltrim($this->FOGCore->getSetting("FOG_NFS_BANDWIDTHPATH"), '/'), $StorageNode->get('interface'));
		$fetchedData = $this->FOGCore->fetchURL($URL);
		$count = 0;
		$len = count($fetchedData);
		for ($i = 0;$i < $len; $i++)
		{
			if (preg_match('/(.*)##(.*)/U', $fetchedData[$i],$match))
				$data[$Nodes[$i]->get('name')] = array('rx' => $match[1],'tx' => $match[2]);
			else
				$data[$Nodes[$i]->get('name')] = json_decode($fetchedData[$i],true);
		}
		print json_encode((array)$data);
	}
	/** diskusage()
		Display's the disk usage graph on the dashboard page.
	*/
	public function diskusage()
	{
		// // Get the node ID -> grab the ino:
		$StorageNode = new StorageNode($_REQUEST['id']);
		if ($StorageNode && $StorageNode->isValid() && $StorageNode->get('isGraphEnabled'))
		{
			try
			{
				$webroot = $this->FOGCore->getSetting('FOG_WEB_ROOT') ? '/'.trim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/').'/' : '/';
				$URL = sprintf('http://%s%sstatus/freespace.php?path=%s',$this->FOGCore->resolveHostname($StorageNode->get('ip')),$webroot,base64_encode($StorageNode->get('path')));
				if ($Response = $this->FOGCore->fetchURL($URL))
				{
					// Legacy client
					if (preg_match('#(.*)@(.*)#', $Response[0], $match))
						$Data = array('free' => $match[1], 'used' => $match[2]);
					else
					{
						$Response = json_decode($Response[0], true);
						$Data = array('free' => $Response['free'], 'used' => $Response['used']);
					}
				}
				else
					throw new Exception('Failed to connect to '.$StorageNode->get('name'));
			}
			catch (Exception $e)
			{
				$Data['error'] = $e->getMessage();
			}
		}
		print json_encode((array)$Data);
	}
	/** clientCount()
		Display's the current client count on the activity graph
	*/
	public function clientcount()
	{
		$ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
		$StorageNode = new StorageNode($_REQUEST['id']);
		if ($StorageNode && $StorageNode->isValid())
		{
			foreach($this->getClass('StorageNodeManager')->find(array('isEnabled' => 1, 'storageGroupID' => $StorageNode->get('storageGroupID'))) AS $SN)
			{
				if ($SN && $SN->isValid())
				{
					$ActivityActive += $SN->getUsedSlotCount();
					$ActivityQueued += $SN->getQueuedSlotCount();
					$ActivityTotalClients += $SN->get('maxClients') - $SN->getUsedSlotCount();
				}
			}
		}
		$data = array(
			'ActivityActive' => $ActivityActive,
			'ActivityQueued' => $ActivityQueued,
			'ActivitySlots' => $ActivityTotalClients,
		);
		print json_encode($data);
	}
}
