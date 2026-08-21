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

namespace FOG;

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
     * Keys that end in "id" but do not hold a foreign key.
     *
     * save() infers "this is an integer id" from the key's name, which is
     * right for all 95 real foreign keys in the tree and wrong for a string
     * identifier that happens to end the same way -- an OIDC client id, a
     * system UUID. The name is a proxy for the column's type, and the model
     * is the only thing that knows the actual type, so it says so here.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = [];
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
     * The bulk sibling of loadQueryTemplate, used by loadMany().
     *
     * Identical apart from IN in place of =, so a bulk load sees exactly the
     * columns, joins and join filters a single load() would. Refs GH-707.
     *
     * @var string
     */
    protected $loadManyQueryTemplate = 'SELECT %s FROM `%s` %s WHERE `%s` IN (%s) %s';
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
                throw new \Exception(_('Table not defined for this class'));
            }
            if (!count($this->databaseFields ?: [])) {
                throw new \Exception(_('Fields not defined for this class'));
            }
            $this->databaseFieldsFlipped = array_flip($this->databaseFields);
            if (is_numeric($data) && $data > 0) {
                $this->set('id', $data)->load();
            } elseif (is_numeric($data)) {
                $this->set('id', $data);
            } elseif (is_array($data)) {
                $this->setQuery($data);
            }
        } catch (\Exception $e) {
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
            self::shortName($this),
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

        $retVal = isset($this->data[$key]) ? $this->data[$key] : '';
        $msg = sprintf(
            '%s: %s, %s: %s',
            _('Returning value of key'),
            $key,
            _('Value'),
            print_r($retVal, 1)
        );
        self::info($msg);

        return $retVal;
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
                throw new \Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                throw new \Exception(_('Invalid key being set'));
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
            $this->dirty[$key] = true;
        } catch (\Exception $e) {
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
                throw new \Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                throw new \Exception(_('Invalid key being added'));
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
            $this->dirty[$key] = true;
        } catch (\Exception $e) {
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
                throw new \Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                throw new \Exception(_('Invalid key being removed'));
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
            $this->dirty[$key] = true;
        } catch (\Exception $e) {
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
     * Column types per table, from commons/schema-expected.php.
     *
     * Null until first asked for; built once per request.
     *
     * @var array|null
     */
    private static $columnTypes = null;

    /**
     * The declared SQL type of a column, or '' when it is not in the manifest.
     *
     * commons/schema-expected.php carries per-column types and is what
     * OpenAPI::_entitySchema() reads for the same question, so this adds no
     * new source of truth. A missing or unreadable manifest returns '', which
     * emptyValueFor() treats as "assume a string column" -- the behaviour that
     * shipped before any of this.
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return string
     */
    protected static function columnType($table, $column)
    {
        if (null === self::$columnTypes) {
            self::$columnTypes = [];
            $manifest = SchemaReconciler::manifest();
            $tables = isset($manifest['tables']) && is_array($manifest['tables'])
                ? $manifest['tables']
                : [];
            foreach ($tables as $tName => $tDef) {
                if (!isset($tDef['columns']) || !is_array($tDef['columns'])) {
                    continue;
                }
                foreach ($tDef['columns'] as $cName => $cType) {
                    self::$columnTypes[strtolower($tName)][strtolower($cName)]
                        = trim($cType);
                }
            }
        }
        $t = strtolower($table);
        if (!isset(self::$columnTypes[$t])) {
            self::_loadPluginColumnTypes($t);
        }
        return self::$columnTypes[$t][strtolower($column)] ?? '';
    }

    /**
     * Loads one table's columns from the server's own catalog.
     *
     * commons/schema-expected.php describes core's 67 tables and nothing
     * else, so a plugin's table is not in it -- and GH-1245's first cut
     * therefore answered '' for every plugin column, which is precisely the
     * bug it set out to fix. That was invisible while PDODB cleared sql_mode;
     * with the clear gone the server refuses the write instead of coercing
     * it, so saving an LDAP server without a port is error 1366 rather than a
     * silently stored 0. On the maintainer's own 1.6 install that is 18
     * tables, 16 enum/set and 44 integer columns.
     *
     * Not solved by adding plugin tables to the manifest: the manifest is
     * generated from core's schema and the reconciler uses it to decide what
     * to CREATE, so a plugin table listed there would be created for
     * everyone whether the plugin is installed or not.
     *
     * Asked once per table per request, and only for a table the manifest
     * does not cover -- core never gets here. An empty result is cached too,
     * so a table that genuinely does not exist is asked about once and then
     * behaves exactly as it did before this method existed.
     *
     * The definition is rebuilt as "<type> NOT NULL" rather than returned
     * raw, so columnIsNullable() reads it with the same regex it applies to
     * the manifest's strings and there is only one notion of "nullable".
     *
     * @param string $table the table, already lowercased
     *
     * @return void
     */
    private static function _loadPluginColumnTypes($table)
    {
        self::$columnTypes[$table] = [];
        try {
            $rows = self::$DB->query(
                "SELECT `COLUMN_NAME` AS `c`, `COLUMN_TYPE` AS `ty`, "
                . "`IS_NULLABLE` AS `n` FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND LOWER(`TABLE_NAME`) = :table",
                [],
                [':table' => $table]
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        } catch (\Exception $e) {
            // A catalog FOG cannot read leaves every column looking unknown,
            // which is the behaviour that shipped before GH-1245 rather than
            // a broken one.
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Column type lookup failed'),
                    _('Table'),
                    $table,
                    _('Error'),
                    $e->getMessage()
                )
            );
            return;
        }
        /*
         * The degradation is deliberate, the SILENCE was not. PDODB swallows
         * a rejected statement, so this never reached the catch above on a
         * real error -- it cached an empty type map and every column on the
         * table went back to being untyped, which is exactly the GH-1245 bug
         * this method exists to prevent, reappearing with nothing said.
         *
         * Behaviour is unchanged: still an empty map, still asked once per
         * table per request. Only now it is written down.
         */
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Column type lookup failed'),
                    _('Table'),
                    $table,
                    _('Error'),
                    self::$DB->error,
                    _('every column on this table will be treated as untyped')
                )
            );
            return;
        }
        foreach ((array) $rows as $row) {
            if (!isset($row['c'], $row['ty'])) {
                continue;
            }
            self::$columnTypes[$table][strtolower($row['c'])] = trim($row['ty'])
                . (isset($row['n']) && strtoupper($row['n']) === 'NO' ? ' NOT NULL' : '');
        }
    }

    /**
     * Can this column hold NULL?
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return bool
     */
    protected static function columnIsNullable($table, $column)
    {
        $type = self::columnType($table, $column);
        return '' !== $type && !preg_match('/\bNOT\s+NULL\b/i', $type);
    }

    /**
     * What an unset optional field should actually be written as.
     *
     * GH-1245. save() used to write '' for every unset optional field whose
     * key does not end in "id". '' is a value only a string column can hold.
     * Everywhere else the server either refuses it under a strict sql_mode or
     * coerces it without one, and FOG only ever saw the second, because
     * PDODB::_connect() cleared sql_mode on every connection. So this is not
     * new behaviour being introduced -- it is the coercion the server was
     * already performing, written down and made legal:
     *
     *   date/time  ->  NULL          (was '0000-00-00 00:00:00')
     *   integer    ->  0             (was 0, via error 1366 downgraded)
     *   enum/set   ->  first member  (was '', the error value at index 0)
     *   anything   ->  ''            (unchanged; '' is a real value here)
     *
     * The integer and enum choices deliberately match the coercion rather
     * than the column's DEFAULT. `hosts.hostEnforce` is declared
     * DEFAULT '1' and 73 of 86 rows on the maintainer's server hold '' --
     * so honouring the default would silently turn enforcement ON for those
     * hosts as a side effect of a storage fix. '' and '0' are both falsey in
     * PHP, so the first enum member behaves as the error value already did.
     *
     * The column's TYPE is the only reliable way to tell these apart; the
     * key's name is not, which is the lesson $databaseFieldsNotInt already
     * exists for.
     *
     * @param string $table  the database table
     * @param string $column the database column
     *
     * @return mixed the value to write; null means a real SQL NULL
     */
    protected static function emptyValueFor($table, $column)
    {
        $type = self::columnType($table, $column);
        if ('' === $type) {
            return '';
        }
        if (preg_match('/^(datetime|timestamp|date)\b/i', $type)) {
            return null;
        }
        if (preg_match('/^(tiny|small|medium|big)?int\b/i', $type)) {
            return 0;
        }
        if (preg_match("/^(enum|set)\\s*\\(\\s*'((?:[^']|'')*)'/i", $type, $match)) {
            return str_replace("''", "'", $match[2]);
        }

        return '';
    }

    /**
     * The row as it was READ, before anything set() touched it.
     *
     * Empty until load() fills it, and empty forever on an object that was
     * built and saved without being read -- which is correct: there was no
     * "before", and the diff against an empty snapshot reads as a create.
     *
     * Filled from data FOG has already paid for. load() SELECTs every column
     * in one query, so this is an array copy of what is already in memory --
     * measured at -0.0058 ms/op against a 0.79 ms object load, which is to
     * say inside the noise. The alternative, re-reading the row before the
     * write, measured +17.6% per edited object and could not be made
     * consistent anyway: there are no transactions anywhere in packages/web
     * or packages/service, so the re-read is of a row that may already have
     * moved. See ADR 0021's Measurements section.
     *
     * @var array
     */
    protected $original = [];
    /**
     * Writes the auditChange rows for this save.
     *
     * Attaches to the header the authorization gate wrote for this request.
     * NO HEADER MEANS NO CHANGE ROWS, on purpose: a change row with nothing
     * saying who was allowed to make it is half a record, and the paths with
     * no gate -- service/, reg-task/, the daemons -- get headers of their own
     * in a later merge rather than orphaned detail now.
     *
     * The diff is restricted to keys this object actually set. A save writes
     * every column it can resolve, so diffing the whole row would report
     * every field of every object as changed-from-null on any path that did
     * not load first.
     *
     * Values are NOT filtered here. Redaction decides, inside Audit, because
     * a caller that decides for itself is exactly how a credential ends up in
     * a log -- twice in one week, in two subsystems (ADR 0021 Context 4).
     *
     * @return void
     */
    private function _auditChanges()
    {
        $audit = Audit::current();
        if (!$audit || $this->_isLogTable()) {
            return;
        }
        $diff = [];
        foreach (array_keys((array)$this->dirty) as $key) {
            // Columns only. additionalFields and the objects built by
            // databaseFieldClassRelationships are not storage, and an object
            // has no meaningful before/after to write down.
            if (!isset($this->databaseFields[$key])) {
                continue;
            }
            $old = $this->original[$key] ?? null;
            $new = $this->data[$key] ?? null;
            if (is_object($old) || is_array($old)
                || is_object($new) || is_array($new)
            ) {
                continue;
            }
            // Cast both, so null and '' are the same non-change: set() is
            // called with either spelling all over the tree and neither is a
            // fact worth a row.
            if ((string)$old === (string)$new) {
                continue;
            }
            $diff[$key] = [$old, $new];
        }
        if (count($diff) < 1) {
            return;
        }
        Audit::changes($audit, $this, (int)$this->get('id'), $diff);
        // This state is now the state of record. Without it a second save in
        // the same request re-reports the first save's changes.
        $this->original = $this->data;
    }
    /**
     * Is this model one of the tables that RECORDS what happened?
     *
     * A log table must not log its own writes. save() and destroy() call
     * logHistory() on success and on failure, so without this a History row
     * would write a History row -- and, since ADR 0021, an auditLog row would
     * write a History row for every audited action, doubling the volume of
     * the table the audit trail exists to replace.
     *
     * Was four separate `!$this instanceof History` checks. Named, because
     * the reason is a property of the class and not an accident of which
     * classes happened to be excluded.
     *
     * @return bool
     */
    private function _isLogTable()
    {
        return $this instanceof History
            || $this instanceof AuditLog
            || $this instanceof AuditChange;
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

            // The model's own list of keys that end in "id" without being a
            // foreign key. Normalized the same way, so the branch below can
            // ask about $key directly.
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

                $val = $this->get($key);

                // GH-1245: set when an empty value has to reach the database
                // as a real SQL NULL rather than being omitted from the
                // statement. See the null guard below the switch.
                $writeNull = false;

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
                // has said this one is a string. Without that exclusion a
                // required string id throws "Required database field is
                // empty" while holding a perfectly good value, and an
                // optional one is silently rewritten to 0.
                elseif (strtolower(substr($key, -2)) === 'id'
                    && !isset($notInt[$key])
                ) {
                    $isRequired = isset($required[$key]);
                    $isEmpty = ($val === null) || (is_string($val) && trim($val) === '');

                    if ($isRequired) {
                        // Required *id must be integer >= 1
                        $validated = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($validated === false) {
                            throw new \Exception(self::$foglang['RequiredDB'] . ": " . $key);
                        }
                        $val = (int)$validated;
                    } else {
                        // Optional *id: allow empty -> NULL; if present, require integer >= 1
                        if ($isEmpty) {
                            /*
                             * GH-1245: a null value is omitted from the
                             * statement further down, which is right for a
                             * nullable column -- the server stores NULL. For a
                             * NOT NULL one it is error 1364, "field doesn't
                             * have a default value", on any strict server;
                             * without one the server quietly supplied 0, which
                             * is what `hosts.hostImage` has been getting for
                             * every host created without an image.
                             *
                             * So write that 0 rather than leaving the column
                             * out and depending on the server to guess.
                             */
                            if (self::columnIsNullable($this->databaseTable, $column)) {
                                $val = null;
                            } else {
                                $val = 0;
                            }
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
                            throw new \Exception(self::$foglang['RequiredDB'] . ": " . $key);
                        }
                        // GH-1245: '' is a value only a string column can
                        // hold. Everywhere else the server was coercing it;
                        // emptyValueFor() writes down what to.
                        $val = self::emptyValueFor(
                            $this->databaseTable,
                            $column
                        );
                        /*
                         * A NULL for a column that cannot hold one means
                         * "leave it out and let the server's DEFAULT apply".
                         * Binding it is error 1048 -- `snapinTasks
                         * .stCheckinDate` and `userAuths.uaExpireDate` are
                         * NOT NULL DEFAULT current_timestamp(), which is why
                         * step 344 left them alone, and MySQL 8 ships
                         * explicit_defaults_for_timestamp=ON so an explicit
                         * NULL is refused rather than turned into "now".
                         */
                        $writeNull = (null === $val)
                            && self::columnIsNullable(
                                $this->databaseTable,
                                $column
                            );
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
                self::shortName($this),
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
             * no default, so it is null until the first statement runs, and
             * Schema::_processSchema()'s `false !== ->error` idiom would fire
             * on that. Truthy reads both spellings of "no error" the same way.
             */
            if (self::$DB->error) {
                throw new \Exception((string) self::$DB->error);
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
                    throw new \Exception(_('Save completed but no valid ID was assigned (insertId=0). Possible duplicate-key update or missing auto-increment.'));
                }
            }

            if (!$this->_isLogTable() && !$this instanceof Plugin) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s.',
                        self::shortName($this),
                        _('ID'),
                        $this->get('id'),
                        _('NAME'),
                        $this->get('name'),
                        _('has been successfully updated')
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s.',
                        self::shortName($this),
                        _('ID'),
                        $this->get('id'),
                        _('has been successfully updated')
                    );
                }
                self::logHistory($msg);
            }
            // After the row is known to have stored, and after its id
            // exists: a change row pointing at subject 0 is worse than none.
            $this->_auditChanges();
        } catch (\Exception $e) {
            if (!$this->_isLogTable()) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s. %s: %s',
                        self::shortName($this),
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
                        self::shortName($this),
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
                self::shortName($this),
                _('Table'),
                $this->databaseTable,
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);
            /*
             * The line that actually gets written. debug() above needs
             * FOG_LOG_DEBUG turned on, and logHistory() needs somebody signed
             * in -- neither is true on the paths that generate most of these,
             * which is the whole of packages/web/service/, lib/reg-task/ and
             * the eight daemons. See FOGBase::logFault().
             *
             * Written LAST, after the history row has been attempted rather
             * than before, so the fault line names the failure that started
             * this and any second failure logging it produces gets its own
             * line rather than replacing that one. Which class and table
             * carry it are in the message because the reader of this file has
             * only the message: there is no row to join back to.
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
                throw new \Exception(_('Key field must be a string'));
            }
            if (!$key) {
                throw new \Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                throw new \Exception(_('Invalid key being added'));
            }
            $val = $this->get($key);
            if (!$val) {
                throw new \Exception(
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
             * it with no rows to merge leaves the object exactly as it was.
             * load() still returns $this either way -- `new Host(42)` must
             * not become fatal because a read failed.
             */
            if (self::$DB->error) {
                self::logFault(
                    sprintf(
                        '%s: %s: %s, %s: %s, %s: %s, %s: %s',
                        _('Database load failed'),
                        _('Class'),
                        self::shortName($this),
                        _('Table'),
                        $this->databaseTable,
                        _('Key'),
                        $key,
                        _('Error'),
                        self::$DB->error
                    )
                );
                throw new \Exception((string) self::$DB->error);
            }
            $this->setQuery($vals);
            // The audit "before", taken here because here is where the row
            // exists in memory for free. FIRST read only: loadItem() calls
            // load() again for a lazily-fetched key, and refreshing the
            // snapshot then would fold changes already made into the
            // baseline and report them as no change at all.
            if (count($this->original) < 1) {
                $this->original = $this->data;
            }
        } catch (\Exception $e) {
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
     * Loads many records of this class in a single query.
     *
     * The bulk counterpart of load(). It reuses the same buildQuery()/
     * getcolumns() pair, so a record hydrated here is indistinguishable from
     * one that load() produced -- same joins, same join filters, same
     * relationship objects hung off it -- rather than a thinner
     * SELECT * that would quietly differ once someone called a getter that
     * relies on a joined table.
     *
     * Called on a prototype instance, e.g.
     * `self::getClass('Image')->loadMany([1, 2, 3])`. Refs GH-707.
     *
     * @param array  $vals The values to load.
     * @param string $key  The field those values are for.
     *
     * @return array The loaded objects, keyed by their $key value. Values
     *               with no matching record are simply absent.
     */
    public function loadMany(array $vals, $key = 'id')
    {
        $objects = [];
        try {
            if (!is_string($key) || !$key) {
                throw new \Exception(_('Key field must be a string'));
            }
            $key = $this->key($key);
            if (!$this->_testFields($key)) {
                throw new \Exception(_('Invalid key being requested'));
            }
            $vals = array_values(
                array_unique(
                    array_filter(
                        $vals,
                        function ($val) {
                            return null !== $val && '' !== $val;
                        }
                    )
                )
            );
            if (count($vals ?: []) < 1) {
                return $objects;
            }
            $join = $whereArrayAnd = [];
            $c = null;
            $this->buildQuery($join, $whereArrayAnd, $c);
            $join = implode((array) array_filter((array) $join));
            $fields = [];
            $this->getcolumns($fields);
            $realKey = $this->databaseFields[$key];
            $holders = [];
            $queryArray = [];
            foreach ($vals as $index => &$val) {
                $holder = sprintf(':loadmany%d', $index);
                $holders[] = $holder;
                $queryArray[$holder] = $val;
                unset($val);
            }
            $query = sprintf(
                $this->loadManyQueryTemplate,
                implode(',', $fields),
                $this->databaseTable,
                $join,
                $realKey,
                implode(',', $holders),
                (
                    count($whereArrayAnd ?: []) ?
                    sprintf(
                        ' AND %s',
                        implode(' AND ', $whereArrayAnd)
                    ) :
                    ''
                )
            );
            self::$DB->query($query, [], $queryArray);
            $rows = self::$DB
                ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
                ->get();
            // Same as load()'s, after the fetch for the same reason: a
            // rejected bulk read returns an EMPTY set, and the caller reads
            // that as "none of those ids exist" rather than "the question
            // was not asked".
            if (self::$DB->error) {
                self::logFault(
                    sprintf(
                        '%s: %s: %s, %s: %s, %s: %s, %s: %d, %s: %s',
                        _('Database bulk load failed'),
                        _('Class'),
                        self::shortName($this),
                        _('Table'),
                        $this->databaseTable,
                        _('Key'),
                        $key,
                        _('Requested'),
                        count($vals),
                        _('Error'),
                        self::$DB->error
                    )
                );
                throw new \Exception((string) self::$DB->error);
            }
            // class-name consumer: fed straight back to getClass(), which
            // resolves a namespaced name and a global one alike.
            $classname = get_class($this);
            foreach ((array) $rows as &$row) {
                if (!isset($row[$realKey])) {
                    unset($row);
                    continue;
                }
                $id = $row[$realKey];
                // A one-to-many join repeats the base row. load() keeps the
                // first row it is handed and ignores the rest, so match it.
                if (!isset($objects[$id])) {
                    $objects[$id] = self::getClass($classname)->setQuery($row);
                }
                unset($row);
            }
        } catch (\Exception $e) {
            $str = sprintf(
                '%s: %s: %s, %s: %s',
                _('Bulk load failed'),
                _('Key'),
                $key,
                _('Error'),
                $e->getMessage()
            );
            self::debug($str);
        }

        return $objects;
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
                throw new \Exception(_('No key being requested'));
            }
            $test = $this->_testFields($key);
            if (!$test) {
                throw new \Exception(_('Invalid key being destroyed'));
            }
            $val = $this->get($key);
            if (!is_numeric($val) && !$val) {
                throw new \Exception(
                    sprintf(
                        '%s: %s',
                        _('Operation field not set'),
                        $key
                    )
                );
            }
            // Lockout guard for the other delete path. Route::deletemass()
            // covers the API and assocSetter()'s cascade; this covers a
            // model destroyed directly. Both build their own DELETE, so
            // neither can rely on the other. Only the by-id form is
            // guarded: destroying by some other key is a bulk operation
            // that goes through deletemass() in practice, and resolving
            // arbitrary keys to ids here would cost a query on every
            // destroy() in the system.
            if ('id' === $key) {
                // Short name: the callee switches on 'user'/'role'/
                // 'usergroup' with a `default: return;`, so a namespaced
                // FQCN here would silently skip the lockout guard.
                Authorization::assertAdminRemainsAfterDelete(
                    strtolower(self::shortName($this)),
                    [$val]
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
            // Same reason as save()'s, above: a rejected DELETE is swallowed
            // by PDODB, so destroy() reported success for a row still there.
            // A DELETE matching nothing is not an error and does not land
            // here -- only a statement the server actually rejected does.
            if (self::$DB->error) {
                throw new \Exception((string) self::$DB->error);
            }
            if (!$this->_isLogTable()) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s.',
                        self::shortName($this),
                        _('ID'),
                        $this->get('id'),
                        _('Name'),
                        $this->get('name'),
                        _('has been successfully destroyed')
                    );
                } else {
                    $msg = sprintf(
                        '%s %s: %s %s.',
                        self::shortName($this),
                        _('ID'),
                        $this->get('id'),
                        _('has been successfully destroyed')
                    );
                }
                self::logHistory($msg);
                // ADR 0021 Decision 7: a delete writes a header carrying
                // subjectType/subjectID/subjectLabel and NO auditChange rows.
                // The header is the only record a delete leaves, so without
                // this it says that somebody exercised host.delete and not
                // which host -- and the row is gone, so nothing can recover
                // it afterwards. Identified here rather than at the gate
                // because this is where the label is still in hand, and it
                // reads the same two fields the history line above does so
                // the two cannot disagree.
                Audit::identify(
                    strtolower(self::shortName($this)),
                    (int)$this->get('id'),
                    (string)$this->get('name')
                );
            }
        } catch (\Exception $e) {
            if (!$this->_isLogTable()) {
                if ($this->get('name')) {
                    $msg = sprintf(
                        '%s %s: %s %s: %s %s. %s: %s',
                        self::shortName($this),
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
                        self::shortName($this),
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
                self::shortName($this),
                _('Table'),
                $this->databaseTable,
                _('ID'),
                $this->get('id'),
                _('Error'),
                $e->getMessage()
            );
            self::debug($msg);
            // Same sink as save()'s, for the same reason.
            self::logFault($msg);

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
            throw new \Exception(_('No key being requested'));
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
     * Loads the related host ids into the 'hosts' field.
     *
     * Shared by entity classes (image, group, printer, snapin, ...) whose
     * loadHosts() differs only by the route queried, the filter applied and
     * the id field plucked.
     *
     * @param string $route the route to query
     * @param array  $find  the filter to apply
     * @param string $field the id field to pluck
     *
     * @return void
     */
    protected function _loadHostIds($route, array $find, $field = 'id')
    {
        $this->set('hosts', (array)Route::getIds($route, $find, $field));
    }
    /**
     * Sets the given storage group as the primary one for an entity.
     *
     * Creates the storagegroup association if missing, clears the primary
     * flag on all of the entity's associations, then marks the chosen group
     * primary. Shared by image/snapin, whose association schema differs only
     * by the entity id field, the route and the association class.
     *
     * @param int    $groupID    the storage group id to set as primary
     * @param int    $entityID   the owning entity id
     * @param string $field      the entity id field (e.g. 'imageID')
     * @param string $assocRoute the association route to query
     * @param string $assocClass the association class name
     *
     * @return void
     */
    protected static function _setPrimaryGroup(
        $groupID,
        $entityID,
        $field,
        $assocRoute,
        $assocClass
    ) {
        $find = [
            'storagegroupID' => $groupID,
            $field => $entityID
        ];
        $exists = Route::getIds(
            $assocRoute,
            $find,
            'storagegroupID'
        );
        if (count($exists) < 1) {
            self::getClass($assocClass)
                ->set($field, $entityID)
                ->set('storagegroupID', $groupID)
                ->save();
        }
        $manager = $assocClass . 'Manager';
        // Unset all current groups to non-primary
        self::getClass($manager)->update(
            [$field => $entityID],
            '',
            ['primary' => 0]
        );
        // Set the passed group as primary
        self::getClass($manager)->update(
            [
                $field => $entityID,
                'storagegroupID' => $groupID
            ],
            '',
            ['primary' => 1]
        );
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
            throw new \Exception(_('No key being requested'));
        }
        $test = $this->_testFields($key);
        if (!$test) {
            throw new \Exception(_('Invalid key being requested'));
        }
        if (!in_array($array_type, ['merge', 'diff'])) {
            throw new \Exception(
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
            // The same opt-out save() honors (GH-1153): a key ending in "id"
            // that the model has declared is not a foreign key gets the
            // generic non-empty check, not the integer one. Without this an
            // OIDC provider whose clientId is "fog-web" is valid enough to
            // save and then invalid forever after, so its login button
            // silently never renders. Built here rather than read in the
            // loop because key() has to run over the declared names too.
            $notInt = [];
            foreach ($this->databaseFieldsNotInt as $strKey) {
                $notInt[$this->key($strKey)] = true;
            }

            foreach ($this->databaseFieldsRequired as &$key) {
                $key = $this->key($key);
                $val = $this->get($key);

                // If key ends with ID (case-insensitive), require integer >= 1
                if (strtolower(substr($key, -2)) === 'id'
                    && !isset($notInt[$key])
                ) {
                    if (filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        throw new \Exception(self::$foglang['RequiredDB'] . ": " . $key);
                    }
                    continue; // don't fall through to the generic empty-check
                }

                // Generic "required" check for non-ID fields:
                // treat null / empty string as missing, but allow 0 / "0"
                if ($val === null || (is_string($val) && trim($val) === '')) {
                    throw new \Exception(self::$foglang['RequiredDB'] . ": " . $key);
                }
            }

            // Validate the model's own 'id' field
            if (filter_var($this->get('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new \Exception(_('Invalid ID passed'));
            }

            if (array_key_exists('name', $this->databaseFields)) {
                $val = trim($this->get('name'));
            }

        } catch (\Exception $e) {
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
        // Short name: this is a join-table key, compared against keys the
        // relationship map supplies as bare lowercase class names.
        $className = strtolower(self::shortName($this));
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
        // Short name: a partially namespaced tree can have FOG\Host while
        // HostManager is still global, so derive the bare name and let the
        // autoloader (and, after Phase 3, the compatibility alias) resolve it.
        $man = self::shortName($this).'Manager';
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
        // Short name: $objstr below becomes a database column name.
        $obj = strtolower(self::shortName($this));
        $objstr = "{$obj}ID";
        $assocstr = "{$alterItem}ID";

        // Don't work on an association the caller didn't actually touch.
        // isDirty(), not isPopulated(): isPopulated() is also true when a
        // key was merely lazy-loaded for reading (e.g. reported in a
        // status response), which would otherwise make this run a full
        // DB diff -- for a no-op result -- on every save() that happens
        // to read this association first. isDirty() only reports true
        // for a real caller-driven write, so an untouched association
        // costs nothing here, not even the Route::getIds() lookup below.
        if (!$this->isDirty($plural)) {
            return $this;
        }

        // Get the current items, normalized to positive integer ids.
        //
        // Every relation routed through here diffs on a "{$alterItem}ID"
        // column, so a non-positive or non-numeric entry can only ever be
        // junk. Two shapes of junk reach this point: a wholly falsy get()
        // fallback (an empty string casts to [''], which subtracts nothing
        // from $cur and then inserts as id 0), and a 0 sitting inside an
        // otherwise valid list.
        //
        // Filtering here rather than at the caller is the point. The filter
        // used to live in Host::save() alone (8e31b5cf0), which left the
        // other 23 assocSetter() call sites unguarded -- and patching a
        // shared hole at one caller is what produced the snapin wipe this
        // supersedes (PR #906). A standing property of the writer cannot be
        // bypassed by a caller that does not know it needs to opt in.
        $items = self::positiveIntIds($this->get($plural));

        // Fetch current associations from the database.
        $cur = Route::getIds(
            $classCall,
            [$objstr => $this->get('id')],
            $assocstr
        );

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
     * @param string $assocOrder Alias to ORDER BY when the grid sorts on the
     *                           association column, for a custom $qStr whose
     *                           association value does not sort the way it
     *                           reads (group's all/some/none tri-state).
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
        $qTotalStr = '',
        $assocOrder = ''
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
        // Short name: both of these become database column names.
        $itemassocID = strtolower(self::shortName($this)). 'ID';
        $secondID = strtolower(self::shortName($this)). 'Assoc';
        // $secvars only exists when a secondary is supplied; $secondRID is
        // likewise only consumed inside the secondary branches below, so keep
        // its computation guarded to avoid touching an undefined $secvars.
        $secondRID = '';
        if ($secondary) {
            $secondRID = (isset($secvars[$itemassocID]) ? $secvars[$itemassocID] : $secvars['id']);
        }

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
                            // Aisle 097: entity names are stored verbatim (Initiator
                            // only trims and strips NULs), and this string is emitted
                            // as JSON then injected as raw HTML by DataTables column 0
                            // in every association tab. entityLink() escapes, so that
                            // holds for every tab using the default association column
                            // set -- and it is now the single sink for the `Name -
                            // (id)` format, which the 'mainlink' formatter in
                            // route.class.php also goes through. It did not before:
                            // that one emitted `(id) - Name`, and the comment this
                            // replaces wrongly claimed the two already matched.
                            return self::entityLink($primary, $row[$idField], $d);
                        }
                    ];
                    break;
            }
            unset($real);
        }
        if ($secondary) {
            $assocColumn = [
                'do' => $secondID,
                'dt' => 'association'
            ];
            if ('' !== $assocOrder) {
                $assocColumn['order'] = $assocOrder;
            }
            $columns[] = $assocColumn;
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\FOGController', 'FOGController');
