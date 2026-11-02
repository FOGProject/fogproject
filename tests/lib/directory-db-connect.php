<?php
/**
 * Connections to the directory DB tests' scratch database, without building
 * it. tests/lib/directory-db.php builds it and includes this file; a forked
 * worker includes this file alone, so it never resets what its parent set up.
 *
 * Environment: FOG_TEST_DSN (mysql:host=...;port=...), FOG_TEST_USER (default
 * root) and FOG_TEST_PASS (default empty). The database is always
 * fog_directory_test.
 */

require_once __DIR__ . '/fog-test-harness.php';

/** The DSN with its database replaced by the scratch one. */
function directoryTestDsn($database = 'fog_directory_test')
{
    $dsn = preg_replace('/;?dbname=[^;]*/i', '', (string)getenv('FOG_TEST_DSN'));
    return rtrim($dsn, ';') . ';dbname=' . $database;
}

/** A new connection to the scratch database. */
function directoryTestPdo()
{
    $user = getenv('FOG_TEST_USER');
    $pass = getenv('FOG_TEST_PASS');
    $pdo = new \PDO(
        directoryTestDsn(),
        false === $user ? 'root' : $user,
        false === $pass ? '' : $pass,
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
        ]
    );
    if ('fog_directory_test' !== (string)$pdo->query('SELECT DATABASE()')->fetchColumn()) {
        throw new \RuntimeException('directory DB tests only run in fog_directory_test');
    }
    return $pdo;
}

/**
 * Points the application's own PDODB at $pdo, so code that reaches the
 * database through FOGBase::$DB (Route::getList() and the like) uses it.
 */
function directoryTestBindPdodb(\PDO $pdo)
{
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', 'fog_directory_test');
    }
    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    // PDODB::query() refuses to run until it knows its database name.
    $name = new \ReflectionProperty(FOG\Db\PDODB::class, '_dbName');
    $name->setAccessible(true);
    $name->setValue(null, 'fog_directory_test');
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
}
