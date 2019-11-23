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
    public static function establish()
    {
        /**
         * Certain scripts don't use the database at all
         * so we skip connecting to the DB entirely for those.
         */
        $noDBpattern = array(
            'status\/bandwidth\.php$',
            'status\/freespace\.php$',
            'status\/getfiles\.php$',
            'status\/gethash\.php$',
            'status\/getservertime\.php$',
            'status\/getsize\.php$',
            'status\/hw\.php$',
            'status\/newtoken\.php$'
        );
        $noDBpattern = '#'.implode($noDBpattern, "|").'#';
        if (preg_match($noDBpattern, self::$scriptname)) {
            return;
        }
        /**
         * If the db is already connected,
         * return immediately.
         */
        if (self::$DB) {
            return new self;
        }
        /**
         * Establish connection.
         */
        self::$DB = new PDODB();
        /**
         * Check our caller to see if it's of service
         * or status dir call.
         */
        $testscript = preg_match(
            '#/service|status/#',
            self::$scriptname
        );
        if (strtolower(self::$reqmethod) === 'post'
            && !self::getLink()
        ) {
            http_response_code(406);
        }
        /**
         * If it is, and we don't have a link and the
         * script is not using dbrunning, inform the
         * calling script that the db is unavailable.
         */
        if (($testscript
            && !self::getLink()
            && false === strpos(self::$scriptname, 'dbrunning'))
        ) {
            echo json_encode(_('A valid database connection could not be made'));
            exit(10);
        }
        /**
         * Get the version
         */
        self::_getVersion();
        /**
         * If the installed schema is greater than or equal to the
         * installed version, return immediately.
         */
        if (self::$mySchema >= FOG_SCHEMA) {
            return new self;
        }
        /**
         * The sub get caller.
         */
        global $sub;
        /**
         * Files that are okay to get
         */
        $okayFiles = array(
            'checkcredentials.php',
            'getversion.php',
            'kernelvers.php',
        );
        /**
         * The script filename
         */
        $filename = basename(self::$scriptname);
        /**
         * If the filename is okay, just perform our redirect.
         */
        if (!in_array($filename, $okayFiles)) {
            /**
             * If we are not already redirected to schema updater,
             * perform our redirect.
             */
            if (!preg_match('#schema#i', self::$querystring)) {
                self::redirect('?node=schema');
            }
        }
        /**
         * The subs we allow some form of passthru
         */
        $subs = array(
            'configure',
            'authorize',
            'requestClientInfo'
        );
        /**
         * If sub is in the passthru,
         * set the test to true.
         */
        if (in_array($sub, $subs)) {
            $test = true;
        }
        /**
         * If the test is true let people know the db
         * is unavailable for now, as the db needs an
         * update.
         */
        if (true === $test) {
            /**
             * If the caller is requiring json send
             * the data in json format.
             *
             * Otherwise just print the #!db flag.
             */
            if (self::$json) {
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
        return new self;
    }
    /**
     * Returns the DB Link object
     *
     * @return object
     */
    public static function getLink()
    {
        return self::$DB->link();
    }
    /**
     * Returns the DB object
     *
     * @return object
     */
    public static function getDB()
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
        self::$mySchema = (int)self::$DB
            ->query($query)
            ->fetch()
            ->get('vValue');
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
    public static function getColumns(
        $table_name,
        $column_name
    ) {
        $sql = sprintf(
            "SELECT COUNT(`%s`)AS`%s`FROM`%s`.`%s`WHERE`%s`='%s'%s",
            'COLUMN_NAME',
            'total',
            'information_schema',
            'COLUMNS',
            'TABLE_SCHEMA',
            self::$DB->dbName(),
            sprintf(
                str_repeat("AND`%s`='%s'", 2),
                'TABLE_NAME',
                $table_name,
                'COLUMN_NAME',
                $column_name
            )
        );
        return self::$DB
            ->query($sql)
            ->fetch()
            ->get('total');
    }
}
