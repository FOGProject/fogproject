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
         * Certain scripts don't use the database at all,
         * so we skip connecting to the DB entirely for those.
         */
        $noDBpattern = [
            'status/getservertime\.php',
            'status/newtoken\.php'
        ];
        $noDBpattern = '#' . implode('|', $noDBpattern) . '#';
        if (preg_match($noDBpattern, self::$scriptname)) {
            return new self;
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
            '#service/|status/#',
            self::$scriptname
        );
        if (strtolower(self::$reqmethod) === 'post'
            && !self::getLink()
        ) {
            http_response_code(HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
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
        $okayFiles = [
            'dbrunning.php',
            'checkcredentials.php',
            'getversion.php',
            'kernelvers.php'
        ];
        /**
         * The script filename
         */
        $filename = basename(self::$scriptname);
        /**
         * If the filename is okay, just perform our redirect.
         */
        /**
         * A login attempt must always be allowed to complete. While the schema
         * is out of date every other request is sent to the schema updater, so
         * hijacking the login route as well leaves an established install with
         * no way to authenticate -- and therefore no way to reach the admin
         * credential the update now requires. The POST is an XHR expecting
         * JSON, so a 302 does not merely inconvenience it: jQuery follows the
         * redirect as a GET, the credentials are discarded, and the user loops
         * back to the schema page forever.
         */
        $isLoginPost = (self::$reqmethod === 'POST'
            && filter_input(INPUT_GET, 'sub') === 'login');
        /**
         * Logout must complete for the same reason, from the other side. The
         * session is destroyed in management/index.php, which never runs
         * because establish() redirects during boot -- so the Logout link on
         * a stale-schema install did nothing, and whoever was signed in could
         * not get back to the login form to sign in as someone who can apply
         * the update. The idle-timeout redirect in User::isLoggedIn() lands on
         * the same node and looped for the same reason.
         *
         * Nothing is granted by letting this through: logout() only clears
         * cookies and session state, touches no table, and the redirect that
         * follows it comes straight back here unauthenticated.
         */
        $isLogout = (filter_input(INPUT_GET, 'node') === 'logout');
        if (!in_array($filename, $okayFiles) && !$isLoginPost && !$isLogout) {
            /**
             * If we are not already redirected to schema updater,
             * perform our redirect.
             */
            if (!preg_match('#schema#i', self::$querystring)) {
                self::redirect('../management/index.php?node=schema');
            }
        }
        /**
         * The subs we allow some form of passthru
         */
        $subs = [
            'configure',
            'authorize',
            'requestClientInfo'
        ];
        /**
         * If sub is in the passthru, let people know the db
         * is unavailable for now, as the db needs an update.
         */
        if (in_array($sub, $subs)) {
            /**
             * If the caller is requiring json send
             * the data in json format.
             *
             * Otherwise, just print the #!db flag.
             */
            if (self::$json) {
                die(
                    json_encode(
                        ['error' => 'db']
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
        return self::$DB ?: new PDODB();
    }
    /**
     * Gets the schema version as stored in the DB.
     *
     * @return void
     */
    private static function _getVersion()
    {
        self::_convertEngine();
        $query = sprintf(
            'SELECT `vValue` FROM `%s`.`schemaVersion`',
            self::$DB->dbName()
        );
        self::$mySchema = (int)self::$DB
            ->query($query)
            ->fetch()
            ->get('vValue');
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
        string $table_name,
        string $column_name
    ): int {
        $sql = sprintf(
            "SELECT COUNT(`COLUMN_NAME`) AS `total`"
            . " FROM `information_schema`.`COLUMNS`"
            . " WHERE `TABLE_SCHEMA` = %s"
            . " AND `TABLE_NAME` = %s"
            . " AND `COLUMN_NAME` = %s",
            self::$DB->escape(self::$DB->dbName()),
            self::$DB->escape($table_name),
            self::$DB->escape($column_name)
        );
        return (int) self::$DB
            ->query($sql)
            ->fetch()
            ->get('total');
    }
    /**
     * Converts myisam to innodb
     *
     * @return void
     */
    private static function _convertEngine()
    {
        $sql_mode = "SELECT @@GLOBAL.sql_mode sql_mode";
        $sql_modeo = self::$DB->query($sql_mode)->fetch()->get('sql_mode');
        $sql_modes = false;
        if (false === stripos($sql_modeo, 'NO_ENGINE_SUBSTITUTION')) {
            $sql_modes = "SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION'";
            self::$DB->query($sql_modes);
        }
        $sql = sprintf(
            "SELECT CONCAT('ALTER TABLE ',TABLE_SCHEMA,'.',TABLE_NAME,' ENGINE=InnoDB') AS Q"
            . " FROM INFORMATION_SCHEMA.TABLES"
            . " WHERE ENGINE='MyISAM'"
            . " AND TABLE_SCHEMA = %s",
            self::$DB->escape(self::$DB->dbName())
        );
        $convert = self::$DB
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get('Q');
        if (false !== $sql_modes) {
            $sql_modes = sprintf(
                "SET GLOBAL sql_mode = %s",
                self::$DB->escape($sql_modeo)
            );
            self::$DB->query($sql_modes);
        }
        if (!count($convert ?: [])) {
            return;
        }
        foreach ($convert as $q) {
            self::$DB->query($q);
        }
    }
}
