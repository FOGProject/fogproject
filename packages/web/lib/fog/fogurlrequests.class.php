<?php
class FOGURLRequests extends FOGBase {
    private $handle;
    private $contextOptions;
    public function __construct() {
        parent::__construct();
        $this->handle = curl_multi_init();
        $this->contextOptions = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT_MS => 15001,
            CURLOPT_TIMEOUT_MS => 15000,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_HEADER => false,
        );
    }
    public function __destruct() {
        curl_multi_close($this->handle);
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
            if (stripos(implode('|',$IPs),$URL) === false) return false;
            $this->contextOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
            $this->contextOptions[CURLOPT_PROXYPORT] = $port;
            $this->contextOptions[CURLOPT_PROXY] = $ip;
            if ($username) $this->contextOptions[CURLOPT_PROXYUSERPWD] = sprintf('%s:%s',$username,$password);
            return true;
        } catch (Exception $e) {
            die($e->getMessage());
        }
        return false;
    }
    public function process($urls, $method = 'GET',$data = null,$sendAsJSON = false,$auth = false,$callback = false,$file = false) {
        $method = trim(strtolower($method));
        $method = !in_array($method,array('get','post'),true) ? 'GET' : strtoupper($method);
        if (!is_array($urls)) $urls = array($urls);
        $url_count = count($urls);
        $this->contextOptions[CURLOPT_CUSTOMREQUEST] = $method;
        for ($i = 0; $i < $url_count; $i++) {
            $ch = curl_init();
            $this->validURL($urls[$i]);
            $this->proxyInfo($urls[$i]);
            if ($auth) $this->contextOptions[$auth];
            if ($file) {
                $this->contextOptions[CURLOPT_FILE] = $file;
                $this->contextOptions[CURLOPT_TIMEOUT_MS] = 3000000000;
            }
            if ($method === 'GET' && $data !== null) {
                $urls[$i] = sprintf('%s?%s',$urls[$i],http_build_query((array)$data));
                $this->contextOptions[CURLOPT_URL] = $urls[$i];
            } else $this->contextOptions[CURLOPT_URL] = $urls[$i];
            if ($method === 'POST' && $data !== null) {
                if ($sendAsJSON) {
                    $data = json_encode($data);
                    $datalen = strlen($data);
                    $this->contextOptions[CURLOPT_HTTPHEADER] = array(
                        'Content-Type: application/json',
                        "Content-Length: $datalen",
                        'Expect:',
                    );
                }
                $this->contextOptions[CURLOPT_POST] = true;
                $this->contextOptions[CURLOPT_POSTFIELDS] = $data;
            }
            curl_setopt_array($ch,$this->contextOptions);
            curl_multi_add_handle($this->handle,$ch);
        }
        $response = array();
        do {
            while (($execrun = curl_multi_exec($this->handle,$running)) == CURLM_CALL_MULTI_PERFORM);
            if ($execrun != CURLM_OK) break;
            while ($done = curl_multi_info_read($this->handle)) {
                $info = curl_getinfo($done['handle']);
                $output = $info['http_code'] != 200 ? '' : curl_multi_getcontent($done['handle']);
                if (is_callable($callback)) $callback($output);
                $ch = curl_init();
                if ($method === 'GET' && $data !== null) {
                    $urls[$i] = sprintf('%s?%s',$urls[$i],http_build_query((array)$data));
                    $this->contextOptions[CURLOPT_URL] = $urls[$i];
                } else $this->contextOptions[CURLOPT_URL] = $urls[$i];
                if ($method === 'POST' && $data !== null) {
                    if ($sendAsJSON) {
                        $data = json_encode($data);
                        $datalen = strlen($data);
                        $this->contextOptions[CURLOPT_HTTPHEADER] = array(
                            'Content-Type: application/json',
                            "Content-Length: $datalen",
                            'Expect:',
                        );
                    }
                    $this->contextOptions[CURLOPT_POST] = true;
                    $this->contextOptions[CURLOPT_POSTFIELDS] = $data;
                }
                curl_setopt_array($ch);
                curl_multi_add_handle($this->master,$ch);
                curl_multi_remove_handle($this->handle,$done['handle']);
                $response[] = $output;
            }
            if ($running) curl_multi_select($this->handle,30);
        } while ($running);
        if (!$file) return $response;
        fclose($file);
    }
    public function isAvailable($URL) {
        $this->validURL($URL);
        $this->proxyInfo($URL);
        $origContext = $this->contextOptions;
        $ch = curl_init();
        $this->contextOptions[CURLOPT_URL] = $URL;
        $this->contextOptions[CURLOPT_HEADER] = true;
        $this->contextOptions[CURLOPT_NOBODY] = true;
        $this->contextOptions[CURLOPT_RETURNTRANSFER] = true;
        $this->contextOptions[CURLOPT_CONNECTTIMEOUT_MS] = 2001;
        $this->contextOptions[CURLOPT_TIMEOUT_MS] = 2000;
        curl_setopt_array($ch,$this->contextOptions);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->contextOptions = $origContext;
        if ($response) return true;
        return false;
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
        $this->proxyInfo($URL);
        $origContext = $this->contextOptions;
        $this->contextOptions[CURLOPT_URL] = $file;
        $this->contextOptions[CURLOPT_RANGE] = $start.'-'.$end;
        $this->contextOptions[CURLOPT_BINARYTRANSFER] = true;
        $this->contextOptions[CURLOPT_WRITEFUNCTION] = array($this,'chunk');
        curl_setopt_array($ch,$this->contextOptions);
        $result = curl_exec($ch);
        curl_close($ch);
        $this->contextOptions = $origContext;
    }
    private function chunk($ch, $str) {
        echo $str;
        return strlen($str);
    }
}
