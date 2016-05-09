<?php
class FOGURLRequests extends FOGBase {
    private $handle;
    private $contextOptions;
    public function __construct() {
        parent::__construct();
        $this->handle = @curl_multi_init();
        $this->contextOptions = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT_MS => 10000000001,
            CURLOPT_TIMEOUT_MS => 10000000000,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_HEADER => false,
        );
    }
    public function __destruct() {
        @curl_multi_close($this->handle);
    }
    private function validURL(&$URL) {
        $URL = filter_var($URL,FILTER_SANITIZE_URL);
        if (filter_var($URL,FILTER_VALIDATE_URL) === false) unset($URL);
        return $URL;
    }
    private function proxyInfo(&$URL) {
        try {
            if (!self::$DB) throw new Exception(_('Unable to connect to the DB'));
            list($ip,$password,$port,$username) = self::getSubObjectIDs('Service',array('name'=>array('FOG_PROXY_IP','FOG_PROXY_PASSWORD','FOG_PROXY_PORT','FOG_PROXY_USERNAME')),'value',false,'AND','name',false,false);
            if ($ip && filter_var($ip,FILTER_VALIDATE_IP) === false) throw new Exception(_('Invalid Proxy IP'));
            $IPs = self::getSubObjectIDs('StorageNode','','ip');
            if (preg_match(sprintf('#^(?!.*%s)#i',implode('|',(array)$IPs)),$URL)) return false;
            $this->contextOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
            $this->contextOptions[CURLOPT_PROXYPORT] = $port;
            $this->contextOptions[CURLOPT_PROXY] = $ip;
            if ($username) $this->contextOPtions[CURLOPT_PROXYUSERPWD] = sprintf('%s:%s',$username,$password);
            return true;
        } catch (Exception $e) {
            die($e->getMessage());
        }
        return false;
    }
    public function process($urls, $method = 'GET',$data = null,$sendAsJSON = false,$auth = false,$callback = false,$file = false) {
        if (!is_array($urls)) $urls = array($urls);
        if (empty($method)) $method = 'GET';
        array_map(function(&$url) use ($urls,$method,$data,$sendAsJSON,$auth,$callback,$file,&$curl) {
            $this->validURL($url);
            if (!$this->isAvailable($url)) return;
            if ($method == 'GET' && $data !== null) $url = sprintf('%s?%s',$url,http_build_query((array)$data));
            $ch = @curl_init($url);
            $this->contextOptions[CURLOPT_URL] = $url;
            if ($auth) $this->contextOptions[CURLOPT_USERPWD] = $auth;
            if ($file) {
                $this->contextOptions[CURLOPT_FILE] = $file;
                $this->contextOptions[CURLOPT_TIMEOUT_MS] = 30000000000;
            }
            if ($method == 'POST' && $data !== null) {
                if ($sendAsJSON) {
                    $data = json_encode($data);
                    $this->contextOptions[CURLOPT_HTTPHEADER] = array(
                        'Content-Type: application/json',
                        'Content-Length: '.strlen($data),
                        'Expect:',
                    );
                }
                $this->contextOptions[CURLOPT_POST] = true;
                $this->contextOptions[CURLOPT_POSTFIELDS] = $data;
            }
            $this->contextOptions[CURLOPT_CUSTOMREQUEST] = $method;
            curl_setopt_array($ch,$this->contextOptions);
            $curl[] = $ch;
            curl_multi_add_handle($this->handle,$ch);
            unset($url);
        },(array)$urls);
        $active = null;
        $response = array();
        do {
            curl_multi_exec($this->handle,$active);
        } while ($active > 0);
        array_map(function(&$val) use (&$response) {
            $response[] = curl_multi_getcontent($val);
            curl_multi_remove_handle($this->handle,$val);
        },(array)$curl);
        if (!$file) return $response;
        @fclose($file);
    }
    public function isAvailable($URL) {
        $this->validURL($URL);
        $this->proxyInfo($URL);
        $origContext = $this->contextOptions;
        $ch = curl_init($URL);
        $this->contextOptions[CURLOPT_URL] = $URL;
        $this->contextOptions[CURLOPT_HEADER] = true;
        $this->contextOptions[CURLOPT_NOBODY] = true;
        $this->contextOptions[CURLOPT_RETURNTRANSFER] = true;
        curl_setopt_array($ch,$this->contextOptions);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->contextOptions = $origContext;
        if ($response) return true;
        return false;
    }
}
