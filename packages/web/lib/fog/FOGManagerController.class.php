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
	public function search()
	{
		try
		{
			$keyword = preg_replace('#%+#', '%', '%'.preg_replace('#[[:space:]]#', '%', preg_replace('#[?*]*#','%',$_REQUEST['crit'])) . '%');
			$_SESSION['caller'] = __FUNCTION__;
			if (empty($keyword))
				throw new Exception('No keyword passed');
			foreach((array)$this->databaseFields AS $common => $dbField)
			{
				if ($common != 'createdBy')
					$findWhere[$common] = $keyword;
			}
			if ($this->classClass == 'User')
				return $this->getClass('UserManager')->find($findWhere,'OR');
			$HostIDs = ($this->childClass == 'Host' ? $this->getClass('HostManager')->find($findWhere,'OR','','','','','','id') : $this->getClass('HostManager')->find(array('name' => $keyword,'description' => $keyword,'ip' => $keyword),'OR','','','','','','id'));
			// Get all the hosts host search is different than other searches
			$MACHosts = $this->getClass('MACAddressAssociationManager')->find(array('mac' => $keyword,'description' => $keyword),'OR','','','','','','hostID');
			$InventoryHosts = $this->getClass('InventoryManager')->find(array('sysserial' => $keyword,'caseserial' => $keyword,'mbserial' => $keyword,'primaryUser' => $keyword,'other1' => $keyword,'other2' => $keyword,'sysman' => $keyword,'sysproduct' => $keyword),'OR','','','','','','hostID');
			$HostIDs = array_unique(array_merge((array)$HostIDs,(array)$MACHosts,(array)$InventoryHosts));
			// Get the IDs of the objects we are trying to "scan" for
			if ($this->childClass == 'Host')
			{
				$GroupIDs = $this->getClass('GroupManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$ImageIDs = $this->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$SnapinIDs = $this->getClass('SnapinManager')->find(array('name' => $keyword,'description' => $keyword,'file' => $keyword),'OR','','','','','','id');
				$PrinterIDs = $this->getClass('PrinterManager')->find(array('name' => $keyword),'OR','','','','','','id');
				$GroupHostIDs = $this->getClass('GroupAssociationManager')->find(array('groupID' => $GroupIDs),'','','','','','','hostID');
				$HostIDs = array_unique(array_merge((array)$HostIDs,(array)$GroupHostIDs,(array)$ImageHostIDs,(array)$SnapinHostIDs,(array)$PrinterHostIDs));
				$findWhere = array('id' => $HostIDs);
				$ImageHostIDs = $this->getClass('HostManager')->find(array('imageID' => $ImageIDs),'','','','','','','id');
				$SnapinHostIDs = $this->getClass('SnapinAssociationManager')->find(array('snapinID' => $SnapinIDs),'','','','','','','hostID');
				$PrinterHostIDs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $PrinterIDs),'','','','','','','hostID');
				unset($GroupIDs,$ImageIDs,$SnapinIDs,$PrinterIDs,$GroupHostIDs,$ImageHostIDs,$SnapinHostIDs,$PrinterHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Group')
			{
				$GroupIDs = $this->getClass('GroupManager')->find($findWhere,'OR','','','','','','id');
				$GroupHostIDs = $this->getClass('GroupAssociationManager')->find(array('hostID' => $HostIDs),'','','','','','','groupID');
				$GroupIDs = array_unique(array_merge((array)$GroupIDs,(array)$GroupHostIDs));
				$findWhere = array('id' => $GroupIDs);
				unset($GroupIDs,$GroupHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Image')
			{
				$ImageIDs = $this->getClass('ImageManager')->find($findWhere,'OR','','','','','','id');
				$ImageHostIDs = $this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','','','imageID');
				$ImageIDs = array_unique(array_merge((array)$ImageIDs,(array)$ImageHostIDs));
				$findWhere = array('id' => $ImageIDs);
				unset($ImageIDs,$ImageHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Snapin')
			{
				$SnapinIDs = $this->getClass('SnapinManager')->find($findWhere,'OR','','','','','','id');
				$SnapinHostIDs = $this->getClass('SnapinAssociationManager')->find(array('hostID' => $HostIDs),'','','','','','','snapinID');
				$SnapinIDs = array_unique(array_merge((array)$SnapinIDs,(array)$SnapinHostIDs));
				$findWhere = array('id' => $SnapinIDs);
				unset($SnapinIDs,$SnapinHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Printer')
			{
				$PrinterIDs = $this->getClass('PrinterManager')->find($findWhere,'OR','','','','','','id');
				$PrinterHostIDs = $this->getClass('PrinterAssociationManager')->find(array('hostID' => $HostIDs),'','','','','','','printerID');
				$PrinterIDs = array_unique(array_merge((array)$PrinterIDs,(array)$PrinterHostIDs));
				$findWhere = array('id' => $PrinterIDs);
				unset($PrinterIDs,$PrinterHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Task')
			{
				$TaskIDs = $this->getClass('TaskManager')->find($findWhere,'OR','','','','','','id');
				$TaskStateIDs = $this->getClass('TaskStateManager')->find(array('name' => $keyword),'','','','','','','id');
				$TaskTypeIDs = $this->getClass('TaskTypeManager')->find(array('name' => $keyword),'','','','','','','id');
				$GroupIDs = $this->getClass('GroupManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$ImageIDs = $this->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$SnapinIDs = $this->getClass('SnapinManager')->find(array('name' => $keyword,'description' => $keyword,'file' => $keyword),'OR','','','','','','id');
				$PrinterIDs = $this->getClass('PrinterManager')->find(array('name' => $keyword),'OR','','','','','','id');
				$GroupHostIDs = $this->getClass('GroupAssociationManager')->find(array('groupID' => $GroupIDs),'','','','','','','hostID');
				$ImageHostIDs = $this->getClass('HostManager')->find(array('imageID' => $ImageIDs),'','','','','','','id');
				$SnapinHostIDs = $this->getClass('SnapinAssociationManager')->find(array('snapinID' => $SnapinIDs),'','','','','','','hostID');
				$PrinterHostIDs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $PrinterIDs),'','','','','','','hostID');
				$HostIDs = array_unique(array_merge((array)$HostIDs,(array)$GroupHostIDs,(array)$ImageHostIDs,(array)$SnapinHostIDs,(array)$PrinterHostIDs));
				$findWhere = array('id' => $TaskIDs,'typeID' => $TaskTypeIDs,'stateID' => $TaskStateIDs,'hostID' => $HostIDs);
				unset($TaskIDs,$TaskTypeIDs,$GroupIDs,$ImageIDs,$SnapinIDs,$PrinterIDs,$GroupHostIDs,$ImageHostIDs,$SnapinHostIDs,$PrinterHostIDs,$HostIDs);
			}
			unset($_SESSION['caller']);
			return $this->childClass == 'Task' ? $this->getClass($this->childClass.'Manager')->find($findWhere,'OR') : array_unique($this->getClass($this->childClass.'Manager')->find((array)$findWhere,'OR'),SORT_REGULAR);
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
	public function find($where = '', $whereOperator = '', $orderBy = '', $sort = '',$compare = '',$groupBy = false,$not = false,$idField = false)
	{
		try
		{
			$getFields = trim(implode(array_keys($this->databaseFieldsFlipped),'`,`'),',');
			if ($idField || (!$where && !$whereOperator && !$orderBy && !$sort && !$compare && !$groupBy && !$not && !$idField))
			{
				if (strtolower($_SESSION['caller']) == 'search')
				{
					$getFields = $this->databaseFields[$idField ? $idField : 'id'];
					$idField = $idField ? $idField : 'id';
				}
				else
				{
					if ($idField)
						$getFields = array_key_exists($idField,$this->databaseFields) ? $this->databaseFields[$idField] : $this->databaseFields['id'];
				}
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
			if ($this->databaseFieldClassRelationships)
			{
				foreach($this->databaseFieldClassRelationships AS $class => $field)
				{
					$class = new $class();
					$getFields .= '`,`'.$class->databaseTable.'`.`'.trim(implode(array_keys($class->databaseFieldsFlipped),'`,`'.$class->databaseTable.'`.`'),',');
					$innerJoin[] = sprintf(' LEFT OUTER JOIN `%s` ON %s=%s ',$class->databaseTable,$class->databaseTable.'.'.$class->databaseFields[$field[0]],$this->databaseFields[$field[1]]);
				}
			}
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
				$sql = "SELECT %s`%s` FROM (SELECT `%s` FROM `%s` %s %s %s %s) AS tmp %s %s %s";
				$fieldValues = array(
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					//(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					$this->databaseTable,
					count($innerJoin) ? implode($innerJoin) : '',
					(count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
					'ORDER BY '.trim(implode($orderArray,','),','),
					$sort,
					count($innerJoin) ? implode($innerJoin) : '',
					'GROUP BY '.trim(implode($groupArray,','),','),
					'ORDER BY '.trim(implode($orderArray,','),','),
					$sort
				);
			}
			else
			{
				$sql = "SELECT %s`%s` FROM `%s` %s %s %s %s";
				$fieldValues = array(
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					$this->databaseTable,
					count($innerJoin) ? implode($innerJoin) : '',
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
				$data = array_unique((array)$ids);
			}
			else
			{
				while($row = $this->DB->fetch()->get())
				{
					$mainclass = new $this->childClass($row);
					array_push($data,$mainclass);
				}
				foreach($data AS $mainclass)
				{
					if ($this->databaseFieldClassRelationships)
					{
						foreach($this->databaseFieldClassRelationships AS $class => $field)
						{
							while($row = $this->DB->fetch()->get($this->getClass($class)->databaseTable.'.'.$field[3]))
							{
								$class = new $class($this->DB->get($field[3]));
								$mainclass->add($field[2],$class);
							}
						}
					}
				}
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
		$matchID = ($_REQUEST['node'] == 'image' ? ($matchID === 0 ? 1 : $matchID) : $matchID);
		if (empty($elementName))
			$elementName = strtolower($this->childClass);
		foreach($this->find($filter ? array('id' => $filter) : '','',$orderBy,'','','',($filter ? true : false)) AS $Object)
			$listArray[] = '<option value="'.$Object->get('id').'"'.($matchID == $Object->get('id') ? ' selected' : '').'>'.$Object->get('name').' - ('.$Object->get('id').')</option>';
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
