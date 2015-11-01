<?php
class FOGURLRequests extends FOGBase {
    /** @var $handle the handle connector for curl */
    private $handle;
    /** @var $contextOptions the context for the curl sessions to operate */
    private $contextOptions;
    /** @function __construct the constructor to build the basic defaults
     * @return void
     */
    public function __construct() {
        parent::__construct();
        $this->handle = @curl_multi_init();
        $this->contextOptions = array(
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT_MS => 2001,
            CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_HEADER => false,
        );
    }
    /** @function __destruct the destructor when class no longer needed.  Closes all open handles
     * @return void
     */
    public function __destruct() {
        @curl_multi_close($this->handle);
    }
    /** @function process the actual process to send
     * @param $urls the url or array of urls to process required
     * @param $method the method to send pass the url (GET or POST) defaults to GET
     * @param $data the specific data to send (defaults to null)
     * @param $sendAsJSON whether to send the data in JSON or not faults to false
     * @param $auth whether to send an auth string.  Defaults to false, other wise takes the actual auth string to pass
     * @param $callback whether to use a user passed callback to process.  Defaults to false
     * @param $file whether we're downloading a file or not.  Defaults to false, others takes the file resource
     * @return if $file it just closes the handle otherwise it returns the response
     */
    public function process($urls, $method = 'GET',$data = null,$sendAsJSON = false,$auth = false,$callback = false,$file = false) {
        if (!is_array($urls)) $urls = array($urls);
        if (empty($method)) $method = 'GET';
        foreach ((array)$urls AS $i => &$url) {
            $url = filter_var($url, FILTER_SANITIZE_URL);
            if (filter_var($url,FILTER_VALIDATE_URL) === false) {
                unset($url);
                continue;
            }
            $ProxyUsed = false;
            if ($this->DB && ($ip = $this->getSetting('FOG_PROXY_IP'))) {
                if (filter_var($ip,FILTER_VALIDATE_IP) === false) {
                    unset($url,$ip);
                    continue;
                }
                $IPs = $this->getSubObjectIDs('StorageNode','','ip');
                if (!preg_match('#^(?!.*'.implode('|',(array)$IPs).')$#i',$url)) $ProxyUsed = true;
                $username = $this->getSetting('FOG_PROXY_USERNAME');
                $password = $this->getSetting('FOG_PROXY_PASSWORD');
            }
            if ($ProxyUsed) {
                $this->contextOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $this->contextOptions[CURLOPT_PROXYPORT] = $this->getSetting('FOG_PROXY_PORT');
                $this->contextOptions[CURLOPT_PROXY] = $ip;
                if ($username) $this->contextOptions[CURLOPT_PROXYUSERPWD] = $username.':'.$password;
            }
            unset($ProxyUsed);
            if ($method == 'GET' && $data !== null) $url = sprintf('%s?%s',$url,http_build_query($data));
            $ch = @curl_init($url);
            $this->contextOptions[CURLOPT_URL] = $url;
            if ($auth) $this->contextOptions[CURLOPT_USERPWD] = $auth;
            if ($file) {
                $this->contextOptions[CURLOPT_FILE] = $file;
                $this->contextOptions[CURLOPT_TIMEOUT_MS] = 300000000;
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
                $this->contextOptions[CURLOPT_POSTFIELDS] = $data;
            }
            $this->contextOptions[CURLOPT_CUSTOMREQUEST] = $method;
            curl_setopt_array($ch,$this->contextOptions);
            $curl[$i] = $ch;
            curl_multi_add_handle($this->handle,$ch);
        }
        unset($url);
        $active = null;
        $response = array();
        do {
            curl_multi_exec($this->handle,$active);
        } while ($active > 0);
        foreach ((array)$curl AS $key => &$val) {
            $response[] = curl_multi_getcontent($val);
            curl_multi_remove_handle($this->handle,$val);
        }
        unset($val);
        if (!$file) return $response;
        @fclose($file);
    }
}
