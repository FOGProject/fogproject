<?php
/**
 * Initiator and FOG Autoloader
 *
 * PHP version 5
 *
 * This file simply is the initator.  It establishes the FOG GUI and system
 * auto loader functionality.
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
 * This file simply is the initator.  It establishes the FOG GUI and system
 * auto loader functionality.
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
     * Constructs the initiator class
     *
     * @return void
     */
    public function __construct()
    {
        /**
         * Find out if the link has service in the call.
         */
        $self = !preg_match(
            '#service#i',
            $_SERVER['PHP_SELF']
        );
        /**
         * Set useragent to false.
         */
        $useragent = false;
        /**
         * If user agent is passed, define the useragent
         */
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }
        /**
         * If we are not a service file
         * and we have a user agent string
         * and the Session hasn't been started,
         * Start the session.
         */
        if ($self && $useragent && !isset($_SESSION)) {
            session_start();
        }
        /**
         * Define our base path (/var/www/, /var/www/html/, etc...)
         */
        define('BASEPATH', self::_determineBasePath());
        /**
         * Regex pattern to search for files of type.
         */
        $regext = '#^.*\.(report|event|class|hook)\.php$#';
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
        $paths = iterator_to_array($RegexIterator, true);
        unset(
            $RecursiveDirectoryIterator,
            $RecursiveIteratorIterator,
            $RegexIterator
        );
        /**
         * Define all paths as an array.
         */
        $allpaths = array();
        /**
         * Loop our paths from earlier storing the dirname of the element.
         */
        foreach ((array)$paths as &$element) {
            $allpaths[] = dirname($element[0]);
            unset($element);
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
        /**
         * Define with extenstions we need to auto load.
         */
        spl_autoload_extensions('.class.php,.event.php,.hook.php,.report.php');
        /**
         * Pass our autoloaded items through our custom loader method.
         */
        spl_autoload_register(array($this, '_fogLoader'));
    }
    /**
     * Gets the base path and sets WEB_ROOT constant
     *
     * @return string the base path as determined.
     */
    private static function _determineBasePath()
    {
        /**
         * Gets our script name and path.
         */
        $script_name = $_SERVER['SCRIPT_NAME'];
        /**
         * Stores our matching if fog is in the name variable
         */
        $match = preg_match('#/fog/#', $script_name);
        if ($match) {
            $match = 'fog/';
        } else {
            $match = '';
        }
        /**
         * Defines our webroot path.
         */
        define(
            'WEB_ROOT',
            sprintf(
                '/%s',
                $match
            )
        );
        /**
         * Check for /srv/http/fog, /var/www/html/fog, or /var/www/fog.
         * Otherwise use the document root as defined by the server.
         */
        if (file_exists('/srv/http/fog')) {
            $path = '/srv/http/fog';
        } elseif (file_exists('/var/www/html/fog')) {
            $path = '/var/www/html/fog';
        } elseif (file_exists('/var/www/fog')) {
            $path = '/var/www/fog';
        } else {
            $docroot = trim($_SERVER['DOCUMENT_ROOT'], '/');
            $path = sprintf(
                '/%s',
                sprintf(
                    '/%s',
                    WEB_ROOT
                )
            );
        }
        return $path;
    }
    /**
     * Cleanup after no longer needed
     *
     * @return void
     */
    public function __destruct()
    {
        spl_autoload_unregister(array($this, '_fogLoader'));
    }
    /**
     * Initiates the environment
     *
     * @return void
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
        new self();
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
        $globalVars = array(
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
        );
        /**
         * Sets our variables to always be trimmed.
         */
        foreach ($globalVars as &$x) {
            global $$x;
            if (isset($_REQUEST[$x])) {
                $_REQUEST[$x] = $$x = trim($_REQUEST[$x]);
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
    }
    /**
     * Sanitizes output
     *
     * @param mixed $value the value to sanitize
     *
     * @return string
     */
    public static function sanitizeItems(&$value = '')
    {
        /**
         * Lambda to sanitize our user input data.
         *
         * @param mixed $key the key of the array.
         * @param mixed $val the value of the array.
         *
         * @return void
         */
        $sanitize_items = function (&$val, &$key) use (&$value) {
            if (is_string($val)) {
                $value[$key] = htmlentities($val, ENT_QUOTES, 'utf-8');
            }
            if (is_array($val)) {
                self::sanitizeItems($value[$key]);
            }
        };
        /**
         * If the value isn't specified, it will sanitize
         * all REQUEST, COOKIE, POST, and GET data.
         * Otherwise it will clean the passed value.
         */
        if (!count($value)) {
            array_walk($_REQUEST, $sanitize_items);
            array_walk($_COOKIE, $sanitize_items);
            array_walk($_POST, $sanitize_items);
            array_walk($_GET, $sanitize_items);
        } else {
            $value = array_values(array_filter(array_unique((array)$value)));
            array_walk($value, $sanitize_items);
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
        $requiredExtensions = array(
            'gettext',
            'mysqli'
        );
        /**
         * Get the loaded extensions.
         */
        $loadedExtensions = get_loaded_extensions();
        /**
         * Cross reference our required with what's loaded.
         */
        $has = array_intersect(
            $requiredExtensions,
            $loadedExtensions
        );
        /**
         * If the count doesn't match our required we know we're missing something.
         */
        if (count($has) < count($requiredExtensions)) {
            throw new Exception(_('Missing one or more extensions.'));
        }
    }
    /**
     * Loads the class files as they're needed
     *
     * @param string $className the class to include as called.
     *
     * @throws Exception
     * @return void
     */
    private function _fogLoader($className)
    {
        /**
         * Sanity check, if the classname is not a string fail.
         */
        if (!is_string($className)) {
            throw new Exception(_('Classname must be a string'));
        }
        /**
         * If the class exists, we know it's already been loaded.
         * Return as we don't need to do anything.
         */
        if (class_exists($className, false)) {
            return;
        }
        /**
         * Ensure the event and hook managers are available.
         * Really only needed for the respective class but
         * doesn't hurt to have in either case.
         */
        global $EventManager;
        global $HookManager;
        /**
         * Load the class.
         */
        spl_autoload($className);
    }
    /**
     * Cleans the buffer
     *
     * @param string $buffer buffer to clean
     *
     * @return string the cleaned up buffer
     */
    public static function sanitizeOutput($buffer)
    {
        /**
         * Line commented for clarity
         */
        $search = array(
            '/\>[^\S ]+/s', //strip whitespaces after tags, except space
            '/[^\S ]+\</s', //strip whitespaces before tags, except space
            '/(\s)+/s',  // shorten multiple whitespace sequences
        );
        /**
         * Replaces what's found with same element here.
         */
        $replace = array(
            '>',
            '<',
            '\\1',
        );
        /**
         * Perform our replace.
         */
        $buffer = preg_replace($search, $replace, $buffer);
        /**
         * Returns the cleaned data.
         */
        return $buffer;
    }
}
Initiator::sanitizeItems();
Initiator::startInit();
$FOGFTP = new FOGFTP();
$FOGCore = new FOGCore();
$DB = FOGCore::getClass('DatabaseManager')->establish()->getDB();
FOGCore::setSessionEnv();
$TimeZone = $_SESSION['TimeZone'];
if (isset($_SESSION['FOG_USER'])) {
    $currentUser = new User($_SESSION['FOG_USER']);
} else {
    $currentUser = new User(0);
}
$HookManager = FOGCore::getClass('HookManager');
$HookManager->load();
$EventManager = FOGCore::getClass('EventManager');
$EventManager->load();
$FOGURLRequests = FOGCore::getClass('FOGURLRequests');
if (in_array($sub, array('configure', 'authorize', 'requestClientInfo'))) {
    new DashboardPage();
    exit;
}
