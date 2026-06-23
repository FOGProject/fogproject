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
    private $_response = [];
    /**
     * Curl options to all url requests.
     *
     * @var array
     */
    public $options = [
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
    ];
    /**
     * Curl headers to send/request.
     *
     * @var array
     */
    private $_headers = [];
    /**
     * The requests themselves.
     *
     * @var array
     */
    private $_requests = [];
    /**
     * The mapping of requests so we can receive
     * information in the proper order as requested.
     *
     * @var array
     */
    private $_requestMap = [];
    /**
     * Proxy context computed once per execute() so the storage-node IP
     * lookup and proxy settings are not re-queried for every request.
     *
     * @var array
     */
    private $_proxy = [];
    /**
     * Initializes our url requests object.
     *
     * @param string $callback Optional callback
     */
    public function __construct($callback = null)
    {
        parent::__construct();
        list(
            $aconntimeout,
            $conntimeout,
            $timeout
        ) = self::getSetting(
            [
                'FOG_URL_AVAILABLE_TIMEOUT',
                'FOG_URL_BASE_CONNECT_TIMEOUT',
                'FOG_URL_BASE_TIMEOUT'
            ]
        );
        /**
         * Accept a positive numeric override, optionally requiring it to be
         * greater than a floor (the available-timeout only ratchets upward).
         */
        $override = function ($value, $floor = 0) {
            return (is_numeric($value) && $value > 0 && $value > $floor)
                ? (int)$value
                : null;
        };
        $this->_aconntimeout = $override($aconntimeout, $this->_aconntimeout)
            ?? $this->_aconntimeout;
        $this->_conntimeout = $override($conntimeout) ?? $this->_conntimeout;
        $this->_timeout = $override($timeout) ?? $this->_timeout;
        $this->_callback = $callback;
        $this->options = $this->_baseOptions();
    }
    /**
     * Cleans up when no longer needed.
     */
    public function __destruct()
    {
        $this->_reset();
    }
    /**
     * The default curl options shared by every request, including the
     * connect/timeout values derived from settings.
     *
     * @return array
     */
    private function _baseOptions()
    {
        return [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->_conntimeout,
            CURLOPT_TIMEOUT => $this->_timeout,
        ];
    }
    /**
     * Resets the per-run state so the shared instance can be reused.
     *
     * @return object
     */
    private function _reset()
    {
        $this->_windowSize = 20;
        $this->_callback = '';
        $this->_headers = [];
        $this->_response = [];
        $this->_requests = [];
        $this->_requestMap = [];
        $this->_proxy = [];
        $this->options = $this->_baseOptions();

        return $this;
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
        if ($name === 'headers') {
            $name = '_headers';
        }
        return isset($this->{$name}) ? $this->{$name} : null;
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
        if ($name === 'headers') {
            $this->_headers = $value + $this->_headers;
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
        $postData = [],
        $headers = [],
        $options = []
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
     * @param mixed $available   To test whether or not url is available
     *
     * @return object
     */
    public function execute($window_size = null, $available = false)
    {
        $window_count = count($this->_requests ?: []);
        if (empty($window_size)
            || !is_numeric($window_size)
            || $window_size > $window_count
        ) {
            $window_size = $window_count;
        }
        if ($window_count < 1) {
            return (array) false;
        }
        $this->_proxy = $this->_proxyContext();
        if ($window_count === 1) {
            return $this->_singleCurl($available);
        }

        return $this->_rollingCurl($window_size, $available);
    }
    /**
     * Run a single url request.
     *
     * @param bool $available To simply test if url is available
     *
     * @return mixed
     */
    private function _singleCurl($available = false)
    {
        $ch = curl_init();
        $request = array_shift($this->_requests);
        curl_setopt_array($ch, $this->_getOptions($request, $available));
        $output = curl_exec($ch);
        $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($available) {
            $output = ($info >= 200 && $info < 400);
        }
        if ($this->_callback && is_callable($this->_callback)) {
            call_user_func($this->_callback, $output, $info, $request);
        }

        return (array)$output;
    }
    /**
     * Perform multiple URL requests.
     *
     * @param mixed $window_size The customized window size to use
     * @param mixed $available   To simply test if the URL is available en masse
     *
     * @return mixed
     */
    private function _rollingCurl($window_size = null, $available = false)
    {
        if ($window_size) {
            $this->_windowSize = $window_size;
        }
        $this->_windowSize = min(count($this->_requests), $this->_windowSize);
        if ($this->_windowSize < 2) {
            throw new Exception(_('Window size must be greater than 1'));
        }
        $timeout = $available
            ? $this->_aconntimeout / 1000
            : $this->_timeout;
        $master = curl_multi_init();
        $requestMap = [];
        foreach ($this->_requests as $i => $request) {
            $ch = curl_init();
            curl_setopt_array($ch, $this->_getOptions($request, $available));
            curl_multi_add_handle($master, $ch);
            $requestMap[spl_object_id($ch)] = $i;
        }
        do {
            curl_multi_exec($master, $running);
            while ($done = curl_multi_info_read($master)) {
                $info = curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);
                $index = $requestMap[spl_object_id($done['handle'])];
                $output = $available
                    ? ($info >= 200 && $info < 400)
                    : curl_multi_getcontent($done['handle']);
                $this->_response[$index] = $output;
                if ($this->_callback && is_callable($this->_callback)) {
                    call_user_func(
                        $this->_callback,
                        $output,
                        $info,
                        $this->_requests[$index]
                    );
                }
                curl_multi_remove_handle($master, $done['handle']);
            }
            if ($running) {
                curl_multi_select($master, $timeout);
            }
        } while ($running);
        curl_multi_close($master);
        ksort($this->_response);
        return $this->_response;
    }
    /**
     * Builds the proxy settings and storage-node bypass pattern once per
     * execute() rather than for every individual request.
     *
     * @return array
     */
    private function _proxyContext()
    {
        list(
            $ip,
            $password,
            $port,
            $username
        ) = self::getSetting(
            [
                'FOG_PROXY_IP',
                'FOG_PROXY_PASSWORD',
                'FOG_PROXY_PORT',
                'FOG_PROXY_USERNAME'
            ]
        );
        $IPs = Route::getIds('storagenode', ['isEnabled' => [1]], 'ip') ?: [];
        $options = [];
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

        return [
            'pattern' => sprintf('#%s#i', implode('|', $IPs)),
            'options' => $options,
        ];
    }
    /**
     * Get options of the request and whole.
     *
     * @param FOGRollingURL $request   the request to get options from
     * @param bool          $available if we're checking available.
     *
     * @return array
     */
    private function _getOptions($request, $available = false)
    {
        $options = $this->options;
        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        $url = $this->_validUrl($request->url);
        if ($request->options) {
            $options = $request->options + $options;
        }
        $options[CURLOPT_URL] = $url;
        if ($request->postData) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->postData;
        }
        if ($this->_headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = (array)$this->_headers;
        }
        if (!isset($options[CURLOPT_COOKIE])) {
            $options[CURLOPT_COOKIE] = session_name() . '=' . session_id();
        }
        if ($available) {
            unset(
                $options[CURLOPT_TIMEOUT],
                $options[CURLOPT_CONNECTTIMEOUT]
            );
            $options[CURLOPT_TIMEOUT_MS] = $this->_aconntimeout;
            $options[CURLOPT_CONNECTTIMEOUT_MS] = $this->_aconntimeout;
            $options[CURLOPT_RETURNTRANSFER] = true;
            $options[CURLOPT_NOBODY] = true;
            $options[CURLOPT_HEADER] = true;
            $options[CURLOPT_NOSIGNAL] = true;
        }
        if ($this->_proxy['options']
            && !preg_match($this->_proxy['pattern'], $url)
        ) {
            $options = $this->_proxy['options'] + $options;
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
            return '';
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
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
     * @param array  $headers    Send specific headers.
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
        $timeout = false,
        $headers = []
    ) {
        $this->_reset();
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
        // ---- BEGIN CSRF + session forwarding ----
        $csrfToken = class_exists('CSRF') ? CSRF::token() : '';

        // Important: release the session lock so the called script can read it
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Normalize & merge headers; drop any old X-Requested-With
        $normalizedHeaders = [];
        foreach ((array) $headers as $h) {
            if (is_string($h) && stripos($h, 'X-Requested-With:') === 0) {
                continue; // remove legacy bypass header
            }
            $normalizedHeaders[] = $h;
        }
        if ($csrfToken !== '') {
            $normalizedHeaders[] = 'X-CSRF-Token: ' . $csrfToken;
        }

        // If caller asked to send JSON, ensure header + encoding now
        if ($sendAsJSON) {
            $hasCT = false;
            foreach ($normalizedHeaders as $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    $hasCT = true;
                    break;
                }
            }
            if (!$hasCT) {
                $normalizedHeaders[] = 'Content-Type: application/json; charset=utf-8';
            }
            if (is_array($data) || is_object($data)) {
                $data = json_encode($data);
            }
        }

        // Assign headers (class merges via __set) and forward the cookie via options
        $this->headers = $normalizedHeaders;
        // ---- END CSRF + session forwarding ----

        if ($file) {
            $this->options[CURLOPT_FILE] = $file;
        }
        $this->options[CURLOPT_USERAGENT] = 'Mozilla/5.0 (Linux x86_64; rv:80.0) Gecko/20100101 Firefox/80.0';
        foreach ((array) $urls as $url) {
            if ($method === 'GET') {
                $this->get($url);
            } else {
                $this->post($url, $data);
            }
        }

        return $this->execute();
    }
    /**
     * Quick test if url is available.
     *
     * @param string $urls    The url to check.
     * @param int    $timeout How long to wait.
     * @param int    $port    The connect to try connecting to.
     *
     * @return array
     */
    public function isAvailable(
        $urls,
        $timeout = 30,
        $port = -1
    ) {
        $this->_reset();
        $output = [];
        foreach ((array) $urls as $url) {
            $url = parse_url($url);
            if (!isset($url['host']) && isset($url['path'])) {
                $url['host'] = $url['path'];
            }
            if ($port == -1 || empty($port) || !$port) {
                if (!isset($url['port']) && isset($url['scheme'])) {
                    switch ($url['scheme']) {
                        case 'http':
                            $port = 80;
                            break;
                        case 'https':
                            $port = 443;
                            break;
                        case 'ftp':
                            $port = 21;
                            break;
                        case 'ssh':
                            $port = 22;
                            break;
                        default:
                            $port = self::$FOGSSH->port;
                    }
                } else {
                    $port = self::$FOGSSH->port;
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
            stream_set_blocking($socket, 0);
            $output[] = true;
            fclose($socket);
        }

        return $output;
    }
}
