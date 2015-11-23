<?php
class Initiator {
    /** $HookPaths the paths were hooks are stored */
    public $HookPaths;
    /** $EventPaths the paths where events are stored */
    public $EventPaths;
    /** $FOGPaths the paths for the main fog stuff */
    public $FOGPaths;
    /** $PagePaths the paths where pages are stored */
    public $PagePaths;
    /** $plugPaths the plugin paths integrated with the other paths */
    public $plugPaths;
    /** __construct() Initiates to load the rest of FOG
     * @return void
     */
    public function __construct() {
        if (!isset($_SESSION)) {
            session_start();
            session_cache_limiter('no-cache');
        }
        define('BASEPATH', self::DetermineBasePath());
        $plugs = sprintf('%s%s%slib%splugins%s*',DIRECTORY_SEPARATOR,trim(str_replace(array('\\','/'),DIRECTORY_SEPARATOR,BASEPATH),DIRECTORY_SEPARATOR),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
        $path = sprintf('%s%s%slib%s%s%s',DIRECTORY_SEPARATOR,trim(str_replace(array('\\','/'),DIRECTORY_SEPARATOR,BASEPATH),DIRECTORY_SEPARATOR),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR,'%s',DIRECTORY_SEPARATOR);
        $this->plugPaths = array_filter(glob($plugs),'is_dir');
        foreach($this->plugPaths AS $plugPath) {
            $plug_class[] = sprintf('%s%s%sclass%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
            $plug_class[] = sprintf('%s%s%sclient%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
            $plug_class[] = sprintf('%s%s%sreg-task%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
            $plug_class[] = sprintf('%s%s%sservice%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
            $plug_hook[] = sprintf('%s%s%shooks%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
            $plug_event[] = sprintf('%s%s%sevents%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
            $plug_page[] = sprintf('%s%s%spages%s',DIRECTORY_SEPARATOR,trim($plugPath,'/'),DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR);
        }
        $FOGPaths = array();
        $FOGPaths = array(sprintf($path,'fog'),sprintf($path,'db'),sprintf($path,'client'),sprintf($path,'reg-task'),sprintf($path,'service'));
        $HookPaths = array(sprintf($path,'hooks'));
        $EventPaths = array(sprintf($path,'events'));
        $PagePaths = array(sprintf($path,'pages'));
        $this->FOGPaths = array_merge((array)$FOGPaths,(array)$plug_class);
        $this->HookPaths = array_merge((array)$HookPaths,(array)$plug_hook);
        $this->EventPaths = array_merge((array)$EventPaths,(array)$plug_event);
        $this->PagePaths = array_merge((array)$PagePaths,(array)$plug_page);
        spl_autoload_register(array($this,'FOGLoader'));
    }
    /** DetermineBasePath() Gets the base path and sets WEB_ROOT constant
     * @return null
     */
    private static function DetermineBasePath() {
        define('WEB_ROOT',sprintf('/%s',(preg_match('#/fog/#',$_SERVER['PHP_SELF'])?'fog/':'')));
        return (file_exists('/srv/http/fog') ? '/srv/http/fog' : (file_exists('/var/www/html/fog') ? '/var/www/html/fog' : (file_exists('/var/www/fog') ? '/var/www/fog' : '/'.trim($_SERVER['DOCUMENT_ROOT'],'/').'/'.WEB_ROOT)));
    }
    /** __destruct() Cleanup after no longer needed
     * @return void
     */
    public function __destruct() {
        foreach (spl_autoload_functions() AS $i => &$function) {
            spl_autoload_unregister($function);
            unset($function);
        }
    }
    /** startInit() initiates the environment
     * @return void
     */
    public static function startInit() {
        @set_time_limit(0);
        @error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        @set_magic_quotes_runtime(0);
        self::verCheck();
        self::extCheck();
        foreach($_REQUEST as $key => &$val) {
            if (is_array($val)) {
                foreach ((array)$val AS $i => &$v) {
                    $val[$i] = trim(mb_convert_encoding(strip_tags($v),'UTF-8','UTF-8'));
                    unset($v);
                }
                $_REQUEST[$key] = filter_var_array($val,
                    FILTER_SANITIZE_FULL_SPECIAL_CHARS
                );
            } else {
                $val = trim(mb_convert_encoding(strip_tags($val),'UTF-8','UTF-8'));
                $_REQUEST[$key] = trim(filter_var($val,
                    FILTER_SANITIZE_FULL_SPECIAL_CHARS
                ));
            }
        }
        foreach(array('node','sub','printertype','id','sub','crit','sort','confirm','tab') AS $x) {
            global $$x;
            if (isset($_REQUEST[$x])) {
                $$x = trim(filter_var(trim(mb_convert_encoding(strip_tags($_REQUEST[$x]),'UTF-8','UTF-8')),
                    FILTER_SANITIZE_FULL_SPECIAL_CHARS
                ));
            }
        }
        unset($x);
        new System();
        new Config();
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
        $requiredExtensions = array('gettext');
        foreach($requiredExtensions AS $extension) {
            if (!in_array($extension, get_loaded_extensions())) $missingExtensions[] = $extension;
        }
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
        $allPaths = array_merge($this->FOGPaths,$this->PagePaths,$this->HookPaths,$this->EventPaths);
        foreach($allPaths AS $i => &$path) {
            $class = sprintf('%s%s.class.php',$path,$className);
            $event = sprintf('%s%s.event.php',$path,$className);
            $hook = sprintf('%s%s.hook.php',$path,$className);
            if (file_exists($class)) {
                require_once($class);
                unset($path);
                break;
            } else if (file_exists($event)) {
                global $EventManager;
                include($event);
                unset($path);
                break;
            } else if (file_exists($hook)) {
                global $HookManager;
                include($hook);
                unset($path);
                break;
            } else unset($path);
        }
        unset($allPaths);
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
/** Starts the init itself */
$Init::startInit();
/** $FOGFTP the FOGFTP class */
$FOGFTP = new FOGFTP();
/** $FOGCore the FOGCore class */
$FOGCore = new FOGCore();
/** $DB set's the DB class from the DatabaseManager */
$DB = $FOGCore->getClass('DatabaseManager')->connect()->DB;
/** $EventManager initiates the EventManager class */
$EventManager = $FOGCore->getClass('EventManager');
/** $HookManager initiates the HookManager class */
$HookManager = $FOGCore->getClass('HookManager');
$FOGCore->setSessionEnv();
/** $TimeZone the timezone setter */
$TimeZone = $_SESSION['TimeZone'];
$HookManager->load();
/** $HookManager initiates the FOGURLRequest class */
$FOGURLRequests = $FOGCore->getClass('FOGURLRequests');
