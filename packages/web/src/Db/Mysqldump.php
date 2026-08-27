<?php
/**
 * Mysqldump, FOG's database dumper.
 *
 * PHP version 7.4+
 *
 * A thin subclass of ifsnop/mysqldump-php that supplies FOG's own
 * connection details. Everything that produces the dump is upstream's.
 *
 * @category Mysqldump
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Db;

/**
 * Mysqldump, FOG's database dumper.
 *
 * Until 1.6.0 this file WAS ifsnop/mysqldump-php -- 2388 lines of it,
 * hand-copied at v2.12 with the namespace commented out so FOG's filename
 * autoloader could find the class. It carried no version, no lockfile and
 * no upgrade path, so the only way to learn that upstream had fixed
 * something was for somebody to go and look. Composer arrived in Phase 0
 * (ADR 0013) to end exactly that, and this is the first of the two
 * libraries it retires.
 *
 * Comparing the old file against its upstream tag found precisely one
 * substantive local change in 2388 lines -- the connection details below.
 * The rest was php-cs-fixer whitespace and the commented-out namespace, so
 * the swap is a no-op for the dump itself and picks up v2.13:
 *
 *  - PDO::MYSQL_ATTR_USE_BUFFERED_QUERY is read through Pdo\Mysql when
 *    that class exists, which is what stops PHP 8.4 emitting a
 *    deprecation on every backup;
 *  - listValues() buffers a row batch and writes it whole. The old code
 *    tracked its running length from the return of a write it then reset,
 *    so net_buffer_length was not measuring what it thought;
 *  - restore() no longer trim()s each line, which was corrupting
 *    statements whose own whitespace mattered.
 *
 * Why a subclass rather than teaching the three call sites to build one:
 * they say getClass('Mysqldump')->start($file) and nothing else, so the
 * connection details would have to be repeated at each of them, and a
 * backup taken from the configuration page could then disagree with one
 * taken by the schema updater. One place, as before.
 *
 * @category Mysqldump
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Mysqldump extends \Ifsnop\Mysqldump\Mysqldump
{
    /**
     * Initialize with FOG's database connection.
     *
     * The parameters are upstream's and are kept so a caller that wants a
     * different database or different dump settings can still say so. What
     * FOG supplies is only the default: an empty $dsn means "this FOG
     * server's database", which is what all three call sites want.
     *
     * @param string $dsn          the connection string, '' for FOG's own
     * @param string $user         the user, '' for FOG's own
     * @param string $pass         the password, '' for FOG's own
     * @param array  $dumpSettings upstream dump settings
     * @param array  $pdoSettings  upstream PDO settings
     */
    public function __construct(
        $dsn = '',
        $user = '',
        $pass = '',
        $dumpSettings = [],
        $pdoSettings = []
    ) {
        if ('' === $dsn) {
            // DATABASE_TYPE is whatever the installer wrote, and 'mysqli'
            // is a valid value there while being no kind of PDO driver.
            // DATABASE_HOST may carry PDO's 'p:' persistent-connection
            // prefix, which belongs on the connection and not in the DSN.
            $dsn = sprintf(
                '%s:host=%s;dbname=%s',
                preg_replace('#^mysqli#i', 'mysql', DATABASE_TYPE),
                preg_replace('#p:#', '', DATABASE_HOST),
                DATABASE_NAME
            );
            $user = DATABASE_USERNAME;
            $pass = DATABASE_PASSWORD;
        }
        parent::__construct($dsn, $user, $pass, $dumpSettings, $pdoSettings);
    }
}
