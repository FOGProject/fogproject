<?php
/**
 * FOG Manager Controller, main object mass getter.
 *
 * PHP version 7.4+
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FOG Manager Controller, main object mass getter.
 *
 * @category FOGManagerController
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGManagerController extends FOGBase
{
    /**
     * The main class for the object.
     *
     * @var string
     */
    protected $childClass;
    /**
     * The table name for the object.
     *
     * @var string
     */
    protected $databaseTable;
    /**
     * The common names and fields.
     *
     * @var array
     */
    protected $databaseFields = array();
    /**
     * The Flipped fields.
     *
     * @var array
     */
    protected $databaseFieldsFlipped = array();
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array();
    /**
     * The Class relationships.
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array();
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = array();
    /**
     * The load template.
     *
     * SELECT <field(s)> FROM `<table>` <join> <where>
     *
     * @var string
     */
    protected $loadQueryTemplate = 'SELECT %s FROM `%s` %s %s %s %s %s';
    /**
     * The count template.
     *
     * @var string
     */
    protected $countQueryTemplate = 'SELECT COUNT(`%s`.`%s`) AS `total` FROM `%s`%s LIMIT 1';
    /**
     * The update template.
     *
     * @var string
     */
    protected $updateQueryTemplate = 'UPDATE `%s` SET %s %s';
    /**
     * The destroy template.
     *
     * @var string
     */
    protected $destroyQueryTemplate = 'DELETE FROM `%s` WHERE `%s`.`%s` IN (%s)';
    /**
     * The exists template.
     *
     * @var string
     */
    protected $existsQueryTemplate = 'SELECT COUNT(`%s`.`%s`) AS `total` FROM `%s` WHERE `%s`.`%s`=%s AND `%s`.`%s` <> %s';
    /**
     * The insert batch template.
     *
     * @var string
     */
    protected $insertBatchTemplate = 'INSERT INTO `%s` (`%s`) VALUES %s ON DUPLICATE KEY UPDATE %s';
    /**
     * The distinct template.
     *
     * @var string
     */
    protected $distinctTemplate = 'SELECT COUNT(DISTINCT `%s`.`%s`) AS `total` FROM `%s`%s LIMIT 1';
    /**
     * Initializes the manager class.
     */
    public function __construct()
    {
        parent::__construct();
        $this->childClass = preg_replace(
            '#_?Manager$#',
            '',
            get_class($this)
        );
        $classVars = self::getClass(
            $this->childClass,
            '',
            true
        );
        $classGet = array(
            'databaseTable',
            'databaseFields',
            'additionalFields',
            'databaseFieldsRequired',
            'databaseFieldClassRelationships',
        );
        $this->databaseTable = &$classVars[$classGet[0]];
        $this->databaseFields = &$classVars[$classGet[1]];
        $this->additionalFields = &$classVars[$classGet[2]];
        $this->databaseFieldsRequired = &$classVars[$classGet[3]];
        $this->databaseFieldClassRelationships = &$classVars[$classGet[4]];
        $this->databaseFieldsFlipped = array_flip($this->databaseFields);
        unset($classGet);
    }
    /**
     * Finds items related to the main object.
     *
     * @param array  $findWhere     what to find
     * @param string $whereOperator how to combine where items
     * @param string $orderBy       how to order fields
     * @param string $sort          how the sort order
     * @param string $compare       how to compare
     * @param string $groupBy       how to group fields
     * @param bool   $not           use not operator
     * @param mixed  $idField       what fields to get
     * @param bool   $onecompare    second where uses AND
     * @param string $filter        array function for filter
     * @param string $scopeWhere    an object-boundary SQL fragment to AND on
     *
     * @return array
     */
    public function find(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false,
        $idField = false,
        $onecompare = true,
        $filter = 'array_unique',
        $scopeWhere = ''
    ) {
        // Fail safe defaults
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($sort)) {
            $sort = 'ASC';
        }
        $this->orderBy($orderBy);
        if (empty($compare)) {
            $compare = '=';
        }
        $findVals = array();
        $not = (
            $not ?
            ' NOT ' :
            ' '
        );
        $whereArray = $whereArrayAnd = array();
        if (count($findWhere) > 0) {
            $count = 0;
            foreach ($findWhere as $field => &$value) {
                $key = trim($field);
                if (!$value) {
                    $value = array(
                        '0',
                        0,
                        null,
                        '',
                    );
                }
                if (is_array($value) && count($value) > 0) {
                    foreach ($value as $i => &$val) {
                        if (is_string($val)) {
                            $val = trim($val);
                        }
                        // Define the key
                        $k = sprintf(
                            '%s_%d',
                            $key,
                            $i
                        );
                        // Define param keys
                        $findKeys[] = sprintf(
                            ':%s',
                            $k
                        );
                        // Define the param array
                        $findVals[$k] = $val;
                        unset($val);
                    }
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%sIN (%s)',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        $not,
                        implode(',', $findKeys)
                    );
                    unset($findKeys);
                } elseif (null === $value) {
                    /*
                     * GH-1245: a null filter asks for rows where the column
                     * holds nothing. Bound as a placeholder it becomes
                     * `col = NULL`, which is never true, so the query
                     * silently returns nothing -- and it now has callers,
                     * because the date columns that used to carry
                     * '0000-00-00 00:00:00' as their "not yet" sentinel hold
                     * NULL from schema step 284 on.
                     */
                    $whereArray[] = sprintf(
                        '`%s`.`%s` IS%sNULL',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        (trim($not) ? ' NOT ' : ' ')
                    );
                } else {
                    if (is_array($value)) {
                        $value = '';
                    }
                    $value = trim($value);
                    $k = sprintf(
                        '%s',
                        $key
                    );
                    // Define the param keys
                    $findKey = sprintf(
                        ':%s',
                        $key
                    );
                    // Define the param array
                    $findVals[$k] = $value;
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%s%s',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        (
                            preg_match('#%#', (string) $value) ?
                            sprintf(' %sLIKE ', $not) :
                            sprintf(
                                '%s%s',
                                (
                                    trim($not) ? '!' : ''
                                ),
                                (
                                    $onecompare ?
                                    (!$count ? $compare : '=') :
                                    $compare
                                )
                            )
                        ),
                        $findKey
                    );
                }
                ++$count;
                unset($value);
            }
        }
        if (!is_array($orderBy)) {
            $orderBy = sprintf(
                'ORDER BY %s`%s`.`%s`%s %s',
                ($orderBy == 'name' ? 'LOWER(' : ''),
                $this->databaseTable,
                $this->databaseFields[$orderBy],
                ($orderBy == 'name' ? ')' : ''),
                $sort
            );
            if ($groupBy) {
                $groupBy = sprintf(
                    'GROUP BY `%s`.`%s`',
                    $this->databaseTable,
                    $this->databaseFields[$groupBy]
                );
            } else {
                $groupBy = '';
            }
        } else {
            $orderString = '';
            foreach ((array)$orderBy as $i => &$order) {
                $orderString .= sprintf(
                    '`%s`.`%s` %s,',
                    $this->databaseTable,
                    $this->databaseFields[$order],
                    is_array($sort) ? $sort[$i] : $sort
                );
                unset($order);
            }
            $orderString = trim($orderString, ','.$sort);
            $orderBy = sprintf(
                'ORDER BY %s ',
                $orderString
            );
        }
        $join = $whereArrayAnd = array();
        $c = null;
        self::getClass($this->childClass)->buildQuery(
            $join,
            $whereArrayAnd,
            $c,
            $not,
            $compare
        );
        $join = array_filter((array) $join);
        $join = implode((array) $join);
        $knownEnable = array(
            'Image',
            'Snapin',
            'StorageNode'
        );
        $nonEnable = !(in_array($this->childClass, $knownEnable));
        $isEnabled = array_key_exists(
            'isEnabled',
            $this->databaseFields
        );
        if ($nonEnable && $isEnabled) {
            $isEnabled = sprintf(
                '`%s`=1',
                $this->databaseFields['isEnabled']
            );
        }
        if ($nonEnable && $isEnabled) {
            $findVals['isEnabled'] = 1;
            $isEnabled = sprintf(
                '`%s`=:isEnabled',
                $this->databaseFields['isEnabled']
            );
        }
        $idFields = array();
        foreach ((array) $idField as &$id) {
            $id = trim($id);
            if (isset($this->databaseFields[$id])) {
                $idFields += (array) $this->databaseFields[$id];
            }
            unset($id);
        }
        $idFields = array_filter($idFields);
        $idField = $idFields;
        unset($idFields);
        $whereClause = (
            count($whereArray) > 0 ?
            sprintf(
                ' WHERE %s%s',
                implode(" $whereOperator ", (array) $whereArray),
                (
                    $isEnabled ?
                    sprintf(' AND %s', $isEnabled) :
                    ''
                )
            ) :
            (
                $isEnabled ?
                sprintf(' WHERE %s', $isEnabled) :
                ''
            )
        );
        $andClause = (
            count($whereArrayAnd) > 0 ?
            (
                count($whereArray) > 0 ?
                sprintf(
                    'AND %s',
                    implode(" $whereOperator ", (array) $whereArrayAnd)
                ) :
                sprintf(
                    ' WHERE %s',
                    implode(" $whereOperator ", (array) $whereArrayAnd)
                )
            ) :
            ''
        );
        // The object boundary, when the caller was given one to apply.
        //
        // Two properties this has to hold that the obvious splice does not.
        // It is ANDed on LAST, after everything the caller asked for, with
        // the caller's own terms parenthesised: $whereOperator is a parameter
        // and 'OR' is a value it takes, so a term merged in beside the
        // caller's could be satisfied INSTEAD of the boundary rather than as
        // well as it. And it is joined with a literal ' AND ', never through
        // $whereOperator, for the same reason. An OR that can reach outside
        // the boundary is not a boundary.
        //
        // Empty means no boundary, which is every caller that does not pass
        // this argument. A caller that means "you may see nothing" passes a
        // fragment saying so, such as '1=0' -- NOT an empty string, which
        // reads here as unrestricted and would hand back the whole table.
        $scopeWhere = trim((string)$scopeWhere);
        if ('' !== $scopeWhere) {
            $inner = trim($whereClause . ' ' . $andClause);
            $inner = preg_replace('#^WHERE\s+#i', '', $inner);
            $whereClause = (
                '' === $inner ?
                sprintf(' WHERE %s', $scopeWhere) :
                sprintf(' WHERE (%s) AND (%s)', $inner, $scopeWhere)
            );
            $andClause = '';
        }
        $query = sprintf(
            $this->loadQueryTemplate,
            (
                count($idField) > 0 ?
                sprintf('`%s`', implode('`,`', (array) $idField)) :
                '*'
            ),
            $this->databaseTable,
            $join,
            $whereClause,
            $andClause,
            $groupBy,
            $orderBy
        );
        $data = [];
        self::$DB->query(
            $query,
            array(),
            $findVals
        )->fetch(
            PDO::FETCH_ASSOC,
            'fetch_all'
        );
        // A rejected read answers an EMPTY set, which every caller reads as
        // "there are none" rather than "the question was not asked". The
        // return contract is left alone -- there is no catch here and callers
        // expect an array -- so the fault line is the whole of the fix.
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Find failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error,
                    _('answering an empty set for a read that never ran')
                )
            );
        }
        if ($idField) {
            $data = (array)self::$DB->get($idField);
            if ($filter) {
                return @$filter($data);
            }
            if (count($data) === 1) {
                $data = array_shift($data);
            }
        } else {
            foreach ((array)self::$DB->get() as &$val) {
                $data[] = new $this->childClass($val);
                unset($val);
            }
        }
        if ($filter) {
            return @$filter($data);
        }
        return $data;
    }
    /**
     * Returns the count of items.
     *
     * @param array  $findWhere     what to find and count
     * @param string $whereOperator how to scan for where multiples
     * @param string $compare       how to compare items
     *
     * @return int
     */
    public function count(
        $findWhere = array(),
        $whereOperator = 'AND',
        $compare = '='
    ) {
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($compare)) {
            $compare = '=';
        }
        $whereArray = array();
        $countVals = $countKeys = array();
        if (count($findWhere)) {
            foreach ((array) $findWhere as $field => &$value) {
                $field = trim($field);
                if (is_array($value) && count($value) > 0) {
                    foreach ((array) $value as $index => &$val) {
                        $key = sprintf(
                            '%s_%d',
                            $field,
                            $index
                        );
                        $countKeys[] = sprintf(':%s', $key);
                        $countVals[$key] = $val;
                        unset($val);
                    }
                    if (count($countKeys) > 0) {
                        $whereArray[] = sprintf(
                            '`%s` IN (%s)',
                            $this->databaseFields[$field],
                            implode(',', $countKeys)
                        );
                    }
                    unset($countKeys);
                } elseif (null === $value) {
                    /*
                     * GH-1245: a null filter asks for rows where the column
                     * holds nothing. Bound as a placeholder it becomes
                     * `col = NULL`, which is never true, so the query
                     * silently returns nothing -- and it now has callers,
                     * because the date columns that used to carry
                     * '0000-00-00 00:00:00' as their "not yet" sentinel hold
                     * NULL from schema step 284 on.
                     */
                    $whereArray[] = sprintf(
                        '`%s` IS NULL',
                        $this->databaseFields[$field]
                    );
                } else {
                    if (is_array($value)) {
                        $value = '';
                    }
                    $countVals[$field] = $value;
                    $whereArray[] = sprintf(
                        '`%s` %s :%s',
                        $this->databaseFields[$field],
                        (
                            preg_match(
                                '#%#',
                                $value
                            ) ?
                            'LIKE' :
                            trim($compare)
                        ),
                        $field
                    );
                }
                unset($value, $field);
            }
        }
        $knownEnable = array(
            'Image',
            'Snapin',
            'StorageNode',
        );
        $nonEnable = !(in_array($this->childClass, $knownEnable));
        $isEnabled = array_key_exists(
            'isEnabled',
            $this->databaseFields
        );
        if ($nonEnable && $isEnabled) {
            $isEnabled = sprintf(
                '`%s`=1',
                $this->databaseFields['isEnabled']
            );
        }
        $query = sprintf(
            $this->countQueryTemplate,
            $this->databaseTable,
            $this->databaseFields['id'],
            $this->databaseTable,
            (
                count($whereArray) ?
                sprintf(
                    ' WHERE %s%s',
                    implode(
                        sprintf(
                            ' %s ',
                            $whereOperator
                        ),
                        (array) $whereArray
                    ),
                    (
                        $isEnabled ?
                        sprintf(
                            ' AND %s',
                            $isEnabled
                        ) :
                        ''
                    )
                ) :
                (
                    $isEnabled ?
                    sprintf(
                        ' WHERE %s',
                        $isEnabled
                    ) :
                    ''
                )
            )
        );

        self::$DB->query($query, array(), $countVals);
        $total = self::$DB->fetch()->get('total');
        // A rejected count answers 0, which reads as "there are none" rather
        // than "nobody asked". After the fetch, so one check covers both
        // halves. Contract unchanged; the fault line is the fix.
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Count failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error,
                    _('answering 0 for a read that never ran')
                )
            );
        }

        return (int)$total;
    }
    /**
     * Inserts data in mass to the database.
     *
     * @param array $fields the fields to insert into
     * @param array $values the values to insert
     *
     * @return array
     */
    public function insertBatch($fields, $values)
    {
        $fieldlength = count($fields);
        $valuelength = count($values);
        if ($fieldlength < 1) {
            throw new Exception(_('No fields passed'));
        }
        if ($valuelength < 1) {
            throw new Exception(_('No values passed'));
        }
        $keys = array();
        foreach ((array) $fields as &$key) {
            $key = $this->databaseFields[$key];
            $keys[] = $key;
            $dups[] = sprintf(
                '`%s`=VALUES(`%s`)',
                $key,
                $key
            );
            unset($key);
        }
        /*
         * GH-1245 again, on the other write path.
         *
         * A caller names the columns it has a value for, which is not the
         * same set as the columns the server will accept an INSERT without.
         * Under a strict sql_mode a NOT NULL column with no DEFAULT that the
         * statement does not name is error 1364 and the whole batch is
         * rejected; without one the server invents a zero value and says
         * nothing. PDODB cleared sql_mode until GH-1245, so every such call
         * site had been relying on the second behaviour without knowing it --
         * saving FOG settings omits settingDesc and settingCategory, and
         * tasking a group's snapins omits stReturnCode and stReturnDetails.
         *
         * So write the coercion down instead of relying on it, exactly as
         * FOGController::save() now does for a single row. The values come
         * from the same emptyValueFor(), which is why it moved to FOGBase.
         *
         * They are deliberately NOT added to the ON DUPLICATE KEY UPDATE
         * list: this fills a column the caller had nothing to say about, so
         * on a row that already exists the stored value must stand. Filling
         * settingDesc into the update list would blank the description of
         * every setting on the page the moment anyone pressed save.
         */
        $fillKeys = array();
        $fillVals = array();
        $named = array_map('strtolower', $keys);
        foreach ((array) self::columnsRequiringValue(
            $this->databaseTable
        ) as $column => $type) {
            if (in_array(strtolower($column), $named, true)) {
                continue;
            }
            $bind = sprintf('_fill_%d', count($fillKeys));
            $keys[] = $column;
            $fillKeys[] = sprintf(':%s', $bind);
            $fillVals[$bind] = self::emptyValueFor(
                $this->databaseTable,
                $column
            );
        }
        $affectedRows = 0;
        $vals = array();
        $insertVals = $fillVals;
        $values = array_chunk($values, 500);
        foreach ((array) $values as $ind => &$v) {
            $insertVals = $fillVals;
            foreach ((array) $v as $index => &$value) {
                $insertKeys = array();
                foreach ((array) $value as $i => &$val) {
                    $key = sprintf(
                        '%s_%d',
                        $fields[$i],
                        $index
                    );
                    $insertKeys[] = sprintf(
                        ':%s',
                        $key
                    );
                    $val = trim($val);
                    $insertVals[$key] = $val;
                    unset($val);
                }
                $vals[] = sprintf(
                    '(%s)',
                    implode(',', array_merge((array) $insertKeys, $fillKeys))
                );
                unset($value);
            }
            if (count($vals) < 1) {
                throw new Exception(_('No data to insert'));
            }
            $query = sprintf(
                $this->insertBatchTemplate,
                $this->databaseTable,
                implode('`,`', $keys),
                implode(',', $vals),
                implode(',', $dups)
            );
            self::$DB->query($query, array(), $insertVals);
            // Same swallowed-error seam as FOGController::save(): without
            // this the loop went on to report affectedRows for a batch the
            // server rejected.
            if (self::$DB->error) {
                throw new Exception((string) self::$DB->error);
            }
            if ($ind === 0) {
                $insertID = (int) self::$DB->insertId();
            }
            $affectedRows += (int) self::$DB->affectedRows();
            unset($v, $vals, $insertVals);
        }
        return array(
            $insertID,
            $affectedRows,
        );
    }
    /**
     * Function deals with enmass updating.
     *
     * @param array  $findWhere     what specific to update
     * @param string $whereOperator what to join where with
     * @param array  $insertData    the data to update
     *
     * @return bool
     */
    public function update(
        $findWhere = array(),
        $whereOperator = 'AND',
        $insertData = array()
    ) {
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }

        if (self::is_assoc_array($insertData)) {
            // Handle single associative array case
            return $this->perform_update($findWhere, $whereOperator, $insertData);
        } elseif (self::is_array_of_assoc_arrays($insertData)) {
            // Handle array of associative arrays case
            foreach ($insertData as $data) {
                if (!$this->perform_update($findWhere, $whereOperator, $data)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
    /**
     * Works to perform the actual actions.
     *
     * @param $findWhere     What are we adjusting
     * @param $whereOperator How are we doing the where/filter lookups
     * @param $insertData    What we're actually updating.
     * @return bool
     */
    private function perform_update($findWhere, $whereOperator, $insertData)
    {
        $insertArray = array();
        $whereArray = array();
        $updateVals = array();
        foreach ((array) $insertData as $field => &$value) {
            $field = trim($field);
            // GH-1245: null is a value to write, not a string to trim.
            // trim(null) is '' -- and a PHP 8.1 deprecation -- which would
            // put the zero date back into a column being cleared.
            $value = (null === $value) ? null : trim($value);
            $updateKey = sprintf(
                ':update_%s',
                $field
            );
            $updateVals[sprintf('update_%s', $field)] = $value;
            $key = sprintf(
                '`%s`.`%s`',
                $this->databaseTable,
                $this->databaseFields[$field]
            );
            $insertArray[] = sprintf(
                '%s=%s',
                $key,
                $updateKey
            );
            unset($value);
        }
        unset($updateKey);
        $findVals = array();
        if (count($findWhere) > 0) {
            foreach ($findWhere as $field => &$value) {
                $key = trim($field);
                if (is_array($value) && count($value) > 0) {
                    foreach ($value as $i => &$val) {
                        $val = trim($val);
                        // Define the key
                        $k = sprintf(
                            '%s_%d',
                            $key,
                            $i
                        );
                        // Define param keys
                        $findKeys[] = sprintf(
                            ':%s',
                            $k
                        );
                        // Define the param array
                        $findVals[$k] = $val;
                        unset($val);
                    }
                    $whereArray[] = sprintf(
                        '`%s`.`%s` IN (%s)',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        implode(',', $findKeys)
                    );
                    unset($findKeys);
                } elseif (null === $value) {
                    /*
                     * GH-1245: a null filter asks for rows where the column
                     * holds nothing. Bound as a placeholder it becomes
                     * `col = NULL`, which is never true, so the query
                     * silently returns nothing -- and it now has callers,
                     * because the date columns that used to carry
                     * '0000-00-00 00:00:00' as their "not yet" sentinel hold
                     * NULL from schema step 284 on.
                     */
                    $whereArray[] = sprintf(
                        '`%s`.`%s` IS NULL',
                        $this->databaseTable,
                        $this->databaseFields[$field]
                    );
                } else {
                    if (is_array($value)) {
                        $value = '';
                    }
                    $value = trim($value);
                    $k = sprintf(
                        '%s',
                        $key
                    );
                    // Define the param keys
                    $findKey = sprintf(
                        ':%s',
                        $key
                    );
                    // Define the param array
                    $findVals[$k] = $value;
                    $whereArray[] = sprintf(
                        '`%s`.`%s`%s%s',
                        $this->databaseTable,
                        $this->databaseFields[$field],
                        (
                            preg_match('#%#', (string) $value) ?
                            ' LIKE' :
                            '='
                        ),
                        $findKey
                    );
                }
                unset($value);
            }
        }
        unset($findKeys, $findKey);
        $query = sprintf(
            $this->updateQueryTemplate,
            $this->databaseTable,
            implode(',', (array) $insertArray),
            (
                count($whereArray) ?
                sprintf(
                    ' WHERE %s',
                    implode(" $whereOperator ", (array) $whereArray)
                ) :
                ''
            )
        );
        $queryVals = self::fastmerge(
            (array) $updateVals,
            (array) $findVals
        );

        self::$DB->query($query, array(), $queryVals);
        /*
         * `(bool) self::$DB->query(...)` was ALWAYS true: query() returns
         * $this, and an object casts to true whatever the server said. So
         * this reported success for every rejected mass update.
         *
         * Faulted here rather than thrown: this method has no catch and its
         * callers expect a bool, so throwing would turn a silently-failed
         * bulk edit into an uncaught 500. False is the honest answer they
         * were already written to read.
         */
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Mass update failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error
                )
            );

            return false;
        }

        return true;
    }
    /**
     * Destroys items related to the main object.
     *
     * @param array  $findWhere     what to find
     * @param string $whereOperator how to combine where items
     * @param string $orderBy       how to order fields
     * @param string $sort          how the sort order
     * @param string $compare       how to compare
     * @param string $groupBy       how to group fields
     * @param bool   $not           use not operator
     *
     * @return bool
     */
    public function destroy(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false
    ) {
        // Fail safe defaults
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($sort)) {
            $sort = 'ASC';
        }
        $this->orderBy($orderBy);
        if (empty($compare)) {
            $compare = '=';
        }
        $ids = $this->find(
            $findWhere,
            $whereOperator,
            $orderBy,
            $sort,
            $compare,
            $groupBy,
            $not,
            'id'
        );
        $destroyVals = array();
        $ids = array_chunk($ids, 500);
        foreach ((array)$ids as &$id) {
            foreach ((array) $id as $index => &$id_1) {
                $keyStr = sprintf('id_%d', $index);
                $destroyKeys[] = sprintf(':%s', $keyStr);
                $destroyVals[$keyStr] = $id_1;
                unset($id_1);
            }
            if (count($findWhere) > 0 && count($ids) < 1) {
                return true;
            }
            $query = sprintf(
                $this->destroyQueryTemplate,
                $this->databaseTable,
                $this->databaseTable,
                $this->databaseFields['id'],
                implode(',', (array) $destroyKeys)
            );
            unset($destroyKeys);
            self::$DB->query($query, array(), $destroyVals);
            // Returned true unconditionally, so a rejected DELETE reported
            // every row removed. Faulted and answered false rather than
            // thrown, for the same reason update() is: no catch here, and
            // callers expect a bool.
            if (self::$DB->error) {
                self::logFault(
                    sprintf(
                        '%s: %s: %s, %s: %s',
                        _('Mass destroy failed'),
                        _('Table'),
                        $this->databaseTable,
                        _('Error'),
                        self::$DB->error
                    )
                );

                return false;
            }
            unset($destroyVals, $destroyKeys);
        }

        return true;
    }
    /**
     * Builds a select box/option box from the elements.
     *
     * @param mixed  $matchID     select the matching id
     * @param string $elementName the name for the select box
     * @param string $orderBy     how to order
     * @param string $filter      should we filter existing
     * @param mixed  $template    should we include a template element
     *
     * @return string
     */
    public function buildSelectBox(
        $matchID = '',
        $elementName = '',
        $orderBy = 'name',
        $filter = '',
        $template = false
    ) {
        global $node;
        global $sub;
        if ($node === 'image' && $sub === 'add') {
            $waszero = false;
            if ($matchID === 0) {
                $waszero = true;
                $matchID = 9;  //default to Windows 10 (9)
            }
        }
        $elementName = trim($elementName);
        if (empty($elementName)) {
            $elementName = strtolower($this->childClass);
        }
        $this->orderBy($orderBy);
        ob_start();
        self::$HookManager
            ->processEvent(
                'SELECT_BUILD',
                array(
                    'matchID' => &$matchID,
                    'elementName' => &$elementName,
                    'orderBy' => &$orderBy,
                    'filter' => &$filter,
                    'template' => &$template,
                    'waszero' => &$waszero,
                    'obj' => $this
                )
            );
        foreach ((array)$this
            ->find(
                $filter ? array('id' => $filter) : '',
                '',
                $orderBy,
                '',
                '',
                '',
                ($filter ? true : false)
            ) as &$Object
        ) {
            if (!$Object->isValid()) {
                continue;
            }
            if (array_key_exists('isEnabled', $this->databaseFields)
                && !$Object->get('isEnabled')
            ) {
                continue;
            }
            printf(
                '<option value="%s"%s>%s</option>',
                Initiator::e($Object->get('id')),
                (
                    $matchID == $Object->get('id') ?
                    ' selected' :
                    (
                        $template ?
                        sprintf('${selected_item%d}', $Object->get('id')) :
                        ''
                    )
                ),
                Initiator::e(sprintf(
                    '%s - (%d)',
                    $Object->get('name'),
                    $Object->get('id')
                ))
            );
            unset($Object);
        }
        $objOpts = ob_get_clean();
        $objOpts = trim($objOpts);
        if (empty($objOpts)) {
            return _('No items found');
        }
        $tmpStr = '<select class="form-control input-group" name="'
            . (
                $template ?
                '${select_name}' :
                $elementName
            )
            . '" id="'
            . $elementName
            . '" autocomplete="off">';
        $tmpStr .= '<option value="">- ';
        $tmpStr .= self::$foglang['PleaseSelect'];
        $tmpStr .= ' -</option>';
        $tmpStr .= $objOpts;
        $tmpStr .= '</select>';
        return $tmpStr;
    }
    /**
     * Checks if item already exists or not.
     *
     * @param string $val     the value to test
     * @param string $id      an ID if already exists
     * @param string $idField the id field to scan
     *
     * @return bool
     */
    public function exists(
        $val,
        $id = 0,
        $idField = 'name'
    ) {
        $idSpecField = 'id';
        if (empty($id)) {
            $id = 0;
        }
        if (empty($idField)) {
            $idField = 'name';
        }
        $existVals = array(
            $idField => $val,
            'id' => $id,
        );

        $query = sprintf(
            $this->existsQueryTemplate,
            $this->databaseTable,
            $this->databaseFields[$idField],
            $this->databaseTable,
            $this->databaseTable,
            $this->databaseFields[$idField],
            sprintf(':%s', $idField),
            $this->databaseTable,
            $this->databaseFields[$idSpecField],
            ':id'
        );

        self::$DB->query($query, array(), $existVals);
        $total = self::$DB->fetch()->get('total');
        /*
         * After the fetch, so one check covers both halves of the read --
         * fetch() records its own failure on ->error and never clears one.
         *
         * A rejected read here answers "no, it does not exist", which is the
         * most expensive wrong answer this class can give: callers use
         * exists() to decide whether to CREATE, so an unreadable database
         * turns into a duplicate rather than an error.
         *
         * The contract is left alone -- callers expect a bool and there is no
         * catch here -- so the fault line is the whole of the fix. Making
         * this throw is a real change to a read contract and belongs in its
         * own decision, not smuggled into a logging fix.
         */
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Existence check failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error,
                    _('answering "does not exist" for a read that never ran')
                )
            );
        }

        return (bool)$total > 0;
    }
    /**
     * Search for items passed to keyword.
     *
     * @param string $keyword       what to search for
     * @param bool   $returnObjects use ids or whole objects
     *
     * @return mixe
     */
    public function search($keyword = '', $returnObjects = false, $scopeWhere = '')
    {
        $keyword = trim($keyword);
        if (!$keyword) {
            $keyword = filter_input(INPUT_POST, 'crit');
        }
        if (!$keyword) {
            $keyword = filter_input(INPUT_GET, 'crit');
        }
        if (!$keyword) {
            throw new Exception(_('Nothing passed to search for'));
        }
        $mac_keyword = str_replace(
            array('-', ':'),
            '',
            $keyword
        );
        $mac_keyword = str_split($mac_keyword, 2);
        $mac_keyword = implode(':', $mac_keyword);
        $mac_keyword = preg_replace(
            '#[%\+\s\+]#',
            '%',
            sprintf(
                '%%%s%%',
                $mac_keyword
            )
        );
        if (empty($keyword) || $keyword === '%') {
            return $this->find(
                array(),
                'AND',
                'name',
                'ASC',
                '=',
                false,
                false,
                false,
                true,
                'array_unique',
                $scopeWhere
            );
        }
        $keyword = preg_replace(
            '#[%\+\s\+]#',
            '%',
            sprintf(
                '%%%s%%',
                $keyword
            )
        );
        if (count((array)$this->aliasedFields) > 0) {
            self::arrayRemove($this->aliasedFields, $this->databaseFields);
        }
        $findWhere = array_fill_keys(array_keys($this->databaseFields), $keyword);
        $find = array(
            'name' => $keyword,
            'description' => $keyword,
        );
        $itemIDs = self::getSubObjectIDs(
            $this->childClass,
            $findWhere,
            'id',
            '',
            'OR'
        );
        $HostIDs = self::getSubObjectIDs(
            'Host',
            array(
                'name' => $keyword,
                'description' => $keyword,
                'ip' => $keyword,
            ),
            'id',
            '',
            'OR'
        );
        switch (strtolower($this->childClass)) {
            case 'user':
                break;
            case 'host':
                $macHostIDs = self::getSubObjectIDs(
                    'MACAddressAssociation',
                    array(
                        'mac' => $mac_keyword,
                        'description' => $keyword,
                    ),
                    'hostID',
                    '',
                    'OR'
                );
                $invHostIDs = self::getSubObjectIDs(
                    'Inventory',
                    array(
                        'sysserial' => $keyword,
                        'caseserial' => $keyword,
                        'mbserial' => $keyword,
                        'primaryUser' => $keyword,
                        'other1' => $keyword,
                        'other2' => $keyword,
                        'sysman' => $keyword,
                        'sysproduct' => $keyword,
                    ),
                    'hostID',
                    '',
                    'OR'
                );
                $HostIDs = self::fastmerge(
                    $HostIDs,
                    $macHostIDs,
                    $invHostIDs
                );
                unset($invHostIDs, $macHostIDs);
                $ImageIDs = self::getSubObjectIDs(
                    'Image',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $GroupIDs = self::getSubObjectIDs(
                    'Group',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $SnapinIDs = self::getSubObjectIDs(
                    'Snapin',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $PrinterIDs = self::getSubObjectIDs(
                    'Printer',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                if (count($ImageIDs) > 0) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'Host',
                            array('imageID' => $ImageIDs)
                        )
                    );
                }
                if (count($GroupIDs) > 0) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'GroupAssociation',
                            array('groupID' => $GroupIDs),
                            'hostID'
                        )
                    );
                }
                if (count($SnapinIDs) > 0) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'SnapinAssociation',
                            array('snapinID' => $SnapinIDs),
                            'hostID'
                        )
                    );
                }
                if (count($PrinterIDs) > 0) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'PrinterAssociation',
                            array('printerID' => $PrinterIDs),
                            'hostID'
                        )
                    );
                }
                $itemIDs = self::fastmerge($itemIDs, $HostIDs);
                $itemIDs = array_filter($itemIDs);
                $itemIDs = array_unique($itemIDs);
                break;
            case 'image':
                if (count($HostIDs)) {
                    $ImageIDs = self::getSubObjectIDs(
                        'Host',
                        array('id' => $HostIDs),
                        'imageID'
                    );
                    $itemIDs = self::fastmerge($itemIDs, $ImageIDs);
                }
                $itemIDs = array_filter($itemIDs);
                $itemIDs = array_unique($itemIDs);
                break;
            case 'task':
                $TaskStateIDs = self::getSubObjectIDs(
                    'TaskState',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $ImageIDs = self::getSubObjectIDs(
                    'Image',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $GroupIDs = self::getSubObjectIDs(
                    'Group',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $SnapinIDs = self::getSubObjectIDs(
                    'Snapin',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                $PrinterIDs = self::getSubObjectIDs(
                    'Printer',
                    $find,
                    'id',
                    '',
                    'OR'
                );
                if (count($ImageIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'Host',
                            array('imageID' => $ImageIDs)
                        )
                    );
                }
                if (count($GroupIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'GroupAssociation',
                            array('groupID' => $GroupIDs),
                            'hostID'
                        )
                    );
                }
                if (count($SnapinIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'SnapinAssociation',
                            array('snapinID' => $SnapinIDs),
                            'hostID'
                        )
                    );
                }
                if (count($PrinterIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'PrinterAssociation',
                            array('printerID' => $PrinterIDs),
                            'hostID'
                        )
                    );
                }
                if (count($TaskStateIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'Task',
                            array('stateID' => $TaskStateIDs)
                        )
                    );
                }
                if (count($TaskTypeIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'Task',
                            array('typeID' => $TaskTypeIDs)
                        )
                    );
                }
                if (count($HostIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            'Task',
                            array('hostID' => $HostIDs)
                        )
                    );
                }
                break;
            default:
                $assoc = sprintf(
                    '%sAssociation',
                    $this->childClass
                );
                $objID = sprintf(
                    '%sID',
                    strtolower($this->childClass)
                );
                if (!class_exists($assoc, false)) {
                    break;
                }
                if (count($itemIDs) && !count($HostIDs)) {
                    break;
                }
                $HostIDs = self::fastmerge(
                    $HostIDs,
                    self::getSubObjectIDs(
                        $assoc,
                        array($objID => $itemIDs),
                        'hostID'
                    )
                );
                if (count($HostIDs)) {
                    $itemIDs = self::fastmerge(
                        $itemIDs,
                        self::getSubObjectIDs(
                            $assoc,
                            array('hostID' => $HostIDs),
                            $objID
                        )
                    );
                }
                break;
        }
        $itemIDs = array_filter($itemIDs);
        $itemIDs = array_unique($itemIDs);
        $itemIDs = self::getSubObjectIDs(
            $this->childClass,
            array('id' => $itemIDs)
        );
        if ($returnObjects) {
            return $this->find(
                array('id' => $itemIDs),
                'AND',
                'name',
                'ASC',
                '=',
                false,
                false,
                false,
                true,
                'array_unique',
                $scopeWhere
            );
        }

        return $itemIDs;
    }
    /**
     * Returns the distinct (all matching).
     *
     * @param string $field         the field to be distinct
     * @param array  $findWhere     what to find
     * @param string $whereOperator how to scan for where multiples
     * @param string $compare       comparitor
     *
     * @return int
     */
    public function distinct(
        $field = '',
        $findWhere = array(),
        $whereOperator = 'AND',
        $compare = '='
    ) {
        if (empty($findWhere)) {
            $findWhere = array();
        }
        if (empty($whereOperator)) {
            $whereOperator = 'AND';
        }
        if (empty($compare)) {
            $compare = '=';
        }
        $whereArray = array();
        $countVals = array();
        // Monotonic index so every bound value gets a unique placeholder.
        // Reusing one shared :countVal collided across conditions and built
        // a malformed query for multi-condition lookups.
        $phIndex = 0;
        if (count($findWhere) > 0) {
            array_walk(
                $findWhere,
                function (
                    &$value,
                    $field
                ) use (
                    &$whereArray,
                    $compare,
                    &$countVals,
                    &$phIndex
                ) {
                    $field = trim($field);
                    if (is_array($value) && count($value) > 0) {
                        $inKeys = array();
                        foreach ((array) $value as &$val) {
                            $ph = sprintf('countVal%d', $phIndex++);
                            $inKeys[] = ':' . $ph;
                            $countVals[$ph] = $val;
                            unset($val);
                        }
                        $whereArray[] = sprintf(
                            '`%s`.`%s` IN (%s)',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            implode(',', $inKeys)
                        );
                    } elseif (null === $value) {
                        // GH-1245: as in find() above -- a null filter means
                        // "the column holds nothing", which is `IS NULL`, not
                        // a bound `= NULL` that matches no row at all.
                        $whereArray[] = sprintf(
                            '`%s`.`%s` IS NULL',
                            $this->databaseTable,
                            $this->databaseFields[$field]
                        );
                    } else {
                        if (is_array($value)) {
                            $value = '';
                        }
                        $ph = sprintf('countVal%d', $phIndex++);
                        $countVals[$ph] = $value;
                        $whereArray[] = sprintf(
                            '`%s`.`%s`%s:%s',
                            $this->databaseTable,
                            $this->databaseFields[$field],
                            (
                                preg_match(
                                    '#%#',
                                    $value
                                ) ?
                                ' LIKE' :
                                $compare
                            ),
                            $ph
                        );
                    }
                    unset($value, $field);
                }
            );
        }
        $query = sprintf(
            $this->distinctTemplate,
            $this->databaseTable,
            $this->databaseFields[$field],
            $this->databaseTable,
            (
                count($whereArray) ?
                sprintf(
                    ' WHERE %s',
                    implode(
                        sprintf(
                            ' %s ',
                            $whereOperator
                        ),
                        $whereArray
                    )
                ) :
                ''
            )
        );

        self::$DB->query($query, array(), $countVals);
        $total = self::$DB->fetch()->get('total');
        // Same as exists(): a rejected distinct count answers 0. After the
        // fetch, so one check covers both halves.
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s, %s',
                    _('Count failed'),
                    _('Table'),
                    $this->databaseTable,
                    _('Error'),
                    self::$DB->error,
                    _('answering 0 for a read that never ran')
                )
            );
        }

        return (int)$total;
    }
    /**
     * Builds the CREATE TABLE for this manager's table, with a default on
     * every column that is optional.
     *
     * GH-1245. Schema::createTable() emits `NOT NULL` with no DEFAULT for
     * almost everything a caller does not spell out, which is the same defect
     * schema step 286 repairs on an existing install -- except that install()
     * calls uninstall() first, and uninstall() DROPS the table. So a plugin
     * being installed, or reinstalled, put the bare columns straight back and
     * the step could not help: 72 of the 99 columns across the plugin tables
     * came back mandatory, and under the server's own sql_mode any INSERT
     * omitting one fails with error 1364.
     *
     * WHICH COLUMNS KEEP THEIR TEETH. Not a judgement call, and not a list
     * kept by hand -- FOG already states it, and this class already holds the
     * statement: $databaseFieldsRequired, resolved up the model's inheritance
     * chain by the constructor. Three kinds of column are left bare:
     *
     *   - the primary key and the auto-increment column;
     *   - anything the model declares required;
     *   - anything whose name ends in ID, because an INSERT that forgets the
     *     row it hangs off should fail rather than make a silent orphan.
     *     Deliberately not gated on an integer type: taskLog.taskID is a
     *     mediumtext and is no less a foreign key for it.
     *
     * That is deliberately the SAME rule schema step 286 applies, so a table
     * created by a plugin install and a table migrated by the step say the
     * same thing. Two installs of the same FOG should not have two different
     * schemas.
     *
     * A default the caller passed explicitly always wins; this only fills in
     * where there was nothing.
     *
     * The signature mirrors Schema::createTable() exactly so a call site
     * changes by one token.
     *
     * @param string $name    What are we calling the table?
     * @param bool   $exists  If not exists?
     * @param array  $fields  The fields and names.
     * @param array  $types   The types for the fields.
     * @param array  $nulls   Which fields to have null or not.
     * @param array  $default Default values for field(s).
     * @param array  $unique  The unique fields.
     * @param string $engine  The db engine for the table.
     * @param string $charset The charset to use for the table.
     * @param string $prime   The primary field, if one.
     * @param string $autoin  The auto increment field.
     *
     * @return string
     */
    public function createTableSql(
        $name,
        $exists,
        $fields,
        $types,
        $nulls,
        $default,
        $unique,
        $engine = 'InnoDB',
        $charset = 'utf8',
        $prime = '',
        $autoin = ''
    ) {
        $keep = array();
        foreach ((array)$this->databaseFieldsRequired as $friendly) {
            if (isset($this->databaseFields[$friendly])) {
                $keep[strtolower($this->databaseFields[$friendly])] = true;
            }
        }
        if ($prime) {
            $keep[strtolower($prime)] = true;
        }
        if ($autoin) {
            $keep[strtolower($autoin)] = true;
        }
        foreach ((array)$fields as $i => $field) {
            $notNull = isset($nulls[$i]) && $nulls[$i] === false;
            $hasDefault = isset($default[$i])
                && false !== $default[$i]
                && null !== $default[$i]
                && '' !== $default[$i];
            if (!$notNull
                || $hasDefault
                || isset($keep[strtolower($field)])
                || preg_match('/ID$/', $field)
            ) {
                continue;
            }
            $fill = Schema::emptyDefaultFor($types[$i]);
            if (null === $fill) {
                // A TEXT or BLOB column on a server too old to carry a
                // default for one. Nothing to do and nothing broken by
                // leaving it: save() writes the column explicitly and
                // insertBatch() backfills it.
                continue;
            }
            $default[$i] = $fill;
        }

        return Schema::createTable(
            $name,
            $exists,
            $fields,
            $types,
            $nulls,
            $default,
            $unique,
            $engine,
            $charset,
            $prime,
            $autoin
        );
    }
    /**
     * Uninstalls the table.
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = Schema::dropTable($this->tablename);
        self::$DB->query($sql);
        // Declared @return bool and returned the PDODB object, which is
        // truthy however the DROP went. A plugin uninstall that left its
        // table in place reported success.
        if (self::$DB->error) {
            self::logFault(
                sprintf(
                    '%s: %s: %s, %s: %s',
                    _('Table uninstall failed'),
                    _('Table'),
                    $this->tablename,
                    _('Error'),
                    self::$DB->error
                )
            );

            return false;
        }

        return true;
    }
}
