<?php

/**
 * PHP version of mysqldump cli that comes with MySQL.
 *
 * Tags: mysql mysqldump pdo php7 php5 database php sql hhvm mariadb mysql-backup.
 *
 * @category Library
 * @package  Ifsnop\Mysqldump
 * @author   Diego Torres <ifsnop@github.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://github.com/ifsnop/mysqldump-php
 *
 */

//namespace Ifsnop\Mysqldump;

//use Exception;
//use PDO;
//use PDOException;

/**
 * Class Mysqldump.
 *
 * @category Library
 * @author   Diego Torres <ifsnop@github.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://github.com/ifsnop/mysqldump-php
 *
 */
class Mysqldump
{

    // Same as mysqldump.
    const MAXLINESIZE = 1000000;

    // List of available compression methods as constants.
    const GZIP  = 'Gzip';
    const BZIP2 = 'Bzip2';
    const NONE  = 'None';
    const GZIPSTREAM = 'Gzipstream';

    // List of available connection strings.
    const UTF8    = 'utf8';
    const UTF8MB4 = 'utf8mb4';
    const BINARY = 'binary';

    /**
     * Database username.
     * @var string
     */
    public $user;

    /**
     * Database password.
     * @var string
     */
    public $pass;

    /**
     * Connection string for PDO.
     * @var string
     */
    public $dsn;

    /**
     * Destination filename, defaults to stdout.
     * @var string
     */
    public $fileName = 'php://stdout';

    // Internal stuff.
    private $tables = array();
    private $views = array();
    private $triggers = array();
    private $procedures = array();
    private $functions = array();
    private $events = array();
    protected $dbHandler = null;
    private $dbType = "";
    private $compressManager;
    private $typeAdapter;
    protected $dumpSettings = array();
    protected $pdoSettings = array();
    private $version;
    private $tableColumnTypes = array();
    private $transformTableRowCallable;
    private $transformColumnValueCallable;
    private $infoCallable;

    /**
     * Database name, parsed from dsn.
     * @var string
     */
    private $dbName;

    /**
     * Host name, parsed from dsn.
     * @var string
     */
    private $host;

    /**
     * Dsn string parsed as an array.
     * @var array
     */
    private $dsnArray = array();

    /**
     * Keyed on table name, with the value as the conditions.
     * e.g. - 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH'
     *
     * @var array
     */
    private $tableWheres = array();
    private $tableLimits = array();

    protected $dumpSettingsDefault = array(
        'include-tables' => array(),
        'exclude-tables' => array(),
        'include-views' => array(),
        'compress' => Mysqldump::NONE,
        'init_commands' => array(),
        'no-data' => array(),
        'if-not-exists' => false,
        'reset-auto-increment' => false,
        'add-drop-database' => false,
        'add-drop-table' => false,
        'add-drop-trigger' => true,
        'add-locks' => true,
        'complete-insert' => false,
        'databases' => false,
        'default-character-set' => Mysqldump::UTF8,
        'disable-keys' => true,
        'extended-insert' => true,
        'events' => false,
        'hex-blob' => true, /* faster than escaped content */
        'insert-ignore' => false,
        'net_buffer_length' => self::MAXLINESIZE,
        'no-autocommit' => true,
        'no-create-db' => false,
        'no-create-info' => false,
        'lock-tables' => true,
        'routines' => false,
        'single-transaction' => true,
        'skip-triggers' => false,
        'skip-tz-utc' => false,
        'skip-comments' => false,
        'skip-dump-date' => false,
        'skip-definer' => false,
        'where' => '',
        /* deprecated */
        'disable-foreign-keys-check' => true
    );

    protected $pdoSettingsDefault = array(
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    );

    /**
     * Constructor of Mysqldump. Note that in the case of an SQLite database
     * connection, the filename must be in the $db parameter.
     *
     * @param string $dsn        PDO DSN connection string
     * @param string $user       SQL account username
     * @param string $pass       SQL account password
     * @param array  $dumpSettings SQL database settings
     * @param array  $pdoSettings  PDO configured attributes
     */
    public function __construct(
        $dsn = '',
        $user = '',
        $pass = '',
        $dumpSettings = array(),
        $pdoSettings = array()
    ) {
        $dsn = sprintf(
            '%s:host=%s;dbname=%s',
            preg_replace('#^mysqli#i', 'mysql', DATABASE_TYPE),
            preg_replace('#p:#', '', DATABASE_HOST),
            DATABASE_NAME
        );

        $this->user = DATABASE_USERNAME;
        $this->pass = DATABASE_PASSWORD;
        $this->parseDsn($dsn);

        // This drops MYSQL dependency, only use the constant if it's defined.
        if ("mysql" === $this->dbType) {
            $this->pdoSettingsDefault[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }

        $this->pdoSettings = array_replace_recursive($this->pdoSettingsDefault, $pdoSettings);
        $this->dumpSettings = array_replace_recursive($this->dumpSettingsDefault, $dumpSettings);
        $this->dumpSettings['init_commands'][] = "SET NAMES ".$this->dumpSettings['default-character-set'];

        if (false === $this->dumpSettings['skip-tz-utc']) {
            $this->dumpSettings['init_commands'][] = "SET TIME_ZONE='+00:00'";
        }

        $diff = array_diff(array_keys($this->dumpSettings), array_keys($this->dumpSettingsDefault));
        if (count($diff) > 0) {
            throw new Exception("Unexpected value in dumpSettings: (".implode(",", $diff).")");
        }

        if (!is_array($this->dumpSettings['include-tables']) ||
            !is_array($this->dumpSettings['exclude-tables'])) {
            throw new Exception("Include-tables and exclude-tables should be arrays");
        }

        // If no include-views is passed in, dump the same views as tables, mimic mysqldump behaviour.
        if (!isset($dumpSettings['include-views'])) {
            $this->dumpSettings['include-views'] = $this->dumpSettings['include-tables'];
        }

        // Create a new compressManager to manage compressed output
        $this->compressManager = CompressManagerFactory::create($this->dumpSettings['compress']);
    }

    /**
     * Destructor of Mysqldump. Unsets dbHandlers and database objects.
     */
    public function __destruct()
    {
        $this->dbHandler = null;
    }

    /**
     * Keyed by table name, with the value as the conditions:
     * e.g. 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH AND deleted=0'
     *
     * @param array $tableWheres
     */
    public function setTableWheres(array $tableWheres)
    {
        $this->tableWheres = $tableWheres;
    }

    /**
     * @param $tableName
     *
     * @return boolean|mixed
     */
    public function getTableWhere($tableName)
    {
        if (!empty($this->tableWheres[$tableName])) {
            return $this->tableWheres[$tableName];
        } elseif ($this->dumpSettings['where']) {
            return $this->dumpSettings['where'];
        }

        return false;
    }

    /**
     * Keyed by table name, with the value as the numeric limit:
     * e.g. 'users' => 3000
     *
     * @param array $tableLimits
     */
    public function setTableLimits(array $tableLimits)
    {
        $this->tableLimits = $tableLimits;
    }

    /**
     * Returns the LIMIT for the table.  Must be numeric to be returned.
     * @param $tableName
     * @return boolean
     */
    public function getTableLimit($tableName)
    {
        if (!isset($this->tableLimits[$tableName])) {
            return false;
        }

        $limit = $this->tableLimits[$tableName];
        if (!is_numeric($limit)) {
            return false;
        }

        return $limit;
    }

    /**
     * Import supplied SQL file
     * @param string $path Absolute path to imported *.sql file
     */
    public function restore($path)
    {
        if(!$path || !is_file($path)) {
            throw new Exception("File {$path} does not exist.");
        }

        $handle = fopen($path, 'rb');

        if(!$handle) {
            throw new Exception("Failed reading file {$path}. Check access permissions.");
        }

        if(!$this->dbHandler) {
            $this->connect();
        }

        $buffer = '';
        while (!feof($handle)) {
            $line = trim(fgets($handle));

            if (substr($line, 0, 2) == '--' || !$line) {
                continue; // skip comments
            }

            $buffer .= $line;

            // if it has a semicolon at the end, it's the end of the query
            if (';' == substr(rtrim($line), -1, 1)) {
                $this->dbHandler->exec($buffer);
                $buffer = '';
            }
        }

        fclose($handle);
    }

    /**
     * Parse DSN string and extract dbname value
     * Several examples of a DSN string
     *   mysql:host=localhost;dbname=testdb
     *   mysql:host=localhost;port=3307;dbname=testdb
     *   mysql:unix_socket=/tmp/mysql.sock;dbname=testdb
     *
     * @param string $dsn dsn string to parse
     * @return boolean
     */
    private function parseDsn($dsn)
    {
        if (empty($dsn) || (false === ($pos = strpos($dsn, ":")))) {
            throw new Exception("Empty DSN string");
        }

        $this->dsn = $dsn;
        $this->dbType = strtolower(substr($dsn, 0, $pos)); // always returns a string

        if (empty($this->dbType)) {
            throw new Exception("Missing database type from DSN string");
        }

        $dsn = substr($dsn, $pos + 1);

        foreach (explode(";", $dsn) as $kvp) {
            $kvpArr = explode("=", $kvp);
            $this->dsnArray[strtolower($kvpArr[0])] = $kvpArr[1];
        }

        if (empty($this->dsnArray['host']) &&
            empty($this->dsnArray['unix_socket'])) {
            throw new Exception("Missing host from DSN string");
        }
        $this->host = (!empty($this->dsnArray['host'])) ?
            $this->dsnArray['host'] : $this->dsnArray['unix_socket'];

        if (empty($this->dsnArray['dbname'])) {
            throw new Exception("Missing database name from DSN string");
        }

        $this->dbName = $this->dsnArray['dbname'];

        return true;
    }

    /**
     * Connect with PDO.
     *
     * @return null
     */
    protected function connect()
    {
        // Connecting with PDO.
        try {
            switch ($this->dbType) {
                case 'sqlite':
                    $this->dbHandler = @new PDO("sqlite:".$this->dbName, null, null, $this->pdoSettings);
                    break;
                case 'mysql':
                case 'pgsql':
                case 'dblib':
                    $this->dbHandler = @new PDO(
                        $this->dsn,
                        $this->user,
                        $this->pass,
                        $this->pdoSettings
                    );
                    // Execute init commands once connected
                    foreach ($this->dumpSettings['init_commands'] as $stmt) {
                        $this->dbHandler->exec($stmt);
                    }
                    // Store server version
                    $this->version = $this->dbHandler->getAttribute(PDO::ATTR_SERVER_VERSION);
                    break;
                default:
                    throw new Exception("Unsupported database type (".$this->dbType.")");
            }
        } catch (PDOException $e) {
            throw new Exception(
                "Connection to ".$this->dbType." failed with message: ".
                $e->getMessage()
            );
        }

        if (is_null($this->dbHandler)) {
            throw new Exception("Connection to ".$this->dbType."failed");
        }

        $this->dbHandler->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
        $this->typeAdapter = TypeAdapterFactory::create($this->dbType, $this->dbHandler, $this->dumpSettings);
    }

    /**
     * Primary function, triggers dumping.
     *
     * @param string $filename  Name of file to write sql dump to
     * @return null
     * @throws \Exception
     */
    public function start($filename = '')
    {
        // Output file can be redefined here
        if (!empty($filename)) {
            $this->fileName = $filename;
        }

        // Connect to database
        $this->connect();

        // Create output file
        $this->compressManager->open($this->fileName);

        // Write some basic info to output file
        $this->compressManager->write($this->getDumpFileHeader());

        // initiate a transaction at global level to create a consistent snapshot
        if ($this->dumpSettings['single-transaction']) {
            $this->dbHandler->exec($this->typeAdapter->setup_transaction());
            $this->dbHandler->exec($this->typeAdapter->start_transaction());
        }

        // Store server settings and use sanner defaults to dump
        $this->compressManager->write(
            $this->typeAdapter->backup_parameters()
        );

        if ($this->dumpSettings['databases']) {
            $this->compressManager->write(
                $this->typeAdapter->getDatabaseHeader($this->dbName)
            );
            if ($this->dumpSettings['add-drop-database']) {
                $this->compressManager->write(
                    $this->typeAdapter->add_drop_database($this->dbName)
                );
            }
        }

        // Get table, view, trigger, procedures, functions and events structures from
        // database.
        $this->getDatabaseStructureTables();
        $this->getDatabaseStructureViews();
        $this->getDatabaseStructureTriggers();
        $this->getDatabaseStructureProcedures();
        $this->getDatabaseStructureFunctions();
        $this->getDatabaseStructureEvents();

        if ($this->dumpSettings['databases']) {
            $this->compressManager->write(
                $this->typeAdapter->databases($this->dbName)
            );
        }

        // If there still are some tables/views in include-tables array,
        // that means that some tables or views weren't found.
        // Give proper error and exit.
        // This check will be removed once include-tables supports regexps.
        if (0 < count($this->dumpSettings['include-tables'])) {
            $name = implode(",", $this->dumpSettings['include-tables']);
            throw new Exception("Table (".$name.") not found in database");
        }

        $this->exportTables();
        $this->exportTriggers();
        $this->exportFunctions();
        $this->exportProcedures();
        $this->exportViews();
        $this->exportEvents();

        // Restore saved parameters.
        $this->compressManager->write(
            $this->typeAdapter->restore_parameters()
        );

        // end transaction
        if ($this->dumpSettings['single-transaction']) {
            $this->dbHandler->exec($this->typeAdapter->commit_transaction());
        }

        // Write some stats to output file.
        $this->compressManager->write($this->getDumpFileFooter());
        // Close output file.
        $this->compressManager->close();

        return;
    }

    /**
     * Returns header for dump file.
     *
     * @return string
     */
    private function getDumpFileHeader()
    {
        $header = '';
        if (!$this->dumpSettings['skip-comments']) {
            // Some info about software, source and time
            $header = "-- mysqldump-php https://github.com/ifsnop/mysqldump-php".PHP_EOL.
                "--".PHP_EOL.
                "-- Host: {$this->host}\tDatabase: {$this->dbName}".PHP_EOL.
                "-- ------------------------------------------------------".PHP_EOL;

            if (!empty($this->version)) {
                $header .= "-- Server version \t".$this->version.PHP_EOL;
            }

            if (!$this->dumpSettings['skip-dump-date']) {
                $header .= "-- Date: ".date('r').PHP_EOL.PHP_EOL;
            }
        }
        return $header;
    }

    /**
     * Returns footer for dump file.
     *
     * @return string
     */
    private function getDumpFileFooter()
    {
        $footer = '';
        if (!$this->dumpSettings['skip-comments']) {
            $footer .= '-- Dump completed';
            if (!$this->dumpSettings['skip-dump-date']) {
                $footer .= ' on: '.date('r');
            }
            $footer .= PHP_EOL;
        }

        return $footer;
    }

    /**
     * Reads table names from database.
     * Fills $this->tables array so they will be dumped later.
     *
     * @return null
     */
    private function getDatabaseStructureTables()
    {
        // Listing all tables from database
        if (empty($this->dumpSettings['include-tables'])) {
            // include all tables for now, blacklisting happens later
            foreach ($this->dbHandler->query($this->typeAdapter->show_tables($this->dbName)) as $row) {
                array_push($this->tables, current($row));
            }
        } else {
            // include only the tables mentioned in include-tables
            foreach ($this->dbHandler->query($this->typeAdapter->show_tables($this->dbName)) as $row) {
                if (in_array(current($row), $this->dumpSettings['include-tables'], true)) {
                    array_push($this->tables, current($row));
                    $elem = array_search(
                        current($row),
                        $this->dumpSettings['include-tables']
                    );
                    unset($this->dumpSettings['include-tables'][$elem]);
                }
            }
        }
        return;
    }

    /**
     * Reads view names from database.
     * Fills $this->tables array so they will be dumped later.
     *
     * @return null
     */
    private function getDatabaseStructureViews()
    {
        // Listing all views from database
        if (empty($this->dumpSettings['include-views'])) {
            // include all views for now, blacklisting happens later
            foreach ($this->dbHandler->query($this->typeAdapter->show_views($this->dbName)) as $row) {
                array_push($this->views, current($row));
            }
        } else {
            // include only the tables mentioned in include-tables
            foreach ($this->dbHandler->query($this->typeAdapter->show_views($this->dbName)) as $row) {
                if (in_array(current($row), $this->dumpSettings['include-views'], true)) {
                    array_push($this->views, current($row));
                    $elem = array_search(
                        current($row),
                        $this->dumpSettings['include-views']
                    );
                    unset($this->dumpSettings['include-views'][$elem]);
                }
            }
        }
        return;
    }

    /**
     * Reads trigger names from database.
     * Fills $this->tables array so they will be dumped later.
     *
     * @return null
     */
    private function getDatabaseStructureTriggers()
    {
        // Listing all triggers from database
        if (false === $this->dumpSettings['skip-triggers']) {
            foreach ($this->dbHandler->query($this->typeAdapter->show_triggers($this->dbName)) as $row) {
                array_push($this->triggers, $row['Trigger']);
            }
        }
        return;
    }

    /**
     * Reads procedure names from database.
     * Fills $this->tables array so they will be dumped later.
     *
     * @return null
     */
    private function getDatabaseStructureProcedures()
    {
        // Listing all procedures from database
        if ($this->dumpSettings['routines']) {
            foreach ($this->dbHandler->query($this->typeAdapter->show_procedures($this->dbName)) as $row) {
                array_push($this->procedures, $row['procedure_name']);
            }
        }
        return;
    }

    /**
     * Reads functions names from database.
     * Fills $this->tables array so they will be dumped later.
     *
     * @return null
     */
    private function getDatabaseStructureFunctions()
    {
        // Listing all functions from database
        if ($this->dumpSettings['routines']) {
            foreach ($this->dbHandler->query($this->typeAdapter->show_functions($this->dbName)) as $row) {
                array_push($this->functions, $row['function_name']);
            }
        }
        return;
    }

    /**
     * Reads event names from database.
     * Fills $this->tables array so they will be dumped later.
     *
     * @return null
     */
    private function getDatabaseStructureEvents()
    {
        // Listing all events from database
        if ($this->dumpSettings['events']) {
            foreach ($this->dbHandler->query($this->typeAdapter->show_events($this->dbName)) as $row) {
                array_push($this->events, $row['event_name']);
            }
        }
        return;
    }

    /**
     * Compare if $table name matches with a definition inside $arr
     * @param $table string
     * @param $arr array with strings or patterns
     * @return boolean
     */
    private function matches($table, $arr)
    {
        $match = false;

        foreach ($arr as $pattern) {
            if ('/' != $pattern[0]) {
                continue;
            }
            if (1 == preg_match($pattern, $table)) {
                $match = true;
            }
        }

        return in_array($table, $arr) || $match;
    }

    /**
     * Exports all the tables selected from database
     *
     * @return null
     */
    private function exportTables()
    {


        // Exporting tables one by one
        foreach ($this->tables as $table) {
            if ($this->matches($table, $this->dumpSettings['exclude-tables'])) {
                continue;
            }
            $this->getTableStructure($table);
            if (false === $this->dumpSettings['no-data']) { // don't break compatibility with old trigger
                $this->listValues($table);
            } elseif (true === $this->dumpSettings['no-data']
                || $this->matches($table, $this->dumpSettings['no-data'])) {
                continue;
            } else {
                $this->listValues($table);
            }
        }
    }

    /**
     * Exports all the views found in database
     *
     * @return null
     */
    private function exportViews()
    {
        if (false === $this->dumpSettings['no-create-info']) {
            // Exporting views one by one
            foreach ($this->views as $view) {
                if ($this->matches($view, $this->dumpSettings['exclude-tables'])) {
                    continue;
                }
                $this->tableColumnTypes[$view] = $this->getTableColumnTypes($view);
                $this->getViewStructureTable($view);
            }
            foreach ($this->views as $view) {
                if ($this->matches($view, $this->dumpSettings['exclude-tables'])) {
                    continue;
                }
                $this->getViewStructureView($view);
            }
        }
    }

    /**
     * Exports all the triggers found in database
     *
     * @return null
     */
    private function exportTriggers()
    {
        // Exporting triggers one by one
        foreach ($this->triggers as $trigger) {
            $this->getTriggerStructure($trigger);
        }

    }

    /**
     * Exports all the procedures found in database
     *
     * @return null
     */
    private function exportProcedures()
    {
        // Exporting triggers one by one
        foreach ($this->procedures as $procedure) {
            $this->getProcedureStructure($procedure);
        }
    }

    /**
     * Exports all the functions found in database
     *
     * @return null
     */
    private function exportFunctions()
    {
        // Exporting triggers one by one
        foreach ($this->functions as $function) {
            $this->getFunctionStructure($function);
        }
    }

    /**
     * Exports all the events found in database
     *
     * @return null
     */
    private function exportEvents()
    {
        // Exporting triggers one by one
        foreach ($this->events as $event) {
            $this->getEventStructure($event);
        }
    }

    /**
     * Table structure extractor
     *
     * @todo move specific mysql code to typeAdapter
     * @param string $tableName  Name of table to export
     * @return null
     */
    private function getTableStructure($tableName)
    {
        if (!$this->dumpSettings['no-create-info']) {
            $ret = '';
            if (!$this->dumpSettings['skip-comments']) {
                $ret = "--".PHP_EOL.
                    "-- Table structure for table `$tableName`".PHP_EOL.
                    "--".PHP_EOL.PHP_EOL;
            }
            $stmt = $this->typeAdapter->show_create_table($tableName);
            foreach ($this->dbHandler->query($stmt) as $r) {
                $this->compressManager->write($ret);
                if ($this->dumpSettings['add-drop-table']) {
                    $this->compressManager->write(
                        $this->typeAdapter->drop_table($tableName)
                    );
                }
                $this->compressManager->write(
                    $this->typeAdapter->create_table($r)
                );
                break;
            }
        }
        $this->tableColumnTypes[$tableName] = $this->getTableColumnTypes($tableName);
        return;
    }

    /**
     * Store column types to create data dumps and for Stand-In tables
     *
     * @param string $tableName  Name of table to export
     * @return array type column types detailed
     */

    private function getTableColumnTypes($tableName)
    {
        $columnTypes = array();
        $columns = $this->dbHandler->query(
            $this->typeAdapter->show_columns($tableName)
        );
        $columns->setFetchMode(PDO::FETCH_ASSOC);

        foreach ($columns as $key => $col) {
            $types = $this->typeAdapter->parseColumnType($col);
            $columnTypes[$col['Field']] = array(
                'is_numeric'=> $types['is_numeric'],
                'is_blob' => $types['is_blob'],
                'type' => $types['type'],
                'type_sql' => $col['Type'],
                'is_virtual' => $types['is_virtual']
            );
        }

        return $columnTypes;
    }

    /**
     * View structure extractor, create table (avoids cyclic references)
     *
     * @todo move mysql specific code to typeAdapter
     * @param string $viewName  Name of view to export
     * @return null
     */
    private function getViewStructureTable($viewName)
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = "--".PHP_EOL.
                "-- Stand-In structure for view `{$viewName}`".PHP_EOL.
                "--".PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->show_create_view($viewName);

        // create views as tables, to resolve dependencies
        foreach ($this->dbHandler->query($stmt) as $r) {
            if ($this->dumpSettings['add-drop-table']) {
                $this->compressManager->write(
                    $this->typeAdapter->drop_view($viewName)
                );
            }

            $this->compressManager->write(
                $this->createStandInTable($viewName)
            );
            break;
        }
    }

    /**
     * Write a create table statement for the table Stand-In, show create
     * table would return a create algorithm when used on a view
     *
     * @param string $viewName  Name of view to export
     * @return string create statement
     */
    public function createStandInTable($viewName)
    {
        $ret = array();
        foreach ($this->tableColumnTypes[$viewName] as $k => $v) {
            $ret[] = "`{$k}` {$v['type_sql']}";
        }
        $ret = implode(PHP_EOL.",", $ret);

        $ret = "CREATE TABLE IF NOT EXISTS `$viewName` (".
            PHP_EOL.$ret.PHP_EOL.");".PHP_EOL;

        return $ret;
    }

    /**
     * View structure extractor, create view
     *
     * @todo move mysql specific code to typeAdapter
     * @param string $viewName  Name of view to export
     * @return null
     */
    private function getViewStructureView($viewName)
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = "--".PHP_EOL.
                "-- View structure for view `{$viewName}`".PHP_EOL.
                "--".PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->show_create_view($viewName);

        // create views, to resolve dependencies
        // replacing tables with views
        foreach ($this->dbHandler->query($stmt) as $r) {
            // because we must replace table with view, we should delete it
            $this->compressManager->write(
                $this->typeAdapter->drop_view($viewName)
            );
            $this->compressManager->write(
                $this->typeAdapter->create_view($r)
            );
            break;
        }
    }

    /**
     * Trigger structure extractor
     *
     * @param string $triggerName  Name of trigger to export
     * @return null
     */
    private function getTriggerStructure($triggerName)
    {
        $stmt = $this->typeAdapter->show_create_trigger($triggerName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            if ($this->dumpSettings['add-drop-trigger']) {
                $this->compressManager->write(
                    $this->typeAdapter->add_drop_trigger($triggerName)
                );
            }
            $this->compressManager->write(
                $this->typeAdapter->create_trigger($r)
            );
            return;
        }
    }

    /**
     * Procedure structure extractor
     *
     * @param string $procedureName  Name of procedure to export
     * @return null
     */
    private function getProcedureStructure($procedureName)
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = "--".PHP_EOL.
                "-- Dumping routines for database '".$this->dbName."'".PHP_EOL.
                "--".PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->show_create_procedure($procedureName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            $this->compressManager->write(
                $this->typeAdapter->create_procedure($r)
            );
            return;
        }
    }

    /**
     * Function structure extractor
     *
     * @param string $functionName  Name of function to export
     * @return null
     */
    private function getFunctionStructure($functionName)
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = "--".PHP_EOL.
                "-- Dumping routines for database '".$this->dbName."'".PHP_EOL.
                "--".PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->show_create_function($functionName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            $this->compressManager->write(
                $this->typeAdapter->create_function($r)
            );
            return;
        }
    }

    /**
     * Event structure extractor
     *
     * @param string $eventName  Name of event to export
     * @return null
     */
    private function getEventStructure($eventName)
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = "--".PHP_EOL.
                "-- Dumping events for database '".$this->dbName."'".PHP_EOL.
                "--".PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->show_create_event($eventName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            $this->compressManager->write(
                $this->typeAdapter->create_event($r)
            );
            return;
        }
    }

    /**
     * Prepare values for output
     *
     * @param string $tableName Name of table which contains rows
     * @param array $row Associative array of column names and values to be
     *   quoted
     *
     * @return array
     */
    private function prepareColumnValues($tableName, array $row)
    {
        $ret = array();
        $columnTypes = $this->tableColumnTypes[$tableName];

        if ($this->transformTableRowCallable) {
            $row = call_user_func($this->transformTableRowCallable, $tableName, $row);
        }

        foreach ($row as $colName => $colValue) {
            if ($this->transformColumnValueCallable) {
                $colValue = call_user_func($this->transformColumnValueCallable, $tableName, $colName, $colValue, $row);
            }

            $ret[] = $this->escape($colValue, $columnTypes[$colName]);
        }

        return $ret;
    }

    /**
     * Escape values with quotes when needed
     *
     * @param string $tableName Name of table which contains rows
     * @param array $row Associative array of column names and values to be quoted
     *
     * @return string
     */
    private function escape($colValue, $colType)
    {
        if (is_null($colValue)) {
            return "NULL";
        } elseif ($this->dumpSettings['hex-blob'] && $colType['is_blob']) {
            if ($colType['type'] == 'bit' || !empty($colValue)) {
                return "0x{$colValue}";
            } else {
                return "''";
            }
        } elseif ($colType['is_numeric']) {
            return $colValue;
        }

        return $this->dbHandler->quote($colValue);
    }

    /**
     * Set a callable that will be used to transform table rows
     *
     * @param callable $callable
     *
     * @return void
     */
    public function setTransformTableRowHook($callable)
    {
        $this->transformTableRowCallable = $callable;
    }

    /**
     * Set a callable that will be used to transform column values
     *
     * @param callable $callable
     *
     * @return void
     *
     * @deprecated Use setTransformTableRowHook instead for better performance
     */
    public function setTransformColumnValueHook($callable)
    {
        $this->transformColumnValueCallable = $callable;
    }

    /**
     * Set a callable that will be used to report dump information
     *
     * @param callable $callable
     *
     * @return void
     */
    public function setInfoHook($callable)
    {
        $this->infoCallable = $callable;
    }

    /**
     * Table rows extractor
     *
     * @param string $tableName  Name of table to export
     *
     * @return null
     */
    private function listValues($tableName)
    {
        $this->prepareListValues($tableName);

        $onlyOnce = true;
        $lineSize = 0;

        // colStmt is used to form a query to obtain row values
        $colStmt = $this->getColumnStmt($tableName);
        // colNames is used to get the name of the columns when using complete-insert
        if ($this->dumpSettings['complete-insert']) {
            $colNames = $this->getColumnNames($tableName);
        }

        $stmt = "SELECT ".implode(",", $colStmt)." FROM `$tableName`";

        // Table specific conditions override the default 'where'
        $condition = $this->getTableWhere($tableName);

        if ($condition) {
            $stmt .= " WHERE {$condition}";
        }

        $limit = $this->getTableLimit($tableName);

        if ($limit !== false) {
            $stmt .= " LIMIT {$limit}";
        }

        $resultSet = $this->dbHandler->query($stmt);
        $resultSet->setFetchMode(PDO::FETCH_ASSOC);

        $ignore = $this->dumpSettings['insert-ignore'] ? '  IGNORE' : '';

        $count = 0;
        foreach ($resultSet as $row) {
            $count++;
            $vals = $this->prepareColumnValues($tableName, $row);
            if ($onlyOnce || !$this->dumpSettings['extended-insert']) {
                if ($this->dumpSettings['complete-insert']) {
                    $lineSize += $this->compressManager->write(
                        "INSERT$ignore INTO `$tableName` (".
                        implode(", ", $colNames).
                        ") VALUES (".implode(",", $vals).")"
                    );
                } else {
                    $lineSize += $this->compressManager->write(
                        "INSERT$ignore INTO `$tableName` VALUES (".implode(",", $vals).")"
                    );
                }
                $onlyOnce = false;
            } else {
                $lineSize += $this->compressManager->write(",(".implode(",", $vals).")");
            }
            if (($lineSize > $this->dumpSettings['net_buffer_length']) ||
                !$this->dumpSettings['extended-insert']) {
                $onlyOnce = true;
                $lineSize = $this->compressManager->write(";".PHP_EOL);
            }
        }
        $resultSet->closeCursor();

        if (!$onlyOnce) {
            $this->compressManager->write(";".PHP_EOL);
        }

        $this->endListValues($tableName, $count);

        if ($this->infoCallable) {
            call_user_func($this->infoCallable, 'table', array('name' => $tableName, 'rowCount' => $count));
        }
    }

    /**
     * Table rows extractor, append information prior to dump
     *
     * @param string $tableName  Name of table to export
     *
     * @return null
     */
    public function prepareListValues($tableName)
    {
        if (!$this->dumpSettings['skip-comments']) {
            $this->compressManager->write(
                "--".PHP_EOL.
                "-- Dumping data for table `$tableName`".PHP_EOL.
                "--".PHP_EOL.PHP_EOL
            );
        }

        if ($this->dumpSettings['lock-tables'] && !$this->dumpSettings['single-transaction']) {
            $this->typeAdapter->lock_table($tableName);
        }

        if ($this->dumpSettings['add-locks']) {
            $this->compressManager->write(
                $this->typeAdapter->start_add_lock_table($tableName)
            );
        }

        if ($this->dumpSettings['disable-keys']) {
            $this->compressManager->write(
                $this->typeAdapter->start_add_disable_keys($tableName)
            );
        }

        // Disable autocommit for faster reload
        if ($this->dumpSettings['no-autocommit']) {
            $this->compressManager->write(
                $this->typeAdapter->start_disable_autocommit()
            );
        }

        return;
    }

    /**
     * Table rows extractor, close locks and commits after dump
     *
     * @param string $tableName Name of table to export.
     * @param integer    $count     Number of rows inserted.
     *
     * @return void
     */
    public function endListValues($tableName, $count = 0)
    {
        if ($this->dumpSettings['disable-keys']) {
            $this->compressManager->write(
                $this->typeAdapter->end_add_disable_keys($tableName)
            );
        }

        if ($this->dumpSettings['add-locks']) {
            $this->compressManager->write(
                $this->typeAdapter->end_add_lock_table($tableName)
            );
        }

        if ($this->dumpSettings['lock-tables'] && !$this->dumpSettings['single-transaction']) {
            $this->typeAdapter->unlock_table($tableName);
        }

        // Commit to enable autocommit
        if ($this->dumpSettings['no-autocommit']) {
            $this->compressManager->write(
                $this->typeAdapter->end_disable_autocommit()
            );
        }

        $this->compressManager->write(PHP_EOL);

        if (!$this->dumpSettings['skip-comments']) {
            $this->compressManager->write(
                "-- Dumped table `".$tableName."` with $count row(s)".PHP_EOL.
                '--'.PHP_EOL.PHP_EOL
            );
        }

        return;
    }

    /**
     * Build SQL List of all columns on current table which will be used for selecting
     *
     * @param string $tableName  Name of table to get columns
     *
     * @return array SQL sentence with columns for select
     */
    public function getColumnStmt($tableName)
    {
        $colStmt = array();
        foreach ($this->tableColumnTypes[$tableName] as $colName => $colType) {
            if ($colType['is_virtual']) {
                $this->dumpSettings['complete-insert'] = true;
                continue;
            } elseif ($colType['type'] == 'bit' && $this->dumpSettings['hex-blob']) {
                $colStmt[] = "LPAD(HEX(`{$colName}`),2,'0') AS `{$colName}`";
            } elseif ($colType['type'] == 'double' && PHP_VERSION_ID > 80100) {
                $colStmt[] = sprintf("CONCAT(`%s`) AS `%s`", $colName, $colName);
            } elseif ($colType['is_blob'] && $this->dumpSettings['hex-blob']) {
                $colStmt[] = "HEX(`{$colName}`) AS `{$colName}`";
            } else {
                $colStmt[] = "`{$colName}`";
            }
        }

        return $colStmt;
    }

    /**
     * Build SQL List of all columns on current table which will be used for inserting
     *
     * @param string $tableName  Name of table to get columns
     *
     * @return array columns for sql sentence for insert
     */
    public function getColumnNames($tableName)
    {
        $colNames = array();
        foreach ($this->tableColumnTypes[$tableName] as $colName => $colType) {
            if ($colType['is_virtual']) {
                $this->dumpSettings['complete-insert'] = true;
                continue;
            } else {
                $colNames[] = "`{$colName}`";
            }
        }
        return $colNames;
    }
}

/**
 * Enum with all available compression methods
 *
 */
abstract class CompressMethod
{
    public static $enums = array(
        Mysqldump::NONE,
        Mysqldump::GZIP,
        Mysqldump::BZIP2,
        Mysqldump::GZIPSTREAM,
    );

    /**
     * @param string $c
     * @return boolean
     */
    public static function isValid($c)
    {
        return in_array($c, self::$enums);
    }
}

abstract class CompressManagerFactory
{
    /**
     * @param string $c
     * @return CompressBzip2|CompressGzip|CompressNone
     */
    public static function create($c)
    {
        $c = ucfirst(strtolower($c));
        if (!CompressMethod::isValid($c)) {
            throw new Exception("Compression method ($c) is not defined yet");
        }

        $method = __NAMESPACE__."\\"."Compress".$c;

        return new $method;
    }
}

class CompressBzip2 extends CompressManagerFactory
{
    private $fileHandler = null;

    public function __construct()
    {
        if (!function_exists("bzopen")) {
            throw new Exception("Compression is enabled, but bzip2 lib is not installed or configured properly");
        }
    }

    /**
     * @param string $filename
     */
    public function open($filename)
    {
        $this->fileHandler = bzopen($filename, "w");
        if (false === $this->fileHandler) {
            throw new Exception("Output file is not writable");
        }

        return true;
    }

    public function write($str)
    {
        $bytesWritten = bzwrite($this->fileHandler, $str);
        if (false === $bytesWritten) {
            throw new Exception("Writting to file failed! Probably, there is no more free space left?");
        }
        return $bytesWritten;
    }

    public function close()
    {
        return bzclose($this->fileHandler);
    }
}

class CompressGzip extends CompressManagerFactory
{
    private $fileHandler = null;

    public function __construct()
    {
        if (!function_exists("gzopen")) {
            throw new Exception("Compression is enabled, but gzip lib is not installed or configured properly");
        }
    }

    /**
     * @param string $filename
     */
    public function open($filename)
    {
        $this->fileHandler = gzopen($filename, "wb");
        if (false === $this->fileHandler) {
            throw new Exception("Output file is not writable");
        }

        return true;
    }

    public function write($str)
    {
        $bytesWritten = gzwrite($this->fileHandler, $str);
        if (false === $bytesWritten) {
            throw new Exception("Writting to file failed! Probably, there is no more free space left?");
        }
        return $bytesWritten;
    }

    public function close()
    {
        return gzclose($this->fileHandler);
    }
}

class CompressNone extends CompressManagerFactory
{
    private $fileHandler = null;

    /**
     * @param string $filename
     */
    public function open($filename)
    {
        $this->fileHandler = fopen($filename, "wb");
        if (false === $this->fileHandler) {
            throw new Exception("Output file is not writable");
        }

        return true;
    }

    public function write($str)
    {
        $bytesWritten = fwrite($this->fileHandler, $str);
        if (false === $bytesWritten) {
            throw new Exception("Writting to file failed! Probably, there is no more free space left?");
        }
        return $bytesWritten;
    }

    public function close()
    {
        return fclose($this->fileHandler);
    }
}

class CompressGzipstream extends CompressManagerFactory
{
    private $fileHandler = null;

    private $compressContext;

    /**
     * @param string $filename
     */
    public function open($filename)
    {
        $this->fileHandler = fopen($filename, "wb");
        if (false === $this->fileHandler) {
            throw new Exception("Output file is not writable");
        }

        $this->compressContext = deflate_init(ZLIB_ENCODING_GZIP, array('level' => 9));
        return true;
    }

    public function write($str)
    {

        $bytesWritten = fwrite($this->fileHandler, deflate_add($this->compressContext, $str, ZLIB_NO_FLUSH));
        if (false === $bytesWritten) {
            throw new Exception("Writting to file failed! Probably, there is no more free space left?");
        }
        return $bytesWritten;
    }

    public function close()
    {
        fwrite($this->fileHandler, deflate_add($this->compressContext, '', ZLIB_FINISH));
        return fclose($this->fileHandler);
    }
}

/**
 * Enum with all available TypeAdapter implementations
 *
 */
abstract class TypeAdapter
{
    public static $enums = array(
        "Sqlite",
        "Mysql"
    );

    /**
     * @param string $c
     * @return boolean
     */
    public static function isValid($c)
    {
        return in_array($c, self::$enums);
    }
}

/**
 * TypeAdapter Factory
 *
 */
abstract class TypeAdapterFactory
{
    protected $dbHandler = null;
    protected $dumpSettings = array();

    /**
     * @param string $c Type of database factory to create (Mysql, Sqlite,...)
     * @param PDO $dbHandler
     */
    public static function create($c, $dbHandler = null, $dumpSettings = array())
    {
        $c = ucfirst(strtolower($c));
        if (!TypeAdapter::isValid($c)) {
            throw new Exception("Database type support for ($c) not yet available");
        }
        $method = __NAMESPACE__."\\"."TypeAdapter".$c;
        return new $method($dbHandler, $dumpSettings);
    }

    public function __construct($dbHandler = null, $dumpSettings = array())
    {
        $this->dbHandler = $dbHandler;
        $this->dumpSettings = $dumpSettings;
    }

    /**
     * function databases Add sql to create and use database
     * @todo make it do something with sqlite
     */
    public function databases()
    {
        return "";
    }

    public function show_create_table($tableName)
    {
        return "SELECT tbl_name as 'Table', sql as 'Create Table' ".
            "FROM sqlite_master ".
            "WHERE type='table' AND tbl_name='$tableName'";
    }

    /**
     * function create_table Get table creation code from database
     * @todo make it do something with sqlite
     */
    public function create_table($row)
    {
        return "";
    }

    public function show_create_view($viewName)
    {
        return "SELECT tbl_name as 'View', sql as 'Create View' ".
            "FROM sqlite_master ".
            "WHERE type='view' AND tbl_name='$viewName'";
    }

    /**
     * function create_view Get view creation code from database
     * @todo make it do something with sqlite
     */
    public function create_view($row)
    {
        return "";
    }

    /**
     * function show_create_trigger Get trigger creation code from database
     * @todo make it do something with sqlite
     */
    public function show_create_trigger($triggerName)
    {
        return "";
    }

    /**
     * function create_trigger Modify trigger code, add delimiters, etc
     * @todo make it do something with sqlite
     */
    public function create_trigger($triggerName)
    {
        return "";
    }

    /**
     * function create_procedure Modify procedure code, add delimiters, etc
     * @todo make it do something with sqlite
     */
    public function create_procedure($procedureName)
    {
        return "";
    }

    /**
     * function create_function Modify function code, add delimiters, etc
     * @todo make it do something with sqlite
     */
    public function create_function($functionName)
    {
        return "";
    }

    public function show_tables()
    {
        return "SELECT tbl_name FROM sqlite_master WHERE type='table'";
    }

    public function show_views()
    {
        return "SELECT tbl_name FROM sqlite_master WHERE type='view'";
    }

    public function show_triggers()
    {
        return "SELECT name FROM sqlite_master WHERE type='trigger'";
    }

    public function show_columns()
    {
        if (func_num_args() != 1) {
            return "";
        }

        $args = func_get_args();

        return "pragma table_info({$args[0]})";
    }

    public function show_procedures()
    {
        return "";
    }

    public function show_functions()
    {
        return "";
    }

    public function show_events()
    {
        return "";
    }

    public function setup_transaction()
    {
        return "";
    }

    public function start_transaction()
    {
        return "BEGIN EXCLUSIVE";
    }

    public function commit_transaction()
    {
        return "COMMIT";
    }

    public function lock_table()
    {
        return "";
    }

    public function unlock_table()
    {
        return "";
    }

    public function start_add_lock_table()
    {
        return PHP_EOL;
    }

    public function end_add_lock_table()
    {
        return PHP_EOL;
    }

    public function start_add_disable_keys()
    {
        return PHP_EOL;
    }

    public function end_add_disable_keys()
    {
        return PHP_EOL;
    }

    public function start_disable_foreign_keys_check()
    {
        return PHP_EOL;
    }

    public function end_disable_foreign_keys_check()
    {
        return PHP_EOL;
    }

    public function add_drop_database()
    {
        return PHP_EOL;
    }

    public function add_drop_trigger()
    {
        return PHP_EOL;
    }

    public function drop_table()
    {
        return PHP_EOL;
    }

    public function drop_view()
    {
        return PHP_EOL;
    }

    /**
     * Decode column metadata and fill info structure.
     * type, is_numeric and is_blob will always be available.
     *
     * @param array $colType Array returned from "SHOW COLUMNS FROM tableName"
     * @return array
     */
    public function parseColumnType($colType)
    {
        return array();
    }

    public function backup_parameters()
    {
        return PHP_EOL;
    }

    public function restore_parameters()
    {
        return PHP_EOL;
    }
}

class TypeAdapterPgsql extends TypeAdapterFactory
{
}

class TypeAdapterDblib extends TypeAdapterFactory
{
}

class TypeAdapterSqlite extends TypeAdapterFactory
{
}

class TypeAdapterMysql extends TypeAdapterFactory
{
    const DEFINER_RE = 'DEFINER=`(?:[^`]|``)*`@`(?:[^`]|``)*`';


    // Numerical Mysql types
    public $mysqlTypes = array(
        'numerical' => array(
            'bit',
            'tinyint',
            'smallint',
            'mediumint',
            'int',
            'integer',
            'bigint',
            'real',
            'double',
            'float',
            'decimal',
            'numeric'
        ),
        'blob' => array(
            'tinyblob',
            'blob',
            'mediumblob',
            'longblob',
            'binary',
            'varbinary',
            'bit',
            'geometry', /* http://bugs.mysql.com/bug.php?id=43544 */
            'point',
            'linestring',
            'polygon',
            'multipoint',
            'multilinestring',
            'multipolygon',
            'geometrycollection',
        )
    );

    public function databases()
    {
        if ($this->dumpSettings['no-create-db']) {
            return "";
        }

        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        $databaseName = $args[0];

        $resultSet = $this->dbHandler->query("SHOW VARIABLES LIKE 'character_set_database';");
        $characterSet = $resultSet->fetchColumn(1);
        $resultSet->closeCursor();

        $resultSet = $this->dbHandler->query("SHOW VARIABLES LIKE 'collation_database';");
        $collationDb = $resultSet->fetchColumn(1);
        $resultSet->closeCursor();
        $ret = "";

        $ret .= "CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$databaseName}`".
            " /*!40100 DEFAULT CHARACTER SET {$characterSet} ".
            " COLLATE {$collationDb} */;".PHP_EOL.PHP_EOL.
            "USE `{$databaseName}`;".PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function show_create_table($tableName)
    {
        return "SHOW CREATE TABLE `$tableName`";
    }

    public function show_create_view($viewName)
    {
        return "SHOW CREATE VIEW `$viewName`";
    }

    public function show_create_trigger($triggerName)
    {
        return "SHOW CREATE TRIGGER `$triggerName`";
    }

    public function show_create_procedure($procedureName)
    {
        return "SHOW CREATE PROCEDURE `$procedureName`";
    }

    public function show_create_function($functionName)
    {
        return "SHOW CREATE FUNCTION `$functionName`";
    }

    public function show_create_event($eventName)
    {
        return "SHOW CREATE EVENT `$eventName`";
    }

    public function create_table($row)
    {
        if (!isset($row['Create Table'])) {
            throw new Exception("Error getting table code, unknown output");
        }

        $createTable = $row['Create Table'];
        if ($this->dumpSettings['reset-auto-increment']) {
            $match = "/AUTO_INCREMENT=[0-9]+/s";
            $replace = "";
            $createTable = preg_replace($match, $replace, $createTable);
        }

        if ($this->dumpSettings['if-not-exists']) {
            $createTable = preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $createTable);
        }

        $ret = "/*!40101 SET @saved_cs_client     = @@character_set_client */;".PHP_EOL.
            "/*!40101 SET character_set_client = ".$this->dumpSettings['default-character-set']." */;".PHP_EOL.
            $createTable.";".PHP_EOL.
            "/*!40101 SET character_set_client = @saved_cs_client */;".PHP_EOL.
            PHP_EOL;
        return $ret;
    }

    public function create_view($row)
    {
        $ret = "";
        if (!isset($row['Create View'])) {
            throw new Exception("Error getting view structure, unknown output");
        }

        $viewStmt = $row['Create View'];

        $definerStr = $this->dumpSettings['skip-definer'] ? '' : '/*!50013 \2 */'.PHP_EOL;

        if ($viewStmtReplaced = preg_replace(
            '/^(CREATE(?:\s+ALGORITHM=(?:UNDEFINED|MERGE|TEMPTABLE))?)\s+('
            .self::DEFINER_RE.'(?:\s+SQL SECURITY (?:DEFINER|INVOKER))?)?\s+(VIEW .+)$/',
            '/*!50001 \1 */'.PHP_EOL.$definerStr.'/*!50001 \3 */',
            $viewStmt,
            1
        )) {
            $viewStmt = $viewStmtReplaced;
        };

        $ret .= $viewStmt.';'.PHP_EOL.PHP_EOL;
        return $ret;
    }

    public function create_trigger($row)
    {
        $ret = "";
        if (!isset($row['SQL Original Statement'])) {
            throw new Exception("Error getting trigger code, unknown output");
        }

        $triggerStmt = $row['SQL Original Statement'];
        $definerStr = $this->dumpSettings['skip-definer'] ? '' : '/*!50017 \2*/ ';
        if ($triggerStmtReplaced = preg_replace(
            '/^(CREATE)\s+('.self::DEFINER_RE.')?\s+(TRIGGER\s.*)$/s',
            '/*!50003 \1*/ '.$definerStr.'/*!50003 \3 */',
            $triggerStmt,
            1
        )) {
            $triggerStmt = $triggerStmtReplaced;
        }

        $ret .= "DELIMITER ;;".PHP_EOL.
            $triggerStmt.";;".PHP_EOL.
            "DELIMITER ;".PHP_EOL.PHP_EOL;
        return $ret;
    }

    public function create_procedure($row)
    {
        $ret = "";
        if (!isset($row['Create Procedure'])) {
            throw new Exception("Error getting procedure code, unknown output. ".
                "Please check 'https://bugs.mysql.com/bug.php?id=14564'");
        }
        $procedureStmt = $row['Create Procedure'];
        if ($this->dumpSettings['skip-definer']) {
            if ($procedureStmtReplaced = preg_replace(
                '/^(CREATE)\s+('.self::DEFINER_RE.')?\s+(PROCEDURE\s.*)$/s',
                '\1 \3',
                $procedureStmt,
                1
            )) {
                $procedureStmt = $procedureStmtReplaced;
            }
        }

        $ret .= "/*!50003 DROP PROCEDURE IF EXISTS `".
            $row['Procedure']."` */;".PHP_EOL.
            "/*!40101 SET @saved_cs_client     = @@character_set_client */;".PHP_EOL.
            "/*!40101 SET character_set_client = ".$this->dumpSettings['default-character-set']." */;".PHP_EOL.
            "DELIMITER ;;".PHP_EOL.
            $procedureStmt." ;;".PHP_EOL.
            "DELIMITER ;".PHP_EOL.
            "/*!40101 SET character_set_client = @saved_cs_client */;".PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function create_function($row)
    {
        $ret = "";
        if (!isset($row['Create Function'])) {
            throw new Exception("Error getting function code, unknown output. ".
                "Please check 'https://bugs.mysql.com/bug.php?id=14564'");
        }
        $functionStmt = $row['Create Function'];
        $characterSetClient = $row['character_set_client'];
        $collationConnection = $row['collation_connection'];
        $sqlMode = $row['sql_mode'];
        if ($this->dumpSettings['skip-definer']) {
            if ($functionStmtReplaced = preg_replace(
                '/^(CREATE)\s+('.self::DEFINER_RE.')?\s+(FUNCTION\s.*)$/s',
                '\1 \3',
                $functionStmt,
                1
            )) {
                $functionStmt = $functionStmtReplaced;
            }
        }

        $ret .= "/*!50003 DROP FUNCTION IF EXISTS `".
            $row['Function']."` */;".PHP_EOL.
            "/*!40101 SET @saved_cs_client     = @@character_set_client */;".PHP_EOL.
            "/*!50003 SET @saved_cs_results     = @@character_set_results */ ;".PHP_EOL.
            "/*!50003 SET @saved_col_connection = @@collation_connection */ ;".PHP_EOL.
            "/*!40101 SET character_set_client = ".$characterSetClient." */;".PHP_EOL.
            "/*!40101 SET character_set_results = ".$characterSetClient." */;".PHP_EOL.
            "/*!50003 SET collation_connection  = ".$collationConnection." */ ;".PHP_EOL.
            "/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;".PHP_EOL.
            "/*!50003 SET sql_mode              = '".$sqlMode."' */ ;;".PHP_EOL.
            "/*!50003 SET @saved_time_zone      = @@time_zone */ ;;".PHP_EOL.
            "/*!50003 SET time_zone             = 'SYSTEM' */ ;;".PHP_EOL.
            "DELIMITER ;;".PHP_EOL.
            $functionStmt." ;;".PHP_EOL.
            "DELIMITER ;".PHP_EOL.
            "/*!50003 SET sql_mode              = @saved_sql_mode */ ;".PHP_EOL.
            "/*!50003 SET character_set_client  = @saved_cs_client */ ;".PHP_EOL.
            "/*!50003 SET character_set_results = @saved_cs_results */ ;".PHP_EOL.
            "/*!50003 SET collation_connection  = @saved_col_connection */ ;".PHP_EOL.
            "/*!50106 SET TIME_ZONE= @saved_time_zone */ ;".PHP_EOL.PHP_EOL;


        return $ret;
    }

    public function create_event($row)
    {
        $ret = "";
        if (!isset($row['Create Event'])) {
            throw new Exception("Error getting event code, unknown output. ".
                "Please check 'http://stackoverflow.com/questions/10853826/mysql-5-5-create-event-gives-syntax-error'");
        }
        $eventName = $row['Event'];
        $eventStmt = $row['Create Event'];
        $sqlMode = $row['sql_mode'];
        $definerStr = $this->dumpSettings['skip-definer'] ? '' : '/*!50117 \2*/ ';

        if ($eventStmtReplaced = preg_replace(
            '/^(CREATE)\s+('.self::DEFINER_RE.')?\s+(EVENT .*)$/',
            '/*!50106 \1*/ '.$definerStr.'/*!50106 \3 */',
            $eventStmt,
            1
        )) {
            $eventStmt = $eventStmtReplaced;
        }

        $ret .= "/*!50106 SET @save_time_zone= @@TIME_ZONE */ ;".PHP_EOL.
            "/*!50106 DROP EVENT IF EXISTS `".$eventName."` */;".PHP_EOL.
            "DELIMITER ;;".PHP_EOL.
            "/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;".PHP_EOL.
            "/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;".PHP_EOL.
            "/*!50003 SET @saved_col_connection = @@collation_connection */ ;;".PHP_EOL.
            "/*!50003 SET character_set_client  = utf8 */ ;;".PHP_EOL.
            "/*!50003 SET character_set_results = utf8 */ ;;".PHP_EOL.
            "/*!50003 SET collation_connection  = utf8_general_ci */ ;;".PHP_EOL.
            "/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;".PHP_EOL.
            "/*!50003 SET sql_mode              = '".$sqlMode."' */ ;;".PHP_EOL.
            "/*!50003 SET @saved_time_zone      = @@time_zone */ ;;".PHP_EOL.
            "/*!50003 SET time_zone             = 'SYSTEM' */ ;;".PHP_EOL.
            $eventStmt." ;;".PHP_EOL.
            "/*!50003 SET time_zone             = @saved_time_zone */ ;;".PHP_EOL.
            "/*!50003 SET sql_mode              = @saved_sql_mode */ ;;".PHP_EOL.
            "/*!50003 SET character_set_client  = @saved_cs_client */ ;;".PHP_EOL.
            "/*!50003 SET character_set_results = @saved_cs_results */ ;;".PHP_EOL.
            "/*!50003 SET collation_connection  = @saved_col_connection */ ;;".PHP_EOL.
            "DELIMITER ;".PHP_EOL.
            "/*!50106 SET TIME_ZONE= @save_time_zone */ ;".PHP_EOL.PHP_EOL;
        // Commented because we are doing this in restore_parameters()
        // "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;" . PHP_EOL . PHP_EOL;

        return $ret;
    }

    public function show_tables()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SELECT TABLE_NAME AS tbl_name ".
            "FROM INFORMATION_SCHEMA.TABLES ".
            "WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='{$args[0]}' ".
            "ORDER BY TABLE_NAME";
    }

    public function show_views()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SELECT TABLE_NAME AS tbl_name ".
            "FROM INFORMATION_SCHEMA.TABLES ".
            "WHERE TABLE_TYPE='VIEW' AND TABLE_SCHEMA='{$args[0]}' ".
            "ORDER BY TABLE_NAME";
    }

    public function show_triggers()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SHOW TRIGGERS FROM `{$args[0]}`;";
    }

    public function show_columns()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SHOW COLUMNS FROM `{$args[0]}`;";
    }

    public function show_procedures()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SELECT SPECIFIC_NAME AS procedure_name ".
            "FROM INFORMATION_SCHEMA.ROUTINES ".
            "WHERE ROUTINE_TYPE='PROCEDURE' AND ROUTINE_SCHEMA='{$args[0]}'";
    }

    public function show_functions()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SELECT SPECIFIC_NAME AS function_name ".
            "FROM INFORMATION_SCHEMA.ROUTINES ".
            "WHERE ROUTINE_TYPE='FUNCTION' AND ROUTINE_SCHEMA='{$args[0]}'";
    }

    /**
     * Get query string to ask for names of events from current database.
     *
     * @param string Name of database
     * @return string
     */
    public function show_events()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "SELECT EVENT_NAME AS event_name ".
            "FROM INFORMATION_SCHEMA.EVENTS ".
            "WHERE EVENT_SCHEMA='{$args[0]}'";
    }

    public function setup_transaction()
    {
        return "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ";
    }

    public function start_transaction()
    {
        return "START TRANSACTION ".
            "/*!40100 WITH CONSISTENT SNAPSHOT */";
    }


    public function commit_transaction()
    {
        return "COMMIT";
    }

    public function lock_table()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return $this->dbHandler->exec("LOCK TABLES `{$args[0]}` READ LOCAL");
    }

    public function unlock_table()
    {
        return $this->dbHandler->exec("UNLOCK TABLES");
    }

    public function start_add_lock_table()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "LOCK TABLES `{$args[0]}` WRITE;".PHP_EOL;
    }

    public function end_add_lock_table()
    {
        return "UNLOCK TABLES;".PHP_EOL;
    }

    public function start_add_disable_keys()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "/*!40000 ALTER TABLE `{$args[0]}` DISABLE KEYS */;".
            PHP_EOL;
    }

    public function end_add_disable_keys()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "/*!40000 ALTER TABLE `{$args[0]}` ENABLE KEYS */;".
            PHP_EOL;
    }

    public function start_disable_autocommit()
    {
        return "SET autocommit=0;".PHP_EOL;
    }

    public function end_disable_autocommit()
    {
        return "COMMIT;".PHP_EOL;
    }

    public function add_drop_database()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "/*!40000 DROP DATABASE IF EXISTS `{$args[0]}`*/;".
            PHP_EOL.PHP_EOL;
    }

    public function add_drop_trigger()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "DROP TRIGGER IF EXISTS `{$args[0]}`;".PHP_EOL;
    }

    public function drop_table()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "DROP TABLE IF EXISTS `{$args[0]}`;".PHP_EOL;
    }

    public function drop_view()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "DROP TABLE IF EXISTS `{$args[0]}`;".PHP_EOL.
            "/*!50001 DROP VIEW IF EXISTS `{$args[0]}`*/;".PHP_EOL;
    }

    public function getDatabaseHeader()
    {
        $this->check_parameters(func_num_args(), $expected_num_args = 1, __METHOD__);
        $args = func_get_args();
        return "--".PHP_EOL.
            "-- Current Database: `{$args[0]}`".PHP_EOL.
            "--".PHP_EOL.PHP_EOL;
    }

    /**
     * Decode column metadata and fill info structure.
     * type, is_numeric and is_blob will always be available.
     *
     * @param array $colType Array returned from "SHOW COLUMNS FROM tableName"
     * @return array
     */
    public function parseColumnType($colType)
    {
        $colInfo = array();
        $colParts = explode(" ", $colType['Type']);

        if ($fparen = strpos($colParts[0], "(")) {
            $colInfo['type'] = substr($colParts[0], 0, $fparen);
            $colInfo['length'] = str_replace(")", "", substr($colParts[0], $fparen + 1));
            $colInfo['attributes'] = isset($colParts[1]) ? $colParts[1] : null;
        } else {
            $colInfo['type'] = $colParts[0];
        }
        $colInfo['is_numeric'] = in_array($colInfo['type'], $this->mysqlTypes['numerical']);
        $colInfo['is_blob'] = in_array($colInfo['type'], $this->mysqlTypes['blob']);
        // for virtual columns that are of type 'Extra', column type
        // could by "STORED GENERATED" or "VIRTUAL GENERATED"
        // MySQL reference: https://dev.mysql.com/doc/refman/5.7/en/create-table-generated-columns.html
        $colInfo['is_virtual'] = strpos($colType['Extra'], "VIRTUAL GENERATED") !== false || strpos($colType['Extra'], "STORED GENERATED") !== false;

        return $colInfo;
    }

    public function backup_parameters()
    {
        $ret = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;".PHP_EOL.
            "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;".PHP_EOL.
            "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;".PHP_EOL.
            "/*!40101 SET NAMES ".$this->dumpSettings['default-character-set']." */;".PHP_EOL;

        if (false === $this->dumpSettings['skip-tz-utc']) {
            $ret .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;".PHP_EOL.
                "/*!40103 SET TIME_ZONE='+00:00' */;".PHP_EOL;
        }

        if ($this->dumpSettings['no-autocommit']) {
            $ret .= "/*!40101 SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT */;".PHP_EOL;
        }

        $ret .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;".PHP_EOL.
            "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;".PHP_EOL.
            "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;".PHP_EOL.
            "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;".PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function restore_parameters()
    {
        $ret = "";

        if (false === $this->dumpSettings['skip-tz-utc']) {
            $ret .= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;".PHP_EOL;
        }

        if ($this->dumpSettings['no-autocommit']) {
            $ret .= "/*!40101 SET AUTOCOMMIT=@OLD_AUTOCOMMIT */;".PHP_EOL;
        }

        $ret .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;".PHP_EOL.
            "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;".PHP_EOL.
            "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;".PHP_EOL.
            "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;".PHP_EOL.
            "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;".PHP_EOL.
            "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;".PHP_EOL.
            "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;".PHP_EOL.PHP_EOL;

        return $ret;
    }

    /**
     * Check number of parameters passed to function, useful when inheriting.
     * Raise exception if unexpected.
     *
     * @param integer $num_args
     * @param integer $expected_num_args
     * @param string $method_name
     */
    private function check_parameters($num_args, $expected_num_args, $method_name)
    {
        if ($num_args != $expected_num_args) {
            throw new Exception("Unexpected parameter passed to $method_name");
        }
        return;
    }
}
