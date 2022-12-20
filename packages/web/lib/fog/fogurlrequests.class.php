<?php
/**
 * Processes URL requests for our needs.
 *
 * PHP version 5
 *
 * @category FOGURLRequests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Processes URL requests for our needs.
 *
 * @category FOGURLRequests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGURLRequests extends FOGBase
{
    /**
     * The maximum urls to process at one time.
     *
     * @var int
     */
    private $_windowSize = 20;
    /**
     * The available connection timeout.
     *
     * Default to 2000 milliseconds.
     *
     * @var int
     */
    private $_aconntimeout = 2000;
    /**
     * The base connection timeout.
     *
     * Defaults to 15 seconds.
     *
     * @var int
     */
    private $_conntimeout = 15;
    /**
     * The timeout value to process each url.
     *
     * Defaults to 86400 seconds or 1 day.
     *
     * @var int
     */
    private $_timeout = 86400;
    /**
     * Defines a specific call back request.
     *
     * TODO: Fixup more appropriately to get data
     * from a callback rather than from an execution
     * instance.
     *
     * @var string
     */
    private $_callback = '';
    /**
     * Contains the response of our url requests.
     *
     * @var array
     */
    private $_response = array();
    /**
     * Curl options to all url requests.
     *
     * @var array
     */
    public $options = array(
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
    );
    /**
     * Curl headers to send/request.
     *
     * @var array
     */
    private $_headers = array();
    /**
     * The requests themselves.
     *
     * @var array
     */
    private $_requests = array();
    /**
     * The mapping of requests so we can receive
     * information in the proper order as requested.
     *
     * @var array
     */
    private $_requestMap = array();
    /**
     * Initializes our url requests object.
     *
     * @param string $callback Optional callback
     */
    public function __construct($callback = null)
    {
        parent::__construct();
        $timeouts = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_URL_AVAILABLE_TIMEOUT',
                    'FOG_URL_BASE_CONNECT_TIMEOUT',
                    'FOG_URL_BASE_TIMEOUT'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        if (isset($timeouts[0])
            && is_numeric($timeouts[0])
            && $timeouts[0] > 0
            && $timeouts[0] > $this->_aconntimeout
        ) {
            $this->_aconntimeout = (int)$timeouts[0];
        }
        if (isset($timeouts[1])
            && is_numeric($timeouts[1])
            && $timeouts[1] > 0
        ) {
            $this->_conntimeout = (int)$timeouts[1];
        }
        if (isset($timeouts[2])
            && is_numeric($timeouts[2])
            && $timeouts[2] > 0
        ) {
            $this->_timeout = (int)$timeouts[2];
        }
        $this->options[CURLOPT_CONNECTTIMEOUT] = $this->_conntimeout;
        $this->options[CURLOPT_TIMEOUT] = $this->_timeout;
        $this->_callback = $callback;
    }
    /**
     * Cleans up when no longer needed.
     */
    public function __destruct()
    {
        $this->_windowSize = 20;
        $this->_callback = '';
        $this->options = array(
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
        );
        $this->_response = array();
        $this->_requests = array();
        $this->_requestMap = array();
    }
    /**
     * Magic caller to get specialized methods
     * in a common method.
     *
     * @param string $name The method to get
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (in_array($name, array('headers'))) {
            $name = sprintf(
                '_%s',
                $name
            );
        }
        return (isset($this->{$name})) ? $this->{$name} : null;
    }
    /**
     * Magic caller to set specialized methods
     * in a common method.
     *
     * @param string $name  The method to set
     * @param mixed  $value The value to set
     *
     * @return object
     */
    public function __set($name, $value)
    {
        $addMethods = array(
            'options',
            'headers',
        );
        if (in_array($name, array('headers'))) {
            $name = sprintf(
                '_%s',
                $name
            );
        }
        if (in_array($name, $addMethods)) {
            $this->{$name} = $value + $this->{$name};
        } else {
            $this->{$name} = $value;
        }

        return $this;
    }
    /**
     * Add a request to the requests variable.
     *
     * @param FOGRollingURL $request the request to add
     *
     * @return object
     */
    public function add($request)
    {
        $this->_requests[] = $request;

        return $this;
    }
    /**
     * Generates the request and stores to our requests variable.
     *
     * @param string $url      The url to request
     * @param string $method   The method to call
     * @param mixed  $postData The data to pass
     * @param mixed  $headers  Any additional request headers to send
     * @param mixed  $options  Any additional request options to use
     *
     * @return object
     */
    public function request(
        $url,
        $method = 'GET',
        $postData = array(),
        $headers = array(),
        $options = array()
    ) {
        $this->_requests[] = new FOGRollingURL(
            $url,
            $method,
            $postData,
            $headers,
            $options
        );

        return $this;
    }
    /**
     * Get method url request definition.
     *
     * @param string $url     The url to request to
     * @param mixed  $headers The custom headers to send with this
     * @param mixed  $options The custom options to send with this
     *
     * @return object
     */
    public function get(
        $url,
        $headers = null,
        $options = null
    ) {
        return $this->request(
            $url,
            'GET',
            null,
            $headers,
            $options
        );
    }
    /**
     * Post method url request definition.
     *
     * @param string $url       The url to request to
     * @param mixed  $post_data The post data to send
     * @param mixed  $headers   The custom headers to send with this
     * @param mixed  $options   The custom options to send with this
     *
     * @return object
     */
    public function post(
        $url,
        $post_data = null,
        $headers = null,
        $options = null
    ) {
        return $this->request(
            $url,
            'POST',
            $post_data,
            $headers,
            $options
        );
    }
    /**
     * Actually executes the requests.
     * If only one request, perform a _singleCurl.
     * If multiple perform _rollingCurl.
     *
     * @param mixed $window_size The window size to allow at run time
     *
     * @return object
     */
    public function execute($window_size = null)
    {
        $window_count = count($this->_requests);
        if (empty($window_size)
            || !is_numeric($window_size)
            || $window_size > $window_count
        ) {
            $window_size = $window_count;
        }
        if ($window_count < 1) {
            return (array) false;
        }
        if ($window_count === 1) {
            return $this->_singleCurl();
        }

        return $this->_rollingCurl($window_size);
    }
    /**
     * Run a single url request.
     *
     * @return mixed
     */
    private function _singleCurl()
    {
        $ch = curl_init();
        $request = array_shift($this->_requests);
        $options = $this->_getOptions($request);
        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($this->_callback && is_callable($this->_callback)) {
            $this->_callback($output, $info, $request);
        }

        return (array)$output;
    }
    /**
     * Perform multiple url requests.
     *
     * @param mixed $window_size The customized window size to use
     *
     * @return mixed
     */
    private function _rollingCurl($window_size = null)
    {
        if ($window_size) {
            $this->_windowSize = $window_size;
        }
        if (sizeof($this->_requests) < $this->_windowSize) {
            $this->_windowSize = sizeof($this->_requests);
        }
        if ($this->_windowSize < 2) {
            throw new Exception(_('Window size must be greater than 1'));
        }
        $timeout = $this->_timeout;
        $master = curl_multi_init();
        for ($i = 0; $i < $this->_windowSize; ++$i) {
            $ch = curl_init();
            $options = $this->_getOptions($this->_requests[$i]);
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);
            if (isset($ch) && gettype($ch) === 'object') {
                $key = spl_object_id($ch);
            } else {
                $key = (string)$ch;
            }
            $this->_requestMap[$key] = $i;
        }
        do {
            while ((
                $execrun = curl_multi_exec(
                    $master,
                    $running
                )
            ) == CURLM_CALL_MULTI_PERFORM) {
            }
            if ($execrun != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($master)) {
                $info = curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);
                if (isset($done['handle']) && gettype($done['handle']) === 'object') {
                    $key = spl_object_id($done['handle']);
                } else {
                    $key = (string)$done['handle'];
                }
                $output = curl_multi_getcontent($done['handle']);
                $this->_response[$this->_requestMap[$key]] = $output;
                if ($this->_callback && is_callable($this->_callback)) {
                    $request = $this->_requests[$this->_requestMap[$key]];
                    $this->_callback($output, $info, $request);
                }
                $sizeof = sizeof($this->_requests);
                if ($i < $sizeof
                    && isset($this->_requests[$i])
                ) {
                    $ch = curl_init();
                    $options = $this->_getOptions($this->_requests[$i]);
                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);
                    $key = (string) $ch;
                    $this->_requestMap[$key] = $i;
                    ++$i;
                } else {
                    unset(
                        $this->_requests[$this->_requestMap[$key]],
                        $this->_requestMap[$key]
                    );
                }
                curl_multi_remove_handle($master, $done['handle']);
            }
            if ($running) {
                curl_multi_select($master, $timeout);
            }
        } while ($running);
        ksort($this->_response);
        curl_multi_close($master);

        return $this->_response;
    }
    /**
     * Get options of the request and whole.
     *
     * @param FOGRollingURL $request the request to get options from
     *
     * @return array
     */
    private function _getOptions($request)
    {
        $options = $this->__get('options');
        $options[CURLOPT_FOLLOWLOCATION] = 1;
        $options[CURLOPT_MAXREDIRS] = 5;
        $url = $this->_validUrl($request->url);
        $headers = $this->__get('headers');
        if ($request->options) {
            $options = $request->options + $options;
        }
        $options[CURLOPT_URL] = $url;
        if ($request->postData) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->postData;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = (array)$headers;
        }
        list($ip, $password, $port, $username) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_PROXY_IP',
                    'FOG_PROXY_PASSWORD',
                    'FOG_PROXY_PORT',
                    'FOG_PROXY_USERNAME',
                ),
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            false
        );
        $IPs = self::getSubObjectIDs('StorageNode', array('isEnabled' => 1));
        $pat = sprintf(
            '#%s#i',
            implode('|', $IPs)
        );
        if (!preg_match($pat, $url)) {
            if ($ip) {
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYPORT] = $port;
                $options[CURLOPT_PROXY] = $ip;
                if ($username) {
                    $options[CURLOPT_PROXYUSERPWD] = sprintf(
                        '%s:%s',
                        $username,
                        $password
                    );
                }
            }
        }

        return $options;
    }
    /**
     * Function simply ensures the url is valid.
     *
     * @param string $url The url test check
     *
     * @return string
     */
    private function _validUrl(&$url)
    {
        if (!isset($url) || empty($url)) {
            return false;
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            unset($url);
        }

        return $url;
    }
    /**
     * Processes the requests as needed.
     *
     * @param mixed  $urls       the urls to process
     * @param string $method     the method to use for all urls
     * @param mixed  $data       post/get data possibly
     * @param bool   $sendAsJSON Send data as json if needed
     * @param mixed  $auth       Any authorization data needed
     * @param string $callback   A callback to use if needed
     * @param string $file       A filename to use to download a file
     * @param mixed  $timeout    allow updating timeout values
     *
     * @return array
     */
    public function process(
        $urls,
        $method = 'GET',
        $data = null,
        $sendAsJSON = false,
        $auth = false,
        $callback = false,
        $file = false,
        $timeout = false
    ) {
        $this->__destruct();
        if (false !== $timeout) {
            $this->_timeout = (int)$timeout;
            $this->options[CURLOPT_TIMEOUT] = (int)$timeout;
        }
        if ($callback && is_callable($callback)) {
            $this->_callback = $callback;
        }
        if ($auth) {
            $this->options[CURLOPT_USERPWD] = $auth;
        }
        if ($sendAsJSON) {
            $data2 = json_encode($data);
            $datalen = strlen($data2);
            $this->options[CURLOPT_HEADER] = true;
            $this->options[CURLOPT_HTTPHEADER] = array(
                'Content-Type: application/json',
                "Content-Length: $datalen",
                'Expect:',
            );
        }
        if ($file) {
            $this->options[CURLOPT_FILE] = $file;
        }
        $this->options[CURLOPT_USERAGENT] = 'Mozilla/5.0 (Linux x86_64; rv:80.0) Gecko/20100101 Firefox/80.0';
        foreach ((array) $urls as &$url) {
            if ($method === 'GET') {
                $this->get($url);
            } else {
                $this->post($url, $data);
            }
            unset($url);
        }

        return $this->execute();
    }
    /**
     * Quick test if url is available.
     *
     * @param string $urls    the url to check.
     * @param int    $timeout the timeout value.
     * @param int    $port    the port to test on.
     *
     * @return void
     */
    public function isAvailable($urls, $timeout = 30, $port = -1)
    {
        $this->__destruct();
        $output = array();
        if (empty($timeout) || !$timeout || $timeout < 1) {
            $timeout = 30;
        }
        foreach ((array) $urls as &$url) {
            $url = parse_url($url);
            if (!isset($url['host']) && isset($url['path'])) {
                $url['host'] = $url['path'];
            }
            if ($port == -1 || empty($port) || !$port) {
                if (!isset($url['port']) && isset($url['scheme'])) {
                    switch ($url['scheme']) {
                        case "http":
                            $port = 80;
                            break;
                        case "https":
                            $port = 443;
                            break;
                        case "ftp":
                            $port = 21;
                            break;
                        default:
                            $port = self::$FOGFTP->get('port');
                    }
                } else {
                    $port = self::$FOGFTP->get('port');
                }
            }
            $socket = @fsockopen(
                $url['host'],
                $port,
                $errno,
                $errstr,
                $timeout
            );
            if (!$socket) {
                $output[] = false;
                continue;
            }
            $output[] = true;
            fclose($socket);
            unset($url);
        }

        return $output;
    }
}
