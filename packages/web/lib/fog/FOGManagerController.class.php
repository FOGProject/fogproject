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
	// DEBUG mode - print all debug messages
	/** Prints the messages if true. */
	public $debug = true;
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
	public function search($keyword = '%')
	{
		try
		{
			// Error checking
			if (empty($this->searchQuery))
				throw new Exception('No query defined');
			if (empty($keyword))
				throw new Exception('No keyword passed');
			// Build query
			$keyword = preg_replace(array('#\*#', '#[[:space:]]#'), array('%', '%'), $keyword);
			$query = preg_replace(array('#\$\{keyword\}#'), array($keyword), $this->searchQuery);
			// Execute query -> Build new object -> Push into data array
			$allSearchResults = $this->DB->query($query);
			while ($searchResult = $this->DB->fetch()->get())
			{
				$data[] = new $this->childClass($searchResult);
			}
			// Return
			return (array)$data;
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
						$whereArray[] = sprintf("`%s` IN ('%s')", $this->key($field), implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->key($field), (preg_match('#%#', $value) ? 'LIKE' : '='), $value);
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
						$whereArray[] = sprintf("`%s` IN ('%s')", $this->key($field), implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->key($field), (preg_match('#%#', $value) ? 'LIKE' : '='), $value);
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
		foreach($this->find('','',$order) AS $Object)
		{
			if (!in_array($Object->get('id'),(array)$filter))
				$listArray[] = '<option value="'.$Object->get('id').'"'.($matchID == $Object->get('id') ? ' selected="selected"' : '' ).'>'.$Object->get('name').' - ('.$Object->get('id').')</option>';
		}
		//return (isset($listArray) ? sprintf('<select name="'.$elementName.'" autocomplete="off"><option value="">- Please Select an option -</option>'.implode("\n",$listArray)) : false).'</select>';
		return (isset($listArray) ? sprintf('<select name="%s" autocomplete="off"><option value="">%s</option>%s</select>',$elementName,'- '._('Please select an option').' -',implode("\n",$listArray)) : false);
	}
	// TODO: Read DB fields from child class
	/** exists($name, $id = 0)
		Finds if the item already exists in the database.
	*/
	function exists($name, $id = 0)
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
		/*
		if (array_key_exists($key, $this->databaseFieldsFlipped))
		{
			return $this->databaseFieldsFlipped[$key];
		}
		*/
		return $key;
	}
}
