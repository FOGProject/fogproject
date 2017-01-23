<?php
/**
 * Handles the database insert/export
 *
 * PHP version 5
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
    protected $databaseFields = array(
        'id' => 'vID',
        'version' => 'vValue',
    );
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
     * @param string $dbname      the database name
     * @param string $table       the table name
     * @param bool   $indexNeeded index is needed
     *
     * @return void
     */
    public function dropDuplicateData(
        $dbname,
        $table = array(),
        $indexNeeded = false
    ) {
        if (empty($dbname)) {
            return;
        }
        if (count($table) < 1) {
            return;
        }
        $queries = array();
        $tablename = $table[0];
        $indexes = (array)$table[1];
        $dropIndex = $table[2];
        if ($indexNeeded) {
            if (count($indexes) < 1) {
                return;
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
        $file = '/tmp/fog_backup_tmp.sql';
        if (!$backup_name) {
            $backup_name = sprintf(
                'fog_backup_%s.sql',
                self::formatTime('', 'Ymd_His')
            );
        }
        $dump = self::getClass('Mysqldump');
        $dump->start($file);
        if (!file_exists($file) || !is_readable($file)) {
            throw new Exception(_('Could not read tmp file.'));
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
            ini_set('request_terminate_timeout', $orig_term_time);
            unlink($file);
            return;
        }
        set_time_limit($orig_exec_time);
        return $file;
    }
    /**
     * Imports the database and updates the db.
     *
     * @param string $file The filename to import from.
     *
     * @return string|bool
     */
    public function importdb($file)
    {
        $orig_exec_time = ini_get('max_execution_time');
        set_time_limit(0);
        if (false === ($fh = fopen($file, 'rb'))) {
            throw new Exception(_('Error Opening DB File'));
        }
        while (($line = fgets($fh)) !== false) {
            if (substr($line, 0, 2) == '--' || $line == '') {
                continue;
            }
            $tmpline .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                if (false === self::$DB->query($tmpline)) {
                    $error .= sprintf(
                        "%s '<strong>%s': %s</strong><br/><br/>",
                        _('Error performing query'),
                        $line,
                        self::$DB->sqlerror()
                    );
                }
                $tmpline = '';
            }
        }
        fclose($fh);
        set_time_limit($orig_exec_time);
        if ($error) {
            return $error;
        }
        return true;
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
            throw new Exception(_('Exists item must be boolean'));
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
        $engine = 'MyISAM',
        $charset = 'utf8',
        $prime = '',
        $autoin = ''
    ) {
        if (empty($name)) {
            throw new Exception(_('Must have a name to create the table'));
        }
        $fieldCount = count($fields);
        $typeCount = count($types);
        if ($fieldCount !== $typeCount) {
            throw new Exception(_('Fields and types must have equal count'));
        }
        if (empty($engine)) {
            $engine = 'MyISAM';
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
                    $default[$i] ?
                    sprintf(
                        ' DEFAULT %s',
                        $default[$i]
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
     * The sql to drop the table passed.
     *
     * @param string $name The table name to drop.
     *
     * @return string
     */
    public static function dropTable($name)
    {
        if (empty($name)) {
            throw new Exception(_('Need the table name to drop'));
        }
        return sprintf(
            'DROP TABLE IF EXISTS `%s`',
            $name
        );
    }
}
