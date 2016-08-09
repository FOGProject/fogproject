<?php
class FOGURLRequests extends FOGBase {
    private $window_size = 5;
    private $timeout = 15;
    private $callback;
    private $response = array();
    public $options = array(
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 15,
    );
    private $headers = array();
    private $requests = array();
    private $requestMap = array();
    public function __construct($callback = null) {
        parent::__construct();
        $this->callback = $callback;
    }
    public function __destruct() {
        unset($this->window_size,$this->callback,$this->options,$this->headers,$this->request);
    }
    public function __get($name) {
        return (isset($this->{$name})) ? $this->{$name} : null;
    }
    public function __set($name, $value) {
        $this->{$name} = in_array($name,array('options','headers')) ? $value + $this->{$name} : $value;
        return true;
    }
    public function add($request) {
        $this->requests[] = $request;
        return true;
    }
    public function request($url, $method = 'GET', $post_data = null, $headers = null, $options = null) {
        $this->requests[] = self::getClass('FOGRollingURL',$url,$method,$post_data,$headers,$options);
        return true;
    }
    public function get($url, $headers = null, $options = null) {
        return $this->request($url, 'GET', null, $headers, $options);
    }
    public function post($url, $post_data = null, $headers = null, $options = null) {
        return $this->request($url, 'POST', $post_data, $headers, $options);
    }
    public function execute($window_size = null) {
        if (sizeof($this->requests) < 1) return;
        return sizeof($this->requests) == 1 ? $this->single_curl() : $this->rolling_curl($window_size);
    }
    private function single_curl() {
        $ch = curl_init();
        $request = array_shift($this->requests);
        $options = $this->get_options($request);
        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($this->callback && is_callable($this->callback)) $this->callback($output,$info,$request);
        else return (array)$output;
        return true;
    }
    private function rolling_curl($window_size = null) {
        if ($window_size) $this->window_size = $window_size;
        if (sizeof($this->requests) < $this->window_size) $this->window_size = sizeof($this->requests);
        if ($this->window_size < 2) throw new Exception(_('Window size must be greater than 1'));
        $master = curl_multi_init();
        for ($i = 0; $i < $this->window_size; $i++) {
            $ch = curl_init();
            $options = $this->get_options($this->requests[$i]);
            curl_setopt_array($ch,$options);
            curl_multi_add_handle($master,$ch);
            $key = (string)$ch;
            $this->requestMap[$key] = $i;
        }
        do {
            while (($execrun = curl_multi_exec($master,$running)) == CURLM_CALL_MULTI_PERFORM);
            if ($execrun != CURLM_OK) break;
            while ($done = curl_multi_info_read($master)) {
                $info = curl_getinfo($done['handle']);
                $output = curl_multi_getcontent($done['handle']);
                $key = (string)$done['handle'];
                $this->response[$this->requestMap[$key]] = $output;
                if ($this->callback && is_callable($this->callback)) {
                    $request = $this->requests[$this->requestMap[$key]];
                    unset($this->requestMap[$key]);
                    $this->callback($output,$info,$request);
                }
                if ($i < sizeof($this->requests) && isset($this->requests[$i]) && $i < count($this->requests)) {
                    $ch = curl_init();
                    $options = $this->get_options($this->requests[$i]);
                    curl_setopt_array($ch,$options);
                    curl_multi_add_handle($master,$ch);
                    $key = (string)$ch;
                    $this->requestMap[$key] = $i;
                    $i++;
                }
                curl_multi_remove_handle($master,$done['handle']);
            }
            if ($running) curl_multi_select($master,$this->timeout);
        } while ($running);
        ksort($this->response);
        curl_multi_close($master);
        return $this->response;
    }
    private function get_options($request) {
        $options = $this->__get('options');
        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        $url = $this->valid_url($request->url);
        $headers = $this->__get('headers');
        if ($request->options) $options = $request->options + $options;
        $options[CURLOPT_URL] = $url;
        if ($request->post_data) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->post_data;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        list($ip,$password,$port,$username) = self::getSubObjectIDs('Service',array('name'=>array('FOG_PROXY_IP','FOG_PROXY_PASSWORD','FOG_PROXY_PORT','FOG_PROXY_USERNAME')),'value',false,'AND','name',false,false);
        $IPs = self::getSubObjectIDs('StorageNode','','ip');
        if (false !== stripos(implode('|',$IPs),$url)) {
            $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_PROXYPORT] = $port;
            $options[CURLOPT_PROXY] = $ip;
            if ($username) $options[CURLOPT_PROXYUSERPWD] = sprintf('%s:%s',$username,$password);
        }
        return $options;
    }
    private function valid_url(&$URL) {
        $URL = filter_var($URL,FILTER_SANITIZE_URL);
        if (filter_var($URL,FILTER_VALIDATE_URL) === false) unset($URL);
        return $URL;
    }
    public function process($urls, $method = 'GET',$data = null,$sendAsJSON = false,$auth = false,$callback = false,$file = false) {
        if ($callback && is_callable($callback)) $this->callback = $callback;
        if ($auth) $this->options[CURLOPT_USERPWD] = $auth;
        if ($sendAsJSON) {
            $data2 = json_encode($data);
            $datalen = strlen($data2);
            $this->options[CURLOPT_HEADER] = true;
            $this->options[CURLOPT_HTTPHEADER] = array(
                'Content-Type: application/json',
                "Content-Length: $datalen",
                'Expect:'
            );
        }
        if ($file) {
            $this->options[CURLOPT_FILE] = $file;
            $this->options[CURLOPT_TIMEOUT] = 300;
        }
        foreach ((array)$urls AS $url) {
            $request = self::getClass('FOGRollingURL',$url);
            if ($method === 'GET') $this->get($url);
            else $this->post($url,$data);
        }
        return $this->execute();
    }
    public function isAvailable($url) {
        $this->timeout = 1;
        $request = self::getClass('FOGRollingURL',$url);
        $request->options[CURLOPT_HEADER] = true;
        $request->options[CURLOPT_NOBODY] = true;
        $request->options[CURLOPT_CONNECTTIMEOUT] = 1;
        $request->options[CURLOPT_TIMEOUT] = 1;
        $this->add($request);
        return $this->execute();
    }
    public function download($file,$chunks = 2048) {
        set_time_limit(0);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-disposition: attachment; filename='.basename($file));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        header('Pragma: public');
        while ($i <= $size) {
            $this->get_chunk($file,(($i==0) ? $i : $i+1),$i+$chunks);
            $i += $chunks;
        }
    }
    private function get_chunk($file,$start,$end) {
        $origContext = $this->options;
        $this->options[CURLOPT_URL] = $file;
        $this->options[CURLOPT_RANGE] = $start.'-'.$end;
        $this->options[CURLOPT_BINARYTRANSFER] = true;
        $this->options[CURLOPT_WRITEFUNCTION] = array($this,'chunk');
        curl_setopt_array($ch,$this->options);
        $result = curl_exec($ch);
        curl_close($ch);
        $this->options = $origContext;
    }
    private function chunk($ch, $str) {
        echo $str;
        return strlen($str);
    }
}
