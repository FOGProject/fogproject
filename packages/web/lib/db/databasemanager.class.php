<?php
/**
 * Database Manager Handles communication from fog to db class.
 *
 * PHP version 5
 *
 * This is what communicates with fog to the db class.
 *
 * @category DatabaseManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Database Manager Handles communication from fog to db class.
 *
 * @category DatabaseManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DatabaseManager extends FOGCore
{
    /**
     * Initiate the connection to the database.
     *
     * @return object
     */
    public function establish()
    {
        if (self::$DB) {
            return $this;
        }
        self::$DB = new PDODB();
        self::_getVersion();
        $test = preg_match('#/service|status/#', self::$scriptname);
        if (($test
            && !is_object(self::$DB->getLink())
            && false === strpos(self::$scriptname, 'dbrunning'))
        ) {
            echo json_encode(_('A valid database connection could not be made'));
            exit;
        }
        if (self::$mySchema < FOG_SCHEMA) {
            global $sub;
            $okayFiles = array(
                'checkcredentials.php',
                'getversion.php',
            );
            $filename = basename(self::$scriptname);
            if (!in_array($filename, $okayFiles)) {
                $subs = array(
                    'configure',
                    'authorize',
                    'requestClientInfo'
                );
                if (!$test && in_array($sub, $subs)) {
                    $test = true;
                }
                if ($test) {
                    if (isset($_REQUEST['json'])) {
                        die(
                            json_encode(
                                array(
                                    'error' => 'db'
                                )
                            )
                        );
                    } else {
                        die('#!db');
                    }
                }
            }
            if (!preg_match('#schema#i', self::$querystring)) {
                $this->redirect('?node=schema');
            }
        }
        return $this;
    }
    /**
     * Returns the DB Link object
     *
     * @return object
     */
    public function getLink()
    {
        return self::$DB->link();
    }
    /**
     * Returns the DB object
     *
     * @return object
     */
    public function getDB()
    {
        return self::$DB;
    }
    /**
     * Get's the schema version as stored in the DB.
     *
     * @return int
     */
    private static function _getVersion()
    {
        $query = sprintf(
            'SELECT `vValue` FROM `%s`.`schemaVersion`',
            self::$DB->dbName()
        );
        self::$mySchema = self::$DB->query($query)->fetch()->get('vValue');
        return self::$mySchema;
    }
    /**
     * Get columns from table testing for a specific column name
     *
     * @param string $table_name  the table to search
     * @param string $column_name the column to search
     *
     * @return int
     */
    public function getColumns($table_name, $column_name)
    {
        $sql = "SELECT COUNT(`COLUMN_NAME`) AS `total` "
            . "FROM `information_schema`.`COLUMNS` WHERE "
            . "`TABLE_SCHEMA`='".DATABASE_NAME."' AND "
            . "`TABLE_NAME`='$table_name' AND "
            . "`COLUMN_NAME`='$column_name'";
        return self::$DB->query($sql)->fetch()->get('total');
    }
}
