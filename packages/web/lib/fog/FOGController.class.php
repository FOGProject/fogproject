<?php
/** Class Name: FOGController
	Controller that extends FOGBase
	Gets the database information
	and returns it to be used as needed.
*/
abstract class FOGController extends FOGBase
{
	// Table
	/** Gets the database table */
	public $databaseTable = '';
	// Name -> Database field name
	/** Gets the database fields */
	public $databaseFields = array();
	// ->load() queries, this way subclasses can override (ie: NodeFailure)
	/** The standard query template for a single call */
	protected $loadQueryTemplateSingle = "SELECT DISTINCT `%s` FROM `%s` `%s` %s WHERE `%s`='%s'";
	/** The standard query template for a multiple call */
	protected $loadQueryTemplateMultiple = "SELECT DISTINCT `%s` FROM `%s` %s %s WHERE %s";
	// Do not update these database fields
	/** Ignore these fileds */
	public $databaseFieldsToIgnore = array(
		'createdBy',
		'createdTime'
	);
	// Allow setting / getting of these additional fields
	/** set or get additional fields. */
	public $additionalFields = array();
	// Required database fields
	/** Required Database Fields */
	public $databaseFieldsRequired = array();
	// Store data array
	/** The data to return */
	protected $data = array();
	// Auto save class data on __destruct
	/** If true will save db on exit auto matically */
	public $autoSave = false;
	// Database field to Class relationship
	/** For classes that are assigned to a particular field */
	public $databaseFieldClassRelationships = array();
	/** To tell what class relationships are required **/
	public $databaseNeededFieldClassRelationships = array();
	/** The Manager of the info. */
	private $Manager;
	// Construct
	/** __construct($data)
		The main constructor for the controller.
		Builds the database fields as needed.
	*/
	public function __construct($data)
	{
		// FOGBase contstructor
		parent::__construct();
		try
		{
			$this->debug = false;
			$this->info = false;
			// Error checking
			if (!count($this->databaseFields))
				throw new Exception('No database fields defined for this class!');
			// Flip database fields and common name - used multiple times
			$this->databaseFieldsFlipped = array_flip($this->databaseFields);
			// Created By
			if (array_key_exists('createdBy', $this->databaseFields) && !empty($_SESSION['FOG_USERNAME']))
				$this->set('createdBy', $this->DB->sanitize($_SESSION['FOG_USERNAME']));
			if (array_key_exists('createdTime', $this->databaseFields))
				$this->set('createdTime', $this->nice_date()->format('Y-m-d H:i:s'));
			// Add incoming data
			if (is_array($data))
			{
				// Iterate data -> Set data
				foreach ($data AS $key => $value)
					$this->set($this->key($key), $value);
			}
			// If incoming data is an INT -> Set as ID -> Load from database
			elseif (is_numeric($data))
			{
				if ($data === 0 || $data < 0)
					throw new Exception(sprintf('No data passed, or less than zero, Value: %s', $data));
				$this->set('id', $data)->load();
			}
		}
		catch (Exception $e)
		{
			$this->error('Record not found, Error: %s', array($e->getMessage()));
		}
		return $this;
	}
	// Destruct
	/** __destruct()
		At close of class, it trys to save the information if autoSave is enabled for that class.
	*/
	public function __destruct()
	{
		// Auto save
		if ($this->autoSave)
			$this->save();
		parent::__destruct();
	}
	// Set
	/** set($key, $value)
		Set's the fields relevent for that class.
	*/
	public function set($key, $value)
	{
		try
		{
			$this->info('Setting Key: %s, Value: %s',array($key,$value));
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key, $this->databaseNeededFieldClassRelationships) && !array_key_exists($key,$this->databaseFieldClassRelationships))
				throw new Exception('Invalid key being set');
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			$this->data[$key] = $value;
		}
		catch (Exception $e)
		{
			$this->debug('Set Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
		}
		return $this;
	}
	// Get
	/** get($key = '')
		Get's all fields or the specified field for the class member.
	*/
	public function get($key = '')
	{
		return ($key && isset($this->data[$key]) ? $this->data[$key] : (!$key ? $this->data : ''));
	}
	// Add
	/** add($key, $value)
		Used to add a new field to the database relevant to the class.
		Could potentially be used to add a new moderation field to the database??
	*/
	public function add($key, $value)
	{
		try
		{
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key, $this->databaseNeededFieldClassRelationships) && !array_key_exists($key,$this->databaseFieldClassRelationships))
				throw new Exception('Invalid data being set');
			$this->info('Adding Key: %s, Value: %s',array($key,$value));
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			$this->data[$key][] = $value;
		}
		catch (Exception $e)
		{
			$this->debug('Add Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
		}
		return $this;
	}
	// Remove
	/** remove($key, $object)
		Removes a field from the relevant class caller.
		Can be used to remove fields from the database??
	*/
	public function remove($key, $object)
	{
		try
		{
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key, $this->databaseNeededFieldClassRelationships) && !array_key_exists($key,$this->databaseFieldClassRelationships))
				throw new Exception('Invalid data being set');
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			foreach ((array)$this->data[$key] AS $i => $data)
			{
				if ($data instanceof MACAddress)
					$newDataArray[] = $data;
				else if ($data->get('id') != $object->get('id'))
					$newDataArray[] = $data;
			}
			$this->data[$key] = (array)$newDataArray;
		}
		catch (Exception $e)
		{
			$this->debug('Remove Failed: Key: %s, Object: %s, Error: %s', array($key, $object, $e->getMessage()));
		}
		return $this;
	}
	// Save
	/** save()
		Saves the information stored in the class variables to the database.
	*/
	public function save()
	{
		try
		{
			// Error checking
			if (!$this->isTableDefined())
				throw new Exception('No Table defined for this class');
			// Variables
			$fieldData = array();
			$fieldsToUpdate = $this->databaseFields;
			// Remove unwanted fields for update query
			foreach ($this->databaseFields AS $name => $fieldName)
			{
				if (in_array($name, $this->databaseFieldsToIgnore))
					unset($fieldsToUpdate[$name]);
			}
			// Build insert key and value arrays
			foreach ($this->databaseFields AS $name => $fieldName)
			{
				if ($this->get($name) != '')
				{
					$insertKeys[] = $this->DB->sanitize($fieldName);
					$insertValues[] = $this->DB->sanitize($this->get($name));
				}
			}
			// Build update field array using filtered data
			foreach ($fieldsToUpdate AS $name => $fieldName)
				$updateData[] = sprintf("`%s` = '%s'", $this->DB->sanitize($fieldName), $this->DB->sanitize($this->get($name)));
			// Force ID to update so ID is returned on DUPLICATE UPDATE - No ID was returning when A) Nothing is inserted (already exists) or B) Nothing is updated (data has not changed)
			$updateData[] = sprintf("`%s` = LAST_INSERT_ID(%s)", $this->DB->sanitize($this->databaseFields['id']), $this->DB->sanitize($this->databaseFields['id']));
			// Insert & Update query all-in-one
			$query = sprintf("INSERT INTO `%s` (`%s`) VALUES ('%s') ON DUPLICATE KEY UPDATE %s",
				$this->databaseTable,
				implode("`, `", (array)$insertKeys),
				implode("', '", (array)$insertValues),
				implode(', ', $updateData)
			);
			if (!$this->DB->query($query))
				throw new Exception($this->DB->sqlerror());
			// Database query was successful - set ID if ID was not set
			if (!$this->get('id'))
				$this->set('id', $this->DB->insert_id());
			// Success
			return true;
		}
		catch (Exception $e)
		{
			$this->debug('Database Save Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
	}
	// Load
	/** load($field = 'id')
		Defaults the load from database as ID, but can be used to load
		whichever field you want.
	*/
	public function load($field = 'id')
	{
		try
		{
			// Error checking
			if (!$this->isTableDefined())
				throw new Exception('No Table defined for this class');
			if (!$this->get($field))
				throw new Exception(sprintf('Operation field not set: %s', strtoupper($field)));
			list($getFields,$join) = $this->buildQuery();
			// Build query
			if (is_array($this->get($field)))
			{
				// Multiple values
				foreach($this->get($field) AS $fieldValue)
					$fieldData[] = sprintf("`%s`='%s'", $this->databaseFields[$field], $fieldValue);
				$query = sprintf(
					$this->loadQueryTemplateMultiple,
					$getFields,
					$this->databaseTable,
					get_class($this),
					count($join) ? implode($join) : '',
					implode(' OR ', $fieldData)
				);
			}
			else
			{
				// Single value
				$query = sprintf(
					$this->loadQueryTemplateSingle,
					$getFields,
					$this->databaseTable,
					get_class($this),
					count($join) ? implode($join) : '',
					$this->databaseFields[$field],
					$this->get($field)
				);
			}
			// Did we find a row in the database?
			if (!$queryData = $this->DB->query($query)->fetch()->get())
				throw new Exception(($this->DB->sqlerror() ? $this->DB->sqlerror() : 'Row not found'));
			// Success
			return $this->getQuery($queryData);
		}
		catch (Exception $e)
		{
			$this->set('id', 0)->debug('Database Load Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
	}
	public function getQuery($queryData,$returnObject = false)
	{
		// Loop returned rows -> Set new data
		foreach ($queryData AS $key => $value)
		{
			if (!array_key_exists($key,$this->databaseFieldsFlipped))
			{
				if ($fieldAssocs = $this->databaseNeededFieldClassRelationships)
				{
					foreach($fieldAssocs AS $class => $field)
					{
						if (!$NewClass[$class] instanceof $class)
							$NewClass[$class] = $this->getClass($class);
						if ($NewClass[$class] && array_key_exists($key,$NewClass[$class]->databaseFieldsFlipped))
							$NewClass[$class]->set($NewClass[$class]->key($key),$value);
					}
					foreach($fieldAssocs AS $class => $field)
					{
						if ($NewClass[$class])
							$this->add($field[2],$NewClass[$class]);
					}
				}
				if ($fieldAssocs = $this->databaseFieldClassRelationships)
				{
					foreach($fieldAssocs AS $class => $field)
					{
						if (!$NewClass[$class] instanceof $class)
							$NewClass[$class] = $this->getClass($class);
						if ($NewClass[$class] && array_key_exists($key,$NewClass[$class]->databaseFieldsFlipped))
							$NewClass[$class]->set($NewClass[$class]->key($key),$value);
					}
					foreach($fieldAssocs AS $class => $field)
					{
						if ($NewClass[$class])
							$this->add($field[2],$NewClass[$class]);
					}
				}
			}
			else if (array_key_exists($key,$this->databaseFieldsFlipped))
				$this->set($this->key($key), $value);
		}
		if ($returnObject)
			return $this;
	}
	public function addClassObject($fieldAssocs,$key,$value)
	{
	}
	// Destroy
	/** destroy($field = 'id')
		Can be used to delete items from the databased.
	*/
	public function destroy($field = 'id')
	{
		try
		{
			// Error checking
			if (!$this->isTableDefined())
				throw new Exception('No Table defined for this class');
			if (!$this->get($field))
				throw new Exception(sprintf('Operation field not set: %s', strtoupper($field)));
			// Query row data
			$query = sprintf("DELETE FROM `%s` WHERE `%s`='%s'",
				$this->DB->sanitize($this->databaseTable),
				$this->DB->sanitize($this->databaseFields[$field]),
				$this->DB->sanitize($this->get($field))
			);
			// Did we find a row in the database?
			if (!$queryData = $this->DB->query($query)->fetch()->get())
				throw new Exception('Failed to delete');
			// Success
			return true;
		}
		catch (Exception $e)
		{
			$this->debug('Database Destroy Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
	}
	public function buildQuery()
	{
		$getFields = implode(array_keys($this->databaseFieldsFlipped),'`,`');
		if ($this->databaseNeededFieldClassRelationships)
		{
			$field = array();
			foreach($this->databaseNeededFieldClassRelationships AS $class => $field)
			{
				$class = $this->getClass($class);
				$getFields .= '`,`'.get_class($class).'`.`'.implode(array_keys($class->databaseFieldsFlipped),'`,`'.get_class($class).'`.`');
				$join[] = sprintf(' INNER JOIN `%s` `%s` ON %s=%s ',$class->databaseTable,get_class($class),'`'.get_class($class).'`.`'.$class->databaseFields[$field[0]].'`','`'.get_class($this).'`.`'.$this->databaseFields[$field[1]].'`');
			}
		}
		if ($this->databaseFieldClassRelationships)
		{
			foreach($this->databaseFieldClassRelationships AS $class => $field)
			{
				$class = new $class(array('id' => 0));
				$getFields .= '`,`'.get_class($class).'`.`'.implode(array_keys($class->databaseFieldsFlipped),'`,`'.get_class($class).'`.`');
				$join[] = sprintf(' LEFT OUTER JOIN `%s` `%s` ON %s=%s ',$class->databaseTable,get_class($class),'`'.get_class($class).'`.`'.$class->databaseFields[$field[0]].'`','`'.get_class($this).'`.`'.$this->databaseFields[$field[1]].'`');
			}
		}
		return array($getFields,$join);
	}
	// Key
	/** key($key)
		Checks if a relevant key exists within the database.
	*/
	public function key($key)
	{
		if (array_key_exists($key, $this->databaseFieldsFlipped))
			$key = $this->databaseFieldsFlipped[$key];
		return $key;
	}
	// isValid
	/** isValid()
		Checks that the returned items are valid for the relevant class calling it.
	*/
	public function isValid()
	{
		try
		{
			foreach ($this->databaseFieldsRequired AS $field)
			{
				if (!$this->get($field))
					throw new Exception($foglang['RequiredDB']);
			}
			if ($this->get('id') || $this->get('name'))
				return true;
		}
		catch (Exception $e)
		{
			$this->debug('isValid Failed: Error: %s', array($e->getMessage()));
		}
		return false;
	}
	/** getManager()
		Checks the relevant class manager class file (Image => ImageManager, Host => HostManager, etc...)
	*/
	public function getManager()
	{
		if (!is_object($this->Manager))
		{
			$managerClass = get_class($this) . 'Manager';
			$this->Manager = new $managerClass();
		}
		return $this->Manager;
	}
	
	// isTableDefined 
	/** istableDefined()
		Makes sur ethe table being called is defined in the database.  osID on hosts database table is not defined anymore.
		This would return false in that case.
	*/
	private function isTableDefined()
	{
		return (!empty($this->databaseTable) ? true : false);
	}
	// Name is returned if class is printed
	/** __toString()
		Returns the name of the class as a string.
	*/
	public function __toString()
	{
		return ($this->get('name') ? $this->get('name') : sprintf('%s #%s', get_class($this), $this->get('id')));
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
