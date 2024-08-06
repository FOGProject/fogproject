<?php
/**
 * FOGController, individual SQL getters/setters.
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
 * FOGController, individual SQL getters/setters.
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
     * Storage point for plugins tab data.
     *
     * @var array
     */
    public $pluginsTabData = [];
    /**
     * The data to set/get.
     *
     * @var array
     */
    protected $data = [];
    /**
     * If true, saves the object automatically.
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
     * The database fields to get.
     *
     * @var array
     */
    protected $databaseFields = [];
    /**
     * The required DB fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [];
    /**
     * Additional elements unrelated to DB side directly for object.
     *
     * @var array
     */
    protected $additionalFields = [];
    /**
     * The flipped fields as we commonize names, flipping allows
     * translation to the main db column.
     *
     * @var array
     */
    protected $databaseFieldsFlipped = [];
    /**
     * Fields to ignore.
     *
     * @var array
     */
    protected $databaseFieldsToIgnore = [
        'createdBy',
        'createdTime'
    ];
    /**
     * Not used now, but can be used to setup alternate db aliases.
     *
     * @var array
     */
    protected $aliasedFields = [];
    /**
     * The sql query string.
     *
     * @var string
     */
    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        %s
        %s
        %s";
    /**
     * The sql filter string.
     *
     * @var string
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        %s";
    /**
     * The sql total string.
     *
     * @var string
     */
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`";
    /**
     * Class relationships, for inner joins of data.
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [];
    /**
     * The select query template to use.
     *
     * @var string
     */
    protected $loadQueryTemplate = 'SELECT %s FROM `%s` %s WHERE `%s`=%s %s';
    /**
     * The insert query template to use.
     *
     * @var string
     */
    protected $insertQueryTemplate = 'INSERT INTO `%s` (%s) VALUES (%s) %s %s';
    /**
     * The delete query template to use.
     *
     * @var string
     */
    protected $destroyQueryTemplate = 'DELETE FROM `%s` WHERE %s=%s%s';
    /**
     * Constructor to set variables.
     *
     * @param mixed $data the data to construct from if different
     *
     * @throws Exception
     *
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
            if (!count($this->databaseFields ?: [])) {
                throw new Exception(_('Fields not defined for this class'));
            }
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            if (is_numeric($data) && $data > 0) {
                $this->set('id', $data)->load();
            } elseif (is_numeric($data)) {
                $this->set('id', $data);
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
            self::error($str);
        }

        return $this;
    }
    /**
     * Closes out the object.
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
     * Default way to present object as a string.
     *
     * @return string
     */
    public function __toString()
    {
        $str = sprintf(
            '%s ID: %s',
            get_class($this),
            $this->get('id')
        );
        if ($this->get('name')) {
            $str = sprintf(
                '%s %s: %s',
                $str,
                _('Name'),
                $this->get('name')
            );
        }

        return $str;
    }
    /**
     * Test our needed fields.
     *
     * @param string $key the key to test
     *
     * @return bool
     */
    private function _testFields($key)
    {
        $this->key($key);
        $inFields = array_key_exists($key, $this->databaseFields);
        $inFieldsFlipped = array_key_exists($key, $this->databaseFieldsFlipped);
        $inAddFields = in_array($key, $this->additionalFields);
        if (!$inFields && !$inFieldsFlipped && !$inAddFields) {
            return false;
        }

        return true;
    }
    /**
     * Gets an item from the key sent, if no key all object data is returned.
     *
     * @param mixed $key the key to get
     *
     * @return mixed
     */
    public function get($key = '')
    {
        $key = $this->key($key);
        if (!$key) {
            return $this->data;
        }
        $test = $this->_testFields($key);
        if (!$test) {
            return false;
        }
        if (!$this->isLoaded($key)) {
            $this->loadItem($key);
        }
        $msg = sprintf(
            '%s: %s, %s: %s',
            _('Returning value of key'),
            $key,
            _('Value'),
            print_r(isset($this->data[$key]) ? $this->data[$key] : '', 1)
        );
        self::info($msg);

        return isset($this->data[$key]) ? $this->data[$key] : '';
    }
    /**
     * Set value to key.
     *
     * @param string $key   the key to set to
     * @param mixed  $value the value to set
     *
     * @throws Exception
     *
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
                throw new Exception(_('Invalid key being set'));
            }
            if (!$this->isLoaded($key)) {
                $this->loadItem($key);
            }
            $msg = sprintf(
                '%s: %s, %s: %s',
                _('Setting Key'),
                $key,
                _('Value'),
                print_r($value, 1)
            );
            self::info($msg);
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
            self::debug($str);
        }

        return $this;
    }
    /**
     * Add value to key (array).
     *
     * @param string $key   the key to add to
     * @param mixed  $value the value to add
     *
     * @throws Exception
     *
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
                throw new Exception(_('Invalid key being added'));
            }
            if (!$this->isLoaded($key)) {
                $this->loadItem($key);
            }
            $msg = sprintf(
                '%s: %s, %s: %s',
                _('Adding Key'),
                $key,
                _('Value'),
                print_r($value, 1)
            );
            self::info($msg);
            if (isset($this->data[$key]) && !is_array($this->data[$key])) {
                $this->data[$key] = [$this->data[$key]];
            }
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
            self::debug($str);
        }

        return $this;
    }
    /**
     * Remove value from key (array).
     *
     * @param string $key   the key to remove from
     * @param mixed  $value the value to remove
     *
     * @throws Exception
     *
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
                throw new Exception(_('Invalid key being removed'));
            }
            if (!$this->isLoaded($key)) {
                $this->loadItem($key);
            }
            if (!is_array($this->data[$key])) {
                $this->data[$key] = [$this->data[$key]];
            }
            $this->data[$key] = array_unique($this->data[$key]);
            $ind = array_search($value, $this->data[$key]);
            if (false !== $ind) {
                $msg = sprintf(
                    '%s: %s, %s: %s',
                    _('Removing Key'),
                    $key,
                    _('Value'),
                    print_r($this->data[$key][$ind], 1)
                );
                self::info($msg);
                unset($this->data[$key][$ind]);
            }
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
            self::debug($str);
        }

        return $this;
    }
    /**
     * Stores data into the database.
     *
     * @return bool|object
     */
    public function save()
    {
        try {
            $insertKeys = [];
            $insertValKeys = $updateValKeys = [];
            $insertValues = $updateValues = [];
            $updateData = $fieldData = [];
            if (count($this->aliasedFields ?: []) > 0) {
                self::arrayRemove($this->aliasedFields, $this->databaseFields);
            }
            foreach ($this->databaseFields as $key => &$column) {
                $key = $this->key($key);
                $column = trim($column);
                $eColumn = sprintf('`%s`', $column);
                $paramInsert = sprintf(':%s_insert', $column);
                $val = $this->get($key);
                switch ($key) {
                    case 'createdBy':
                        if (!$val) {
                            if (self::$FOGUser->isValid()) {
                                $val = trim(self::$FOGUser->get('name'));
                            } else {
                                $val = 'fog';
                            }
                        }
                        break;
                    case 'createdTime':
                        if (!($val && self::validDate($val))) {
                            $val = self::formatTime('now', 'Y-m-d H:i:s');
                        }
                        break;
                    case 'id':
                        if (!(is_numeric($val) && $val > 0)) {
                            continue 2;
                        }
                }
                if (is_null($val)) {
                    $val = '';
                }
                $insertKeys[] = $eColumn;
                $insertValKeys[] = $paramInsert;
                $insertValues[] = $val;
                $updateData[] = sprintf(
                    '%s=VALUES(%s)',
                    $eColumn,
                    $eColumn
                );
                unset(
                    $column,
                    $eColumn,
                    $key,
                    $val
                );
            }
            $query = sprintf(
                $this->insertQueryTemplate,
                $this->databaseTable,
                implode(',', (array) $insertKeys),
                implode(',', (array) $insertValKeys),
                'ON DUPLICATE KEY UPDATE',
                implode(',', (array) $updateData)
            );
            $queryArray = array_combine(
                $insertValKeys,
                $insertValues
            );
            $msg = sprintf(
                '%s %s %s',
                _('Saving data for'),
                get_class($this),
                _('object')
            );
            self::info($msg);
            self::$DB->query($query, [], $queryArray);
            if (!$this->get('id') || $this->get('id') < 1) {
                $this->set('id', self::$DB->insertId());
            }
            if (!$this instanceof History
                && !$this instanceof Plugin
            ) {
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
                self::logHistory($msg);
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
                self::logHistory($msg);
            }
            $msg = sprintf(
                '%s: %s: %s, %s: %s',
                _('Database save failed'),
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);

            return false;
        }

        return $this;
    }
    /**
     * Loads the item from the database.
     *
     * @param string $key the key to load
     *
     * @throws Exception
     *
     * @return object
     */
    public function load($key = 'id')
    {
        try {
            if (!is_string($key)) {
                throw new Exception(_('Key field must be a string'));
            }
            if (!$key) {
                throw new Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
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
            $join = $whereArrayAnd = [];
            $c = null;
            $this->buildQuery($join, $whereArrayAnd, $c);
            $join = array_filter((array) $join);
            $join = implode((array) $join);
            $fields = [];
            $this->getcolumns($fields);
            $key = $this->key($key);
            $paramKey = sprintf(':%s', $key);
            $query = sprintf(
                $this->loadQueryTemplate,
                implode(',', $fields),
                $this->databaseTable,
                $join,
                $this->databaseFields[$key],
                $paramKey,
                (
                    count($whereArrayAnd ?: []) ?
                    sprintf(
                        ' AND %s',
                        implode(' AND ', $whereArrayAnd)
                    ) :
                    ''
                )
            );
            $msg = sprintf(
                '%s %s',
                _('Loading data to field'),
                $key
            );
            self::info($msg);
            $queryArray = array_combine(
                (array) $paramKey,
                (array) $val
            );
            self::$DB->query(
                $query,
                [],
                $queryArray
            );
            $vals = self::$DB->fetch()->get();
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
            self::debug($str);
        }

        return $this;
    }
    /**
     * Gets the columns.
     *
     * @param array $fields The fields to get.
     *
     * @return void
     */
    public function getcolumns(&$fields)
    {
        /**
         * Lambda to get the fields to use.
         *
         * @param string $k      The key (for class relations).
         * @param string $column The column name.
         */
        $getFields = function (&$column, $k) use (&$fields, &$table) {
            $column = trim($column);
            $fields[] = sprintf('`%s`.*', $table);
            unset($column, $k);
        };
        $table = $this->databaseTable;
        if (count($this->databaseFields ?: []) > 0) {
            array_walk($this->databaseFields, $getFields);
        }
        foreach ((array)$this->databaseFieldClassRelationships as $class => &$arr) {
            self::getClass($class)->getcolumns($fields);
            unset($arr);
        }
        $fields = array_unique($fields);
    }
    /**
     * Removes the item from the database.
     *
     * @param string $key the key to remove
     *
     * @throws Exception
     *
     * @return object
     */
    public function destroy($key = 'id')
    {
        try {
            if (empty($key)) {
                $key = 'id';
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
            if (!is_numeric($val) && !$val) {
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
                $paramKey,
                ''
            );
            $queryArray = array_combine(
                (array) $paramKey,
                (array) $val
            );
            self::$DB->query($query, [], $queryArray);
            if (!$this instanceof History) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s.',
                        get_class($this),
                        _('ID'),
                        $this->get('id'),
                        _('Name'),
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
                self::logHistory($msg);
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
                self::logHistory($msg);
            }
            $msg = sprintf(
                '%s: %s: %s, %s: %s',
                _('Destroy failed'),
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);

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
    public function key(&$key)
    {
        $key = trim($key);
        if (array_key_exists($key, $this->databaseFieldsFlipped)) {
            $key = $this->databaseFieldsFlipped[$key];
        }

        return $key;
    }
    /**
     * Load the item field.
     *
     * @param string $key the key to load
     *
     * @throws Exception
     *
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
     * Adds or removes items from key field.
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
     *
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
        if (!in_array($array_type, ['merge', 'diff'])) {
            throw new Exception(
                _('Invalid type, merge to add, diff to remove')
            );
        }
        $array = array_filter($array);
        if (count($array) < 1) {
            return $this;
        }
        switch ($array_type) {
            case 'merge':
                foreach ((array)$array as &$a) {
                    $this->add($key, $a);
                    unset($a);
                }
                break;
            case 'diff':
                foreach ((array)$array as &$a) {
                    $this->remove($key, $a);
                    unset($a);
                }
        }
        return $this;
    }
    /**
     * Tests if an object is valid.
     *
     * @throws Exception
     *
     * @return bool
     */
    public function isValid()
    {
        try {
            foreach ($this->databaseFieldsRequired as &$key) {
                $key = $this->key($key);
                $val = $this->get($key);
                if (!is_numeric($val) && !$val) {
                    throw new Exception(self::$foglang['RequiredDB'] . ": " . $key);
                }
                unset($key);
            }
            if ($this->get('id') < 1) {
                throw new Exception(_('Invalid ID passed'));
            }
            if (array_key_exists('name', $this->databaseFields)) {
                $val = trim($this->get('name'));
            }
        } catch (Exception $e) {
            $str = sprintf(
                '%s: %s: %s',
                _('Failed'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($str);

            return false;
        }

        return true;
    }
    /**
     * Builds query strings as needed.
     *
     * @param array  $join          The join array.
     * @param array  $whereArrayAnd The where array.
     * @param array  $c             The join object.
     * @param bool   $not           Whether to compare using not operator.
     * @param string $compare       The comparator to use.
     *
     * @return array
     */
    public function buildQuery(
        &$join,
        &$whereArrayAnd,
        &$c,
        $not = false,
        $compare = '='
    ) {
        /**
         * Lambda function to build the where array additionals.
         *
         * @param string $field the field to work from
         * @param mixed  $value the value of the field
         */
        $whereInfo = function (
            &$value,
            $field
        ) use (
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
                if (strpos($value, '%')) {
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
         * Lambda function to build the join of a query.
         *
         * @param string $class  the class to work from
         * @param mixed  $fields the fields to work off
         */
        $joinInfo = function (
            &$fields,
            $class
        ) use (
            &$join,
            &$whereArrayAnd,
            &$c,
            $whereInfo,
            $not,
            $compare
        ) {
            $className = strtolower($class);
            $c = self::getClass($class);
            if (!array_key_exists($className, $join)) {
                $join[$className] = sprintf(
                    ' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s` ',
                    $c->databaseTable,
                    $c->databaseTable,
                    $c->databaseFields[$fields[0]],
                    $this->databaseTable,
                    $this->databaseFields[$fields[1]]
                );
            }
            if (isset($fields[3]) && $fields[3]) {
                array_walk($fields[3], $whereInfo);
            }
            $c->buildQuery($join, $whereArrayAnd, $c, $not, $compare);
            unset($class, $fields, $c);
        };
        $className = strtolower(get_class($this));
        if (!array_key_exists($className, $join)) {
            $join[$className] = false;
        }
        if (count($this->databaseFieldClassRelationships ?: []) > 0) {
            foreach ($this->databaseFieldClassRelationships as $key => &$val) {
                $joinInfo($val, $key);
                unset($val);
            }
        }
        return [implode((array) $join), $whereArrayAnd];
    }
    /**
     * Set's the queries data into the object as/where needed.
     *
     * @param array $queryData The data to work from.
     *
     * @return object
     */
    public function setQuery(&$queryData)
    {
        $classData = array_intersect_key(
            (array) $queryData,
            (array) $this->databaseFieldsFlipped
        );
        if (count($classData ?: []) < 1) {
            $classData = array_intersect_key(
                (array) $queryData,
                (array)$this->databaseFields
            );
        } else {
            foreach ($this->databaseFieldsFlipped as $db_key => &$obj_key) {
                self::arrayChangeKey($classData, $db_key, $obj_key);
                unset($db_key, $obj_key);
            }
        }
        $this->data = self::fastmerge(
            (array) $this->data,
            (array) $classData
        );
        foreach ($this->databaseFieldClassRelationships as $class => &$fields) {
            $class = self::getClass($class);
            $this->set(
                $fields[2],
                $class->setQuery($queryData)
            );
            unset($class, $fields);
        }
        unset($queryData);

        return $this;
    }
    /**
     * Get an objects manager class.
     *
     * @return object
     */
    public function getManager()
    {
        $man = get_class($this).'Manager';
        return new $man;
    }
    /**
     * Sets values for associative fields.
     *
     * @param string $assocItem    the assoc item to work from/with
     * @param string $alterItem    the alternate item to work with
     * @param bool   $implicitCall call class implicitly instead of appending
     *                             with association
     *
     * @return object
     */
    public function assocSetter($assocItem, $alterItem = '', $implicitCall = false)
    {
        // Lower our item.
        $alterItem = strtolower($alterItem ?: $assocItem);
        // Getter is pluralized.
        $plural = "{$alterItem}s";
        // Class to call, if implicit leave off association.
        $classCall = ($implicitCall ? $assocItem : "{$assocItem}Association");
        // Main object and string setters.
        $obj = strtolower(get_class($this));
        $objstr = "{$obj}ID";
        $assocstr = "{$alterItem}ID";

        // Don't work on item that isn't loaded yet.
        if (!$this->isLoaded($plural)) {
            return $this;
        }

        // Get the current items.
        $items = $this->get($plural);

        // Fetch current associations from the database.
        Route::ids(
            $classCall,
            [$objstr => $this->get('id')],
            $assocstr
        );
        $cur = json_decode(Route::getData(), true);

        // Determine items to remove (in $cur but not in $items).
        $rem = array_diff((array)$cur, (array)$items);
        if (count($rem ?: [])) {
            Route::deletemass(
                $classCall,
                [
                    $assocstr => $rem,
                    $objstr => $this->get('id')
                ]
            );
        }

        // Determine items to add (in $items but not in $cur).
        $diff = array_diff((array)$items, (array)$cur);
        if (!count($diff ?: [])) {
            return $this;
        }

        // Prepare for insertion.
        $insert_fields = [
            $objstr,
            $assocstr
        ];
        $insert_values = [];
        if ($assocstr == 'moduleID'
            || strtolower($classCall) == 'moduleassociation'
        ) {
            $insert_fields[] = 'state';
        }

        foreach ($diff as $id) {
            $insert_val = [
                $this->get('id'),
                $id
            ];
            if ($assocstr == 'moduleID'
                || strtolower($classCall) == 'moduleassociation'
            ) {
                $insert_val[] = 1;
            }
            $insert_values[] = $insert_val;
        }

        // Perform batch insertion if there are values to insert.
        if (count($insert_values ?: []) > 0) {
            self::getClass("{$classCall}manager")->insertBatch(
                $insert_fields,
                $insert_values
            );
        }

        return $this;
    }
    /**
     * Gets items in list format.
     *
     * @param string $primary    What are we getting?
     * @param string $secondary  The secondary (what we're getting items from)?
     * @param array  $join       Any Joins needed?
     * @param string $where      Any special "Where" items?
     * @param array  $addColumns Any additional columns
     * @param string $qStr       Custom SQL String to use?
     * @param string $qFilterStr Custom SQL Filter String to use?
     * @param string $qTotalStr  Custom SQL Total string to use?
     *
     * @return string
     */
    public function getItemsList(
        $primary,
        $secondary,
        $join = [],
        $where = '',
        $addColumns = [],
        $qStr = '',
        $qFilterStr = '',
        $qTotalStr = ''
    ) {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $priman = $primary . 'manager';
        $secman = $secondary . 'manager';

        $priman = self::getClass($priman);
        $privars = $priman->getColumns();

        if ($secondary) {
            $secman = self::getClass($secman);
            $secvars = $secman->getColumns();
        }

        $itemID = $privars['id'];
        $itemassocID = strtolower(get_class($this)). 'ID';
        $secondID = strtolower(get_class($this)). 'Assoc';
        $secondRID = (isset($secvars[$itemassocID]) ? $secvars[$itemassocID] : $secvars['id']);

        $qStr = trim($qStr);
        $qFilterStr = trim($qFilterStr);
        $qTotalStr = trim($qTotalStr);

        if (empty($qStr)) {
            if ($secondary) {
                $sqlStr = "SELECT `%s`,"
                    . "IF(`" . $secondRID . "` = '"
                    . $this->get('id')
                    . "','associated','dissociated') AS `" . $secondID . "` "
                    . "FROM `%s`";
            } else {
                $sqlStr = "SELECT `%s` FROM `%s`";
            }
            foreach ($join as &$j) {
                $sqlStr .= ' ' . $j . ' ';
                unset($j);
            }
            $sqlStr .= ' %s %s %s';
        } else {
            $sqlStr = $qStr;
        }
        if (empty($qFilterStr)) {
            $sqlFilterStr = "SELECT COUNT(`%s`) "
                . "FROM `%s`";
            foreach ($join as &$j) {
                $sqlFilterStr .= ' ' . $j . ' ';
                unset($j);
            }
            $sqlFilterStr .= ' %s';
        } else {
            $sqlFilterStr = $qFilterStr;
        }
        if (empty($qTotalStr)) {
            $sqlTotalStr = "SELECT COUNT(`%s`) "
                . "FROM `%s`";
        } else {
            $sqlTotalStr = $qTotalStr;
        }

        foreach ($privars as $common => &$real) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            switch ($common) {
                case 'id':
                    $idField = $real;
                    break;
                case 'name':
                    $columns[] = [
                        'db' => $real,
                        'dt' => 'mainLink',
                        'formatter' => function ($d, $row) use ($primary, $idField) {
                            if (!$d) {
                                return;
                            }
                            return '<a href="../management/index.php?node=' . $primary . '&'
                                . 'sub=edit&id='
                                . $row[$idField]
                                . '">'
                                . $d . " - (" . $row[$idField] . ")"
                                . '</a>';
                        }
                    ];
                    break;
            }
            unset($real);
        }
        if ($secondary) {
            $columns[] = [
                'do' => $secondID,
                'dt' => 'association'
            ];
        }
        foreach ((array)$addColumns as &$column) {
            $columns[] = $column;
            unset($column);
        }

        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $priman->getTable(),
                $itemID,
                $columns,
                $sqlStr,
                $sqlFilterStr,
                $sqlTotalStr,
                $where
            )
        );
        exit;
    }
}
