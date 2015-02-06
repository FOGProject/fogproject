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
	public function __destruct()
	{
		parent::__destruct();
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
			foreach((array)$this->databaseFields AS $common => $dbField)
				$findWhere[$common] = $keyword;
			if ($classSearch == 'User')
				return $UserMan = $this->getClass('UserManager')->find($findWhere,'OR');
			// If the class to search is Host use the below for searching.
			$HostIDs = $this->getClass('HostManager')->find($findWhere,'OR','','','','','','id');
			$MACHosts = $this->getClass('MACAddressAssociationManager')->find(array('mac' => $keyword,'description' => $keyword),'OR','','','','','','hostID');
			$InventoryHosts = $this->getClass('InventoryManager')->find(array('sysserial' => $keyword,'caseserial' => $keyword,'mbserial' => $keyword,'primaryUser' => $keyword,'other1' => $keyword,'other2' => $keyword,'sysman' => $keyword,'sysproduct' => $keyword),'OR','','','','','','hostID');
			$HostIDs = array_unique((array)array_merge((array)$HostIDs,(array)$MACHosts,(array)$InventoryHosts));
			return $this->getClass('HostManager')->find(array('id' => $HostIDs));
			if ($classSearch == 'Host')
			{
				return array_values(array_unique($HostMan));
				$GroupMan = $this->getClass('GroupManager')->find(array('name' => $keyword,'description' => $keyword),'OR');
				foreach((array)$GroupMan AS $Group)
				{
					if ($Group && $Group->isValid())
					{
						foreach((array)$this->getClass('GroupAssociationManager')->find(array('groupID' => $Group->get('id'))) AS $GroupAssoc)
						{
							if ($GroupAssoc && $GroupAssoc->isValid())
								$Hosts[] = $GroupAssoc->get('hostID');
						}
					}
				}
				$ImageMan = $this->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword),'OR');
				foreach((array)$ImageMan AS $Image)
				{
					if ($Image && $Image->isValid())
					{
						foreach((array)$this->getClass('HostManager')->find(array('imageID' => $Image->get('id'))) AS $Host)
						{
							if ($Host && $Host->isValid())
								$Hosts[] = $Host->get('id');
						}
					}
				}
				$SnapinMan = $this->getClass('SnapinManager')->find(array('name' => $keyword,'description' => $keyword,'file' => $keyword),'OR');
				foreach((array)$SnapinMan AS $Snapin)
				{
					if ($Snapin && $Snapin->isValid())
					{
						foreach((array)$this->getClass('SnapinAssociationManager')->find(array('snapinID' => $Snapin->get('id'))) AS $SnapinAssoc)
						{
							if ($SnapinAssoc && $SnapinAssoc->isValid())
								$Hosts[] = $SnapinAssoc->get('hostID');
						}
					}
				}
				$PrinterMan = $this->getClass('PrinterManager')->find(array('name' => $keyword));
				foreach((array)$PrinterMan AS $Printer)
				{
					if ($Printer && $Printer->isValid())
					{
						foreach((array)$this->getClass('PrinterAssociationManager')->find(array('printerID' => $Printer->get('id'))) AS $PrinterAssoc)
						{
							if ($PrinterAssoc && $PrinterAssoc->isValid())
								$Hosts[] = $PrinterAssoc->get('hostID');
						}
					}
				}
				return array_unique((array)$Hosts);
				$Data = null;
				foreach((array)$Hosts AS $id)
				{
					$Host = new Host($id);
					if ($Host && $Host->isValid())
						$Data[] = $Host;
				}
				return array_unique((array)$Data);
			}
			// Only used in the future for other class files.
			$Hosts = array_unique((array)$Hosts);
			if ($classSearch == 'Group')
			{
				$GroupMan = $this->getClass('GroupManager')->find($findWhere,'OR');
				foreach((array)$GroupMan AS $Group)
				{
					if ($Group && $Group->isValid())
						$Data[] = $Group;
				}
				foreach((array)$Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach((array)$this->getClass('GroupAssociationManager')->find(array('hostID' => $Host->get('id'))) AS $GroupAssoc)
						{
							if ($GroupAssoc && $GroupAssoc->isValid())
								$Data[] = new Group($GroupAssoc->get('groupID'));
						}
					}
				}
			}
			else if ($classSearch == 'Image')
			{
				$ImageMan = $this->getClass('ImageManager')->find($findWhere,'OR');
				foreach((array)$ImageMan AS $Image)
				{
					if ($Image && $Image->isValid())
						$Data[] = $Image;
				}
				foreach((array)$Hosts AS $Host)
				{
					if ($Hosts && $Host->isValid() && $Host->getImage() && $Host->getImage()->isValid())
						$Data[] = $Host->getImage();
				}
			}
			else if ($classSearch == 'Snapin')
			{
				$SnapinMan = $this->getClass('SnapinManager')->find($findWhere,'OR');
				foreach((array)$SnapinMan AS $Snapin)
				{
					if ($Snapin && $Snapin->isValid())
						$Data[] = $Snapin;
				}
				foreach((array)$Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach((array)$this->getClass('SnapinAssociationManager')->find(array('hostID' => $Host->get('id'))) AS $SnapinAssoc)
						{
							if ($SnapinAssoc && $SnapinAssoc->isValid())
								$Data[] = new Snapin($SnapinAssoc->get('snapinID'));
						}
					}
				}
			}
			else if ($classSearch == 'Printer')
			{
				$PrinterMan = $this->getClass('PrinterManager')->find($findWhere,'OR');
				foreach((array)$PrinterMan AS $Printer)
				{
					if ($Printer && $Printer->isValid())
						$Data[] = $Printer;
				}
				foreach((array)$Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						foreach((array)$this->getClass('PrinterAssociationManager')->find(array('hostID' => $Host->get('id'))) AS $PrinterAssoc)
						{
							if ($PrinterAssoc && $PrinterAssoc->isValid())
								$Data[] = new Printer($PrinterAssoc->get('printerID'));
						}
					}
				}
			}
			else if ($classSearch == 'Task')
			{
				$TaskMan = $this->getClass('TaskManager')->find($findWhere,'OR');
				foreach((array)$TaskMan AS $Task)
				{
					if ($Task && $Task->isValid())
						$Data[] = $Task;
				}
				$TaskStateMan = $this->getClass('TaskStateManager')->find(array('name' => $keyword));
				foreach((array)$TaskStateMan AS $TaskState)
				{
					if ($TaskState && $TaskState->isValid())
						$TaskStates[] = $TaskState->get('id');
				}
				$TaskStates = array_values(array_unique((array)$TaskStates));
				$TaskTypeMan = $this->getClass('TaskTypeManager')->find(array('name' => $keyword));
				foreach((array)$TaskTypeMan AS $TaskType)
				{
					if ($TaskType && $TaskType->isValid())
						$TaskTypes[] = $TaskType->get('id');
				}
				$TaskTypes = array_values(array_unique((array)$TaskTypes));
				foreach((array)$Hosts AS $Host)
				{
					if ($Host && $Host->isValid())
						$HostIDs[] = $Host->get('id');
				}
				$HostIDs = array_values(array_unique((array)$HostIDs));
				$ImageMan = $this->getClass('ImageManager')->find(array('name' => $keyword));
				foreach((array)$ImageMan AS $Image)
				{
					if ($Image && $Image->isValid())
					{
						foreach((array)$this->getClass('HostManager')->find(array('imageID' => $Image->get('id'))) AS $Host)
						{
							if ($Host && $Host->isValid())
								$HostImages[] = $Host;
						}
						$HostImages = array_unique((array)$HostImages);
						foreach((array)$HostImages AS $Host)
						{
							if ($Host && $Host->isValid())
								array_push($HostIDs,$Host->get('id'));
						}
					}
				}
				$HostIDs = array_values(array_unique((array)$HostIDs));
				$findWhere = array('typeID' => $TaskTypes,'stateID' => $TaskStates,'hostID' => $HostIDs);
				$TaskMan = $this->getClass('TaskManager')->find($findWhere,'OR');
				foreach((array)$TaskMan AS $Task)
				{
					if ($Task && $Task->isValid())
						$Data[] = $Task;
				}
			}
			$Data = array_unique((array)$Data,SORT_REGULAR);
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
	public function find($where = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC',$compare = '=',$groupBy = false,$not = false,$idField = false)
	{
		try
		{
			$getFields = trim(implode(array_keys($this->databaseFieldsFlipped),'`,`'),',');
			if ($idField || (!$where && !$whereOperator && !$orderBy && !$sort && !$compare && !$groupBy && !$not && !$idField))
			{
				$getFields = $this->databaseFields[$idField ? $idField : 'id'];
				$idField = $idField ? $idField : 'id';
			}
			if (empty($compare))
				$compare = '=';
			// Fail safe defaults
			if (empty($where))
				$where = array();
			if (empty($whereOperator))
				$whereOperator = 'AND';
			if (empty($orderBy))
			{
				if ($this->databaseFields['name'])
					$orderBy = 'name';
				else
					$orderBy = 'id';
			}
			else if (!$this->databaseFields[$orderBy])
				$orderBy = 'id';
			$not = ($not ? ' NOT ' : '');
			// Error checking
			if (empty($this->databaseTable))
				throw new Exception('No database table defined');
			// Create Where Array
			if (count($where))
			{
				foreach((array)$where AS $field => $value)
				{
					if (is_array($value))
						$whereArray[] = sprintf("`%s`%sIN ('%s')", $this->key($field), $not,implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->key($field), (preg_match('#%#', $value) ? 'LIKE' : $compare), $value);
				}
			}
			foreach((array)$orderBy AS $item)
			{
				if ($this->databaseFields[$item])
					$orderArray[] = sprintf("`%s`",$this->databaseFields[$item]);
			}
			foreach((array)$groupBy AS $item)
			{
				if ($this->databaseFields[$item])
					$groupArray[] = sprintf("`%s`",$this->databaseFields[$item]);
			}
			if ($groupBy)
			{
				$sql = "SELECT %s`%s` FROM (SELECT %s`%s` FROM `%s` %s %s %s) AS tmp %s %s %s";
				$fieldValues = array(
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					$this->databaseTable,
					(count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
					'ORDER BY '.trim(implode($orderArray,','),','),
					$sort,
					'GROUP BY '.trim(implode($groupArray,','),','),
					'ORDER BY '.trim(implode($orderArray,','),','),
					$sort
				);
			}
			else
			{
				$sql = "SELECT %s`%s` FROM `%s` %s %s %s";
				$fieldValues = array(
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					$this->databaseTable,
					(count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
					'ORDER BY '.trim(implode($orderArray,','),','),
					$sort
				);
			}
			$data = array();
			// Select all
			$this->DB->query($sql,$fieldValues);
			if ($idField)
			{
				while($id = $this->DB->fetch(MYSQLI_NUM)->get($idField))
					$ids[] = $id[0];
				return array_unique((array)$ids);
			}
			else
			{
				while($row = $this->DB->fetch()->get())
					array_push($data,new $this->childClass($row));
			}
			unset($id,$ids,$row);
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
	public function count($where = array(), $whereOperator = 'AND', $compare = '=')
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
				foreach((array)$where AS $field => $value)
				{
					if (is_array($value))
						$whereArray[] = sprintf("`%s` IN ('%s')", $this->key($field), implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->key($field), (preg_match('#%#', $value) ? 'LIKE' : $compare), $value);
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
	public function buildSelectBox($matchID = '', $elementName = '', $orderBy = 'name', $filter = '')
	{
		$matchID = ($_REQUEST['node'] == 'image' ? ($matchID === '0' ? '1' : $matchID) : $matchID);
		if (empty($elementName))
			$elementName = strtolower($this->childClass);
		foreach((array)$this->find('','',$orderBy) AS $Object)
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
	public function exists($name, $id = 0, $idfield = 'id')
	{
		if (empty($idfield))
			$idfield = 'id';
		$this->DB->query("SELECT COUNT(%s) AS total FROM `%s` WHERE `%s` = '%s' AND `%s` <> '%s'", 
			array(	
				$this->databaseFields[$idfield],
				$this->databaseTable,
				$this->databaseFields['name'],
				$name,
				$this->databaseFields[$idfield],
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
