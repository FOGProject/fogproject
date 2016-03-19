<?php
abstract class FOGBase {
    public static $foglang;
    public static $ajax = false;
    public static $post = false;
    public static $service = false;
    protected $isLoaded = array();
    protected static $debug = false;
    protected static $info = false;
    protected static $buildSelectBox;
    protected static $ftpfilesonly;
    protected static $selected;
    protected static $DB;
    protected static $FOGFTP;
    protected static $FOGCore;
    protected static $EventManager;
    protected static $HookManager;
    protected static $TimeZone;
    protected static $FOGUser;
    protected static $FOGPageManager;
    protected static $FOGURLRequests;
    protected static $FOGSubMenu;
    protected static $urlself;
    protected static $isMobile;
    protected static $searchPages = array(
        'user',
        'host',
        'group',
        'image',
        'snapin',
        'printer',
        'task',
    );
    private static $initialized = false;
    private static function init() {
        if (self::$initialized === true) return $this;
        global $foglang;
        global $FOGFTP;
        global $FOGCore;
        global $DB;
        global $currentUser;
        global $EventManager;
        global $HookManager;
        global $FOGURLRequests;
        global $FOGPageManager;
        global $TimeZone;
        self::$foglang =& $foglang;
        self::$FOGFTP =& $FOGFTP;
        self::$FOGCore = &$FOGCore;
        self::$DB =& $DB;
        self::$EventManager =& $EventManager;
        self::$HookManager =& $HookManager;
        self::$FOGUser =& $currentUser;
        self::$urlself = htmlentities($_SERVER['SCRIPT_NAME'],ENT_QUOTES,'utf-8');
        self::$isMobile = (bool)preg_match('#/mobile/#i',self::$urlself);
        self::$service = (bool)preg_match('#/service/#i', self::$urlself);
        self::$ajax = (bool)isset($_SERVER['HTTP_X_REQUESTED_WITH']) && preg_match('#^xmlhttprequest$#i',$_SERVER['HTTP_X_REQUESTED_WITH']);
        self::$post = (bool)preg_match('#^post$#i',isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] ? $_SERVER['REQUEST_METHOD'] : '');
        self::$FOGURLRequests = &$FOGURLRequests;
        self::$FOGPageManager = &$FOGPageManager;
        self::$TimeZone = &$TimeZone;
        self::$buildSelectBox = function(&$option,&$index = false) {
            $value = $option;
            if ($index) $value = $index;
            printf('<option value="%s"%s>%s</option>',
                $value,
                (self::$selected == $value ? ' selected' : ''),
                $option
            );
            unset($option,$index,$value);
        };
        self::$initialized = true;
        return $this;
    }
    public function __construct() {
        return self::init();
    }
    public function __toString() {
        return (string)get_class($this);
    }
    public static function getClass($class, $data = '',$props = false) {
        $args = func_get_args();
        array_shift($args);
        $obj = new ReflectionClass($class);
        if ($props === true) return $obj->getDefaultProperties();
        return $obj->getConstructor() ? (count($args) ? $obj->newInstanceArgs($args) : $obj->newInstance($data)) : $obj->newInstanceWithoutConstructor();
    }
    public function getHostItem($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false,$override = false) {
        $mac = $_REQUEST['mac'];
        if ($encoded === true) $mac = base64_decode($mac);
        $mac = trim($mac);
        $MACs = $this->parseMacList($mac,!$service,$service);
        if (!$MACs && !$hostnotrequired) throw new Exception($service ? '#!im' : sprintf('%s %s',self::$foglang['InvalidMAC'],$_REQUEST['mac']));
        if ($returnmacs) return (is_array($MACs) ? $MACs : array($MACs));
        $Host = self::getClass('HostManager')->getHostByMacAddresses($MACs);
        if (!$hostnotrequired && (!$Host || !$Host->isValid() || $Host->get('pending')) && !$override) throw new Exception($service ? '#!ih' : _('Invalid Host'));
        return $Host;
    }
    public function getAllBlamedNodes() {
        $Host = $this->getHostItem(false);
        $DateInterval = $this->nice_date()->modify('-5 minutes');
        $nodeRet = array_map(function(&$NodeFailure) use (&$nodeRet) {
            if (!$NodeFailure->isValid()) return;
            $DateTime = $this->nice_date($NodeFailure->get('failureTime'));
            if ($DateTime < $DateInterval) {
                $NodeFailure->destroy();
                return;
            }
            return (int)$NodeFailure->get('id');
        },(array)self::getClass('NodeFailureManager')->find(array('taskID'=>$Host->get('task')->get('id'),'hostID'=>$Host->get('id'))));
        return array_values(array_filter(array_unique((array)$nodeRet)));
    }
    protected function getActivePlugins() {
        return array_map('strtolower',(array)self::getSubObjectIDs('Plugin','','name'));
    }
    protected function fatalError($txt, $data = array()) {
        if (!self::$service && !self::$ajax) {
            echo sprintf('<div class="debug-error">FOG FATAL ERROR: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function error($txt, $data = array()) {
        if (self::$debug && !self::$service && !self::$ajax) {
            echo sprintf('<div class="debug-error">FOG ERROR: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function debug($txt, $data = array()) {
        if (self::$debug && !self::$service && !self::$ajax) {
            echo sprintf('<div class="debug-error">FOG DEBUG: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function info($txt, $data = array()) {
        if (self::$info && !self::$service && !self::$ajax) {
            echo sprintf('<div class="debug-info">FOG INFO: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function setMessage($txt, $data = array()) {
        $_SESSION['FOG_MESSAGES'] = (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt);
    }
    protected function getMessages() {
        if (!isset($_SESSION['FOG_MESSAGES'])) $_SESSION['FOG_MESSAGES'] = array();
        $messages = (array)$_SESSION['FOG_MESSAGES'];
        unset($_SESSION['FOG_MESSAGES']);
        if (self::$HookManager instanceof HookManager) self::$HookManager->processEvent('MessageBox',array('data'=>&$messages));
        array_walk($messages,function(&$message,&$i) {
            if (!$i) echo '<!-- FOG Messages -->';
            printf('<div class="fog-message-box">%s</div>',$message);
        },$messages);
        unset($messages);
    }
    protected function redirect($url = '') {
        if (!headers_sent() && !self::$service) {
            header('Strict-Transport-Security: "max-age=15768000"');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('X-Robots-Tag: none');
            header('X-Frame-Options: SAMEORIGIN');
            header('Cache-Control: no-cache');
            header("Location: $url");
            exit;
        }
    }
    protected function array_insert_before($key, array &$array, $new_key, $new_value) {
        if (in_array($key, $array)) return;
        $new = array();
        foreach($array AS $k => &$value) {
            if ($k === $key) $new[$new_key] = $new_value;
            $new[$k] = $value;
            unset($value);
        }
        $array = $new;
    }
    protected function array_insert_after($key, array &$array, $new_key, $new_value) {
        if (in_array($key, $array)) return;
        $new = array();
        foreach($array AS $k => &$value) {
            $new[$k] = $value;
            if ($k === $key) $new[$new_key] = $new_value;
            unset($value);
        }
        $array = $new;
    }
    protected function array_remove($key, array &$array) {
        if (is_array($key)) {
            foreach ($key AS $k => &$value) {
                unset($array[$value]);
                unset($value);
            }
        } else {
            foreach($array AS $k => &$value) {
                if (is_array($value)) $this->array_remove($key, $value);
                else unset($array[$key]);
                unset($value);
            }
        }
    }
    protected function isLoaded(&$key) {
        $key = $this->key($key);
        $this->isLoaded[$key] = (bool)isset($this->isLoaded[$key]);
        return $this->isLoaded[$key];
    }
    protected function resetRequest() {
        $reqVars = (array)$_REQUEST;
        unset($_REQUEST);
        if (!isset($_SESSION['post_request_vals'])) $_SESSION['post_request_vals'] = array();
        $sesVars = (array)$_SESSION['post_request_vals'];
        foreach($sesVars AS $key => &$val) {
            $_REQUEST[$key] = $val;
            unset($val);
        }
        foreach($reqVars AS $key => &$val) {
            $_REQUEST[$key] = $val;
            unset($val);
        }
        unset($_SESSION['post_request_vals'], $reqVars);
    }
    protected function setRequest() {
        if (!$_SESSION['post_request_vals'] && self::$post) $_SESSION['post_request_vals'] = $_REQUEST;
    }
    protected function formatByteSize($size) {
        $units = array('iB','KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB');
        $factor = floor((strlen($size) - 1)/3);
        return sprintf('%3.2f %s',$size/pow(1024,$factor),@$units[$factor]);
    }
    protected function getGlobalModuleStatus($names = false) {
        return array(
            'dircleanup' => !$names ? (int)$this->getSetting('FOG_SERVICE_DIRECTORYCLEANER_ENABLED') : 'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
            'usercleanup' => !$names ? (int)$this->getSetting('FOG_SERVICE_USERCLEANUP_ENABLED') : 'FOG_SERVICE_USERCLEANUP_ENABLED',
            'displaymanager' => !$names ? (int)$this->getSetting('FOG_SERVICE_DISPLAYMANAGER_ENABLED') : 'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
            'autologout' => !$names ? (int)$this->getSetting('FOG_SERVICE_AUTOLOGOFF_ENABLED') : 'FOG_SERVICE_AUTOLOGOFF_ENABLED',
            'greenfog' => !$names ? $this->getSetting('FOG_SERVICE_GREENFOG_ENABLED') : 'FOG_SERVICE_GREENFOG_ENABLED',
            'hostnamechanger' => !$names ? (int)$this->getSetting('FOG_SERVICE_HOSTNAMECHANGER_ENABLED') : 'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
            'snapinclient' => !$names ? (int)$this->getSetting('FOG_SERVICE_SNAPIN_ENABLED') : 'FOG_SERVICE_SNAPIN_ENABLED',
            'clientupdater' => !$names ? (int)$this->getSetting('FOG_SERVICE_CLIENTUPDATER_ENABLED') : 'FOG_SERVICE_CLIENTUPDATER_ENABLED',
            'hostregister' => !$names ? (int)$this->getSetting('FOG_SERVICE_HOSTREGISTER_ENABLED') : 'FOG_SERVICE_HOSTREGISTER_ENABLED',
            'printermanager' => !$names ? (int)$this->getSetting('FOG_SERVICE_PRINTERMANAGER_ENABLED') : 'FOG_SERVICE_PRINTERMANAGER_ENABLED',
            'taskreboot' => !$names ? (int)$this->getSetting('FOG_SERVICE_TASKREBOOT_ENABLED') : 'FOG_SERVICE_TASKREBOOT_ENABLED',
            'usertracker' => !$names ? (int)$this->getSetting('FOG_SERVICE_USERTRACKER_ENABLED') : 'FOG_SERVICE_USERTRACKER_ENABLED',
        );
    }
    public function nice_date($Date = 'now',$utc = false) {
        $TZ = self::getClass('DateTimeZone',($utc || empty(self::$TimeZone)? 'UTC' : self::$TimeZone));
        return self::getClass('DateTime',$Date,$TZ);
    }
    public function formatTime($time, $format = false, $utc = false) {
        if (!$time instanceof DateTime) $time = $this->nice_date($time,$utc);
        if ($format) {
            if (!$this->validDate($time)) return _('No Data');
            return $time->format($format);
        }
        $now = $this->nice_date('now',$utc);
        // Get difference of the current to supplied.
        $diff = $now->format('U') - $time->format('U');
        $absolute = abs($diff);
        if (is_nan($diff)) return _('Not a number');
        if (!$this->validDate($time)) return _('No Data');
        $date = $time->format('Y/m/d');
        if ($now->format('Y/m/d') == $date) {
            if (0 <= $diff && $absolute < 60) return 'Moments ago';
            else if ($diff < 0 && $absolute < 60) return 'Seconds from now';
            else if ($absolute < 3600) return $this->humanify($diff / 60,'minute');
            else return $this->humanify($diff / 3600,'hour');
        }
        $dayAgo = clone $now;
        $dayAgo->modify('-1 day');
        $dayAhead = clone $now;
        $dayAhead->modify('+1 day');
        if ($dayAgo->format('Y/m/d') == $date) return 'Ran Yesterday at '.$time->format('H:i');
        else if ($dayAhead->format('Y/m/d') == $date) return 'Runs today at '.$time->format('H:i');
        else if ($absolute / 86400 <= 7) return $this->humanify($diff / 86400,'day');
        else if ($absolute / 604800 <= 5) return $this->humanify($diff / 604800,'week');
        else if ($absolute / 2628000 < 12) return $this->humanify($diff / 2628000,'month');
        return $this->humanify($diff / 31536000,'year');
    }
    protected function validDate($Date, $format = '') {
        if ($format == 'N') return ($Date instanceof DateTime ? ($Date->format('N') >= 0 && $Date->format('N') <= 7) : $Date >= 0 && $Date <= 7);
        if (!$Date instanceof DateTime) $Date = $this->nice_date($Date);
        if (!$format) $format = 'm/d/Y';
        return DateTime::createFromFormat($format,$Date->format($format),self::getClass('DateTimeZone',self::$TimeZone));
    }
    protected function pluralize($count,$text,$space = false) {
        return sprintf("%d %s%s%s",(int)$count,$text,(int)$count != 1 ? 's' : '',$space === true ? ' ' : '');
    }
    protected function diff($start, $end, $ago = false) {
        if (!$start instanceof DateTime) $start = $this->nice_date($start);
        if (!$end instanceof DateTime) $end = $this->nice_date($end);
        $Duration = $start->diff($end);
        $str = '';
        $suffix = '';
        if ($ago === true) {
            if ($Duration->invert) $suffix = 'ago';
            if (($v = $Duration->y) > 0) return sprintf('%s %s',$this->pluralize($v,'year'),$suffix);
            if (($v = $Duration->m) > 0) return sprintf('%s %s',$this->pluralize($v,'month'),$suffix);
            if (($v = $Duration->d) > 0) return sprintf('%s %s',$this->pluralize($v,'day'),$suffix);
            if (($v = $Duration->h) > 0) return sprintf('%s %s',$this->pluralize($v,'hour'),$suffix);
            if (($v = $Duration->i) > 0) return sprintf('%s %s',$this->pluralize($v,'minute'),$suffix);
            return sprintf('%s %s',$this->pluralize($Duration->s,'second'),$suffix);
        } else if ($ago === false) {
            if (($v = $Duration->y) > 0) $str .= $this->pluralize($v,'year',true);
            if (($v = $Duration->m) > 0) $str .= $this->pluralize($v,'month',true);
            if (($v = $Duration->d) > 0) $str .= $this->pluralize($v,'day',true);
            if (($v = $Duration->h) > 0) $str .= $this->pluralize($v,'hour',true);
            if (($v = $Duration->i) > 0) $str .= $this->pluralize($v,'minute',true);
            if (($v = $Duration->s) > 0) $str .= $this->pluralize($v,'second');
            return $str;
        }
    }
    protected function humanify($diff, $unit) {
        $before = _($diff < 0 ? 'In ' : '');
        $after = _($diff > 0 ? ' ago' : '');
        $diff = floor(abs($diff));
        if ($diff > 1) $unit .= 's';
        return sprintf('%s%d %s%s',$before,$diff,$unit,$after);
    }
    protected function endsWith($str, $sub) {
        return (bool)(substr($str,strlen($str)-strlen($sub)) === $sub);
    }
    protected function getFTPByteSize($StorageNode,$file) {
        try {
            if (!$StorageNode->isValid()) throw new Exception(_('No storage node'));
            self::$FOGFTP
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'))
                ->set('host',$StorageNode->get('ip'));
            if (!self::$FOGFTP->connect()) throw new Exception(_('Cannot connect to node.'));
            $size = $this->formatByteSize((double)self::$FOGFTP->size($file));
        } catch (Exception $e) {
            return $e->getMessage();
        }
        self::$FOGFTP->close();
        return $size;
    }
    protected function array_filter_recursive(&$input,$keepkeys = false) {
        $input = (array)$input;
        array_map(function(&$value) {
            if (is_array($value)) $value = $this->array_filter_recursive($value,$keepkeys);
            unset($input);
        },$input);
        $input = array_filter($input);
        if (!$keepkeys) $input = array_values($input);
        return $input;
    }
    protected function array_change_key(&$array, $old_key, $new_key) {
        $array[$new_key] = count(preg_grep('#text/plain#i',headers_list())) > 0 || self::$service ? html_entity_decode($array[$old_key],ENT_QUOTES,'UTF-8') : $array[$old_key];
        if ($old_key != $new_key) unset($array[$old_key]);
    }
    protected function byteconvert($kilobytes) {
        return (($kilobytes / 8) * 1024);
    }
    protected function hex2bin($hex) {
        $hex2bin = function($keyToUnhex) {
            if (function_exists('hex2bin')) return hex2bin($keyToUnhex);
            $n = strlen($keyToUnhex);
            $i = 0;
            $sbin = '';
            while ($i<$n) {
                $a = substr($hex,$i,2);
                $sbin .= @pack('H*',$a);
                $i += 2;
            }
            return $sbin;
        };
        return $hex2bin($hex);
    }
    protected function createSecToken() {
        $token = sprintf('%s%s',md5(uniqid(mt_rand(), true)),md5(uniqid(mt_rand(),true)));
        return trim(bin2hex($token));
    }
    protected function encryptpw($pass) {
        $decrypt = $this->aesdecrypt($pass);
        $newpass = $pass;
        if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true)) $newpass = $decrypt;
        return ($newpass ? $this->aesencrypt($newpass) : '');
    }
    public function aesencrypt($data,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC) {
        $iv_size = mcrypt_get_iv_size($enctype,$mode);
        if (!$key) {
            $addKey = true;
            $key = openssl_random_pseudo_bytes($iv_size,$cstrong);
        } else $key = $this->hex2bin($key);
        $iv = mcrypt_create_iv($iv_size,MCRYPT_DEV_URANDOM);
        $cipher = mcrypt_encrypt($enctype,$key,$data,$mode,$iv);
        return sprintf('%s|%s%s',bin2hex($iv),bin2hex($cipher),($addKey ? sprintf('|%s',bin2hex($key)) : ''));
    }
    public function aesdecrypt($encdata,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC) {
        $iv_size = mcrypt_get_iv_size($enctype,$mode);
        $data = explode('|',$encdata);
        $iv = @pack('H*',$data[0]);
        $encoded = @pack('H*',$data[1]);
        if (!$key && $data[2]) $key = @pack('H*',$data[2]);
        if (empty($key)) return '';
        $decipher = mcrypt_decrypt($enctype,$key,$encoded,$mode,$iv);
        return html_entity_decode($decipher,ENT_QUOTES,'UTF-8');
    }
    protected function certEncrypt($data,$Host) {
        if (!$Host || !$Host->isValid()) throw new Exception('#!ih');
        if (!$Host->get('pub_key')) throw new Exception('#!ihc');
        return $this->aesencrypt($data,$Host->get('pub_key'));
    }
    protected function certDecrypt($data,$padding = true) {
        if ($padding) $padding = OPENSSL_PKCS1_PADDING;
        else $padding = OPENSSL_NO_PADDING;
        $data = $this->hex2bin($data);
        $paths = array_map('trim',(array)$this->getSubObjectIDs('StorageNode',array('isEnabled'=>1),'sslpath'));
        foreach ($paths AS &$path) {
            $sslfile = sprintf('%s%s.srvprivate.key',$path,DIRECTORY_SEPARATOR);
            if (file_exists($sslfile)) {
                unset($path);
                break;
            }
            unset($sslfile,$path);
        }
        if (!$sslfile) throw new Exception(_('Private key not found'));
        if (!is_readable($sslfile)) throw new Exception(_('Private key not readable'));
        if (!($priv_key = openssl_pkey_get_private(file_get_contents($sslfile)))) throw new Exception(_('Private key failed'));
        $a_key = openssl_pkey_get_details($priv_key);
        $chunkSize = ceil($a_key['bits']/8);
        $output = '';
        while ($data) {
            $chunk = substr($data,0,$chunkSize);
            $data = substr($data,$chunkSize);
            $decrypt = '';
            if (!openssl_private_decrypt($chunk,$decrypt,$priv_key,$padding)) throw new Exception(_('Failed to decrypt data'));
            $output .= $decrypt;
        }
        openssl_free_key($priv_key);
        return $output;
    }
    protected function parseMacList($stringlist,$image = false,$client = false) {
        $MAClist = array();
        $MACs = $stringlist;
        $lowerAndTrim = function($element) {
            return strtolower(trim($element));
        };
        if (!is_array($stringlist) && strpos($stringlist,'|')) $MACs = array_values(array_filter(array_unique(array_map($lowerAndTrim,(array)explode('|',$stringlist)))));
        if ($client) {
            $ClientIgnoredMACs = array_map($lowerAndTrim,(array)$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$MACs,'clientIgnore'=>1),'mac'));
            $MACs = array_diff((array)$MACs,(array)$ClientIgnoredMACs);
            unset($ClientIgnoredMACs);
        }
        if ($image) {
            $ImageIgnoredMACs = array_map($lowerAndTrim,(array)$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$MACs,'imageIgnore'=>1),'mac'));
            $MACs = array_diff((array)$MACs,(array)$ImageIgnoredMACs);
            unset($ImageIgnoredMACs);
        }
        $MACs = array_values(array_unique(array_filter((array)$MACs)));
        $Ignore = (array)array_filter(array_map($lowerAndTrim,(array)explode(',',$this->getSetting('FOG_QUICKREG_PENDING_MAC_FILTER'))));
        if (count($Ignore)) $MACs = array_values(array_unique(array_filter(array_diff((array)$MACs,preg_grep(sprintf('#%s#i',implode('|',(array)$Ignore)),$MACs)))));
        $MACs = preg_grep('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$|^([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}$|^[a-fA-F0-9]{12}$|^([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}$/',(array)$MACs);
        if (!count($MACs)) return false;
        return (array)$MACs;
    }
    protected function sendData($datatosend,$service = true) {
        if ($service) {
            $Host = $this->getHostItem();
            if ($this->nice_date() >= $this->nice_date($Host->get('sec_time'))) $Host->set('pub_key','')->save();
            if (isset($_REQUEST['newService'])) printf('#!enkey=%s',$this->certEncrypt($datatosend,$Host));
            else echo $datatosend;
            exit;
        }
    }
    protected function array_strpos($haystack, $needles, $case = true) {
        $needles = (array)$needles;
        foreach($needles AS $i => &$needle) {
            if ($case) return (bool)strpos($haystack,$needle) !== false;
            else return (bool)stripos($haystack,$needle) !== false;
            unset($needle);
        }
        return false;
    }
    protected function log($txt, $level = 1) {
        if (self::$ajax) return;
        $txt = trim(preg_replace(array("#\r#","#\n#",'#\s+#','# ,#'),array('',' ',' ',','),$txt));
        if (empty($txt)) return;
        $txt = sprintf('[%s] %s',$this->nice_date()->format('Y-m-d H:i:s'),$txt);
        if ($this->logLevel >= $level) echo $txt;
        //$this->logHistory($txt);
    }
    protected function logHistory($string) {
        $string = htmlentities(mb_convert_encoding($string,'UTF-8'),ENT_QUOTES,'UTF-8');
        $name = $_SESSION['FOG_USERNAME'] ? $_SESSION['FOG_USERNAME'] : 'fog';
        if (self::$DB) {
            self::getClass('History')
                ->set('info',$string)
                ->set('ip',$_SERVER['REMOTE_ADDR'])
                ->save();
        }
    }
    public function orderBy(&$orderBy) {
        if (empty($orderBy)) {
            $orderBy = 'name';
            if (!array_key_exists($orderBy,$this->databaseFields)) $orderBy = 'id';
        } else {
            if (!is_array($orderBy)) {
                $orderBy = trim($orderBy);
                if (!array_key_exists($orderBy,$this->databaseFields)) $orderBy = 'name';
                if (!array_key_exists($orderBy,$this->databaseFields)) $orderBy = 'id';
            }
        }
    }
    public function getSubObjectIDs($object = 'Host',$findWhere = array(),$getField = 'id',$not = false,$operator = 'AND',$orderBy = 'name',$groupBy = false,$filter = 'array_unique') {
        if (empty($object)) $object = 'Host';
        if (empty($getField)) $getField = 'id';
        if (empty($operator)) $operator = 'AND';
        return self::getClass($object)->getManager()->find($findWhere,$operator,$orderBy,'','',$groupBy,$not,$getField,'',$filter);
    }
    public function getSetting($key) {
        $value = $this->getSubObjectIDs('Service',array('name'=>$key),'value');
        return trim(html_entity_decode(mb_convert_encoding(str_replace('\r\n',"\n",array_shift($value)),'UTF-8'),ENT_QUOTES,'UTF-8'));
    }
    public function setSetting($key, $value) {
        self::getClass('ServiceManager')->update(array('name'=>$key),'',array('value'=>trim($value)));
        return $this;
    }
    public function getQueuedStates() {
        return (array)self::getClass('TaskState')->getQueuedStates();
    }
    public function getQueuedState() {
        return self::getClass('TaskState')->getQueuedState();
    }
    public function getCheckedInState() {
        return self::getClass('TaskState')->getCheckedInState();
    }
    public function getProgressState() {
        return self::getClass('TaskState')->getProgressState();
    }
    public function getCompleteState() {
        return self::getClass('TaskState')->getCompleteState();
    }
    public function getCancelledState() {
        return self::getClass('TaskState')->getCancelledState();
    }
    public function string_between($string, $start, $end) {
        $string = " $string";
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }
}
