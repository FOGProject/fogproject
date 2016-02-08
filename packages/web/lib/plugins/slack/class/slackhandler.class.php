<?php
class SlackHandler {
    private $_apiToken;
    private static $_apiEndpoint = 'https://slack.com/api/<method>';
    private $_curlCallback;
    public function __construct($apiToken) {
        $this->_apiToken = $apiToken;
        if (!function_exists('curl_init')) throw new SlackException('cURL library is not loaded.');
    }
    public function call($method,$args = array()) {
        $args['token'] = $this->_apiToken;
        return $this->_curlRequest(str_replace('<method>',$method,self::$_apiEndpoint),'POST',$args);
    }
    /**
     * Send a request to a remote server using cURL.
     *
     * @param string $url        URL to send the request to.
     * @param string $method     HTTP method.
     * @param array  $data       Query data.
     * @param bool   $sendAsJSON Send the request as JSON.
     * @param bool   $auth       Use the API key to authenticate
     *
     * @return object Response.
     */
    private function _curlRequest($url, $method, $data = null, $sendAsJSON = false, $auth = true) {
        $Requests = new FOGURLRequests();
        $data = $Requests->process($url,$method,$data,$sendAsJSON,($auth ? $this->_apiToken : false),$this->_curlCallback);
        return @json_decode($data[0]);
    }
}
