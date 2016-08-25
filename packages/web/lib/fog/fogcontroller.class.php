<?php
/**
 * FOGController, individual SQL getters/setters
 *
 * PHP Version 5
 *
 * Gets and sets data for an individual object.
 * Generates the SQL Statements more specifically.
 *
 * @category FOGController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FOGController, individual SQL getters/setters
 *
 * Gets and sets data for an individual object.
 * Generates the SQL Statements more specifically.
 *
 * @category FOGController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGController extends FOGBase
{
    /**
     * The data to set/get
     *
     * @var array
     */
    protected $data = array();
    /**
     * If true, saves the object automatically
     *
     * @var bool
     */
    protected $autoSave = false;
    /**
     * The database table to work from.
     *
     * @var string
     */
    protected $databaseTable = '';
    /**
     * The database fields to get
     *
     * @var array
     */
    protected $databaseFields = array();
    /**
     * The required DB fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array();
    /**
     * Additional elements unrelated to DB side directly for object.
     *
     * @var array
     */
    protected $additionalFields = array();
    /**
     * The flipped fields as we commonize names, flipping allows
     * translation to the main db column.
     *
     * @var array
     */
    protected $databaseFieldsFlipped = array();
    /**
     * Fields to ignore
     *
     * @var array
     */
    protected $databaseFieldsToIgnore = array(
        'createdBy',
        'createdTime'
    );
    /**
     * Not used now, but can be used to setup alternate db aliases.
     *
     * @var array
     */
    protected $aliasedFields = array();
    /**
     * Class relationships, for inner joins of data.
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array();
    /**
     * The select query template to use.
     *
     * @var string
     */
    protected $loadQueryTemplate = "SELECT %s FROM `%s` %s WHERE `%s`=%s %s";
    /**
     * The insert query template to use.
     *
     * @var string
     */
    protected $insertQueryTemplate = "INSERT INTO `%s` (%s) "
        . "VALUES (%s) ON DUPLICATE KEY UPDATE %s";
    /**
     * The delete query template to use.
     *
     * @var string
     */
    protected $destroyQueryTemplate = "DELETE FROM `%s` WHERE `%s`=%s";
    /**
     * Constructor to set variables.
     *
     * @param mixed $data the data to construct from if different
     *
     * @throws Exception
     * @return self
     */
    public function __construct($data = '')
    {
        parent::__construct();
        $this->databaseTable = trim($this->databaseTable);
        $this->databaseFields = array_unique($this->databaseFields);
        $this->databaseFields = array_filter($this->databaseFields);
        try {
            if (!isset($this->databaseTable)) {
                throw new Exception(_('Table not defined for this class'));
            }
            if (!count($this->databaseFields)) {
                throw new Exception(_('Fields not defined for this class'));
            }
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            if (is_numeric($data)) {
                $this->set('id', $data)->load();
            } elseif (is_array($data)) {
                $this->setQuery($data);
            }
        } catch (Exception $e) {
            $str = sprintf(
                '%s, %s: %s',
                _('Record not found'),
                _('Error'),
                $e->getMessage()
            );
            $this->error($str);
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    /**
     * Closes out the object
     *
     * @return bool
     */
    public function __destruct()
    {
        if ($this->autoSave) {
            $this->save();
        }
        return false;
    }
    /**
     * Default way to present object as a string
     *
     * @return string
     */
    public function __toString()
    {
        $str = sprintf('%s ID: %s', get_class($this), $this->get('id'));
        if ($this->get('name')) {
            $str = sprintf('%s %s: %s', $str, _('Name'), $this->get('name'));
        }
        return $str;
    }
    /**
     * Test our needed fields
     *
     * @param string $key the key to test
     *
     * @return bool
     */
    private function _testFields($key)
    {
        $inFields = array_key_exists($key, $this->databaseFields);
        $inFieldsFlipped = array_key_exists($key, $this->databaseFieldsFlipped);
        $inAddFields = in_array($key, $this->additionalFields);
        if (!$inFields && !$inFieldsFlipped && !$inAddFields) {
            unset($this->data[$key]);
            return false;
        }
        return true;
    }
    /**
     * Gets an item from the key sent, if no key all object data is returned
     *
     * @param mixed $key the key to get
     *
     * @return object
     */
    public function get($key = '')
    {
        $key = $this->key($key);
        if (!$key) {
            return $this->data;
        }
        $test = $this->_testFields($key);
        if (!$test) {
            unset($this->data[$key]);
            return false;
        }
        if (!$this->isLoaded($key)) {
            $this->loadItem($key);
        }
        if (!isset($this->data[$key])) {
            return $this->data[$key] = '';
        }
        if (is_object($this->data[$key])) {
            $msg = sprintf(
                '%s: %s, %s: %s',
                _('Returning value of key'),
                $key,
                _('Object'),
                $this->data[$key]->__toString()
            );
        } else if (is_array($this->data[$key])) {
            $msg = sprintf(
                '%s: %s',
                _('Returning array within key'),
                $key
            );
        } else {
            $msg = sprintf(
                '%s: %s, %s: %s',
                _('Returning value of key'),
                $key,
                _('Value'),
                $this->data[$key]
            );
        }
        $this->info($msg);
        return $this->data[$key];
    }
    /**
     * Set value to key
     *
     * @param string $key   the key to set to
     * @param mixed  $value the value to set
     *
     * @throws Exception
     * @return object
     */
    public function set($key, $value)
    {
        try {
            $key = $this->key($key);
            if (!$key) {
                throw new Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being set'));
            }
            if (!$this->isLoaded($key)) {
                $this->loadItem($key);
            }
            if (is_numeric($value) && $value < ($key == 'id' ? 1 : -1)) {
                throw new Exception(_('Invalid numeric entry'));
            }
            if (is_object($value)) {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Setting Key'),
                    $key,
                    _('Object'),
                    $value->__toString()
                );
            } elseif (is_array($value)) {
                $msg = sprintf(
                    '%s: %s %s',
                    _('Setting Key'),
                    $key,
                    _('Array')
                );
            } else {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Setting Key'),
                    $key,
                    _('Value'),
                    $value
                );
            }
            $this->info($msg);
            $this->data[$key] = $value;
        } catch (Exception $e) {
            $str = sprintf(
                '%s: %s: %s, %s: %s',
                _('Set failed'),
                _('Key'),
                $key,
                _('Error'),
                $e->getMessage()
            );
            $this->debug($str);
        }
        return $this;
    }
    /**
     * Add value to key (array)
     *
     * @param string $key   the key to add to
     * @param mixed  $value the value to add
     *
     * @throws Exception
     * @return object
     */
    public function add($key, $value)
    {
        try {
            $key = $this->key($key);
            if (!$key) {
                throw new Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being added'));
            }
            if (!$this->isLoaded($key)) {
                $this->loadItem($key);
            }
            if (is_object($value)) {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Adding Key'),
                    $key,
                    _('Object'),
                    $value->__toString()
                );
            } elseif (is_array($value)) {
                $msg = sprintf(
                    '%s: %s %s',
                    _('Adding Key'),
                    $key,
                    _('Array')
                );
            } else {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Adding Key'),
                    $key,
                    _('Value'),
                    $value
                );
            }
            $this->info($msg);
            $this->data[$key][] = $value;
        } catch (Exception $e) {
            $str = sprintf(
                '%s: %s: %s, %s: %s',
                _('Add failed'),
                _('Key'),
                $key,
                _('Error'),
                $e->getMessage()
            );
            $this->debug($str);
        }
        return $this;
    }
    /**
     * Remove value from key (array)
     *
     * @param string $key   the key to remove from
     * @param mixed  $value the value to remove
     *
     * @throws Exception
     * @return object
     */
    public function remove($key, $value)
    {
        try {
            $key = $this->key($key);
            if (!$key) {
                throw new Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being removed'));
            }
            if (!$this->isLoaded($key)) {
                $this->loadItem($key);
            }
            if (!is_array($this->data[$key])) {
                $this->data[$key] = array($this->data[$key]);
            }
            $this->data[$key] = array_unique($this->data[$key]);
            $index = array_search($value, $this->data[$key]);
            if (is_object($this->data[$key][$index])) {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Removing Key'),
                    $key,
                    _('Object'),
                    $this->data[$key][$index]->__toString()
                );
            } elseif (is_array($this->data[$key][$index])) {
                $msg = sprintf(
                    '%s: %s %s',
                    _('Removing Key'),
                    $key,
                    _('Array')
                );
            } else {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Removing Key'),
                    $key,
                    _('Value'),
                    $this->data[$key][$index]
                );
            }
            $this->info($msg);
            unset($this->data[$key][$index]);
            $this->data[$key] = array_values(array_filter($this->data[$key]));
        } catch (Exception $e) {
            $str = sprintf(
                '%s: %s: %s, %s: %s',
                _('Remove failed'),
                _('Key'),
                $key,
                _('Error'),
                $e->getMessage()
            );
            $this->debug($str);
        }
        return $this;
    }
    /**
     * Stores data into the database
     *
     * @return bool|object
     */
    public function save()
    {
        try {
            $insertKeys = array();
            $insertValKeys = $updateValKeys = array();
            $insertValues = $updateValues = array();
            $updateData = $fieldData = array();
            if (count($this->aliasedFields)) {
                $this->arrayRemove($this->aliasedFields, $this->databaseFields);
            }
            foreach ($this->databaseFields AS $key => &$column) {
                $key = $this->key($key);
                $column = trim($column);
                $eColumn = sprintf('`%s`', $column);
                $paramInsert = sprintf(':%s_insert', $column);
                $paramUpdate = sprintf(':%s_update', $column);
                $val = $this->get($key);
                switch ($key) {
                case 'createdBy':
                    if (!$val) {
                        if (isset($_SESSION['FOG_USERNAME'])) {
                            $val = trim($_SESSION['FOG_USERNAME']);
                        } else {
                            $val = 'fog';
                        }
                    }
                    break;
                case 'createdTime':
                    if (!($val && $this->validDate($val))) {
                        $val = $this->formatTime('now', 'Y-m-d H:i:s');
                    }
                    break;
                case 'id':
                    if (!$val) {
                        continue;
                    }
                }
                $insertKeys[] = $eColumn;
                $insertValKeys[] = $paramInsert;
                $insertValues[] = $updateValues[] = $val;
                $updateValKeys[] = $paramUpdate;
                $updateData[] = sprintf("%s=%s", $eColumn, $paramUpdate);
                unset(
                    $column,
                    $eColumn,
                    $key,
                    $val,
                    $paramInsert,
                    $paramUpdate
                );
            }
            $query = sprintf(
                $this->insertQueryTemplate,
                $this->databaseTable,
                implode(',', (array)$insertKeys),
                implode(',', (array)$insertValKeys),
                implode(',', (array)$updateData)
            );
            $queryArray = array_combine(
                array_merge(
                    $insertValKeys,
                    $updateValKeys
                ),
                array_merge(
                    $insertValues,
                    $updateValues
                )
            );
            $msg = sprintf(
                '%s %s %s',
                _('Saving data for'),
                get_class($this),
                _('object')
            );
            $this->info($msg);
            self::$DB->query($query, array(), $queryArray);
            if (!$this->get('id') || $this->get('id') < 1) {
                $this->set('id', self::$DB->insert_id());
            }
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s.',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('NAME'),
                        $this->get('name'),
                        _('has been successfully updated')
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s.',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('has been successfully updated')
                    );
                }
                $this->log($msg);
            }
        } catch (Exception $e) {
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s. %s: %s',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('Name'),
                        $this->get('name'),
                        _('has failed to save'),
                        _('Error'),
                        $e->getMessage()
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s. %s: %s',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('has failed to save'),
                        _('Error'),
                        $e->getMessage()
                    );
                }
                $this->log($msg);
            }
            $msg = sprintf(
                '%s: %s: %s, %s: %s',
                _('Database save failed'),
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            $this->debug($msg);
            return false;
        }
        return $this;
    }
    /**
     * Loads the item from the database
     *
     * @param string $key the key to load
     *
     * @throws Exception
     * @return object
     */
    public function load($key = 'id')
    {
        try {
            if (!is_string($key)) {
                throw new Exception(_('Key field must be a string'));
            }
            $key = $this->key($key);
            if (!$key) {
                throw new Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                unset($this->data[$key]);
                throw new Exception(_('Invalid key being added'));
            }
            $val = $this->get($key);
            if (!$val) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        _('Operation field not set'),
                        $key
                    )
                );
            }
            list($join, $where) = $this->buildQuery();
            $fields = array();
            /**
             * Lambda to get the fields to use
             *
             * @param string $k      the key (for class relations really)
             * @param string $column the column name
             *
             * @return void
             */
            $getFields = function (&$column, $k) use (&$fields, &$table) {
                $column = trim($column);
                $fields[] = sprintf('`%s`.`%s`', $table, $column);
                unset($column, $k);
            };
            $table = $this->databaseTable;
            array_walk($this->databaseFields, $getFields);
            foreach ($this->databaseFieldClassRelationships AS $class => &$arr) {
                $class = self::getClass($class);
                $table = $class->databaseTable;
                array_walk($class->databaseFields, $getFields);
            }
            $paramKey = sprintf(':%s', $key);
            $query = sprintf(
                $this->loadQueryTemplate,
                implode(',', $fields),
                $this->databaseTable,
                $join,
                $this->databaseFields[$key],
                $paramKey,
                count($where) ? sprintf(' AND %s', implode(' AND ', $where)) : ''
            );
            $msg = sprintf(
                '%s %s',
                _('Loading data to field'),
                $key
            );
            $this->info($msg);
            $queryArray = array_combine(
                (array)$paramKey,
                (array)$val
            );
            $vals = array();
            $vals = self::$DB->query($query, array(), $queryArray)
                ->fetch('', 'fetch_assoc')
                ->get();
            $this->setQuery($vals);
        } catch (Exception $e) {
            $str = sprintf(
                '%s: %s: %s, %s: %s',
                _('Load failed'),
                _('Key'),
                $key,
                _('Error'),
                $e->getMessage()
            );
            $this->debug($str);
        }
        return $this;
    }
    /**
     * Removes the item from the database
     *
     * @param string $key the key to remove
     *
     * @throws Exception
     * @return object
     */
    public function destroy($key = 'id')
    {
        try {
            if (!is_string($key)) {
                throw new Exception(_('Key field must be a string'));
            }
            $key = $this->key($key);
            if (!$key) {
                throw new Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                throw new Exception(_('Invalid key being destroyed'));
            }
            $val = $this->get($key);
            if (!$val) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        _('Operation field not set'),
                        $key
                    )
                );
            }
            $column = $this->databaseFields[$key];
            $eColumn = sprintf(
                '`%s`.`%s`',
                $this->databaseTable,
                $column
            );
            $paramKey = sprintf(':%s', $column);
            $query = sprintf(
                $this->destroyQueryTemplate,
                $this->databaseTable,
                $eColumn,
                $paramKey
            );
            $queryArray = array_combine(
                (array)$paramKey,
                (array)$value
            );
            self::$DB->query($query, array(), $queryArray);
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s.',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('NAME'),
                        $this->get('name'),
                        _('has been successfully destroyed')
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s.',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('has been successfully destroyed')
                    );
                }
                $this->log($msg);
            }
        } catch (Exception $e) {
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s. %s: %s',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('Name'),
                        $this->get('name'),
                        _('has failed to destroy'),
                        _('Error'),
                        $e->getMessage()
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s. %s: %s',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('has failed to destroy'),
                        _('Error'),
                        $e->getMessage()
                    );
                }
                $this->log($msg);
            }
            $msg = sprintf(
                '%s: %s: %s, %s: %s',
                _('Destroy failed'),
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            $this->debug($msg);
            return false;
        }
        return $this;
    }
    /**
     * Get's the relevant common key if available.
     *
     * @param string|array $key the key to get commonized
     *
     * @return mixed
     */
    protected function key(&$key)
    {
        if (!is_array($key)) {
            $key = trim($key);
            if (array_key_exists($key, $this->databaseFieldsFlipped)) {
                $key = $this->databaseFieldsFlipped[$key];
            }
            return $key;
        }
        return array_walk($key, array($this, 'key'));
    }
    /**
     * Load the item field
     *
     * @param string $key the key to load
     *
     * @throws Exception
     * @return object
     */
    protected function loadItem($key)
    {
        $key = $this->key($key);
        if (!$key) {
            throw new Exception(_('No key being requested'));
        }
        $test = $this->_testFields($key);
        if (!$test) {
            return $this;
        }
        $methodCall = sprintf('load%s', ucfirst($key));
        if (method_exists($this, $methodCall)) {
            $this->{$methodCall}();
        }
        unset($methodCall);
        return $this;
    }
    /**
     * Adds or removes items from key field
     *
     * Example:
     * Remove:
     * $this->addRemItem('hosts', $some_var_data, 'diff')
     * Add:
     * $this->addRemItem('hosts', $some_var_data, 'merge')
     *
     * @param string $key        the key to add/remove from
     * @param mixed  $array      the data to add/remove from
     * @param string $array_type the array type to use
     *
     * @throws Exception
     * @return object
     */
    protected function addRemItem($key, $array, $array_type)
    {
        $key = $this->key($key);
        if (!$key) {
            throw new Exception(_('No key being requested'));
        }
        $test = $this->_testFields($key);
        if (!$test) {
            throw new Exception(_('Invalid key being requested'));
        }
        if (!in_array($array_type, array('merge', 'diff'))) {
            throw new Exception(
                _('Invalid type, merge to add, diff to remove')
            );
        }
        $array_type = sprintf(
            'array_%s',
            $array_type
        );
        if (!is_callable($array_type)) {
            throw new Exception(
                sprintf(
                    '%s %s: %s %s',
                    _('Array type'),
                    _('Type'),
                    $array_type,
                    _('is not callable')
                )
            );
        }
        $array = $array_type(
            (array)$this->get($key),
            (array)$array
        );
        return $this->set($key, $array);
    }
    /**
     * Tests if an object is valid.
     *
     * @throws Exception
     * @return bool
     */
    public function isValid()
    {
        try {
            foreach ($this->databaseFieldsRequired AS &$key) {
                $key = $this->key($key);
                $val = $this->get($key);
                if (!$val) {
                    throw new Exception(self::$foglang['RequiredDB']);
                }
                unset($key);
            }
            if ($this->get('id') < 1) {
                throw new Exception(_('Invalid ID passed'));
            }
            if (array_key_exists('name', $this->databaseFields)) {
                $val = trim($this->get('name'));
                if (!$val) {
                    throw new Exception(
                        sprintf(
                            '%s %s',
                            get_class($this),
                            _('no longer exists')
                        )
                    );
                }
            }
        } catch (Exception $e) {
            $str = sprintf(
                '%s: %s: %s',
                _('Failed'),
                _('Error'),
                $e->getMessage()
            );
            $this->debug($str);
            return false;
        }
        return true;
    }
    /**
     * Builds query strings as needed
     *
     * @param bool   $not     whether to compare using not operators
     * @param string $compare the comparator to use
     *
     * @return array
     */
    public function buildQuery($not = false, $compare = '=')
    {
        $join = array();
        $whereArrayAnd = array();
        $c = '';
        /**
         * Lambda function to build the where array additionals
         *
         * @param string $field the field to work from
         * @param mixed  $value the value of the field
         *
         * @return void
         */
        $whereInfo = function (&$value, &$field) use (
            &$whereArrayAnd,
            &$c,
            $not,
            $compare
        ) {
            if (is_array($value)) {
                $whereArrayAnd[] = sprintf(
                    "`%s`.`%s` IN ('%s')",
                    $c->databaseTable,
                    $field,
                    implode("','", $value)
                );
            } else {
                if (preg_match('#%#', $value)) {
                    $compare = 'LIKE';
                }
                $whereArrayAnd[] = sprintf(
                    "`%s`.`%s` %s '%s'",
                    $c->databaseTable,
                    $c->databaseFields[$field],
                    $compare,
                    $value
                );
            }
            unset($value, $field);
        };
        /**
         * Lambda function to build the join of a query
         *
         * @param string $class  the class to work from
         * @param mixed  $fields the fields to work off
         *
         * @return void
         */
        $joinInfo = function (&$fields, &$class) use (
            &$join,
            &$whereArrayAnd,
            &$c,
            $whereInfo,
            $not,
            $compare
        ) {
            $c = self::getClass($class);
            $join[] = sprintf(
                ' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s` ',
                $c->databaseTable,
                $c->databaseTable,
                $c->databaseFields[$fields[0]],
                $this->databaseTable,
                $this->databaseFields[$fields[1]]
            );
            if ($fields[3]) {
                array_walk($fields[3], $whereInfo);
            }
            unset($class, $fields, $c);
        };
        array_walk($this->databaseFieldClassRelationships, $joinInfo);
        return array(implode((array)$join),$whereArrayAnd);
    }
    /**
     * Set's the queries data into the object as/where needed
     *
     * @param array $queryData the data to work from
     *
     * @return object
     */
    public function setQuery(&$queryData)
    {
        $classData = array_intersect_key(
            (array)$queryData,
            (array)$this->databaseFieldsFlipped
        );
        if (count($classData) <= 0) {
            $classData = array_intersect_key(
                (array)$queryData,
                $this->databaseFields
            );
        } else {
            foreach ($this->databaseFieldsFlipped AS $db_key => &$obj_key) {
                $this->arrayChangeKey($classData, $db_key, $obj_key);
                unset($db_key, $obj_key);
            }
        }
        array_walk($classData, 'trim');
        $this->data = array_merge(
            (array)$this->data,
            (array)$classData
        );
        foreach ($this->databaseFieldClassRelationships AS $class => &$fields) {
            $class = self::getClass($class);
            $leftover = array_intersect_key(
                (array)$queryData,
                (array)$class->databaseFieldsFlipped
            );
            $this->set($fields[2], $class->setQuery($leftover));
            unset($class, $fields);
        }
        return $this;
    }
    /**
     * Get an objects manager class
     *
     * @return object
     */
    public function getManager()
    {
        $class = sprintf('%sManager', get_class($this));
        return new $class();
    }
}
