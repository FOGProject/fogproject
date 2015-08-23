<?php
abstract class FOGController extends FOGBase {
    /** @var databaseTable
     * Sets the databaseTable to perform lookups
     */
    public $databaseTable = '';
    /** @var databaseFields
     * The Fields the database contains
     * using common for friendly names
     */
    public $databaseFields = array();
    /** @var loadQueryTemplateMultiple
     * The Query template in case of multiple items passed to data
     * Protected so as to allow other classes to assign into them
     */
    protected $loadQueryTemplateSingle = "SELECT * FROM %s %s WHERE %s='%s' %s";
    /** @var loadQueryTemplateMultiple
     * The Query template in case of multiple items passed to data
     */
    protected $loadQueryTemplateMultiple = "SELECT * FROM %s %s WHERE %s %s";
    /** @var databaseFieldsToIgnore
     * Which fields to not really care about updatin
     */
    public $databaseFieldsToIgnore = array(
        'createdBy',
        'createdTime'
    );
    /** @var additionalFields
     * Fields to allow assignment into object
     * but are not directly associated to the
     * objects table
     */
    public $additionalFields = array();
    /** @var aliasedField
     * Aliased fields that aren't directly related to db
     * but not capable of being updated or searched
     */
    public $aliasedFields = array();
    /** @var databaseFieldsRequired
     * Required fields to allow updating/inserting into
     * the database
     */
    public $databaseFieldsRequired = array();
    /** @var data
     * The data to actually set and return to the object
     */
    protected $data = array();
    /** @var autoSave
     * If set, when the object is destroyed it will save first.
     */
    public $autoSave = false;
    /** @var databaseFieldClassRelationships
     * Set the classes to associate to between objects
     * This is hard as most use associative properties
     * But each object (including associations) are
     * counted as their own objects
     */
    public $databaseFieldClassRelationships = array();
    /** The Manager of the info. */
    /** @var Manager
     * Just sets the class manager field as needed.
     */
    private $Manager;
    /** @param data
     * Initializer of the objects themselves.
     */
    public function __construct($data = '') {
        /** FOGBase constructor
         * Allows the rest of the base of fog to come
         * with the object begin called
         */
        parent::__construct();
        try {
            /** sets if to print controller debug information to screen/log/either/both*/
            $this->debug = false;
            /** sets if to print controller general information to screen/log/either/both*/
            $this->info = false;
            // Error checking
            if (!count($this->databaseFields)) throw new Exception('No database fields defined for this class!');
            // Flip database fields and common name - used multiple times
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            // Created By
            if (array_key_exists(createdBy,$this->databaseFields) && !empty($_SESSION[FOG_USERNAME])) $this->set(createdBy,$this->DB->sanitize($_SESSION[FOG_USERNAME]));
            if (array_key_exists(createdTime,$this->databaseFields)) $this->set(createdTime,$this->nice_date()->format('Y-m-d H:i:s'));
            // Add incoming data
            if (is_array($data)) $this->data = $data;
            // If incoming data is an INT -> Set as ID -> Load from database
            else if (is_numeric($data)) {
                if ($data === 0 || $data < 0) throw new Exception(sprintf('No data passed, or less than zero, Value: %s', $data));
                $this->set(id,$data)->load();
            }
        } catch (Exception $e) {
            $this->error('Record not found, Error: %s', array($e->getMessage()));
        }
        return $this;
    }
    // Destruct
    /** __destruct()
     * At close of class, it trys to save the information
     * if autoSave is enabled for that class.
     * @return void
     */
    public function __destruct() {if ($this->autoSave) $this->save();}
        // Set
        /** set($key, $value)
         * @param $key the key to set
         * @param $value the value to set into the key, can be
         *    an array of items too as needed.
         * Set's the fields relevent for that class.
         */
        public function set($key, $value) {
            try {
                $this->info('Setting Key: %s, Value: %s',array($key,$value));
                if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key,$this->databaseFieldClassRelationships)) throw new Exception('Invalid key being set');
                if (array_key_exists($key, $this->databaseFieldsFlipped)) $key = $this->databaseFieldsFlipped[$key];
                $this->data[$key] = $value;
            } catch (Exception $e) {
                $this->debug('Set Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
            }
            return $this;
        }
    // Get
    /** get($key = '')
     * Get's all fields or the specified field for the class member.
     * @return the data from
     */
    public function get($key = '') {return ($key && isset($this->data[$key]) ? $this->data[$key] : (!$key ? $this->data : ''));}
        // Add
        /** add($key, $value)
         * @param $key the key to add value into
         * @param $value the item to add to the key
         * @return the class returned with data set
         * Used to add a new field to the database relevant to the class.
         * Could potentially be used to add a new moderation field to the database??
         */
        public function add($key, $value) {
            try {
                if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key,$this->databaseFieldClassRelationships)) throw new Exception('Invalid data being added');
                $this->info('Adding Key: %s, Value: %s',array($key,$value));
                $key = $this->key($key);
                $this->data[$key][] = $value;
            } catch (Exception $e) {
                $this->debug('Add Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
            }
            return $this;
        }
    // Remove
    /** remove($key, $object)
     * @param $key the key to remove
     * @param $object the object/element to remove.
     * @return $this the class itself with modified data.
     * Removes a field from the relevant class caller.
     * Can be used to remove fields from the database??
     */
    public function remove($key, $object) {
        try {
            if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key,$this->databaseFieldClassRelationships)) throw new Exception('Invalid data being removed');
            if (array_key_exists($key, $this->databaseFieldsFlipped)) $key = $this->databaseFieldsFlipped[$key];
            $this->info('Remove attempt: Key: %s, Object: %s', array($key, $object));
            if (!is_array($this->data[$key])) $this->data[$key] = array($this->data[$key]);
            asort($this->data[$key]);
            $index = $this->binary_search($object,$this->data[$key]);
            if ($index > -1) unset($this->data[$key][$index]);
            $this->data[$key] = array_values(array_filter($this->data[$key]));
        } catch (Exception $e) {
            $this->debug('Remove Failed: Key: %s, Object: %s, Error: %s', array($key, $object, $e->getMessage()));
        }
        return $this;
    }
    // Save
    /** save()
     * @return boolean, returns if the item was saved or not.
     */
    public function save() {
        try {
            // Error checking
            if (!$this->isTableDefined()) throw new Exception('No Table defined for this class');
            // Variables
            $fieldData = array();
            if ($this->aliasedFields) $this->array_remove($this->aliasedFields,$this->databaseFields);
            $fieldsToUpdate = $this->databaseFields;
            // Build insert key and value arrays
            foreach ($this->databaseFields AS $name => &$fieldName) {
                if ($this->get($name) != '') {
                    $insertKeys[] = (preg_match('#default#i',$fieldName) ? '`'.$fieldName.'`' : $fieldName);
                    $insertValues[] = $this->DB->sanitize($this->get($name));
                }
            }
            unset($fieldName);
            // Build update field array using filtered data
            foreach ($fieldsToUpdate AS $name => &$fieldName) {
                $fieldName = (preg_match('#default#i',$fieldName) ? '`'.$fieldName.'`' : $fieldName);
                $updateData[] = sprintf("%s = '%s'", $fieldName, $this->DB->sanitize($this->get($name)));
            }
            unset($fieldName);
            // Force ID to update so ID is returned on DUPLICATE UPDATE - No ID was returning when A) Nothing is inserted (already exists) or B) Nothing is updated (data has not changed)
            $updateData[] = sprintf("%s = LAST_INSERT_ID(%s)", $this->databaseFields[id], $this->databaseFields[id]);
            // Insert & Update query all-in-one
            $query = sprintf("INSERT INTO %s (%s) VALUES ('%s') ON DUPLICATE KEY UPDATE %s",
                $this->databaseTable,
                implode(", ", (array)$insertKeys),
                implode("', '", (array)$insertValues),
                implode(', ', (array)$updateData)
            );
            if (!$this->DB->query($query)) throw new Exception($this->DB->sqlerror());
            if ($this->DB->queryResult() instanceof mysqli_result) $this->DB->queryResult()->free_result();
            // Database query was successful - set ID if ID was not set
            if (!$this->get(id)) $this->set(id,$this->DB->insert_id());
            $res = $this;
        } catch (Exception $e) {
            $this->debug('Database Save Failed: ID: %s, Error: %s', array($this->get(id), $e->getMessage()));
            $res = false;
        }
        return $res;
    }
    // Load
    /** load($field = 'id')
     * @param $field the item to load
     * @return boolean, if the class was loaded or not.
     */
    public function load($field = 'id') {
        try {
            // Error checking
            if (!$this->isTableDefined()) throw new Exception('No Table defined for this class');
            if (!$this->get($field)) throw new Exception(sprintf('Operation field not set: %s', strtoupper($field)));
            list($join,$where) = $this->buildQuery();
            // Build query
            if (is_array($this->get($field))) {
                // Multiple values
                $fields = $this->get($field);
                foreach((array)$fields AS $i => &$fieldValue) $fieldData[] = sprintf("%s='%s'", $this->databaseFields[$field], $fieldValue);
                unset($fieldValue);
                $query = sprintf(
                    $this->loadQueryTemplateMultiple,
                    $this->databaseTable,
                    $join,
                    implode(' OR ', $fieldData),
                    count($where) ? ' AND '.implode(' AND ',$where) : ''
                );
            } else {
                // Single value
                $query = sprintf(
                    $this->loadQueryTemplateSingle,
                    $this->databaseTable,
                    $join,
                    $this->databaseFields[$field],
                    $this->get($field),
                    count($where) ? '  AND '.implode(' AND ',$where) : ''
                );
            }
            $this->setQuery($this->DB->query($query)->fetch()->get());
            // Success
            $res = true;
        } catch (Exception $e) {
            $this->set('id', 0)->debug('Database Load Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
            $res = false;
        }
        // Fail
        return $res;
    }
    /** buildQuery builds the joins as needed for the associative
     *     linking of objects.
     * @param $not not used, but if we need it it's there
     * @param $compare not used, but if we need it it's there
     * @returns the elements of the query we need
     */
    public function buildQuery($not = false,$compare = '=') {
        foreach((array)$this->databaseFieldClassRelationships AS $class => &$fields) {
            $join[] = sprintf(' LEFT OUTER JOIN %s ON %s.%s=%s.%s ',$this->getClass($class)->databaseTable,$this->getClass($class)->databaseTable,$this->getClass($class)->databaseFields[$fields[0]],$this->databaseTable,$this->databaseFields[$fields[1]]);
            if ($fields[3]) {
                foreach((array)$fields[3] AS $field => &$value) {
                    if (is_array($value)) $whereArrayAnd[] = sprintf("%s.%s IN ('%s')",$this->getClass($class)->databaseTable,$this->getClass($class)->databaseFields[$field], implode("','",$value));
                    else $whereArrayAnd[] = sprintf("%s.%s %s '%s'",$this->getClass($class)->databaseTable,$this->getClass($class)->databaseFields[$field],(preg_match('#%#',$value) ? 'LIKE' : $compare),$value);
                }
                unset($value);
            }
        }
        unset($fields);
        return array(implode((array)$join),$whereArrayAnd);
    }
    /** setQuery sets the objects into the class for us
     * @param $queryData
     * @return the set class
     */
    public function setQuery($queryData) {
        foreach((array)$queryData AS $key => &$val) $this->data[$this->key($key)] = $val;
        unset($val);
        if (count($this->databaseFieldClassRelationships)) {
            foreach((array)$this->databaseFieldClassRelationships AS $class => &$fields) $this->set($fields[2],$this->getClass($class)->setQuery($queryData));
            unset($fields);
        }
        return $this;
    }
    // Destroy
    /** destroy removes objects from the databse for us.
     * @param $field the element to search for to remove
     * @return boolean if it was good or not.
     */
    public function destroy($field = 'id') {
        try {
            // Error checking
            if (!$this->isTableDefined()) throw new Exception('No Table defined for this class');
            if (!$this->get($field)) throw new Exception(sprintf('Operation field not set: %s', strtoupper($field)));
            // Query row data
            if (!$this->DB->query(sprintf("DELETE FROM %s WHERE %s='%s'",
                $this->databaseTable,
                $this->databaseFields[$field],
                $this->DB->sanitize($this->get($field))))->fetch()->get())
                throw new Exception('Failed to delete');
            // Success
            return true;
        } catch (Exception $e) {
            $this->debug('Database Destroy Failed: ID: %s, Error: %s', array($this->get(id), $e->getMessage()));
        }
        // Fail
        return false;
    }
    // Key
    /** key returns the key or the flipped key as needed.
     * @param $key the key to test
     * @return the flipped key
     */
    public function key($key) {
        if (array_key_exists($key, $this->databaseFieldsFlipped)) $key = $this->databaseFieldsFlipped[$key];
        return $key;
    }
    // isValid
    /** isValid() Checks that the returned items are valid
     *    for the relevant class calling it.
     * @return boolean if the item is valid or not
     */
    public function isValid() {
        try {
            foreach ($this->databaseFieldsRequired AS $i => &$field) if (!$this->get($field)) throw new Exception($foglang[RequiredDB]);
            unset($field);
            if (array_key_exists(name,$this->databaseFields) && !($this->get(id) && $this->get(name))) throw new Exception(_($this->childClass.' no longer exists'));
            else if ($this->get(id)) return true;
        } catch (Exception $e) {
            $this->debug('isValid Failed: Error: %s',array($e->getMessage()));
        }
        return false;
    }
    /** getManager()
     * gets the relevant class manager class file
     * (Image => ImageManager, Host => HostManager, etc...)
     * @return the manager itself.
     */
    public function getManager() {
        if (!is_object($this->Manager)) {
            $managerClass = get_class($this) . 'Manager';
            $this->Manager = $this->getClass($managerClass);
        }
        return $this->Manager;
    }

    // isTableDefined
    /** istableDefined() tests if the table is defined for the class.
     * @return boolean
     */
    private function isTableDefined() {return !empty($this->databaseTable);}
        // Name is returned if class is printed
        /** __toString()
         * @return string name of the class
         */
        public function __toString() {return ($this->get('name') ? $this->get('name') : sprintf('%s #%s', get_class($this), $this->get('id')));}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
