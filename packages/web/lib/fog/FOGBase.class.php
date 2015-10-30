<?php
abstract class FOGBase {
    protected $debug = false;
    protected $info = false;
    protected $FOGFTP;
    protected $FOGCore;
    protected $DB;
    protected $HookManager;
    protected $EventManager;
    protected $FOGUser;
    protected $FOGPageManager;
    protected $FOGURLRequests;
    protected $FOGSubMenu;
    protected $foglang;
    protected $isMobile;
    protected $isLoaded = array();
    protected $searchPages = array(
        'user',
        'host',
        'group',
        'image',
        'snapin',
        'printer',
        'task',
        'hosts',
        'tasks',
    );
    public $ajax = false;
    public $post = false;
    public $service = false;
    public function __construct() {
        global $foglang;
        global $FOGFTP;
        global $FOGCore;
        global $DB;
        global $currentUser;
        global $HookManager;
        global $EventManager;
        global $FOGURLRequests;
        global $FOGPageManager;
        global $TimeZone;
        $this->foglang = &$foglang;
        $this->FOGFTP = &$FOGFTP;
        $this->FOGCore = &$FOGCore;
        $this->DB = &$DB;
        $this->FOGUser = &$currentUser;
        $this->EventManager = &$EventManager;
        $this->HookManager = &$HookManager;
        $this->FOGURLRequests = &$FOGURLRequests;
        $this->FOGPageManager = &$FOGPageManager;
        $this->TimeZone = &$TimeZone;
        $this->isMobile = (bool)preg_match('#/mobile/#i',$_SERVER['PHP_SELF']);
        $this->service = (bool)preg_match('#/service/#i', $_SERVER['PHP_SELF']);
        $this->ajax = (bool)preg_match('#^xmlhttprequest$#i',$_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->post = (bool)preg_match('#^post$#i',$_SERVER['REQUEST_METHOD']);
    }
    public function __toString() {
        return (string)get_class($this);
    }
    public function getClass($class, $data = '') {
        $args = func_get_args();
        array_shift($args);
        $r = new ReflectionClass($class);
        return $r->getConstructor() ? (count($args) ? $r->newInstanceArgs($args) : $r->newInstance($data)) : $r->newInstanceWithoutConstructor();
    }
    public function getHostItem($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false,$override = false) {
        $mac = $_REQUEST[mac];
        if ($encoded === true) $mac = base64_decode($mac);
        $mac = trim($mac);
        $MACs = $this->parseMacList($mac,!$service,$service);
        if (!$MACs && !$hostnotrequired) throw new Exception($service ? '#!im' : $this->foglang['InvalidMAC'].' '.$_REQUEST[mac]);
        if ($returnmacs) return (is_array($MACs) ? $MACs : array($MACs));
        $Host = $this->getClass('HostManager')->getHostByMacAddresses($MACs);
        if (!$hostnotrequired && (!$Host || !$Host->isValid() || $Host->get(pending)) && !$override) throw new Exception($service ? '#!ih' : _('Invalid Host'));
        return $Host;
    }
    public function getAllBlamedNodes() {
        $Host = $this->getHostItem(false);
        $NodeFailures = $this->getClass(NodeFailureManager)->find(array(taskID=>$Host->get(task)->get(id),hostID=>$Host->get(id)));
        $DateInterval = $this->nice_date()->modify('-5 minutes');
        foreach($NodeFailures AS $i => &$NodeFailure) {
            $DateTime = $this->nice_date($NodeFailure->get(failureTime));
            if ($DateTime >= $DateInterval) {
                $node = $NodeFailure->get(id);
                if (!in_array($node,(array)$nodeRet)) $nodeRet[] = $node;
            } else $NodeFailure->destroy();
        }
        unset($NodeFailure);
        return $nodeRet;
    }
    protected function getActivePlugins() {
        return array_map('strtolower',$this->getClass('PluginManager')->find(array('installed'=>1),'','','','','','','name'));
    }
    protected function fatalError($txt, $data = array()) {
        if (!$this->service && !$this->ajax) {
            echo sprintf('<div class="debug-error">FOG FATAL ERROR: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function error($txt, $data = array()) {
        if ($this->debug && !$this->service && !$this->ajax) {
            echo sprintf('<div class="debug-error">FOG ERROR: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function debug($txt, $data = array()) {
        if ($this->debug && !$this->service && !$this->ajax) {
            echo sprintf('<div class="debug-error">FOG DEBUG: %s: %s</div>',
                get_class($this),
                (count($data) ? vsprintf($txt, (is_array($data) ? $data : array($data))) : $txt)
            );
        }
    }
    protected function info($txt, $data = array()) {
        if ($this->info && !$this->service && !$this->ajax) {
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
        $messages = $_SESSION['FOG_MESSAGES'];
        unset($_SESSION['FOG_MESSAGES']);
        if ($this->HookManager instanceof HookManager) $this->HookManager->processEvent('MessageBox',array('data'=>&$messages));
        foreach ((array)$messages AS $i => &$message) {
            if (!$i) echo '<!-- FOG Messages -->';
            echo '<div class="fog-message-box">'.$message.'</div>';
        }
        unset($message);
    }
    protected function redirect($url = '') {
        if (empty($url)) $url = sprintf('%s?%s',$_SERVER['PHP_SELF'],$_SERVER['QUERY_STRING']);
        if (!headers_sent() && !$this->service) {
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
        if ($this->binary_search($key, $array) > -1) {
            $new = array();
            foreach ($array as $k => &$value) {
                if ($k === $key) $new[$new_key] = $new_value;
                $new[$k] = $value;
            }
            unset($value);
            return $new;
        }
        return false;
    }
    protected function array_insert_after($key, array &$array, $new_key, $new_value) {
        if ($this->binary_search($key, $array) > -1) {
            $new = array();
            foreach ($array as $k => &$value) {
                $new[$k] = $value;
                if ($k === $key) $new[$new_key] = $new_value;
            }
            unset($value);
            return $new;
        }
        return false;
    }
    protected function array_remove($key, array &$array) {
        if (is_array($key)) {
            foreach ($key AS $k => &$value) unset($array[$value]);
            unset($value);
        } else {
            foreach ($array AS $k => &$value) {
                if (is_array($value)) $this->array_remove($key, $value);
                else unset($array[$key]);
            }
            unset($value);
        }
    }
    protected function binary_search($needle, $haystack) {
        $left = 0;
        $right = sizeof($haystack) - 1;
        $values = array_values($haystack);
        $keys = array_keys($haystack);
        while ($left <= $right) {
            $mid = $left + $right >> 1;
            if ($mid == $needle) return $mid;
            elseif ($values[$mid] == $needle) return $keys[$mid];
            elseif ($mid > $needle || $values[$mid] > $needle) $right = $mid - 1;
            elseif ($mid < $needle || $values[$mid] < $needle) $left = $mid + 1;
        }
        return -1;
    }
    protected function isLoaded($key) {
        $this->isLoaded[$key] = (isset($this->isLoaded[$key]) ? true : false);
        return $this->isLoaded[$key];
    }
    protected function resetRequest() {
        $reqVars = $_REQUEST;
        unset($_REQUEST);
        foreach((array)$_SESSION['post_request_vals'] AS $key => &$val) $_REQUEST[$key] = $val;
        unset($val);
        foreach((array)$reqVars AS $key => &$val) $_REQUEST[$key] = $val;
        unset($val);
        unset($_SESSION['post_request_vals'], $reqVars);
    }
    protected function setRequest() {
        if (!$_SESSION['post_request_vals'] && $this->post) $_SESSION['post_request_vals'] = $_REQUEST;
    }
    protected function formatByteSize($size) {
        $units = array('iB','KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB');
        $factor = floor((strlen($size) - 1)/3);
        return sprintf('%3.2f %s',$size/pow(1024,$factor),@$units[$factor]);
    }
    protected function getGlobalModuleStatus($names = false) {
        return array(
            'dircleanup' => !$names ? $this->getSetting('FOG_SERVICE_DIRECTORYCLEANER_ENABLED') : 'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
            'usercleanup' => !$names ? $this->getSetting('FOG_SERVICE_USERCLEANUP_ENABLED') : 'FOG_SERVICE_USERCLEANUP_ENABLED',
            'displaymanager' => !$names ? $this->getSetting('FOG_SERVICE_DISPLAYMANAGER_ENABLED') : 'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
            'autologout' => !$names ? $this->getSetting('FOG_SERVICE_AUTOLOGOFF_ENABLED') : 'FOG_SERVICE_AUTOLOGOFF_ENABLED',
            'greenfog' => !$names ? $this->getSetting('FOG_SERVICE_GREENFOG_ENABLED') : 'FOG_SERVICE_GREENFOG_ENABLED',
            'hostnamechanger' => !$names ? $this->getSetting('FOG_SERVICE_HOSTNAMECHANGER_ENABLED') : 'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
            'snapinclient' => !$names ? $this->getSetting('FOG_SERVICE_SNAPIN_ENABLED') : 'FOG_SERVICE_SNAPIN_ENABLED',
            'clientupdater' => !$names ? $this->getSetting('FOG_SERVICE_CLIENTUPDATER_ENABLED') : 'FOG_SERVICE_CLIENTUPDATER_ENABLED',
            'hostregister' => !$names ? $this->getSetting('FOG_SERVICE_HOSTREGISTER_ENABLED') : 'FOG_SERVICE_HOSTREGISTER_ENABLED',
            'printermanager' => !$names ? $this->getSetting('FOG_SERVICE_PRINTERMANAGER_ENABLED') : 'FOG_SERVICE_PRINTERMANAGER_ENABLED',
            'taskreboot' => !$names ? $this->getSetting('FOG_SERVICE_TASKREBOOT_ENABLED') : 'FOG_SERVICE_TASKREBOOT_ENABLED',
            'usertracker' => !$names ? $this->getSetting('FOG_SERVICE_USERTRACKER_ENABLED') : 'FOG_SERVICE_USERTRACKER_ENABLED',
        );
    }
    public function nice_date($Date = 'now',$utc = false) {
        $TZ = $this->getClass('DateTimeZone',($utc ? 'UTC' : $this->TimeZone));
        return $this->getClass('DateTime',$Date,$TZ);
    }
    public function formatTime($time, $format = false, $utc = false) {
        if (!$time instanceof DateTime) $time = $this->nice_date($time,$utc);
        if ($format) return $time->format($format);
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
        // Forced format
        if (!$this->validDate($time)) return 'No Data';
        $CurrTime = $this->nice_date('now',$utc);
        if ($time < $CurrTime) $TimeVal = $CurrTime->diff($time);
        if ($time > $CurrTime) $TimeVal = $time->diff($CurrTime);
        return ($time > $CurrTime ? _('Next Run Time: ') : _('Ran At: ')).$time->format('Y-m-d H:i:s');
    }
    protected function validDate($Date, $format = '') {
        if ($format == 'N') return ($Date instanceof DateTime ? ($Date->format('N') >= 0 && $Date->format('N') <= 7) : $Date >= 0 && $Date <= 7);
        if (!$Date instanceof DateTime) $Date = $this->nice_date($Date);
        if (!$format) $format = 'm/d/Y';
        return DateTime::createFromFormat($format,$Date->format($format),$this->getClass(DateTimeZone,$this->TimeZone));
    }
    protected function diff($start, $end) {
        if (!$start instanceof DateTime) $start = $this->nice_date($start);
        if (!$end instanceof DateTime) $end = $this->nice_date($end);
        $Duration = $start->diff($end);
        return $Duration->format('%H:%I:%S');
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
            if (!$StorageNode || !$StorageNode->isValid()) throw new Exception('No Storage Node');
            $this->FOGFTP
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'))
                ->set('host',$StorageNode->get('ip'));
            if (!$this->FOGFTP->connect()) throw new Exception(_('Cannot connect to node.'));
            $size = $this->formatByteSize((double)$this->FOGFTP->size($file));
        } catch (Exception $e) {
            return $e->getMessage();
        }
        $this->FOGFTP->close();
        return $size;
    }
    protected function array_filter_recursive(&$input,$keepkeys = false) {
        foreach($input AS $i => &$value) {
            if (is_array($value)) $value = $this->array_filter_recursive($value);
        }
        unset($value);
        $input = array_filter($input);
        if (!$keepkeys) $input = array_values($input);
        return $input;
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
        $token = md5(uniqid(mt_rand(), true)).md5(uniqid(mt_rand(),true));
        return trim(bin2hex($token));
    }
    protected function encryptpw($pass) {
        $decrypt = $this->aesdecrypt($pass);
        $newpass = $pass;
        if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true)) $newpass = $decrypt;
        return ($newpass ? $this->aesencrypt($newpass) : '');
    }
    protected function aesencrypt($data,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC) {
        $iv_size = mcrypt_get_iv_size($enctype,$mode);
        if (!$key) {
            $addKey = true;
            $key = openssl_random_pseudo_bytes($iv_size,$cstrong);
        } else $key = $this->hex2bin($key);
        $iv = mcrypt_create_iv($iv_size,MCRYPT_DEV_URANDOM);
        $cipher = mcrypt_encrypt($enctype,$key,$data,$mode,$iv);
        return bin2hex($iv).'|'.bin2hex($cipher).($addKey ? '|'.bin2hex($key) : '');
    }
    protected function aesdecrypt($encdata,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC) {
        $iv_size = mcrypt_get_iv_size($enctype,$mode);
        $data = explode('|',$encdata);
        $iv = @pack('H*',$data[0]);
        $encoded = @pack('H*',$data[1]);
        if (!$key && $data[2]) {
            $key = @pack('H*',$data[2]);
            $decipher = mcrypt_decrypt($enctype,$key,$encoded,$mode,$iv);
        }
        return html_entity_decode($decipher);
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
        $path = '/'.trim($this->getSetting('FOG_SNAPINDIR'),'/');
        $path = !$path ? '/opt/fog/snapins/ssl/' : $path.'/ssl/';
        if (!$priv_key = openssl_pkey_get_private(file_get_contents($path.'.srvprivate.key'))) throw new Exception('Private Key Failed');
        $a_key = openssl_pkey_get_details($priv_key);
        $chunkSize = ceil($a_key['bits'] / 8);
        $output = '';
        while ($data) {
            $chunk = substr($data, 0, $chunkSize);
            $data = substr($data,$chunkSize);
            $decrypt = '';
            if (!openssl_private_decrypt($chunk,$decrypt,$priv_key,$padding)) throw new Exception('Failed to decrypt data');
            $output .= $decrypt;
        }
        openssl_free_key($priv_key);
        return $output;
    }
    protected function parseMacList($stringlist,$image = false,$client = false) {
        $MAClist = array();
        $MACs = $this->getClass('MACAddressAssociationManager')->find(array('mac'=>explode('|',$stringlist)));
        foreach((array)$MACs AS $i => &$MAC) {
            $MAC = $this->getClass('MACAddress',$MAC);
            if ($MAC->isValid() && (($image && !$MAC->isImageIgnored()) || ($client && !$MAC->isClientIgnored()) || (!$image && !$client))) $MAClist[] = $this->getClass('MACAddress',$MAC)->__toString();
        }
        unset($MAC);
        $MACs = explode('|',$stringlist);
        foreach((array)$MACs AS $i => &$MAC) {
            $MAC = $this->getClass('MACAddress',$MAC);
            if ($MAC->isValid() && !in_array(strtolower($MAC->__toString()),(array)$MAClist) && (($image && !$MAC->isImageIgnored()) || ($client && !$MAC->isClientIgnored()) || (!$image && !$client))) $MAClist[] = $this->getClass('MACAddress',$MAC)->__toString();
        }
        unset($MAC);
        $Ignore = array_filter(array_map('trim',explode(',',$this->getClass('FOGCore')->getSetting('FOG_QUICKREG_PENDING_MAC_FILTER'))));
        if (count($Ignore)) {
            foreach($Ignore AS $i => &$ignore) {
                $matches = preg_grep("#$ignore#i",(array)$MAClist);
                if (count($matches)) {
                    $NewMatches = array_merge((array)$NewMatches,$matches);
                    unset($matches);
                }
            }
            unset($ignore);
        }
        if (!count($MAClist)) return false;
        return array_unique(array_diff((array)$MAClist,(array)$NewMatches));
    }
    protected function sendData($datatosend,$service = true) {
        if ($service) {
            $Host = $this->getHostItem();
            if ($this->nice_date() >= $this->nice_date($Host->get(sec_time))) $Host->set(pub_key,null)->save();
            if (isset($_REQUEST['newService']) && $this->getSetting('FOG_AES_ENCRYPT')) echo "#!enkey=".$this->certEncrypt($datatosend,$Host);
            else echo $datatosend;
        }
    }
    protected function array_strpos($haystack, $needles, $case = true) {
        foreach ($needles AS $i => &$needle) {
            if ($case) return (bool)strpos($haystack,$needle) !== false;
            else return (bool)stripos($haystack,$needle) !== false;
        }
        unset($needle);
        return false;
    }
    protected function logHistory($string) {
        $name = $_SESSION['FOG_USERNAME'] ? $_SESSION['FOG_USERNAME'] : 'fog';
        if ($this->DB) {
            $this->getClass('History')
                ->set('info',strip_tags($string))
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
    public function getSubObjectIDs($object = 'Host',$findWhere = array(),$getField = 'id',$not = false,$operator = 'AND',$orderBy = 'name') {
        if (empty($object)) $object = 'Host';
        if (empty($getField)) $getField = 'id';
        if (empty($operator)) $operator = 'AND';
        return $this->getClass($object)->getManager()->find($findWhere,$operator,$orderBy,'','','',$not,$getField);
    }
    public function getSetting($key) {
        $value = $this->getSubObjectIDs('Service',array('name'=>$key),'value');
        return array_shift($value);
    }
    public function setSetting($key, $value) {
        $this->getClass('ServiceManager')->update(array('name'=>$key),'',$value);
        return $this;
    }
}
