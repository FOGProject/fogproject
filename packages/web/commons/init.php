<?php
/**
 * Initiator and FOG Autoloader
 *
 * PHP version 5
 *
 * This file simply is the initiator.  It establishes the FOG GUI and system
 * autoloader functionality.
 *
 * This initiator also creates the sanitization and cleansing needed
 * within the GUI and main system for speed and performance.
 *
 * @category Initiator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Initiator and FOG Autoloader
 *
 * This file simply is the initiator.  It establishes the FOG GUI and system
 * autoloader functionality.
 *
 * This initiator also creates the sanitization and cleansing needed
 * within the GUI and main system for speed and performance.
 *
 * @category Initiator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Initiator
{
    /**
     * Our sanitization.
     *
     * @var callable
     */
    private static $_sanitizeItems;
    /**
     * Set sanitize items
     *
     * @return void
     */
    private static function setSanitize()
    {
        if (!self::$_sanitizeItems) {
            /**
             * Lambda to sanitize our user input data.
             *
             * @param mixed $key the key of the array.
             * @param mixed $val the value of the array.
             *
             * @return void
             */
            self::$_sanitizeItems = function (&$val, $key) use (&$value) {
                if (is_string($val)) {
                    $value[$key] = filter_var($val);
                }
                if (is_array($val)) {
                    array_walk($val, self::$_sanitizeItems);
                }
                return $value;
            };
        }
    }
    /**
     * Constructs the initiator class
     *
     * @return void
     */
    public function __construct()
    {
        self::setSanitize();
        /**
         * If user agent is passed, define the useragent
         */
        $useragent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
        /**
         * Define our base path (/var/www/, /var/www/html/, etc...)
         */
        define('DS', addslashes(DIRECTORY_SEPARATOR));
        define('BASEPATH', self::_determineBasePath());
        /**
         * Regex pattern to search for files of type.
         */
        $regext = '#^.*\.(report|event|class|hook|page)\.php$#';
        /**
         * Use our basepath and find all files that are not dots.
         */
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            BASEPATH,
            FileSystemIterator::SKIP_DOTS
        );
        /**
         * Create iterator item based on the directory iterator returns.
         */
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        /**
         * Filter our iterator using the regex applied earlier.
         */
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        /**
         * Set our iterator items into an array format.
         */
        $paths = iterator_to_array($RegexIterator);
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        /**
         * Define all paths as an array.
         */
        $allpaths = [];
        /**
         * Loop our paths from earlier storing the dirname of the element.
         */
        foreach ($paths as $element) {
            $allpaths[] = dirname($element[0]);
        }
        /**
         * Set our include paths as all paths.
         */
        set_include_path(
            sprintf(
                '%s%s%s',
                implode(
                    PATH_SEPARATOR,
                    $allpaths
                ),
                PATH_SEPARATOR,
                get_include_path()
            )
        );
        spl_autoload_extensions(
            '.class.php,.page.php,.event.php,.hook.php,.report.php'
        );
        /**
         * Pass our autoload items through our custom loader method.
         */
        spl_autoload_register();
        /**
         * If we are not a service file,
         * and we have a user agent string
         * and the Session hasn't been started,
         * Start the session.
         */
        if ($useragent
            && session_status() == PHP_SESSION_NONE
        ) {
            session_start();
        }
    }
    /**
     * Initiates language configuration
     *
     * @param string $lang
     *
     * @return void
     */
    public static function language(string $lang = 'en')
    {
        $validLangs = [
            'de' => 'DE',
            'en' => 'US',
            'es' => 'ES',
            'eu' => 'ES',
            'fr' => 'FR',
            'it' => 'IT',
            'pt' => 'BR',
            'zh' => 'CN'
        ];
        if (!in_array($lang, array_keys($validLangs))) {
            $lang = 'en';
        }

        if (PHP_SESSION_NONE != session_status()) {
            $_SESSION['FOG_LANG'] = $lang;
        }
        
        $lang = "{$lang}_$validLangs[$lang]";
        $domain = 'messages';
        $apppath = realpath(__DIR__ . '/../management/languages');

        if (defined('LC_MESSAGES')) {
            setlocale(LC_MESSAGES, $lang.".UTF-8");
        } else {
            putenv("LC_ALL=$lang");
        }

        bind_textdomain_codeset($domain, 'UTF-8');
        bindtextdomain($domain, $apppath);
        textdomain($domain);
    }
    /**
     * Gets the base path and sets WEB_ROOT constant
     *
     * @return string the base path as determined.
     */
    private static function _determineBasePath()
    {
        return sprintf(
            '%s%s',
            dirname(__DIR__),
            DS
        );
    }

    /**
     * Initiates the environment
     *
     * @return void
     * @throws Exception
     */
    public static function startInit()
    {
        /**
         * Setup our error reporting information.
         */
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        /**
         * This enables the autoloader to work.
         */
        new self;
        /**
         * Check if the version of php is valid.
         */
        self::_verCheck();
        /**
         * Check if the extensions fog needs are available.
         */
        self::_extCheck();
        /**
         * Sets up variables for sub/node callers among other
         * request/post/get variables for neater access.
         */
        $globalVars = [
            'newService',
            'json',
            'node',
            'sub',
            'printertype',
            'id',
            'groupid',
            'sub',
            'crit',
            'sort',
            'confirm',
            'tab',
            'type',
        ];
        /**
         * Sets our variables to always be trimmed.
         */
        foreach ($globalVars as $x) {
            global $$x;
            $$x = filter_input(INPUT_GET, $x);
            if (!$$x) {
                $$x = filter_input(INPUT_POST, $x);
            }
            unset($x);
        }
        /**
         * Initialize the system itself.
         */
        new System();
        /**
         * Initialize the configuration.
         */
        new Config();
        /**
         * Language Starting
         */
        self::language($_SESSION['FOG_LANG'] ?? 'en');
    }
    /**
     * Sanitizes output
     *
     * @param mixed $value the value to sanitize
     *
     * @return string|array
     */
    public static function sanitizeItems(&$value = '')
    {
        self::setSanitize();
        /**
         * If the value isn't specified, it will sanitize
         * all REQUEST, COOKIE, POST, and GET data.
         * Otherwise, it will clean the passed value.
         */
        if ((is_array($value) && !count($value ?: [])) || (is_string($value) && !$value)) {
            $process = [
                &$_GET,
                &$_POST,
                &$_COOKIE,
                &$_SESSION
            ];
            array_walk($process, self::$_sanitizeItems);
        } else {
            if (is_array($value)) {
                array_walk($value, self::$_sanitizeItems);
            } else {
                $value = filter_var($value);
            }
        }
        return $value;
    }
    /**
     * Checks the php version is good with current system
     *
     * @return void
     */
    private static function _verCheck()
    {
        try {
            /**
             * If the version is less than 5.5.0 fail.
             */
            if (!version_compare(phpversion(), '5.5.0', '>=')) {
                throw new Exception(
                    sprintf(
                        'FOG Requires PHP v5.5.0 or higher. You have PHP v%s',
                        phpversion()
                    )
                );
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
    /**
     * Checks required extensions are installed
     *
     * @throws Exception
     * @return void
     */
    private static function _extCheck()
    {
        /**
         * List of required extensions.
         */
        $requiredExtensions = [
            'gettext',
            'mysqli'
        ];
        /**
         * Get the loaded extensions.
         */
        $loadedExtensions = get_loaded_extensions();
        /**
         * Cross-reference our required with what's loaded.
         */
        $has = array_intersect(
            $requiredExtensions,
            $loadedExtensions
        );
        /**
         * If the count doesn't match our required we know we're missing something.
         */
        if (count($has ?: []) < count($requiredExtensions ?: [])) {
            throw new Exception(_('Missing one or more extensions.'));
        }
    }
    /**
     * Cleans the buffer
     *
     * @param string $buffer buffer to clean
     *
     * @return string the cleaned up buffer
     */
    public static function sanitizeOutput(string $buffer)
    {
        /**
         * Line commented for clarity
         */
        $search = [
            '/>[^\S ]+/', //strip whitespaces after tags, except space
            '/[^\S ]+</', //strip whitespaces before tags, except space
            '/(\s)+/',  // shorten multiple whitespace sequences
        ];
        /**
         * Replaces what's found with same element here.
         */
        $replace = [
            '>',
            '<',
            '\\1',
        ];
        /**
         * Perform our replacement.
         */
        preg_replace(
            $search,
            $replace,
            $buffer
        );
        /**
         * Returns the cleaned data.
         */
        return $buffer;
    }
}
