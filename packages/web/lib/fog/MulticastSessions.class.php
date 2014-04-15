<?php
class MulticastSessions extends FOGController
{
	// Table
	public $databaseTable = 'multicastSessions';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'				=> 'msID',
		'name'				=> 'msName',
		'port'				=> 'msBasePort',
		'logpath'			=> 'msLogPath',
		'image'				=> 'msImage',
		'clients'			=> 'msClients',
		'interface'			=> 'msInterface',
		'starttime'			=> 'msStartDateTime',
		'percent'			=> 'msPercent',
		'stateID'			=> 'msState',
		'completetime'		=> 'msCompleteDateTime',
		'isDD'				=> 'msIsDD',
		'NFSGroupID'		=> 'msNFSGroupID',
		'anon3'				=> 'msAnon3',
		'anon4'				=> 'msAnon4',
		'anon5'				=> 'msAnon5',
	);

	public $additionalFields = array(
		'hosts',
	);

    public $databaseFieldClassRelationships	= array(
		'msID' => 'MulticastSessionAssociation',
		'stateID' => 'Task',
	);

	private function loadHosts()
	{
		if (!$this->isLoaded('hosts'))
		{
			if ($this->get('id'))
			{
				$this->DB->query("SELECT hosts.* FROM (select * from multicastSessions where msState in (0,1)) multicastSessions inner join multicastSessionsAssoc on ( multicastSessionsAssoc.msID = multicastSessions.msID inner join ( select * from tasks where taskStateID in (1, 2) ) tasks on ( multicastSessionsAssoc.tID = tasks.taskID )inner join hosts on (taskHostID = hostID)");
				while ($host = $this->DB->fetch()->get())
				{
					$hosts[] = new Host($host);
				}
			}
			$this->set('hosts',(array)$hosts);
		}
	}

	public function get($key = '')
	{
		if ($this->key($key) == 'hosts')
			$this->loadHosts();

		return parent::get($key);
	}

	public function set($key, $value)
	{
		if($this->key($key) == 'hosts')
		{
			$this->loadHosts();
			foreach ((array)$value AS $host)
			{
				$newValue[] = ($host instanceof Host ? $host : new Host($host));
			}

			$value = (array)$newValue;
		}

		return parent::set($key,$value);
	}

	public function add($key, $value)
	{
		if ($this>key($key) == 'hosts' && !($value instanceof Host))
		{
			$this->loadHosts();

			$value = new Host($value);
		}

		return parent::add($key, $value);
	}

	public function remove($key, $object)
	{
		if ($this->key($key) == 'hosts')
			$this->loadHosts();
		return parent::remove($key, $object);
	}
}
