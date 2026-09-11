<?php
/**
 * FOGController, individual SQL getters/setters.
 *
 * PHP version 7.4+
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
     * The data to set/get.
     *
     * @var array
     */
    protected $data = array();
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
    protected $databaseFields = array();
    /**
     * The required DB fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array();
    /**
     * Keys that end in "id" but do not hold a foreign key.
     *
     * save() and isValid() both infer "this is an integer id" from the key's
     * name, which is right for every real foreign key in the tree and wrong
     * for a string identifier that happens to end the same way -- a system
     * UUID, a task id kept in a text column. The name is a proxy for the
     * column's type, and the model is the only thing that knows the actual
     * type, so it says so here.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = array();
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
     * Fields to ignore.
     *
     * @var array
     */
    protected $databaseFieldsToIgnore = array(
        'createdBy',
        'createdTime',
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
            if (!count($this->databaseFields)) {
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
        $str = sprintf('%s ID: %s', get_class($this), $this->get('id'));
        if ($this->get('name')) {
            $str = sprintf('%s %s: %s', $str, _('Name'), $this->get('name'));
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
            print_r(isset($this->data[$key]) ? $this->data[$key] : 'null', 1)
        );
        self::info($msg);

        return isset($this->data[$key]) ? $this->data[$key] : null;
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
                '%s: %s, $s: %s',
                _('Setting Key'),
                $key,
                _('Value'),
                print_r($value, 1)
            );
            self::info($msg);
            $this->data[$key] = $value;
            $this->dirty[$key] = true;
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
                $this->data[$key] = array($this->data[$key]);
            }
            $this->data[$key][] = $value;
            $this->dirty[$key] = true;
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
                $this->data[$key] = (array)$this->data[$key];
            }
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
            $this->dirty[$key] = true;
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
            $insertValKeys = [];
            $insertValues = [];
            $updateData = [];

            if (count($this->aliasedFields ?: []) > 0) {
                self::arrayRemove($this->aliasedFields, $this->databaseFields);
            }

            // Build a lookup of required keys (normalized the same way isValid() does)
            $required = [];
            foreach ($this->databaseFieldsRequired as $reqKey) {
                $reqKeyNorm = $this->key($reqKey);
                $required[$reqKeyNorm] = true;
            }

            // Keys the model has declared are NOT foreign keys, normalized the
            // same way, so the branch below can ask about $key directly.
            $notInt = [];
            foreach ($this->databaseFieldsNotInt as $strKey) {
                $notInt[$this->key($strKey)] = true;
            }

            foreach ($this->databaseFields as $rawKey => $column) {
                $key = $this->key($rawKey);
                $column = trim($column);

                if ($column === '') {
                    continue;
                }

                $eColumn = sprintf('`%s`', $column);
                $paramInsert = sprintf(':%s_insert', $column);

                // GH-1245: set when the column is to be written as a real
                // SQL NULL rather than left out of the statement.
                $writeNull = false;

                $val = $this->get($key);

                // Primary key 'id': allow null/empty/0 so DB auto-increments.
                // If it's not a valid positive int, we exclude it from INSERT/UPDATE.
                if (strtolower($key) === 'id') {
                    $validId = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($validId === false) {
                        continue; // omit id column entirely
                    }
                    $val = (int)$validId;
                }

                // Keys ending with "id" (case-insensitive), unless the model
                // has said this one is a string rather than a foreign key.
                elseif (strtolower(substr($key, -2)) === 'id'
                    && !isset($notInt[$key])
                ) {
                    $isRequired = isset($required[$key]);
                    $isEmpty = ($val === null) || (is_string($val) && trim($val) === '');

                    if ($isRequired) {
                        // Required *id must be integer >= 1
                        $validated = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($validated === false) {
                            throw new Exception(self::$foglang['RequiredDB'] . ": " . $key);
                        }
                        $val = (int)$validated;
                    } else {
                        // Optional *id: allow empty -> 0 (no association); if present, require integer >= 1
                        if ($isEmpty) {
                            $val = 0;
                        } else {
                            $validated = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                            if ($validated === false) {
                                $validated = 0;
                            }
                            $val = (int)$validated;
                        }
                    }
                } else {
                    $isRequired = isset($required[$key]);
                    $isEmpty = ($val === null) || (is_string($val) && trim($val) === '');
                    if ($isEmpty) {
                        if ($isRequired) {
                            throw new Exception(self::$foglang['RequiredDB'] . ": " . $key);
                        }
                        // GH-1245: '' is a value only a string column can
                        // hold. Everywhere else the server was coercing it;
                        // emptyValueFor() writes down what to.
                        $val = self::emptyValueFor($this->databaseTable, $column);
                        /*
                         * A NULL for a column that cannot hold one means
                         * "leave it out and let the server's DEFAULT apply".
                         * Binding it is error 1048 -- snapinTasks
                         * .stCheckinDate and userTracking.utDateTime are
                         * NOT NULL DEFAULT current_timestamp(), which is why
                         * schema step 284 leaves them alone, and MySQL 8 ships
                         * explicit_defaults_for_timestamp=ON so an explicit
                         * NULL is refused rather than turned into "now".
                         */
                        $writeNull = (null === $val)
                            && self::columnIsNullable($this->databaseTable, $column);
                    }
                }

                switch ($key) {
                    case 'createdBy':
                        // Only treat null/empty-string as missing (don't treat "0" as empty)
                        if ($val === null || (is_string($val) && trim($val) === '')) {
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
                }

                // Normalize strings (but preserve NULL)
                if ($val !== null && is_string($val)) {
                    $val = trim($val);
                }

                // Don't make an entry if the value isn't set (null = truly unset).
                // Empty string is a valid user-supplied value and must be written.
                //
                // GH-1245: an emptied DATE column is the exception. Omitting
                // it would leave ON DUPLICATE KEY UPDATE with nothing to say
                // about that column, so an existing date could never be
                // cleared -- the write would report success and change
                // nothing. It is bound as a real NULL instead.
                if ($val === null && !$writeNull) {
                    continue;
                }

                $insertKeys[] = $eColumn;
                $insertValKeys[] = $paramInsert;
                $insertValues[] = $val;

                $updateData[] = sprintf('%s=VALUES(%s)', $eColumn, $eColumn);
            }

            $query = sprintf(
                $this->insertQueryTemplate,
                $this->databaseTable,
                implode(',', (array)$insertKeys),
                implode(',', (array)$insertValKeys),
                'ON DUPLICATE KEY UPDATE',
                implode(',', (array)$updateData)
            );

            $queryArray = array_combine($insertValKeys, $insertValues);

            $msg = sprintf(
                '%s %s %s',
                _('Saving data for'),
                get_class($this),
                _('object')
            );
            self::info($msg);

            self::$DB->query($query, [], $queryArray);
            /*
             * PDODB swallows a rejected statement, so ASK it.
             *
             * PDO runs in ERRMODE_EXCEPTION, but PDODB::query() catches the
             * PDOException, records the message on ->error and returns
             * normally; it rethrows only when $throwOnQueryError is true,
             * which nothing sets and which must not be set globally -- it
             * would turn every already-tolerated failure across the codebase
             * into an uncaught 500 at once.
             *
             * So without this check the catch below never runs on a real SQL
             * error. For a NEW row that was survivable by accident: insertId()
             * comes back 0 and the "no valid ID was assigned" throw further
             * down catches it. For an EXISTING row -- every progress update,
             * every task state change, every inventory write against a known
             * host -- there was nothing to catch on, so save() went on to log
             * the SUCCESS message and return $this. `if (!$obj->save())` was
             * not merely unrecorded on those paths, it was answered "fine".
             *
             * Truthy rather than `false !== ...`: PDODB declares $error with
             * no default, so it is null until the first statement runs.
             */
            if (self::$DB->error) {
                throw new Exception((string) self::$DB->error);
            }
            $lastInsertID = self::$DB->insertId();

            // Force ID correctness: if we still don't have a valid ID, this wasn't created properly.
            $currentId = $this->get('id');
            $validCurrentId = filter_var($currentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($validCurrentId === false) {
                $newId = (int)$lastInsertID;
                if ($newId > 0) {
                    $this->set('id', $newId);
                } else {
                    // This prevents "Task ID: 0 ... successfully updated" lies.
                    throw new Exception(_('Save completed but no valid ID was assigned (insertId=0). Possible duplicate-key update or missing auto-increment.'));
                }
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
                '%s: %s: %s, %s: %s, %s: %s, %s: %s',
                _('Database save failed'),
                _('Class'),
                get_class($this),
                _('Table'),
                $this->databaseTable,
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);
            /*
             * The line that actually gets written. debug() on this branch
             * writes to no file at all and returns immediately on a service
             * or ajax request, and logHistory() needs somebody signed in --
             * neither is true on the paths that generate most of these.
             * See FOGBase::logFault().
             */
            self::logFault($msg);

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
            $join = $whereArrayAnd = array();
            $c = null;
            $this->buildQuery($join, $whereArrayAnd, $c);
            $join = array_filter((array) $join);
            $join = implode((array) $join);
            $fields = array();
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
                    count($whereArrayAnd) ?
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
                array(),
                $queryArray
            );
            $vals = self::$DB->fetch()->get();
            /*
             * A rejected SELECT is swallowed the same way a rejected INSERT
             * is -- see save(). fetch()->get() then hands back nothing, and
             * an object that could not be read is indistinguishable from a
             * row that genuinely holds no data. That is the read half of the
             * same defect: not a wrong answer anybody can see, a plausible
             * empty one.
             *
             * AFTER the fetch, not between it and the query, so that ONE
             * check covers both halves of the read. fetch() records its own
             * failure on ->error and never clears one, and query() always
             * sets ->error immediately before -- so a fetch that failed
             * because the query did still reports the query's message here,
             * not "No query result, use query() first".
             *
             * Recorded HERE rather than in the catch below, and that split is
             * the point. This catch also handles the method's ORDINARY
             * control flow -- "Operation field not set" fires on every
             * `new Host()` built without an id, which is constant traffic --
             * so faulting the whole catch would bury the one line that
             * matters under thousands that do not.
             *
             * Throwing after logging costs nothing and buys the debug line
             * below: setQuery() merges (fastmerge, never clears), so skipping
             * it with nothing to merge leaves the object exactly as it was.
             * load() still returns $this either way -- `new Host(42)` must
             * not become fatal because a read failed.
             */
            if (self::$DB->error) {
                self::logFault(
                    sprintf(
                        '%s: %s: %s, %s: %s, %s: %s, %s: %s',
                        _('Database load failed'),
                        _('Class'),
                        get_class($this),
                        _('Table'),
                        $this->databaseTable,
                        _('Key'),
                        $key,
                        _('Error'),
                        self::$DB->error
                    )
                );
                throw new Exception((string) self::$DB->error);
            }
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
        if (count($this->databaseFields) > 0) {
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
            self::$DB->query($query, array(), $queryArray);
            // Same reason as save()'s, above: a rejected DELETE is swallowed
            // by PDODB, so destroy() reported success for a row still there.
            // A DELETE matching nothing is not an error and does not land
            // here -- only a statement the server actually rejected does.
            if (self::$DB->error) {
                throw new Exception((string) self::$DB->error);
            }
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
                '%s: %s: %s, %s: %s, %s: %s, %s: %s',
                _('Destroy failed'),
                _('Class'),
                get_class($this),
                _('Table'),
                $this->databaseTable,
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);
            /*
             * The line that actually gets written. debug() on this branch
             * writes to no file at all and returns immediately on a service
             * or ajax request, and logHistory() needs somebody signed in --
             * neither is true on the paths that generate most of these.
             * See FOGBase::logFault().
             */
            self::logFault($msg);

            return false;
        }

        return $this;
    }
    /**
     * Gets the relevant common key if available.
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
            // A loadX() method caches what it fetched via set(), which
            // marks $key dirty as an unavoidable side effect. That's a
            // cache-fill, not a caller-driven change, so retract the mark
            // immediately -- isDirty() should only ever report true for a
            // key a caller actually meant to write.
            unset($this->dirty[$key]);
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
        if (!in_array($array_type, array('merge', 'diff'))) {
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
                break;
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
            // The same opt-out save() honors. Both methods carry their own
            // copy of the "ends in id, so it is a foreign key" inference, so
            // both need the exclusion: fixing only save() lets an object save
            // its string identifier and then fail validation forever after.
            $notInt = [];
            foreach ($this->databaseFieldsNotInt as $strKey) {
                $notInt[$this->key($strKey)] = true;
            }

            foreach ($this->databaseFieldsRequired as $reqKey) {
                $key = $this->key($reqKey);
                $val = $this->get($key);

                // If key ends with ID (case-insensitive), require integer >= 1
                if (strtolower(substr($key, -2)) === 'id'
                    && !isset($notInt[$key])
                ) {
                    if (filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        throw new Exception(self::$foglang['RequiredDB'] . ": " . $key);
                    }
                    continue; // don't fall through to the generic empty-check
                }

                // Generic "required" check for non-ID fields:
                // treat null / empty string as missing, but allow 0 / "0"
                if ($val === null || (is_string($val) && trim($val) === '')) {
                    throw new Exception(self::$foglang['RequiredDB'] . ": " . $key);
                }
            }

            // Validate the model's own 'id' field
            if (filter_var($this->get('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new Exception(_('Invalid ID passed'));
            }

            if (array_key_exists('name', $this->databaseFields)) {
                $val = trim($this->get('name'));
            }

        } catch (Exception $e) {
            $str = sprintf('%s: %s: %s', _('Failed'), _('Error'), $e->getMessage());
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
            $not,
            $compare
        ) {
            $className = strtolower($class);
            $c = self::getClass($class);
            if (!array_key_exists($className, $join)) {
                // The relationship's optional 4th element is a filter on the
                // joined (optional) table. It must live in the JOIN ON clause,
                // not in WHERE: a WHERE condition on the right-hand table of a
                // LEFT JOIN silently degrades it to an INNER JOIN, dropping the
                // base row entirely when there is no matching joined row (e.g.
                // a host with no primary MAC would fail to load at all).
                $onExtra = '';
                if (isset($fields[3]) && $fields[3]) {
                    foreach ((array) $fields[3] as $filterField => $filterValue) {
                        if (is_array($filterValue)) {
                            $onExtra .= sprintf(
                                " AND `%s`.`%s` IN ('%s')",
                                $c->databaseTable,
                                $c->databaseFields[$filterField],
                                implode("','", $filterValue)
                            );
                        } else {
                            $onExtra .= sprintf(
                                " AND `%s`.`%s` = '%s'",
                                $c->databaseTable,
                                $c->databaseFields[$filterField],
                                $filterValue
                            );
                        }
                    }
                }
                $join[$className] = sprintf(
                    ' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s`%s ',
                    $c->databaseTable,
                    $c->databaseTable,
                    $c->databaseFields[$fields[0]],
                    $this->databaseTable,
                    $this->databaseFields[$fields[1]],
                    $onExtra
                );
            }
            $c->buildQuery($join, $whereArrayAnd, $c, $not, $compare);
            unset($class, $fields, $c);
        };
        $className = strtolower(get_class($this));
        if (!array_key_exists($className, $join)) {
            $join[$className] = false;
        }
        if (count($this->databaseFieldClassRelationships) > 0) {
            array_walk($this->databaseFieldClassRelationships, $joinInfo);
        }
        return array(implode((array) $join), $whereArrayAnd);
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
        if (count($classData) < 1) {
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
        $class = sprintf('%sManager', get_class($this));

        return new $class();
    }
    /**
     * Set's values for associative fields.
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
        // Lower our item
        $alterItem = strtolower($alterItem ?: $assocItem);
        // Getter is pluralized
        $plural = "{$alterItem}s";
        // Class to call, if implicit leave off association.
        $classCall = ($implicitCall ? $assocItem : "{$assocItem}Association");
        // Main object and string setters.
        $obj = strtolower(get_class($this));
        $objstr = "{$obj}ID";
        $assocstr = "{$alterItem}ID";

        // Don't work on an association the caller didn't actually touch.
        // isDirty(), not isPopulated(): isPopulated() is also true when a
        // key was merely lazy-loaded for reading (e.g. reported in a
        // status response), which would otherwise make this run a full
        // DB diff -- for a no-op result -- on every save() that happens
        // to read this association first. isDirty() only reports true
        // for a real caller-driven write, so an untouched association
        // costs nothing here, not even the Route::ids() lookup below.
        if (!$this->isDirty($plural)) {
            return $this;
        }

        // Get the current items, normalized to positive integer ids. Every
        // relation routed through here diffs on a "{$alterItem}ID" column, so
        // a non-positive or non-numeric entry can only be junk -- whether a
        // falsy get() fallback or a 0 sitting inside an otherwise valid list.
        $items = self::positiveIntIds($this->get($plural));
        Route::ids(
            $classCall,
            [$objstr => $this->get('id')],
            $assocstr
        );
        $cur = json_decode(Route::getData(), true);

        // Get the items differing between the current and what we have associated.
        // Remove the items if there's anything to remove.
        $rem = array_diff($cur, $items);
        if (count($rem)) {
            Route::deletemass(
                $classCall,
                [
                    $objstr => $this->get('id'),
                    $assocstr => $rem,
                ]
            );
        }

        // Setup our insert.
        $insert_fields = [
            $objstr,
            $assocstr
        ];
        $insert_values = [];
        if ($assocstr == 'moduleID') {
            $insert_fields[] = 'state';
        }
        foreach ($items as &$id) {
            $insert_val = [
                $this->get('id'),
                $id
            ];
            if ($assocstr == 'moduleID') {
                $insert_val[] = 1;
            }
            $insert_values[] = $insert_val;
            unset($insert_val, $id);
        }
        if (count($insert_values ?: []) > 0) {
            self::getClass("{$classCall}manager")->insertBatch(
                $insert_fields,
                $insert_values
            );
        }

        return $this;
    }
}
