<?php
/**
 * Database Manager Handles communication from fog to db class.
 *
 * PHP version 7.4+
 *
 * This is what communicates with fog to the db class.
 *
 * @category DatabaseManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Db;

use FOG\Base\FOGCore;
use FOG\Router\HTTPResponseCodes;

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
     * Memoized column lists, keyed by lowercased table name.
     *
     * Populated by tableColumns(). Per-request only.
     *
     * @var array
     */
    private static $_tableColumns = [];
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
         * Legacy MyISAM -> InnoDB conversion, on the schema install/upgrade
         * path only.
         *
         * This used to run from _getVersion(), i.e. on EVERY request: a
         * `SELECT @@GLOBAL.sql_mode` plus an INFORMATION_SCHEMA.TABLES scan
         * filtered on ENGINE, whose result an up-to-date install then threw
         * away at the check above. It is a migration concern -- the only way a
         * MyISAM table realistically appears is a legacy install being brought
         * forward, which is exactly this path -- so it belongs after the
         * up-to-date early return, not before it.
         *
         * Tradeoff accepted: a MyISAM table introduced by hand (or by
         * restoring an old dump) onto an already-current install is no longer
         * converted by the next page load; it is converted at the next schema
         * update. Two queries per request, on every entry point, was too high
         * a standing price for that case.
         */
        self::_convertEngine();
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
            'kernelvers.php',
            /**
             * The installer's pre-upgrade database dump, and it has to be
             * here or it is never taken when it matters.
             *
             * Everything not on this list is sent to the schema updater
             * while the schema is out of date -- which is precisely the
             * state the dump exists to let you roll back OUT of. So the one
             * situation where you most want a backup was the one situation
             * guaranteed not to produce one. GH-1147 is what that looks like
             * in practice: a reconcile failed, which stopped the schema
             * version being recorded, which bounced this endpoint, and the
             * installer asked the admin to press Enter to continue an
             * upgrade with no dump taken.
             *
             * Safe to run against a schema that is not current. Mysqldump
             * reads whatever structure is actually there; nothing in this
             * path needs the schema to match FOG_SCHEMA, and a dump of a
             * half-migrated database is still exactly the dump you want.
             *
             * Not a widening of what is reachable. backup_db.php gates
             * itself on the request being same-machine, and the whole
             * maintenance/ directory is additionally gated by the web
             * server (`Require local` for apache, and the matching nginx
             * location), so this changes only whether a request that has
             * ALREADY passed both of those is redirected away.
             */
            'backup_db.php'
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
        $requripath = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');
        /**
         * fog-agent's API must never be redirected. It speaks JSON over a
         * client certificate and has no browser to land anywhere, so the
         * redirect below reaches it as an answer it cannot use -- and the
         * redirect is RELATIVE ('../management/index.php?node=schema'), so a
         * client that follows redirects resolves it against
         * <webroot>/agent/v1/ and requests <webroot>/agent/management/
         * index.php, which does not exist. Observed in the lab on
         * 2026-09-04: every agent in the estate logged
         *
         *     poll: HTTP 404, body is not JSON: File not found.
         *
         * once per poll interval for the whole upgrade window, and nothing
         * in that sentence names a schema update.
         *
         * So say so instead. 503 with a reason the agent prints, and no
         * Retry-After games: the agent already has a poll interval and will
         * be back. Nothing is granted here -- this answers LESS than the
         * redirect did, and it answers it before any route matches, so no
         * handler and no table is reached.
         *
         * Matched on the segment rather than the configured webroot because
         * FOG_WEB_ROOT is a globalSettings lookup and the schema is, by
         * definition here, not current. Route::AGENT_ROUTE_SEGMENT is the
         * one definition both places use.
         */
        $isAgentApi = false !== strpos(
            $requripath,
            '/' . \FOG\Router\Route::AGENT_ROUTE_SEGMENT
        );
        if ($isAgentApi) {
            header('Content-type: application/json');
            http_response_code(HTTPResponseCodes::HTTP_SERVICE_UNAVAILABLE);
            die(
                json_encode(
                    [
                        'status' => 'schema_update_pending',
                        'error' => 'FOG is applying a database schema update;'
                            . ' the agent API resumes when it finishes',
                    ]
                )
            );
        }
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
     * Every column name that actually exists on a table, lowercased.
     *
     * getColumns() costs a query per column, which is far too much to ask per
     * field. This is one query per table, memoised for the request, so the
     * schema-drift filter in FOGManagerController can afford to run on every
     * read.
     *
     * Returns an empty array when the table is absent or unreadable. Callers
     * must treat "empty" as "don't know" and filter nothing -- an unreadable
     * information_schema must never look like "this table has no columns",
     * which would strip every field off the model.
     *
     * @param string $table_name The table to describe.
     *
     * @return array
     */
    public static function tableColumns(string $table_name): array
    {
        $key = strtolower($table_name);
        if (array_key_exists($key, self::$_tableColumns)) {
            return self::$_tableColumns[$key];
        }
        self::$_tableColumns[$key] = [];
        if (!self::$DB || !self::getLink()) {
            return [];
        }
        $sql = sprintf(
            "SELECT `COLUMN_NAME`"
            . " FROM `information_schema`.`COLUMNS`"
            . " WHERE `TABLE_SCHEMA` = %s"
            . " AND `TABLE_NAME` = %s",
            self::$DB->escape(self::$DB->dbName()),
            self::$DB->escape($table_name)
        );
        $res = self::$DB->query($sql);
        if (false !== $res->error) {
            return [];
        }
        $cols = [];
        $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get('COLUMN_NAME');
        foreach ((array)$rows as $col) {
            if (is_string($col) && $col !== '') {
                $cols[] = strtolower($col);
            }
        }
        self::$_tableColumns[$key] = $cols;
        return $cols;
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
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
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
        /*
         * ALTER TABLE ... ENGINE=InnoDB rebuilds the whole table, and on a
         * large taskLog or hosts that legitimately runs for minutes with
         * the server sending nothing back. PDODB puts a 300s ceiling on reads
         * so a wedged MySQL cannot hang a worker forever (#944) -- that
         * ceiling is right for a request and wrong here, where tripping it
         * would abandon a schema upgrade midway through rebuilding tables.
         * Lift it for the duration and put back exactly what was there.
         */
        $readTimeout = ini_get('mysqlnd.net_read_timeout');
        ini_set('mysqlnd.net_read_timeout', '86400');
        try {
            foreach ($convert as $q) {
                self::$DB->query($q);
            }
        } finally {
            ini_set('mysqlnd.net_read_timeout', (string)$readTimeout);
        }
    }
}
