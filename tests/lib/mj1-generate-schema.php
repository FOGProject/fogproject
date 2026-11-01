<?php
// Creates a disposable reference database for the upstream schema generator.
if (getenv('MJ1_DB_ALLOW_RESET') !== '1') { throw new \RuntimeException('scratch DB opt-in required'); }
$dsn = getenv('MJ1_DB_DSN');
if (!is_string($dsn) || strpos($dsn, 'mysql:') !== 0) { throw new \RuntimeException('a MySQL scratch DSN is required'); }
$dsnParts = [];
foreach (explode(';', substr($dsn, strlen('mysql:'))) as $part) {
    if ('' === $part) { continue; }
    $pair = explode('=', $part, 2);
    if (count($pair) !== 2 || '' === $pair[0]) { throw new \RuntimeException('invalid MySQL DSN component'); }
    $key = strtolower($pair[0]);
    if (!in_array($key, ['host', 'dbname', 'port', 'charset'], true) || isset($dsnParts[$key])) {
        throw new \RuntimeException('unsupported or duplicate MySQL DSN component: ' . $key);
    }
    $dsnParts[$key] = $pair[1];
}
if (empty($dsnParts['host']) || empty($dsnParts['dbname'])
    || !preg_match('/\A[A-Za-z0-9_.:-]+\z/', $dsnParts['host'])
    || !preg_match('/\Afog_mj1(?:_[A-Za-z0-9_]+)?\z/', $dsnParts['dbname'])
    || (isset($dsnParts['port']) && (!ctype_digit($dsnParts['port']) || (int)$dsnParts['port'] < 1 || (int)$dsnParts['port'] > 65535))) {
    throw new \RuntimeException('DSN must identify a validated fog_mj1 scratch database and host');
}
$dbName = $dsnParts['dbname'];
$dbHost = $dsnParts['host'] . (isset($dsnParts['port']) ? ';port=' . (int)$dsnParts['port'] : '');
$dbUser = getenv('MJ1_DB_USER') ?: 'root';
$dbPassword = getenv('MJ1_DB_PASSWORD') ?: '';
$pdo = new \PDO($dsn, $dbUser, $dbPassword, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $dbName) { throw new \RuntimeException('connected database does not match the validated scratch DSN'); }
$manifest = require dirname(__DIR__, 2) . '/packages/web/commons/schema-expected.php';
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table => $definition) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    if ('directoryPlacementIntent' === $table) {
        // Schema 447 creates this table; leave it absent so the collected
        // migration, not the current manifest, supplies the reference shape.
        continue;
    }
    $create = $definition['create'];
    if ('hostDirectory' === $table) {
        // Build the official 446 shape from the current manifest so schema
        // 447's two ALTER statements add these fields exactly once.
        $create = preg_replace(
            '/,\s*`hdRejoin(?:Config|State)`\s+text\s+(?:NULL\s+)?DEFAULT NULL/i',
            '',
            $create,
            -1,
            $removed
        );
        if (2 !== $removed
            || false !== strpos($create, '`hdRejoinConfig`')
            || false !== strpos($create, '`hdRejoinState`')) {
            throw new \RuntimeException('current manifest hostDirectory must contain exactly the two schema 447 fields');
        }
    }
    $pdo->exec($create);
}
$sql = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/mj1-schema-sql.php') . ' ' . escapeshellarg($dbName));
$ddl447 = is_string($sql) ? json_decode($sql, true) : null;
if (!is_array($ddl447) || array_keys($ddl447) !== [0, 1, 2]
    || strpos($ddl447[0] ?? '', 'ALTER TABLE `hostDirectory` ADD COLUMN `hdRejoinConfig`') !== 0
    || strpos($ddl447[1] ?? '', 'ALTER TABLE `hostDirectory` ADD COLUMN `hdRejoinState`') !== 0
    || strpos($ddl447[2] ?? '', 'CREATE TABLE IF NOT EXISTS `directoryPlacementIntent`') !== 0) {
    throw new \RuntimeException('schema helper must return the actual collected 447 DDL array in order');
}
foreach ($ddl447 as $statement) {
    $pdo->exec($statement);
}
$actualIntentColumns = $pdo->query('SHOW COLUMNS FROM directoryPlacementIntent')->fetchAll(\PDO::FETCH_COLUMN);
if (array_diff(array_keys($manifest['tables']['directoryPlacementIntent']['columns']), $actualIntentColumns)) {
    throw new \RuntimeException('schema 447 intent table differs from schema-expected.php');
}
$config = "<?php\nclass Config\n{\n    public function __construct()\n    {\n"
    . "        define('DATABASE_HOST', " . var_export($dbHost, true) . ");\n"
    . "        define('DATABASE_NAME', " . var_export($dbName, true) . ");\n"
    . "        define('DATABASE_USERNAME', " . var_export($dbUser, true) . ");\n"
    . "        define('DATABASE_PASSWORD', " . var_export($dbPassword, true) . ");\n"
    . "    }\n}\n";
$configPath = dirname(__DIR__, 2) . '/packages/web/commons/config.class.php';
if (false === file_put_contents($configPath, $config) || !chmod($configPath, 0600)) {
    throw new \RuntimeException('could not write the scratch database config with mode 0600');
}
echo "PASS: scratch reference schema prepared from official schema 447 statements\n";
