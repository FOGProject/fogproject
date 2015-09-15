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
        define('BASEPATH', self::DetermineBasePath());
        $this->plugPaths = array_filter(glob(BASEPATH . '/lib/plugins/*'), 'is_dir');
        foreach($this->plugPaths AS $plugPath) {
            $plug_class[] = $plugPath.'/class/';
            $plug_class[] = $plugPath.'/client/';
            $plug_class[] = $plugPath.'/reg-task/';
            $plug_class[] = $plugPath.'/service/';
            $plug_hook[] = $plugPath.'/hooks/';
            $plug_event[] = $plugPath.'/events/';
            $plug_page[] = $plugPath.'/pages/';
        }
        $FOGPaths = array(BASEPATH . '/lib/fog/', BASEPATH . '/lib/db/',BASEPATH.'/lib/client/',BASEPATH.'/lib/reg-task/',BASEPATH.'/lib/service/');
        $HookPaths = array(BASEPATH . '/lib/hooks/');
        $EventPaths = array(BASEPATH . '/lib/events/');
        $PagePaths = array(BASEPATH . '/lib/pages/');
        $this->FOGPaths = array_merge((array)$FOGPaths,(array)$plug_class);
        $this->HookPaths = array_merge((array)$HookPaths,(array)$plug_hook);
        $this->EventPaths = array_merge((array)$EventPaths,(array)$plug_event);
        $this->PagePaths = array_merge((array)$PagePaths,(array)$plug_page);
        $AllPaths = array_merge($this->FOGPaths,$this->HookPaths,$this->EventPaths,$this->PagePaths);
        $pathStr = '';
        foreach ($AllPaths AS $i => &$Path) $pathStr .= $Path.PATH_SEPARATOR;
        set_include_path(get_include_path().PATH_SEPARATOR.$pathStr);
        spl_autoload_register(array($this,'FOGLoader'));
        spl_autoload_register(array($this,'FOGPages'));
        spl_autoload_register(array($this,'FOGHooks'));
        spl_autoload_register(array($this,'FOGEvents'));
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
        spl_autoload_unregister(array($this,'FOGLoader'));
        spl_autoload_unregister(array($this,'FOGPages'));
        spl_autoload_unregister(array($this,'FOGHooks'));
        spl_autoload_unregister(array($this,'FOGEvents'));
    }
    /** startInit() initiates the environment
     * @return void
     */
    public static function startInit() {
        @set_time_limit(0);
        @error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        @ini_set('session.save_handler','mm');
        @ini_set('session.cookie_httponly',true);
        @ob_start(array(self,'sanitize_output'));
        @session_start();
        @session_cache_limiter('no-cache');
        @session_set_cookie_params(0,null,null,true,true);
        @set_magic_quotes_runtime(0);
        self::verCheck();
        self::extCheck();
        foreach($_REQUEST as $key => $val) $_REQUEST[$key] = is_array($val) ? filter_var_array($val,FILTER_SANITIZE_STRING) : filter_var($val,FILTER_SANITIZE_STRING);
        foreach($_GET as $key => $val) $_GET[$key] = is_array($val) ? filter_var_array($val,FILTER_SANITIZE_STRING) : filter_var($val,FILTER_SANITIZE_STRING);
        foreach($_POST as $key => $val) $_POST[$key] = is_array($val) ? filter_var_array($val,FILTER_SANITIZE_STRING) : filter_var($val,FILTER_SANITIZE_STRING);
        foreach(array('node','sub','printertype','id','sub','crit','sort','confirm','tab') AS $x) {
            global $$x;
            $$x = isset($_REQUEST[$x]) ? filter_var($_REQUEST[$x],FILTER_SANITIZE_STRING) : '';
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
            if (count($missingExtensions)) throw new Exception('Missing Extensions: '. implode(', ',(array)$missingExtensions));
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
            putenv('LC_ALL='.$_SESSION['locale']);
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
        foreach($this->FOGPaths AS $path) (!class_exists($className) && file_exists($path.$className.'.class.php') ? require_once($path.$className.'.class.php') : null);
    }
    /** FOGPages() Loads the page files as they're needed.
     * @param $className the page classes to include as called.
     * @return void
     */
    private function FOGPages($className) {
        foreach($this->PagePaths as $path) (!class_exists($className) && file_exists($path.$className.'.class.php') ? require_once($path.$className.'.class.php') : null);
    }
    /** FOGHooks() Loads the hook files as they're needed.
     * @param $className the class to include as called.
     * @return void
     */
    private function FOGHooks($className) {
        global $HookManager;
        foreach($this->HookPaths AS $path) (!class_exists($className) && file_exists($path.$className.'.hook.php') ? include_once($path.$className.'.hook.php') : null);
    }
    /** FOGEvents() Loads the event files as they're needed.
     * @param $className the class to include as called.
     * @return void
     */
    private function FOGEvents($className) {
        global $EventManager;
        foreach($this->EventPaths AS $path) (!class_exists($className) && file_exists($path.$className.'.event.php') ? include_once($path.$className.'.event.php') : null);
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
/** $DatabaseManager the DatabaseManager class */
$DatabaseManager = new DatabaseManager();
/** $DB set's the DB class from the DatabaseManager */
$DB = $FOGCore->DB = $DatabaseManager->connect()->DB;
/** Cleanup all invalid entrees */
//$FOGCore->cleanInvalidEntries();
/** Loads any Session variables */
$FOGCore->setSessionEnv();
/** $TimeZone the timezone setter */
$TimeZone = $FOGCore->TimeZone = $_SESSION[TimeZone];
/** $EventManager initiates the EventManager class */
$FOGCore->EventManager = $EventManager = $FOGCore->getClass(EventManager);
/** $HookManager initiates the HookManager class */
$FOGCore->HookManager = $HookManager = $FOGCore->getClass(HookManager);
$HookManager->load();
$EventManager->load();
/** $HookManager initiates the FOGURLRequest class */
$FOGCore->FOGURLRequests = $FOGURLRequests = $FOGCore->getClass(FOGURLRequests);
