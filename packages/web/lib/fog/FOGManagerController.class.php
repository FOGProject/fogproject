<?php
/** \class FOGManagerController
	Used by the Manager Class files as the base
	for these files.
*/
abstract class FOGManagerController extends FOGBase
{
	// Table
	/** Sets the table the manager class needs to look for
		it's elements.
	*/
	public $databaseTable = '';
	// Search query
	/** Sets the search query to pull information.
		Alternate is find().
	*/
	public $searchQuery = '';
	// Child class name variables
	/** For the child class */
	protected $childClass;
	/** The variables of the class */
	protected $classVariables;
	/** The database fields. */
	protected $databaseFields;
	/** The database to class relationships. */
	protected $databaseFieldClassRelationships;
	// Construct
	/** __construct()
		Different constructor from FOG Base
	*/
	public function __construct()
	{
		// FOGBase contstructor
		parent::__construct();
		// Set child classes name
		$this->childClass = preg_replace('#_?Manager$#', '', get_class($this));
		// Get child class variables
		$this->classVariables = get_class_vars($this->childClass);
		// Set required child variable data
		$this->databaseFields = $this->classVariables['databaseFields'];
		$this->databaseFieldsFlipped = array_flip($this->databaseFields);
		$this->databaseTable = $this->classVariables['databaseTable'];
		$this->databaseFieldClassRelationships = $this->classVariables['databaseFieldClassRelationships'];
	}
	// Search
	/** search($keyword = '%') defaults the search
		part to use the wildcard.
	*/
	public function search($keyword = '%',$classSearch = 'Host')
	{
		try
		{
			$Data = null;
			if (empty($keyword))
				throw new Exception('No keyword passed');
			foreach($this->databaseFields AS $common => $dbField)
				$findWhere[$common] = $keyword;
			// Get all hosts with matching keyword of hostname value
			// If the class to search is not Host use the below for searching.
			if ($classSearch != 'Host')
				$HostMan = $this->FOGCore->getClass('HostManager')->find(array('name' => $keyword,'mac' => $keyword,'description' => $keyword,'ip' => $keyword),'OR');
			// If the class to search is Host use the below for searching.
			if ($classSearch == 'Host')
				$HostMan = $this->FOGCore->getClass('HostManager')->find($findWhere,'OR');
			foreach($HostMan AS $Host)
			{
				if ($Host && $Host->isValid() && !$Host->get('pending'))
					$Hosts[] = $Host;
			}
			$AdditionMacMan = $this->FOGCore->getClass('MACAddressAssociationManager')->find(array('mac' => $keyword,'description' => $keyword),'OR');
			foreach($AdditionMacMan AS $HostAdd)
			{
				if ($HostAdd && $HostAdd->isValid())
					$Hosts[] = new Host($HostAdd->get('hostID'));
			}
			$PendingMac = $this->FOGCore->getClass('PendingMACManager')->find(array('pending' => $keyword));
			foreach($PendingMac AS $PendMAC)
			{
				if ($PendMAC && $PendMAC->isValid())
					$Hosts[] = new Host($PendMAC->get('hostID'));
			}
			$InventoryMan = $this->FOGCore->getClass('InventoryManager')->find(array('sysserial' => $keyword,'caseserial' => $keyword,'mbserial' => $keyword,'primaryUser' => $keyword,'other1' => $keyword,'other2' => $keyword,'sysman' => $keyword,'sysproduct' => $keyword),'OR');
			foreach($InventoryMan AS $Inventory)
			{
				if ($Inventory && $Inventory->isValid())
					$Hosts[] = new Host($Inventory->get('hostID'));
			}
			if ($classSearch == 'Host')
			{
				$GroupMan = $this->FOGCore->getClass('GroupManager')->find(array('name' => $keyword,'description' => $keyword),'OR');
				foreach($GroupMan AS $Group)
				{
					if ($Group && $Group->isValid())
					{
						foreach($this->FOGCore->getClass('GroupAssociationManager')->find(array('groupID' => $Group->get('id'))) AS $GroupAssoc)
						{
							if ($GroupAssoc && $GroupAssoc->isValid())
								$Hosts[] = new Host($GroupAssoc->get('hostID'));
						}
					}
				}
				$ImageMan = $this->FOGCore->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword),'OR');
				foreach($ImageMan AS $Image)
				{
					if ($Image && $Image->isValid())
					{
						foreach($this->FOGCore->getClass('HostManager')->find(array('imageID' => $Image->get('id'))) AS $Host)
						{
							if ($Host && $Host->isValid())
								$Hosts[] = $Host;
						}
					}
				}
				$SnapinMan = $this->FOGCore->getClass('SnapinManager')->find(array('name' => $keyword,'description' => $keyword,'file' => $keyword),'OR');
				foreach($SnapinMan AS $Snapin)
				{
					if ($Snapin && $Snapin->isValid())
					{
						foreach($this->FOGCore->getClass('SnapinAssociationManager')->find(array('snapinID' => $Snapin->get('id'))) AS $SnapinAssoc)
						{
							if ($SnapinAssoc && $SnapinAssoc->isValid())
								$Hosts[] = new Host($SnapinAssoc->get('hostID'));
						}
					}
				}
				$PrinterMan = $this->FOGCore->getClass('PrinterManager')->find(array('name' => $keyword));
				foreach($PrinterMan AS $Printer)
				{
					if ($Printer && $Printer->isValid())
					{
						foreach($this->FOGCore->getClass('PrinterAssociationManager')->find(array('printerID' => $Printer->get('id'))) AS $PrinterAssoc)
						{
							if ($PrinterAssoc && $PrinterAssoc->isValid())
								$Hosts[] = new Host($PrinterAssoc->get('hostID'));
						}
					}
				}
				$Data = array_unique($Hosts);
			}
			// Only used in the future for other class files.
			$Hosts = array_unique((array)$Hosts);
			if ($classSearch == 'Group')
			{
				$GroupMan = $this->FOGCore->getClass('GroupManager')->find($findWhere,'OR');
				foreach($GroupMan AS $Group)
				{
					if ($Group && $Group->isValid())
						$Data[] = $Group;
				}
				foreach($Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach($this->FOGCore->getClass('GroupAssociationManager')->find(array('hostID' => $Host->get('id'))) AS $GroupAssoc)
						{
							if ($GroupAssoc && $GroupAssoc->isValid())
								$Data[] = new Group($GroupAssoc->get('groupID'));
						}
					}
				}
			}
			if ($classSearch == 'Image')
			{
				$ImageMan = $this->FOGCore->getClass('ImageManager')->find($findWhere,'OR');
				foreach($ImageMan AS $Image)
				{
					if ($Image && $Image->isValid())
						$Data[] = $Image;
				}
				foreach($Hosts AS $Host)
				{
					if ($Hosts && $Host->isValid() && $Host->getImage() && $Host->getImage()->isValid())
						$Data[] = $Host->getImage();
				}
			}
			if ($classSearch == 'Snapin')
			{
				$SnapinMan = $this->FOGCore->getClass('SnapinManager')->find($findWhere,'OR');
				foreach($SnapinMan AS $Snapin)
				{
					if ($Snapin && $Snapin->isValid())
						$Data[] = $Snapin;
				}
				foreach($Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach($this->FOGCore->getClass('SnapinAssociationManager')->find(array('hostID' => $Host->get('id'))) AS $SnapinAssoc)
						{
							if ($SnapinAssoc && $SnapinAssoc->isValid())
								$Data[] = new Snapin($SnapinAssoc->get('snapinID'));
						}
					}
				}
			}
			if ($classSearch == 'Printer')
			{
				$PrinterMan = $this->FOGCore->getClass('PrinterManager')->find($findWhere,'OR');
				foreach($PrinterMan AS $Printer)
				{
					if ($Printer && $Printer->isValid())
						$Data[] = $Printer;
				}
				foreach($Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach($this->FOGCore->getClass('PrinterAssociationManager')->find(array('hostID' => $Host->get('id'))) AS $PrinterAssoc)
						{
							if ($PrinterAssoc && $PrinterAssoc->isValid())
								$Data[] = new Printer($PrinterAssoc->get('printerID'));
						}
					}
				}
			}
			if ($classSearch == 'Task')
			{
				$TaskMan = $this->FOGCore->getClass('TaskManager')->find($findWhere,'OR');
				foreach($TaskMan AS $Task)
				{
					if ($Task && $Task->isValid())
						$Data[] = $Task;
				}
				foreach($Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach($this->FOGCore->getClass('TaskManager')->find(array('hostID' => $Host->get('id'))) AS $Task)
						{
							if ($Task && $Task->isValid())
								$Data[] = $Task;
						}
					}
				}
				$TaskStateMan = $this->FOGCore->getClass('TaskStateManager')->find(array('name' => $keyword));
				foreach($TaskStateMan AS $TaskState)
				{
					if ($TaskState && $TaskState->isValid())
					{
						foreach($this->FOGCore->getClass('TaskManager')->find(array('stateID' => $TaskState->get('id'))) AS $Task)
						{
							if ($Task && $Task->isValid())
								$Data[] = $Task;
						}
					}
				}
				$TaskTypeMan = $this->FOGCore->getClass('TaskTypeManager')->find(array('name' => $keyword));
				foreach($TaskTypeMan AS $TaskType)
				{
					if ($TaskType && $TaskType->isValid())
					{
						foreach($this->FOGCore->getClass('TaskManager')->find(array('typeID' => $TaskType->get('id'))) AS $Task)
						{
							if ($Task && $Task->isValid())
								$Data[] = $Task;
						}
					}
				}
				$ImageMan = $this->FOGCore->getClass('ImageManager')->find(array('name' => $keyword));
				foreach($ImageMan AS $Image)
				{
					if ($Image && $Image->isValid())
					{
						foreach($this->FOGCore->getClass('HostManager')->find(array('imageID' => $Image->get('id'))) AS $Host)
						{
							if ($Host && $Host->isValid())
								$Hosts[] = $Host;
						}
						$Hosts = array_unique($Hosts);
						foreach($Hosts AS $Host)
						{
							if ($Host && $Host->isValid())
							{			
								foreach($this->FOGCore->getClass('TaskManager')->find(array('hostID' => $Host->get('id'))) AS $Task)
								{
									if ($Task && $Task->isValid())
										$Data[] = $Task;
								}
							}
						}
					}
				}
			}
			$Data = array_unique($Data);
			return (array)$Data;
		}
		catch (Exception $e)
		{
			$this->debug('Search failed! Error: %s', array($e->getMessage()));
		}
		return false;
	}
	/** find($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC')
		Pulls the information from the database into the resepective class file.
	*/
	public function find($where = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC')
	{
		try
		{
			// Fail safe defaults
			if (empty($where))
				$where = array();
			if (empty($whereOperator))
				$whereOperator = 'AND';
			// Error checking
			if (empty($this->databaseTable))
				throw new Exception('No database table defined');
			// Create Where Array
			if (count($where))
			{
				foreach ($where AS $field => $value)
				{
					if (is_array($value))
						$whereArray[] = sprintf("`%s` IN ('%s')", $this->DB->sanitize($this->key($field)), implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->DB->sanitize($this->key($field)), (preg_match('#%#', $value) ? 'LIKE' : '='), $value);
				}
			}
			// Select all
			$this->DB->query("SELECT * FROM `%s`%s ORDER BY `%s` %s", array(
				$this->databaseTable,
				(count($whereArray) ? ' WHERE ' . implode(' ' . $whereOperator . ' ', $whereArray) : ''),
				($this->databaseFields[$orderBy] ? $this->databaseFields[$orderBy] : $this->databaseFields['id']),
				$sort
			));
			while ($row = $this->DB->fetch()->get())
			{
				//$data[] = $row;
				$data[] = new $this->childClass($row);
			}
			// Return
			return (array)$data;
		}
		catch (Exception $e)
		{
			$this->debug('Find all failed! Error: %s', array($e->getMessage()));
		}
		return false;
	}
	/** count($where = array(),$whereOperator = 'AND')
		Returns the count of the database.
	*/
	public function count($where = array(), $whereOperator = 'AND')
	{
		try
		{
			// Fail safe defaults
			if (empty($where))
				$where = array();
			if (empty($whereOperator))
				$whereOperator = 'AND';
			// Error checking
			if (empty($this->databaseTable))
				throw new Exception('No database table defined');
			// Create Where Array
			if (count($where))
			{
				foreach ($where AS $field => $value)
				{
					if (is_array($value))
						$whereArray[] = sprintf("`%s` IN ('%s')", $this->DB->sanitize($this->key($field)), implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->DB->sanitize($this->key($field)), (preg_match('#%#', $value) ? 'LIKE' : '='), $value);
				}
			}
			// Count result rows
			$this->DB->query("SELECT COUNT(%s) AS total FROM `%s`%s LIMIT 1", array(
				$this->databaseFields['id'],
				$this->databaseTable,
				(count($whereArray) ? ' WHERE ' . implode(' ' . $whereOperator . ' ', $whereArray) : '')
			));
			// Return
			return (int)$this->DB->fetch()->get('total');
		}
		catch (Exception $e)
		{
			$this->debug('Find all failed! Error: %s', array($e->getMessage()));
		}
		return false;
	}
	// Blackout - 12:09 PM 26/04/2012
	// NOTE: VERY! powerful... use with care
	/** destroy($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC')
		Removes the relevant fields from the database.
	*/
	public function destroy($where = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC')
	{
		foreach ((array)$this->find($where, $whereOperator, $orderBy, $sort) AS $object)
			$object->destroy();
		return true;
	}
	// Blackout - 11:28 AM 22/11/2011
	/** buildSelectBox($matchID = '',$elementName = '',$orderBy = 'name')
		Builds a select box for the class values found.
	*/
	function buildSelectBox($matchID = '', $elementName = '', $orderBy = 'name', $filter = '')
	{
		$matchID = ($_REQUEST['node'] == 'images' ? ($matchID === '0' ? '1' : $matchID) : $matchID);
		if (empty($elementName))
			$elementName = strtolower($this->childClass);
		foreach($this->find('','',$orderBy) AS $Object)
		{
			if (!in_array($Object->get('id'),(array)$filter))
				$listArray[] = '<option value="'.$Object->get('id').'"'.($matchID == $Object->get('id') ? ' selected="selected"' : '' ).'>'.$Object->get('name').' - ('.$Object->get('id').')</option>';
		}
		return (isset($listArray) ? sprintf('<select name="%s" autocomplete="off"><option value="">%s</option>%s</select>',$elementName,'- '.$this->foglang['PleaseSelect'].' -',implode("\n",$listArray)) : false);
	}
	// TODO: Read DB fields from child class
	/** exists($name, $id = 0)
		Finds if the item already exists in the database.
	*/
	public function exists($name, $id = 0)
	{
		$this->DB->query("SELECT COUNT(%s) AS total FROM `%s` WHERE `%s` = '%s' AND `%s` <> '%s'", 
			array(	
				$this->databaseFields['id'],
				$this->databaseTable,
				$this->databaseFields['name'],
				$name,
				$this->databaseFields['id'],
				$id
			)
		);
		return ($this->DB->fetch()->get('total') ? true : false);
	}
	// Key
	/** key($key)
		Returns the key's of the database fields.
	*/
	public function key($key)
	{
		if (array_key_exists($key, $this->databaseFields))
			return $this->databaseFields[$key];
		// Cannot be used until all references to acual field names are converted to common names
		if (array_key_exists($key, $this->databaseFieldsFlipped))
			return $this->databaseFieldsFlipped[$key];
		return $key;
	}
}
