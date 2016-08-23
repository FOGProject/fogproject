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
        $msg = sprintf(
            '%s %s %s',
            _('Saving data for'),
            get_class($this),
            _('object')
        );
        $this->info($msg);
        try {
            $insertKeys = array();
            $insertValKeys = $updateValKeys = array();
            $insertValues = $updateValues = array();
            $updateData = $fieldData = array();
            if (count($this->aliasedFields)) {
                $this->arrayRemove($this->aliasedFields, $this->databaseFields);
            }
            foreach ($this->databaseFields AS $name => &$field) {
                $field = trim($field);
                $key = sprintf('`%s`', $field);
                $paramInsert = sprintf(':%s_insert', $field);
                $paramUpdate = sprintf(':%s_update', $field);
                switch ($name) {
                case 'createdBy':
                    if (isset($_SESSION['FOG_USERNAME'])) {
                        $username = trim($_SESSION['FOG_USERNAME']);
                    } else {
                        $username = 'fog';
                    }
                    if (!$this->get($name)) {
                        $val = $username;
                    }
                    break;
                case 'createdTime':
                    if (!$this->validDate($this->get($name))) {
                        $val = $this->formatTime('now', 'Y-m-d H:i:s');
                    }
                    break;
                default:
                    $val = $this->get($name);
                    break;
                }
                switch (true) {
                case ($name == 'id' && !$val):
                    continue 2;
                default:
                    break;
                }
                $insertKeys[] = $key;
                $insertValKeys[] = $paramInsert;
                $insertValues[] = $val;
                $updateValKeys[] = $paramUpdate;
                $updateValues[] = $val;
                $updateData[] = sprintf('%s=%s', $key, $paramUpdate);
                unset($val, $field, $name, $key);
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
                        _('NAME'),
                        $this->get('name'),
                        _('has failed to save'),
                        _('ERROR'),
                        $e->getMessage()
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s. %s: %s',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('has failed to save'),
                        _('ERROR'),
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
    public function load($field = 'id')
    {
        $this->info(sprintf(_('Loading data to field %s'), $field));
        try {
            if (!is_array($field) && $field && !$this->get($field)) {
                throw new Exception(_(sprintf(_('Operation Field not set: %s'), $field)));
            }
            list($join, $where) = $this->buildQuery();
            if (!is_array($field)) {
                $field = array($field);
            }
            $fields = array();
            $getFields = function (&$dbColumn, $key) use (&$fields, &$table) {
                $fields[] = sprintf('`%s`.`%s`', $table, trim($dbColumn));
                unset($dbColumn, $key);
            };
            $table = $this->databaseTable;
            array_walk($this->databaseFields, $getFields);
            array_walk($this->databaseFieldClassRelationships, function (&$stuff, $class) use (&$fields, &$table, $getFields) {
                $class = self::getClass($class);
                $table = $class->databaseTable;
                array_walk($class->databaseFields, $getFields);
            });
            array_walk($field, function (&$key, &$index) use ($join, $where, $fields) {
                $key = $this->key($key);
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
                $vals = array();
                $vals = self::$DB->query($query, array(), array_combine((array)$paramKey, (array)$this->get($key)))->fetch('', 'fetch_assoc')->get();
                $this->setQuery($vals);
                unset($vals, $key, $join, $where);
            });
        } catch (Exception $e) {
            $this->debug(_('Load failed: %s'), array($e->getMessage()));
        }
        return $this;
    }
    public function destroy($field = 'id')
    {
        $this->info(sprintf(_('Destroying data from field %s'), $field));
        try {
            if (!$this->get($field)) {
                throw new Exception(sprintf(_('Operation Field not set: %s'), $field));
            }
            if (!array_key_exists($field, $this->databaseFields) && !array_key_exists($field, $this->databaseFieldsFlipped)) {
                throw new Exception(_('Invalid Operation Field set'));
            }
            if (array_key_exists($field, $this->databaseFields)) {
                $fieldToGet = $this->databaseFields[$field];
            }
            $paramKey = sprintf(':%s', $fieldToGet);
            $value = $this->get($this->key($field));
            $query = sprintf(
                $this->destroyQueryTemplate,
                $this->databaseTable,
                $fieldToGet,
                $paramKey
            );
            self::$DB->query($query, array(), array_combine((array)$paramKey, (array)$value));
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $this->log(sprintf('%s ID: %s NAME: %s %s.', get_class($this), $this->get('id'), $this->get('name'), _('has been destroyed')));
                } else {
                    $this->log(sprintf('%s ID: %s %s.', get_class($this), $this->get('id'), _('has been destroyed')));
                }
            }
        } catch (Exception $e) {
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $this->log(sprintf('%s ID: %s NAME: %s %s. ERROR: %s', get_class($this), $this->get('id'), $this->get('name'), _('has failed to be destroyed'), $e->getMessage()));
                } else {
                    $this->log(sprintf('%s ID: %s %s. ERROR: %s', get_class($this), $this->get('id'), _('has failed to be destroyed'), $e->getMessage()));
                }
            }
            $this->debug(_('Destroy failed: %s'), array($e->getMessage()));
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
     * @return object
     */
    protected function loadItem($key)
    {
        if (!array_key_exists($key, $this->databaseFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !in_array($key, $this->additionalFields)) {
            return $this;
        }
        $methodCall = sprintf('load%s', ucfirst($key));
        if (method_exists($this, $methodCall)) {
            $this->{$methodCall}();
        }
        unset($methodCall);
        return $this;
    }
    protected function addRemItem($var, $array, $array_type)
    {
        if (!in_array($array_type, array('merge', 'diff'))) {
            throw new Exception(_('Invalid type'));
        }
        $array_type = sprintf('array_%s', $array_type);
        return $this->set($var, array_unique($array_type((array)$this->get($var), $array)));
    }
    public function isValid()
    {
        try {
            array_walk($this->databaseFieldsRequired, function (&$field, &$index) {
                if (!$this->get($field) === 0 && !$this->get($field)) {
                    throw new Exception(self::$foglang['RequiredDB']);
                }
                unset($field);
            });
            if ($this->get('id') < 1) {
                throw new Exception(_('Invalid ID'));
            }
            if (array_key_exists('name', (array)$this->databaseFields) && !$this->get('name')) {
                throw new Exception(_(get_class($this).' no longer exists'));
            }
        } catch (Exception $e) {
            $this->debug('isValid Failed: Error: %s', array($e->getMessage()));
            return false;
        }
        return true;
    }
    public function buildQuery($not = false, $compare = '=')
    {
        $join = array();
        $whereArrayAnd = array();
        $c = null;
        $whereInfo = function (&$value, &$field) use (&$whereArrayAnd, &$c, $not, $compare) {
            if (is_array($value)) {
                $whereArrayAnd[] = sprintf("`%s`.`%s` IN ('%s')", $c->databaseTable, $field, implode("','", $value));
            } else {
                $whereArrayAnd[] = sprintf("`%s`.`%s` %s '%s'", $c->databaseTable, $c->databaseFields[$field], (preg_match('#%#', $value) ? 'LIKE' : $compare), $value);
            }
            unset($value, $field);
        };
        $joinInfo = function (&$fields, &$class) use (&$join, &$whereArrayAnd, &$whereInfo, &$c, $not, $compare) {
            $c = self::getClass($class);
            $join[] = sprintf(' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s` ', $c->databaseTable, $c->databaseTable, $c->databaseFields[$fields[0]], $this->databaseTable, $this->databaseFields[$fields[1]]);
            if ($fields[3]) {
                array_walk($fields[3], $whereInfo);
            }
            unset($class, $fields, $c);
        };
        array_walk($this->databaseFieldClassRelationships, $joinInfo);
        return array(implode((array)$join),$whereArrayAnd);
    }
    public function setQuery(&$queryData)
    {
        $classData = array_intersect_key((array)$queryData, (array)$this->databaseFieldsFlipped);
        if (count($classData) <= 0) {
            $classData = array_intersect_key((array)$queryData, $this->databaseFields);
        } else {
            array_walk($this->databaseFieldsFlipped, function (&$obj_key, &$db_key) use (&$classData) {
                $this->arrayChangeKey($classData, $db_key, $obj_key);
                unset($obj_key, $db_key);
            });
        }
        array_walk($classData, 'trim');
        $this->data = array_merge((array)$this->data, (array)$classData);
        array_walk($this->databaseFieldClassRelationships, function (&$fields, &$class) use (&$queryData) {
            $class = self::getClass($class);
            $leftover = array_intersect_key((array)$queryData, (array)$class->databaseFieldsFlipped);
            $this->set($fields[2], $class->setQuery($leftover));
            unset($fields, $class);
        });
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
