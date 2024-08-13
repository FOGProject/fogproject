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
            self::$sanitizeItems = function (&$val, $key) {
                if (is_string($val)) {
                    $val = filter_var($val, FILTER_SANITIZE_STRING);
                }
                if (is_array($val)) {
                    array_walk($val, self::$sanitizeItems);
                }
            };
        }
    }

    public function __construct()
    {
        self::setSanitize();
        $useragent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING);
        define('DS', DIRECTORY_SEPARATOR);
        define('BASEPATH', self::_determineBasePath());

        $regext = '#^.*\.(report|event|class|hook|page)\.php$#';
        $paths = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(BASEPATH, FileSystemIterator::SKIP_DOTS)
            ),
            $regext,
            RegexIterator::GET_MATCH
        );

        $allpaths = array_map('dirname', array_column(iterator_to_array($paths), 0));
        set_include_path(implode(PATH_SEPARATOR, $allpaths) . PATH_SEPARATOR . get_include_path());
        spl_autoload_extensions('.class.php,.page.php,.event.php,.hook.php,.report.php');
        spl_autoload_register();

        if ($useragent && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function language(string $lang = 'en'): void
    {
        $validLangs = ['de' => 'DE', 'en' => 'US', 'es' => 'ES', 'eu' => 'ES', 'fr' => 'FR', 'it' => 'IT', 'pt' => 'BR', 'zh' => 'CN'];
        $lang = array_key_exists($lang, $validLangs) ? $lang : 'en';
        if (session_status() !== PHP_SESSION_NONE) {
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

    public static function startInit(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        new self;
        self::_verCheck();
        self::_extCheck();
        $globalVars = ['newService', 'json', 'node', 'sub', 'printertype', 'id', 'groupid', 'sub', 'crit', 'sort', 'confirm', 'tab', 'type'];
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
        return preg_replace($search, $replace, $buffer);
    }
}
