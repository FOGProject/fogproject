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
        $self = !preg_match('#service#i', $_SERVER['PHP_SELF']);
        $useragent = false;
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }
        if ($self && $useragent && !isset($_SESSION)) {
            session_start();
            session_cache_limiter('nocache');
        }
        define('BASEPATH', self::_determineBasePath());
        $regext = '#^.*\.(report|event|class|hook)\.php$#';
        $RecursiveDirectoryIterator = new RecursiveDirectoryIterator(
            BASEPATH,
            FileSystemIterator::SKIP_DOTS
        );
        $RecursiveIteratorIterator = new RecursiveIteratorIterator(
            $RecursiveDirectoryIterator
        );
        $RegexIterator = new RegexIterator(
            $RecursiveIteratorIterator,
            $regext,
            RegexIterator::GET_MATCH
        );
        $paths = iterator_to_array($RegexIterator, true);
        $allpaths = array_map(
            function ($element) {
                return dirname($element[0]);
            },
            $paths
        );
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
        spl_autoload_extensions('.class.php,.event.php,.hook.php,.report.php');
        spl_autoload_register(array($this, '_fogLoader'));
    }
    /**
     * Gets the base path and sets WEB_ROOT constant
     *
     * @return string the base path as determined.
     */
    private static function _determineBasePath()
    {
        $script_name = $_SERVER['SCRIPT_NAME'];
        $match = preg_match('#/fog/#', $script_name);
        if ($match) {
            $match = 'fog/';
        } else {
            $match = '';
        }
        define(
            'WEB_ROOT',
            sprintf(
                '/%s',
                $match
            )
        );
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
     * __destruct() Cleanup after no longer needed
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
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        new self();
        self::_verCheck();
        self::_extCheck();
        $globalVars = array(
            'newService',
            'json',
            'node',
            'sub',
            'printertype',
            'id',
            'sub',
            'crit',
            'sort',
            'confirm',
            'tab',
        );
        array_map(
            function (&$x) {
                global $$x;
                if (isset($_REQUEST[$x])) {
                    $_REQUEST[$x] = $$x = trim($_REQUEST[$x]);
                }
                unset($x);
            },
            $globalVars
        );
        new System();
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
        $sanitize_items = function (&$val, &$key) use (&$value) {
            if (is_string($val)) {
                $value[$key] = htmlentities($val, ENT_QUOTES, 'utf-8');
            }
            if (is_array($val)) {
                self::sanitizeItems($value[$key]);
            }
        };
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
        $requiredExtensions = array('gettext','mysqli');
        $loadedExtensions = get_loaded_extensions();
        $has = array_intersect($requiredExtensions, $loadedExtensions);
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
        if (!is_string($className)) {
            throw new Exception(_('Classname must be a string'));
        }
        if (class_exists($className, false)) {
            return;
        }
        global $EventManager;
        global $HookManager;
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
        $search = array(
            '/\>[^\S ]+/s', //strip whitespaces after tags, except space
            '/[^\S ]+\</s', //strip whitespaces before tags, except space
            '/(\s)+/s',  // shorten multiple whitespace sequences
        );
        $replace = array(
            '>',
            '<',
            '\\1',
        );
        $buffer = preg_replace($search, $replace, $buffer);
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
