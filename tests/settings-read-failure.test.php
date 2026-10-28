<?php
/**
 * A failed settings read must not be iterated as though it were rows.
 *
 * PDODB::get() answers false when a read fails, not an empty set, and
 * (array) false is [false]. getSetting() and _loadAllSettings() cast and
 * iterated it, so every failed read wrote two "Trying to access array offset
 * on false" warnings. A daemon supervisor reads settings on a closed link
 * after #1930, and on RC18 on a socket its child shares, so services filled
 * servicemaster.log with them (GH-1994).
 *
 * This runs the REAL FOGBase against a DB stub whose every read fails, and
 * checks: no warning, a stale value survives the failed refresh, and
 * refreshSettingsCache() does not empty the cache it was meant to refresh.
 * No database.
 *
 * Usage: php tests/settings-read-failure.test.php [repo-root]
 * Exit status 0 = pass, 1 = fail.
 */

$root = $argv[1] ?? dirname(__DIR__);
$src = $root . '/packages/web/src/';
$tmp = sys_get_temp_dir() . '/fog-settings-read-' . getmypid();
@mkdir($tmp, 0700, true);

define('DS', DIRECTORY_SEPARATOR);
define('BASEPATH', $root . '/packages/web/');
define('FOG_CACHE_DIR', $tmp);

spl_autoload_register(function ($class) use ($src) {
    if (0 !== strpos($class, 'FOG\\')) {
        return;
    }
    $file = $src . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/**
 * Stands in for PDODB: answers rows while $fail is false, false after.
 */
class FailingDB
{
    public $fail = false;
    public $rows = [];
    public function query($sql)
    {
        return $this;
    }
    public function fetch($type, $fetchType)
    {
        return $this;
    }
    public function get()
    {
        return $this->fail ? false : $this->rows;
    }
}

$db = new FailingDB();
$db->rows = [['settingKey' => 'FOG_GH1994', 'settingValue' => 'kept']];
$prop = new \ReflectionProperty('FOG\Base\FOGBase', 'DB');
$prop->setAccessible(true);
$prop->setValue(null, $db);

$warnings = [];
set_error_handler(function ($no, $str, $file, $line) use (&$warnings) {
    $warnings[] = "$str at " . basename($file) . ":$line";
    return true;
});

$fails = [];
$base = 'FOG\Base\FOGBase';

// Prime the cache while the database answers.
if ('kept' !== $base::getSetting('FOG_GH1994')) {
    $fails[] = 'getSetting() did not read the stub row; the test cannot see its subject';
}

// Every entry is now stale, and every read fails.
$base::$settingsCacheTTL = 0;
$db->fail = true;

$missing = $base::getSetting('FOG_GH1994_ABSENT');
if (null !== $missing) {
    $fails[] = 'a failed read of an uncached key answered ' . var_export($missing, true);
}
if ('kept' !== $base::getSetting('FOG_GH1994')) {
    $fails[] = 'a failed read dropped the stale value it was refreshing';
}
$loaded = $base::refreshSettingsCache();
if (0 !== $loaded) {
    $fails[] = "refreshSettingsCache() reported $loaded settings from a failed read";
}
if ('kept' !== $base::getSetting('FOG_GH1994')) {
    $fails[] = 'a failed refreshSettingsCache() emptied the cache';
}
foreach ($warnings as $w) {
    $fails[] = "warning: $w";
}

restore_error_handler();
array_map('unlink', glob($tmp . '/*') ?: []);
@unlink($tmp . '/.settings_cache_flush');
@rmdir($tmp);

if ($fails) {
    foreach ($fails as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}
echo "OK: a failed settings read warns nothing and keeps the cache\n";
exit(0);
