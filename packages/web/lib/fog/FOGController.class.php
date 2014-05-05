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
	protected $loadQueryTemplateSingle = "SELECT * FROM `%s` WHERE `%s`='%s'";
	/** The standard query template for a multiple call */
	protected $loadQueryTemplateMultiple = "SELECT * FROM `%s` WHERE %s";
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
			// Error checking
			if (!count($this->databaseFields))
				throw new Exception('No database fields defined for this class!');
			// Flip database fields and common name - used multiple times
			$this->databaseFieldsFlipped = array_flip($this->databaseFields);
			// Created By
			if (array_key_exists('createdBy', $this->databaseFields) && !empty($_SESSION['FOG_USER']))
				$this->set('createdBy', $_SESSION['FOG_USER']);
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
			// Unknown data format
			else
				throw new Exception('No data array or ID passed!');
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
	}
	// Set
	/** set($key, $value)
		Set's the fields relevent for that class.
	*/
	public function set($key, $value)
	{
		try
		{
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped))
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
		return (!empty($key) && isset($this->data[$key]) ? $this->data[$key] : (empty($key) ? $this->data : ''));
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
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped))
				throw new Exception('Invalid data being set');
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
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped))
				throw new Exception('Invalid data being set');
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			foreach ((array)$this->data[$key] AS $i => $data)
			{
				if ($data->get('id') != $object->get('id'))
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
				$this->DB->sanitize($this->databaseTable),
				implode("`, `", $insertKeys),
				implode("', '", $insertValues),
				implode(', ', $updateData)
			);
			if (!$this->DB->query($query))
				throw new Exception($this->DB->error());
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
			// Build query
			if (is_array($this->get($field)))
			{
				// Multiple values
				foreach ($this->get($field) AS $fieldValue)
					$fieldData[] = sprintf("`%s`='%s'", $this->DB->sanitize($this->databaseFields[$field]), $this->DB->sanitize($fieldValue));
				$query = sprintf($this->loadQueryTemplateMultiple,
					$this->DB->sanitize($this->databaseTable),
					implode(' OR ', $fieldData)
				);
			}
			else
			{
				// Single value
				$query = sprintf($this->loadQueryTemplateSingle,
					$this->DB->sanitize($this->databaseTable),
					$this->DB->sanitize($this->databaseFields[$field]),
					$this->DB->sanitize($this->get($field))
				);
			}
			// Did we find a row in the database?
			if (!$queryData = $this->DB->query($query)->fetch()->get())
				throw new Exception(($this->DB->error() ? $this->DB->error() : 'Row not found'));
			// Loop returned rows -> Set new data
			foreach ($queryData AS $key => $value)
				$this->set($this->key($key), (string)$value);
			// Success
			return true;
		}
		catch (Exception $e)
		{
			$this->set('id', 0)->debug('Database Load Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
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
	// Key
	/** key($key)
		Checks if a relevant key exists within the database.
	*/
	public function key($key)
	{
		if (array_key_exists($key, $this->databaseFieldsFlipped))
			return $this->databaseFieldsFlipped[$key];
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
					throw new Exception(_('Required database field is empty'));
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
