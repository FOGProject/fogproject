<?php
/**
 * Handles the database insert/export
 *
 * PHP version 7.4+
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Db\Mysqldump;

/**
 * Handles the database insert/export
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Schema extends FOGController
{
    /**
     * All of the available comparators.
     *
     * @var array
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'like binary', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*'
    ];
    /**
     * The schema version table
     *
     * @var string
     */
    protected $databaseTable = 'schemaVersion';
    /**
     * The schema table and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'vID',
        'version' => 'vValue'
    ];
    /**
     * Simply returns the database name
     *
     * @return string
     */
    public static function getDBName()
    {
        return DATABASE_NAME;
    }
    /**
     * Creates the database creation query
     *
     * @return string
     */
    public static function createDatabaseQuery()
    {
        return self::createDatabase(self::getDBName(), false);
    }
    /**
     * Ensures we're using the database
     *
     * @return string
     */
    public static function useDatabaseQuery()
    {
        return sprintf(
            'USE `%s`',
            self::getDBName()
        );
    }
    /**
     * Recreates the database passed and removes
     * duplicate data
     *
     * $table is a positional tuple, not a name: [0] the table, [1] the
     * column or columns the uniqueness is over, and optionally [2] an index
     * to drop afterward. The queries are RETURNED for the caller to fold
     * into its own upgrade step -- commons/schema.php fastmerge()s them --
     * and are not executed here.
     *
     * @param string $dbname      the database name
     * @param array  $table       [tablename, indexes, dropIndex]
     * @param bool   $indexNeeded index is needed
     *
     * @return array the queries to run, empty when there is nothing to do
     */
    public function dropDuplicateData(
        $dbname,
        $table = [],
        $indexNeeded = false
    ) {
        if (empty($dbname)) {
            return [];
        }
        if (count($table) < 1) {
            return [];
        }
        $queries = [];
        $tablename = $table[0];
        $indexes = (array)$table[1];
        $dropIndex = false;
        if (isset($table[2])) {
            $dropIndex = $table[2];
        }
        if ($indexNeeded) {
            if (count($indexes) < 1) {
                return [];
            } elseif (count($indexes) === 1) {
                $ending = sprintf(
                    'INDEX (`%s`)',
                    array_shift($indexes)
                );
            } else {
                $ending = sprintf(
                    'INDEX `%s` (`%s`)',
                    array_shift($indexes),
                    implode('`,`', $indexes)
                );
            }
        } else {
            $ending = sprintf(
                '(`%s`)',
                implode('`,`', $indexes)
            );
        }
        $queries[] = sprintf(
            'DROP TABLE IF EXISTS `%s`.`_%s`',
            $dbname,
            $tablename
        );
        $queries[] = sprintf(
            'CREATE TABLE `%s`.`_%s` LIKE `%s`.`%s`',
            $dbname,
            $tablename,
            $dbname,
            $tablename
        );
        $queries[] = sprintf(
            'ALTER TABLE `%s`.`_%s` ADD UNIQUE %s',
            $dbname,
            $tablename,
            $ending
        );
        $queries[] = sprintf(
            'INSERT IGNORE INTO `%s`.`_%s` %s',
            $dbname,
            $tablename,
            sprintf(
                'SELECT * FROM `%s`.`%s`',
                $dbname,
                $tablename
            )
        );
        $queries[] = sprintf(
            'DROP TABLE `%s`.`%s`',
            $dbname,
            $tablename
        );
        $queries[] = sprintf(
            'RENAME TABLE `%s`.`_%s` TO `%s`.`%s`',
            $dbname,
            $tablename,
            $dbname,
            $tablename
        );
        if ($dropIndex) {
            $queries[] = sprintf(
                'ALTER TABLE `%s`.`%s` DROP INDEX `%s`',
                $dbname,
                $tablename,
                $dropIndex
            );
        }
        return $queries;
    }
    /**
     * Export the db and present it as a file.
     *
     * @param string $backup_name The backup name to use.
     * @param bool   $remove_file Remove the backup when done.
     *
     * @return string The filename to export from.
     */
    public function exportdb(
        $backup_name = '',
        $remove_file = true
    ) {
        $orig_exec_time = ini_get('max_execution_time');
        set_time_limit(0);
        // A fixed path in /tmp was three bugs at once (GH-1410): the dump
        // holds every credential in the deployment and the default umask
        // made it world-readable for the duration; two concurrent exports
        // clobbered each other; and fopen() follows symlinks, so a
        // pre-planted symlink at a guessable name was a write-as-web-user
        // primitive. tempnam() creates the file 0600 and unpredictably
        // named, and Mysqldump's own fopen(...,'wb') truncates rather than
        // recreating it, so the mode survives.
        $file = tempnam(sys_get_temp_dir(), 'fog_backup_');
        if (false === $file) {
            throw new \Exception(_('Could not create tmp file.'));
        }
        chmod($file, 0600);
        if (!$backup_name) {
            $backup_name = sprintf(
                'fog_backup_%s.sql',
                self::formatTime('now', 'Ymd_His')
            );
        }
        $dump = new Mysqldump();
        $dump->start($file);
        if (!file_exists($file) || !is_readable($file)) {
            throw new \Exception(_('Could not read tmp file.'));
        }
        if ($remove_file) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            $fh = fopen($file, 'rb');
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=$backup_name");
            header('Cache-Control: private');
            while (feof($fh) === false) {
                echo fread($fh, 4096);
            }
            fclose($fh);
            ini_set('max_execution_time', $orig_exec_time);
            // No request_terminate_timeout restore: nothing here changes it,
            // it is a php-fpm pool directive rather than a runtime-settable
            // ini, and the line that restored it passed $orig_term_time --
            // a variable this method never captured.
            if (file_exists($file)) {
                unlink($file);
            }
            return;
        }
        set_time_limit($orig_exec_time);
        return $file;
    }
    /**
     * Import the db from a file.
     *
     * @param string $file The file to import from.
     *
     * @return bool|string True on success, error string on failure.
     */
    public function importdb($file)
    {
        $orig_exec_time = ini_get('max_execution_time');
        set_time_limit(0);

        if (false === ($fh = fopen($file, 'rb'))) {
            throw new \Exception(_('Error Opening DB File'));
        }

        $error = '';
        $tmpline = '';

        // Transaction + FK off (best-effort)
        try {
            self::$DB->query('SET FOREIGN_KEY_CHECKS=0');
            self::$DB->query('SET autocommit=0');
            self::$DB->query('START TRANSACTION');
        } catch (\Throwable $e) {
            // ignore
        }

        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);

            // Skip plain comments/blank
            if ($trim === '' || substr($trim, 0, 2) === '--') {
                continue;
            }

            // Unwrap guarded mysqldump directives: /*!40101 SET ... */;
            if (preg_match('#^/\*![0-9]{3,}\s+(.*)\*/\s*;?$#s', $trim, $m)) {
                $tmpline .= rtrim($m[1], ';') . ';' . PHP_EOL;
            } else {
                $tmpline .= $line;
            }

            // End of statement?
            if (substr(rtrim($line), -1) !== ';') {
                continue;
            }

            $stmt = trim($tmpline);
            $tmpline = '';

            // Pre-drop on CREATE TABLE (so imports work over existing schemas)
            if (preg_match('/^CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?/i', $stmt, $m)) {
                $drop = sprintf('DROP TABLE IF EXISTS `%s`', $m[1]);
                if (false === self::$DB->query($drop)) {
                    $error .= sprintf(
                        "%s '<strong>%s': %s</strong><br/><br/>",
                        _('Error performing query'),
                        $drop,
                        self::$DB->sqlerror()
                    );
                    break;
                }
            }

            // Chunk extended INSERTS safely
            if (preg_match('/^INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?.*?\sVALUES\s*(.+);\s*$/is', $stmt, $mm)) {
                $insertHead = substr($stmt, 0, stripos($stmt, 'VALUES'));
                $valuesPart = trim(substr($stmt, stripos($stmt, 'VALUES') + 6)); // includes the (...)… list, no trailing ';'

                // Build rows array using a state machine that respects quotes/escapes/paren depth.
                $rows = self::splitExtendedInsertRows($valuesPart);

                $batchSize = 200; // tune as you like
                $batch = [];
                foreach ($rows as $row) {
                    if ($row === '') {
                        continue;
                    }
                    $batch[] = $row;
                    if (count($batch) >= $batchSize) {
                        $sql = $insertHead . ' VALUES ' . implode(',', $batch) . ';';
                        if (false === self::$DB->query($sql)) {
                            $error .= sprintf(
                                "%s '<strong>%s': %s</strong><br/><br/>",
                                _('Error performing query'),
                                $insertHead . ' VALUES …',
                                self::$DB->sqlerror()
                            );
                            break 2;
                        }
                        $batch = [];
                    }
                }
                if (!$error && $batch) {
                    $sql = $insertHead . ' VALUES ' . implode(',', $batch) . ';';
                    if (false === self::$DB->query($sql)) {
                        $error .= sprintf(
                            "%s '<strong>%s': %s</strong><br/><br/>",
                            _('Error performing query'),
                            $insertHead . ' VALUES …',
                            self::$DB->sqlerror()
                        );
                    }
                }
            } else {
                if (false === self::$DB->query($stmt)) {
                    $error .= sprintf(
                        "%s '<strong>%s': %s</strong><br/><br/>",
                        _('Error performing query'),
                        $stmt,
                        self::$DB->sqlerror()
                    );
                    break;
                }
            }
        }

        fclose($fh);
        set_time_limit($orig_exec_time);

        try {
            self::$DB->query($error ? 'ROLLBACK' : 'COMMIT');
        } catch (\Throwable $e) { /* ignore */
        }
        try {
            self::$DB->query('SET FOREIGN_KEY_CHECKS=1');
            self::$DB->query('SET autocommit=1');
        } catch (\Throwable $e) { /* ignore */
        }

        return $error ?: true;
    }
    /**
     * Split a VALUES blob like:  (..),(..),(..)
     * into an array of row chunks preserving inner commas/parentheses/quotes.
     */
    private static function splitExtendedInsertRows(string $valuesPart): array
    {
        $valuesPart = rtrim(trim($valuesPart), ';');
        // Ensure the overall thing starts with '(' and treat commas at depth 0 as separators.
        $rows = [];
        $buf = '';
        $inQuote = false;
        $q = '';
        $esc = false;
        $depth = 0;
        $len = strlen($valuesPart);

        for ($i = 0; $i < $len; $i++) {
            $ch = $valuesPart[$i];

            if ($esc) {
                $buf .= $ch;
                $esc = false;
                continue;
            }
            if ($ch === '\\') {
                $buf .= $ch;
                $esc = true;
                continue;
            }

            if ($inQuote) {
                if ($ch === $q) {
                    $inQuote = false;
                }
                $buf .= $ch;
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $inQuote = true;
                $q = $ch;
                $buf .= $ch;
                continue;
            }

            if ($ch === '(') {
                $depth++;
                $buf .= $ch;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $buf .= $ch;
                continue;
            }

            // Split on commas only when not inside quotes and depth == 0
            if ($ch === ',' && $depth === 0) {
                $row = trim($buf);
                if ($row !== '') {
                    $rows[] = $row;
                }
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        $row = trim($buf);
        if ($row !== '') {
            $rows[] = $row;
        }

        // Normalize: ensure each row retains its surrounding parentheses
        $rows = array_map(function ($r) {
            $r = trim($r);
            if ($r[0] !== '(') {
                $r = '(' . $r;
            }
            if ($r[strlen($r) - 1] !== ')') {
                $r .= ')';
            }
            return $r;
        }, $rows);

        return $rows;
    }
    /**
     * SQL create database syntax.
     *
     * @param string $name   What are we calling it?
     * @param bool   $exists If not exists?
     *
     * @return string
     */
    public static function createDatabase(
        $name,
        $exists
    ) {
        if (!is_bool($exists)) {
            throw new \Exception(_('Exists item must be boolean'));
        }
        $string = sprintf(
            'CREATE DATABASE %s`%s`',
            (
                false == $exists ?
                ' IF NOT EXISTS' :
                ''
            ),
            $name
        );
        return $string;
    }
    /**
     * Renders a caller's default as an SQL literal.
     *
     * GH-1245. What callers pass is a VALUE, not SQL -- a literal like '0' or
     * '0000-00-00 00:00:00'. The one exception is CURRENT_TIMESTAMP, which is
     * an expression and must go in bare. Emitting them all raw puts an
     * unquoted 0 against an ENUM -- where 0 is an INDEX, and index 0 is the
     * error value -- and an unquoted zero date against a TIMESTAMP. The
     * server refuses both, so a plugin's CREATE TABLE would die on itself.
     *
     * It never showed because the truthiness test in createTable() dropped
     * every '0' before it could reach the server. Fixing that test is what
     * makes these visible; they were wrong the whole time.
     *
     * Quoting is skipped for a value that is already quoted, for a
     * parenthesised expression -- which is how MySQL 8.0.13+ requires a
     * TEXT/BLOB default to be written -- and for the small set of bare SQL
     * expressions FOG actually uses.
     *
     * @param string $value the default as the caller wrote it
     *
     * @return string
     */
    public static function defaultLiteral($value)
    {
        $value = (string)$value;
        $trim = trim($value);
        if (preg_match("/^'.*'$/s", $trim)
            || preg_match('/^\(.*\)$/s', $trim)
            || preg_match(
                '/^(CURRENT_TIMESTAMP(\(\))?|NOW\(\)|NULL)$/i',
                $trim
            )
        ) {
            return $value;
        }

        return sprintf("'%s'", str_replace("'", "''", $value));
    }
    /**
     * The DEFAULT an optional NOT NULL column of this type should carry.
     *
     * GH-1245. A column declared NOT NULL with no DEFAULT is only mandatory
     * if something enforces it, and for nine years nothing did: PDODB cleared
     * sql_mode on every connection, so the server downgraded the error to a
     * warning and substituted an implicit zero. What is returned here IS that
     * implicit zero, written down -- the same rule schema step 348 applies to
     * an existing install and FOGBase::emptyValueFor() applies to a write. So
     * a table a plugin creates and a table the step migrated end up saying
     * the same thing, which is the point: two installs of the same FOG should
     * not have two different schemas.
     *
     * The type vocabulary here is deliberately NOT step 348's. The step reads
     * COLUMN_TYPE, where an integer is always 'int(11)' or 'bigint(20)';
     * these types are hand-written by the caller, and the plugins say
     * 'INTEGER' far more often than anything else, plus BOOLEAN and
     * TIMESTAMP. A rule copied across without widening it silently gives
     * every integer column a default of '' -- so the two are not identical on
     * purpose.
     *
     * @param string $type the column type, as handed to createTable()
     *
     * @return string|null the SQL literal, or null if this server cannot
     *                     carry a default for this type at all
     */
    public static function emptyDefaultFor($type)
    {
        $type = trim((string)$type);
        $lob = (bool)preg_match(
            '/^(tiny|medium|long)?(text|blob)\b/i',
            $type
        );
        if ($lob) {
            // MySQL could not attach a DEFAULT to a TEXT or BLOB column
            // until 8.0.13 and still requires it parenthesised as an
            // expression; MariaDB has taken the plain literal since 10.2.1.
            // Below that there is nothing to do and nothing broken by
            // skipping: save() writes the column explicitly and
            // insertBatch() backfills it.
            static $lobStyle = null;
            if (null === $lobStyle) {
                $version = (string)self::$DB
                    ->query('SELECT VERSION() AS `v`')
                    ->fetch()
                    ->get('v');
                if (false !== stripos($version, 'mariadb')) {
                    $lobStyle = 'literal';
                } else {
                    preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m);
                    $lobStyle = count($m) === 4
                        && (int)$m[1] * 10000
                            + (int)$m[2] * 100
                            + (int)$m[3] >= 80013
                        ? 'expression'
                        : 'none';
                }
            }
            if ($lobStyle === 'none') {
                return null;
            }

            return $lobStyle === 'expression' ? "('')" : "''";
        }
        if (preg_match('/^(datetime|timestamp)\b/i', $type)) {
            return 'current_timestamp()';
        }
        if (preg_match('/^(tiny|small|medium|big)?int(eger)?\b/i', $type)
            || preg_match('/^bool(ean)?\b/i', $type)
        ) {
            return '0';
        }
        if (preg_match("/^(enum|set)\\s*\\(\\s*'((?:[^']|'')*)'/i", $type, $member)) {
            return "'" . $member[2] . "'";
        }

        return "''";
    }
    /**
     * Converts enum('0','1') columns to tinyint(1), preserving every value.
     *
     * ADR 0028. FOG spelled its two-state columns enum('0','1') for years,
     * which put a trap in every one of them: an integer written to an ENUM is
     * a member INDEX, not a value, so 1 selects the member '0' -- FALSE --
     * and 0 is the error value STRICT_TRANS_TABLES refuses. tinyint(1) has no
     * such trap. Core converts its columns in schema step 368; each bundled
     * plugin converts its own from its own schema() (ADR 0009), and calls
     * this so there is one implementation of the conversion rather than four.
     *
     * 🔴 THREE STATEMENTS, NOT ONE, AND THAT IS NOT OPTIONAL. A direct
     * `ALTER TABLE t MODIFY c TINYINT(1)` converts an ENUM BY INDEX. Measured
     * on MariaDB 11.8:
     *
     *     before:  '0'  '1'  '0'  '1'
     *     after:    1    2    1    2
     *
     * Every false becomes 1 and every true becomes 2 -- both truthy, no
     * error, nothing logged, on every upgrading server. VARCHAR(1) first
     * converts by LABEL; the second ALTER then converts the resulting
     * '0'/'1' strings by VALUE, which is the wanted mapping. The UPDATE
     * between the two is not cosmetic either: a row still holding the ENUM
     * error value from before GH-1245 arrives at the varchar stage as '',
     * which tinyint refuses, so without it the upgrade fails hard on exactly
     * the databases that most need repairing.
     *
     * Nullability and default are read from the catalog and carried across
     * rather than assumed -- LDAPServers.lsAllowAPI is nullable and
     * lsUseGroupMatch has no default at all, and rewriting either would be a
     * behavior change smuggled in by a type change.
     *
     * Re-running is a read: a column that is not still exactly
     * enum('0','1') is skipped, so a converted column is left alone and so is
     * one an admin has changed to something else.
     *
     * @param array $map table name => list of column names.
     *
     * @return bool
     */
    public static function enumToTinyint(array $map)
    {
        foreach ($map as $table => $columns) {
            $rows = self::$DB->query(
                "SELECT `COLUMN_NAME` AS `c`, `COLUMN_TYPE` AS `ty`, "
                . "`COLUMN_DEFAULT` AS `d`, `IS_NULLABLE` AS `n` "
                . "FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND LOWER(`TABLE_NAME`) = :table",
                [],
                [':table' => strtolower($table)]
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

            $want = array_map('strtolower', (array) $columns);
            foreach ((array) $rows as $row) {
                if (!isset($row['c'], $row['ty'])
                    || !in_array(strtolower($row['c']), $want, true)
                ) {
                    continue;
                }
                if (!preg_match("/^enum\\('0','1'\\)$/i", trim($row['ty']))) {
                    continue;
                }

                $nullable = isset($row['n'])
                    && 0 === strcasecmp((string) $row['n'], 'YES');
                // MariaDB reports a string default quoted ('1') and reports
                // "no default" as SQL NULL; MySQL 8 reports the member
                // unquoted. Trimming the quotes normalizes both.
                $raw = $row['d'];
                $hasDefault = null !== $raw
                    && 0 !== strcasecmp((string) $raw, 'NULL');
                $default = ('1' === trim((string) $raw, "'")) ? '1' : '0';

                $null = $nullable ? 'NULL' : 'NOT NULL';
                if (!$hasDefault && $nullable) {
                    $strTail = $null . ' DEFAULT NULL';
                    $intTail = $null . ' DEFAULT NULL';
                } elseif (!$hasDefault) {
                    $strTail = $null;
                    $intTail = $null;
                } else {
                    $strTail = $null . " DEFAULT '" . $default . "'";
                    $intTail = $null . ' DEFAULT ' . $default;
                }

                self::$DB->query(
                    sprintf(
                        'ALTER TABLE `%s` MODIFY COLUMN `%s` VARCHAR(1) %s',
                        $table,
                        $row['c'],
                        $strTail
                    )
                );
                self::$DB->query(
                    sprintf(
                        'UPDATE `%s` SET `%s` = \'0\' '
                        . "WHERE `%s` IS NOT NULL AND `%s` NOT IN ('0', '1')",
                        $table,
                        $row['c'],
                        $row['c'],
                        $row['c']
                    )
                );
                self::$DB->query(
                    sprintf(
                        'ALTER TABLE `%s` MODIFY COLUMN `%s` TINYINT(1) %s',
                        $table,
                        $row['c'],
                        $intTail
                    )
                );
            }
        }

        return true;
    }
    /**
     * SQL create table syntax
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
     * @throws Exception
     * @return string
     */
    public static function createTable(
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
        if (empty($name)) {
            throw new \Exception(_('Must have a name to create the table'));
        }
        $fieldCount = count($fields);
        $typeCount = count($types);
        if ($fieldCount !== $typeCount) {
            throw new \Exception(_('Fields and types must have equal count'));
        }
        if (empty($engine)) {
            $engine = 'InnoDB';
        }
        if (empty($charset)) {
            $charset = 'utf8';
        }
        $sql = sprintf(
            'CREATE TABLE%s `%s` (',
            (
                $exists ?
                ' IF NOT EXISTS' :
                ''
            ),
            $name
        );
        foreach ((array)$fields as $i => &$field) {
            $sql .= sprintf(
                '`%s` %s%s%s%s,',
                $field,
                $types[$i],
                (
                    $nulls[$i] === false ?
                    ' NOT NULL' :
                    ''
                ),
                (
                    // Truthiness dropped a DEFAULT of '0' on the floor: '0'
                    // is falsey in PHP, so a caller asking for DEFAULT '0'
                    // -- an enum's first member, a boolean-ish flag -- got a
                    // bare NOT NULL column instead. That is GH-1245's defect
                    // inflicted by the builder itself.
                    isset($default[$i])
                    && false !== $default[$i]
                    && null !== $default[$i]
                    && '' !== $default[$i] ?
                    sprintf(
                        ' DEFAULT %s',
                        self::defaultLiteral($default[$i])
                    ) :
                    ''
                ),
                (
                    $field === $autoin ?
                    ' AUTO_INCREMENT' :
                    ''
                )
            );
            unset($field);
        }
        if ($prime) {
            $sql .= sprintf(
                'PRIMARY KEY (`%s`)',
                $prime
            );
        }
        foreach ((array)$unique as $i => &$uniq) {
            if (!$uniq) {
                continue;
            }
            if (is_array($uniq)) {
                $uniq = implode('`,`', $uniq);
            }
            $sql .= sprintf(
                ',UNIQUE INDEX `index%d` (`%s`)',
                $i,
                $uniq
            );
            unset($uniq);
        }
        $sql .= ') ';
        $sql .= sprintf(
            'ENGINE=%s',
            $engine
        );
        if ($autoin) {
            $sql .= ' AUTO_INCREMENT=1';
        }
        $sql .= ' DEFAULT ';
        $sql .= sprintf(
            'CHARSET=%s',
            $charset
        );
        $sql .= ' ROW_FORMAT=DYNAMIC';
        return $sql;
    }
    /**
     * Applies an ordered, append-only list of schema update steps,
     * starting after the already-applied count.
     *
     * Mirrors the core Schema Updater's idempotent runner: "already
     * exists / does not exist" style errors are tolerated so additive
     * steps (CREATE TABLE IF NOT EXISTS, ALTER TABLE ... ADD COLUMN,
     * INSERT IGNORE, ...) are safe to re-run. This is what makes a
     * re-run/upgrade non-destructive: nothing here drops data.
     *
     * Each step is either a SQL string or a callable. A callable should
     * return true on success or an error string on failure.
     *
     * @param array $steps   Ordered steps (SQL string or callable).
     * @param int   $applied Number of steps already applied.
     *
     * @return array ['applied' => int, 'error' => string|null]
     */
    public static function applyUpdates($steps, $applied = 0)
    {
        $steps = (array)$steps;
        $applied = (int)$applied;
        $total = count($steps);
        if ($total <= $applied) {
            return ['applied' => $applied, 'error' => null];
        }
        $skiperrs = [
            1050, // Table already exists
            1054, // Unknown column
            1060, // Duplicate column name
            1061, // Duplicate key name
            1062, // Duplicate entry
            1091, // Can't DROP; does not exist
        ];
        $items = array_slice($steps, $applied, null, true);
        foreach ($items as $index => $step) {
            if (!$step) {
                $applied = $index + 1;
                continue;
            }
            if (is_callable($step)) {
                $result = $step();
                if (is_string($result)) {
                    return ['applied' => $applied, 'error' => $result];
                }
            } elseif (false !== self::$DB->query($step)->error) {
                $err = self::$DB->errorCode;
                if (!in_array($err, $skiperrs)) {
                    return [
                        'applied' => $applied,
                        'error' => self::$DB->error
                    ];
                }
            }
            $applied = $index + 1;
        }
        return ['applied' => $applied, 'error' => null];
    }
    /**
     * The sql to drop the table passed.
     *
     * @param string $name The table name to drop.
     *
     * @return string
     */
    public static function dropTable($name)
    {
        if (empty($name)) {
            throw new \Exception(_('Need the table name to drop'));
        }
        return sprintf(
            'DROP TABLE IF EXISTS `%s`',
            $name
        );
    }
    /**
     * Rows this release requires to exist, seeded by identity rather than
     * by array position.
     *
     * The row-data counterpart to SchemaReconciler, and it exists for the
     * same reason -- an indexed step only ever runs for installs sitting
     * below it. vValue is a COUNT of applied elements, so a database whose
     * count already exceeds the array length is permanently "up to date"
     * and will never run another indexed step, whatever FOG_SCHEMA says. A
     * 1.5 count carried across an upgrade does exactly that, and so does a
     * value set by hand to get past a version check. Those installs are
     * structurally repaired by the reconciler and were, until this, left
     * missing any row a later step was supposed to insert.
     *
     * SchemaReconciler cannot cover it: it is declared strictly additive
     * and explicitly never touches row data, which is what makes a stale
     * manifest harmless there. This is the narrow, explicit exception --
     * rows keyed by a natural identity, inserted only when absent, never
     * updated and never deleted. An entry that is already present is left
     * exactly as the administrator has it.
     *
     * Seeded WITHOUT a primary key value. pxeMenu.pxeID is auto_increment
     * and the table is user-writable, so hardcoding "the next free id" is
     * only correct on a pristine install -- see IpxeBootMenu::_menuOpt(), which
     * matches these rows by name for the same reason.
     *
     * @return array Table => ['key' => identity column, 'rows' => name => cols]
     */
    private static function _requiredRows()
    {
        // name => column => value. Identity is the name.
        //
        // Every NOT NULL column without a default has to be listed, including
        // pxeHotKeyEnable and pxeKeySequence -- both added by later ALTERs
        // (see commons/schema.php) and so absent from the original CREATE
        // TABLE. The seeding steps that came before could omit them because
        // INSERT IGNORE downgrades error 1364 ("field doesn't have a default
        // value") to a warning and substitutes an implicit default; a plain
        // INSERT does not, and fails outright. Matching the values every
        // existing row already carries: hotkeys off, empty sequence.
        return [
            'pxeMenu' => [
                'key' => 'pxeName',
                'rows' => [
                    'fog.enrollsecureboot' => [
                        'pxeDesc' => 'Enroll Secure Boot Key (MOK attended setup)',
                        'pxeParams' => '',
                        'pxeDefault' => 0,
                        'pxeRegOnly' => 2,
                        'pxeArgs' => null,
                        'pxeHotKeyEnable' => '0',
                        'pxeKeySequence' => '',
                    ],
                    'fog.enrollsecurebootunattended' => [
                        'pxeDesc' => 'Enroll Secure Boot Key (Unattended - '
                            . 'secure boot in setup mode required)',
                        'pxeParams' => '',
                        'pxeDefault' => 0,
                        'pxeRegOnly' => 2,
                        'pxeArgs' => 'mode=enrollsb',
                        'pxeHotKeyEnable' => '0',
                        'pxeKeySequence' => '',
                    ],
                ],
            ],
        ];
    }
    /**
     * Is any required row missing?
     *
     * Exists so SchemaUpdaterPage can tell "up to date" apart from "nothing
     * INDEXED left to do, but rows are still missing".
     *
     * That distinction is load-bearing. The updater page redirects away
     * whenever vValue >= FOG_SCHEMA, and the installer's own deploy POSTs to
     * that same page -- so on a database sitting at or above the constant,
     * seedRequiredRows() could never be reached, which is precisely the state
     * it was written to repair. Found by deleting a seeded row on a server at
     * vValue == FOG_SCHEMA and watching the updater redirect instead of
     * restoring it.
     *
     * Costs one COUNT per required row, and only on the schema page -- never
     * on a normal request, which is why the equivalent gate in
     * DatabaseManager::init() is deliberately left alone.
     *
     * @return bool True when at least one required row is absent.
     */
    public static function requiredRowsMissing()
    {
        foreach (self::_requiredRows() as $table => $spec) {
            foreach ((array)$spec['rows'] as $name => $values) {
                $sql = sprintf(
                    'SELECT COUNT(*) AS `cnt` FROM `%s` WHERE `%s` = %s',
                    $table,
                    $spec['key'],
                    self::$DB->escape($name)
                );
                $res = self::$DB->query($sql);
                if (false !== $res->error) {
                    // Unreadable means unknown. Saying "missing" would strand
                    // an admin on the updater page for a probe that cannot run.
                    continue;
                }
                $row = $res->fetch(\PDO::FETCH_ASSOC)->get();
                if (is_array($row) && isset($row['cnt']) && (int)$row['cnt'] < 1) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Inserts any required row that is absent. See _requiredRows().
     *
     * @return int|string Rows inserted, or an error string on failure.
     */
    public static function seedRequiredRows()
    {
        $required = self::_requiredRows();
        $inserted = 0;
        foreach ($required as $table => $spec) {
            foreach ((array)$spec['rows'] as $name => $values) {
                $sql = sprintf(
                    'SELECT COUNT(*) AS `cnt` FROM `%s` WHERE `%s` = %s',
                    $table,
                    $spec['key'],
                    self::$DB->escape($name)
                );
                $res = self::$DB->query($sql);
                if (false !== $res->error) {
                    return $res->error;
                }
                $row = $res->fetch(\PDO::FETCH_ASSOC)->get();
                // Unreadable count is not "absent" -- inserting on a failed
                // probe is how you end up with duplicates. Skip instead.
                if (!is_array($row) || !isset($row['cnt'])) {
                    continue;
                }
                if ((int)$row['cnt'] > 0) {
                    continue;
                }
                $cols = array_merge([$spec['key']], array_keys($values));
                $vals = array_merge([$name], array_values($values));
                $sql = sprintf(
                    'INSERT INTO `%s` (`%s`) VALUES (%s)',
                    $table,
                    implode('`,`', $cols),
                    implode(
                        ',',
                        array_map(
                            function ($v) {
                                return null === $v
                                    ? 'NULL'
                                    : self::$DB->escape($v);
                            },
                            $vals
                        )
                    )
                );
                $res = self::$DB->query($sql);
                if (false !== $res->error) {
                    return $res->error;
                }
                ++$inserted;
            }
        }
        return $inserted;
    }
}
