<?php
declare(strict_types=1);

/**
 * Initiator and FOG Autoloader
 *
 * Establishes the FOG GUI and system autoloader functionality while ensuring
 * input sanitization and system initialization for performance and security.
 *
 * @category Initiator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Initiator
{
    private static $sanitizeItems;

    private static function setSanitize(): void
    {
        if (!self::$sanitizeItems) {
            self::$sanitizeItems = function (&$val, $key): void {
                if (is_array($val)) {
                    array_walk($val, self::$sanitizeItems);
                    return;
                }

                if (is_string($val)) {
                    $val = trim(str_replace("\0", '', $val));
                }
            };
        }
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }

    public function __construct()
    {
        // Use cookies only, no URL-based sessions
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        // 'secure' is intentionally false so the same session cookie is valid
        // over both HTTP and HTTPS. If you want HTTPS-only, set this to true
        // and add a hard HTTP→HTTPS redirect at the web-server level so the
        // PHP session path is never reached over plain HTTP.
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',            // default current host
            'secure'   => false,         // false = dual HTTP+HTTPS support
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        self::setSanitize();
        define('DS', DIRECTORY_SEPARATOR);
        define('BASEPATH', self::_determineBasePath());

        // The boot file-list cache (classFileList) needs the cache dir before
        // System.class.php loads. Define the constants here, guarded, so System
        // (the canonical, documented home for FOG_BASE_DIR) defers to these.
        if (!defined('FOG_BASE_DIR')) {
            define('FOG_BASE_DIR', '/opt/fog');
        }
        if (!defined('FOG_CACHE_DIR')) {
            define('FOG_CACHE_DIR', FOG_BASE_DIR . DS . 'cache');
        }

        $allpaths = array_unique(array_map('dirname', self::classFileList()));
        set_include_path(implode(PATH_SEPARATOR, $allpaths) . PATH_SEPARATOR . get_include_path());
        spl_autoload_extensions('.class.php,.page.php,.event.php,.hook.php,.report.php');
        spl_autoload_register();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function language(string $lang = 'en'): void
    {
        $validLangs = ['de' => 'DE', 'en' => 'US', 'es' => 'ES', 'eu' => 'ES', 'fr' => 'FR', 'it' => 'IT', 'pt' => 'BR', 'zh' => 'CN'];
        $lang = array_key_exists($lang, $validLangs) ? $lang : 'en';
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['FOG_LANG'] = $lang;
        }
        $lang = "{$lang}_{$validLangs[$lang]}";
        $domain = 'messages';
        $apppath = realpath(__DIR__ . '/../management/languages');
        setlocale(LC_MESSAGES, $lang . ".UTF-8");
        bindtextdomain($domain, $apppath);
        textdomain($domain);
    }

    private static function _determineBasePath(): string
    {
        return dirname(__DIR__) . DS;
    }

    /** Seconds a persisted file-list cache is trusted before it is rebuilt. */
    private const FILELIST_TTL = 300;

    /** In-process memo of the class-source file list. */
    private static ?array $fileList = null;

    /**
     * Every autoloadable source file under BASEPATH (*.class.php, *.page.php,
     * *.event.php, *.hook.php, *.report.php).
     *
     * The recursive directory walk this requires is the most expensive part of
     * each request's bootstrap (it stats the whole tree) and was previously run
     * up to three times per request (here plus each HookManager/EventManager
     * scan). It is now done once: memoised for the request and persisted to a
     * short-lived JSON cache so sibling requests skip the walk entirely. The
     * cache self-heals — rebuilt whenever it is missing, older than
     * FILELIST_TTL, or fails validation — so newly deployed classes appear
     * within the TTL with no manual flush.
     *
     * @return string[] Absolute file paths.
     */
    public static function classFileList(): array
    {
        if (self::$fileList !== null) {
            return self::$fileList;
        }
        $cacheFile = FOG_CACHE_DIR . DS . 'filelist.' . md5(BASEPATH) . '.json';
        $cached = self::_readFileListCache($cacheFile);
        if ($cached !== null) {
            return self::$fileList = $cached;
        }
        $files = self::_scanClassFiles();
        self::_writeFileListCache($cacheFile, $files);
        return self::$fileList = $files;
    }

    private static function _scanClassFiles(): array
    {
        $regext = '#^.*\.(report|event|class|hook|page)\.php$#';
        $paths = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(BASEPATH, FileSystemIterator::SKIP_DOTS)
            ),
            $regext,
            RegexIterator::GET_MATCH
        );
        return array_column(iterator_to_array($paths), 0);
    }

    private static function _readFileListCache(string $file): ?array
    {
        clearstatcache(true, $file);
        if (!is_file($file)
            || (time() - (int) filemtime($file)) > self::FILELIST_TTL
        ) {
            return null;
        }
        $json = @file_get_contents($file);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || $data === []) {
            return null;
        }
        // FOG_CACHE_DIR is world-writable (sticky). Reject any list pointing
        // outside the code tree so a poisoned cache can never feed foreign
        // paths to the autoloader; in-tree-but-stale is healed by the TTL.
        foreach ($data as $path) {
            if (!is_string($path)
                || strncmp($path, BASEPATH, strlen(BASEPATH)) !== 0
            ) {
                return null;
            }
        }
        return $data;
    }

    private static function _writeFileListCache(string $file, array $files): void
    {
        $json = json_encode(array_values($files));
        if ($json === false) {
            return;
        }
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    public static function startInit(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        new self;
        self::sanitizeItems();
        self::_verCheck();
        self::_extCheck();
        $globalVars = ['newService', 'json', 'node', 'sub', 'printertype', 'id', 'groupid', 'crit', 'sort', 'confirm', 'tab', 'type'];
        foreach ($globalVars as $var) {
            global $$var;
            $$var = filter_input(INPUT_GET, $var) ?? filter_input(INPUT_POST, $var);
        }
        new System();
        new Config();
        self::language($_SESSION['FOG_LANG'] ?? 'en');
    }

    public static function sanitizeItems(&$value = '')
    {
        self::setSanitize();
        $process = [&$_GET, &$_POST, &$_COOKIE, &$_SESSION];
        array_walk($process, self::$sanitizeItems);
        return $value;
    }

    private static function _verCheck(): void
    {
        if (version_compare(phpversion(), '7.4', '<')) {
            throw new Exception('FOG Requires PHP v7.4 or higher. You have PHP v' . phpversion());
        }
    }

    private static function _extCheck(): void
    {
        $requiredExtensions = ['gettext', 'mysqli'];
        $loadedExtensions = get_loaded_extensions();
        if (count(array_intersect($requiredExtensions, $loadedExtensions)) < count($requiredExtensions)) {
            throw new Exception(_('Missing one or more extensions.'));
        }
    }

    public static function sanitizeOutput(string $buffer): string
    {
        $search = ['/>\s+</', '/(\s)+/'];
        $replace = ['> <', '\\1'];
        return preg_replace($search, $replace, $buffer) ?? $buffer;
    }
}
