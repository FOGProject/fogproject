<?php
class Initiator {
    /** __construct() Initiates to load the rest of FOG
     * @return void
     */
    public function __construct() {
        if (!isset($_SESSION)) {
            session_start();
            session_cache_limiter('no-cache');
        }
        define('BASEPATH', self::DetermineBasePath());
        $allpaths = array_map(function($element) {
            return dirname($element[0]);
        },iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASEPATH,FileSystemIterator::SKIP_DOTS)),'#^.*\.(event|class|hook)\.php$#',RecursiveRegexIterator::GET_MATCH)));
        set_include_path(sprintf('%s%s%s',implode(PATH_SEPARATOR,$allpaths),PATH_SEPARATOR,get_include_path));
        spl_autoload_extensions('.class.php,.event.php,.hook.php');
        spl_autoload_register(array($this,'FOGLoader'));
    }
    /** DetermineBasePath() Gets the base path and sets WEB_ROOT constant
     * @return null
     */
    private static function DetermineBasePath() {
        $script_name = htmlentities($_SERVER['SCRIPT_NAME'],ENT_QUOTES,'utf-8');
        define('WEB_ROOT',sprintf('/%s',(preg_match('#/fog/#',$script_name)?'fog/':'')));
        return (file_exists('/srv/http/fog') ? '/srv/http/fog' : (file_exists('/var/www/html/fog') ? '/var/www/html/fog' : (file_exists('/var/www/fog') ? '/var/www/fog' : '/'.trim($_SERVER['DOCUMENT_ROOT'],'/').'/'.WEB_ROOT)));
    }
    /** __destruct() Cleanup after no longer needed
     * @return void
     */
    public function __destruct() {
        spl_autoload_unregister(array($this,'FOGLaoder'));
    }
    /** startInit() initiates the environment
     * @return void
     */
    public static function startInit() {
        @set_time_limit(0);
        @error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        self::verCheck();
        self::extCheck();
        $globalVars = array('node','sub','printertype','id','sub','crit','sort','confirm','tab');
        array_map(function(&$x) {
            global $$x;
            if (isset($_REQUEST[$x])) $_REQUEST[$x] = $$x = trim(htmlentities(mb_convert_encoding($_REQUEST[$x],'UTF-8'),ENT_QUOTES,'UTF-8'));
            unset($x);
        },$globalVars);
        new System();
        new Config();
    }
    public function sanitize_items(&$value = '') {
        $sanitize_items = function(&$val,&$key) use (&$value) {
            if (is_string($val)) $val = htmlentities($val,ENT_QUOTES,'utf-8');
            if (is_array($val)) $value = $this->sanitize_items($val);
        };
        if (!$value) {
            array_walk($_REQUEST,$sanitize_items);
            array_walk($_COOKIE,$sanitize_items);
            array_walk($_POST,$sanitize_items);
            array_walk($_GET,$sanitize_items);
        } else {
            array_walk($value,$sanitize_items);
            return $value;
        }
    }
    /** verCheck() Checks the php version is good with current system
     * @return void
     */
    private static function verCheck() {
        try {
            if (!version_compare(phpversion(),'5.3.0','>=')) throw new Exception('FOG Requires PHP v5.3.0 or higher. You have PHP v'.phpversion());
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
    /** extCheck() Checks required extentions are installed
     * @return void
     */
    private static function extCheck() {
        $requiredExtensions = array('gettext','mysqli');
        $missingExtensions = array_values(array_unique(array_filter(array_map(function(&$ext) {
            if (!in_array($ext,get_loaded_extensions())) return $ext;
        },$requiredExtensions))));
        try {
            if (count($missingExtensions)) throw new Exception(sprintf('%s: %s',_('Missing Extensions'),implode(', ',(array)$missingExtensions)));
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
    /** endInit() Calls the params at the end of the init
     * @return void
     */
    public static function endInit() {
        if ($_SESSION['locale']) {
            putenv("LC_ALL={$_SESSION['locale']}");
            setlocale(LC_ALL, $_SESSION['locale']);
        }
        bindtextdomain('messages', 'languages');
        textdomain('messages');
    }
    /** FOGLoader() Loads the class files as they're needed
     * @param $className the class to include as called.
     * @return void
     */
    private function FOGLoader($className) {
        if (in_array($className,get_declared_classes())) return;
        global $EventManager;
        global $HookManager;
        spl_autoload($className);
    }
    /** sanitize_output() Clean the buffer
     * @param $buffer the buffer to clean
     * @return the cleaned up buffer
     */
    public static function sanitize_output($buffer) {
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
        $buffer = preg_replace($search,$replace,$buffer);
        return $buffer;
    }
}
/** $Init the initiator class */
$Init = new Initiator();
/** Sanitize user input */
$Init->sanitize_items();
/** Starts the init itself */
$Init::startInit();
/** $FOGFTP the FOGFTP class */
$FOGFTP = new FOGFTP();
/** $FOGCore the FOGCore class */
$FOGCore = new FOGCore();
/** $DB set's the DB class from the DatabaseManager */
$DB = FOGCore::getClass('DatabaseManager')->establish()->getDB();
/** $EventManager initiates the EventManager class */
$EventManager = FOGCore::getClass('EventManager');
/** $HookManager initiates the HookManager class */
$HookManager = FOGCore::getClass('HookManager');
$FOGCore->setSessionEnv();
/** $TimeZone the timezone setter */
$TimeZone = $_SESSION['TimeZone'];
$HookManager->load();
$EventManager->load();
/** $HookManager initiates the FOGURLRequest class */
$FOGURLRequests = FOGCore::getClass('FOGURLRequests');
