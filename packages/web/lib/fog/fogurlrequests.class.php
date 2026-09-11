<?php
/**
 * Processes URL requests for our needs.
 *
 * PHP version 7.4+
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
     * The TLS options used for a FOG-owned host, and only for one.
     *
     * A storage node presents whatever certificate the installer generated
     * for it -- self-signed, or signed by the CA this server minted -- and it
     * is addressed by bare IP, so no certificate could name it correctly
     * anyway. Verification there would break replication on every install and
     * buy nothing: the node's address came out of this server's own database.
     *
     * Applied by host, not by caller, so a new caller cannot inherit it by
     * accident. See isFogHost().
     *
     * @var array
     */
    const NODE_TLS_OPTIONS = array(
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    );
    /**
     * Curl options to all url requests.
     *
     * Verification is ON. It used to be off for every request this class
     * made, which was written for the storage-node traffic above and then
     * silently applied to everything else -- the GitHub kernel/init listing,
     * the fogproject.org version check, the kernel download itself. Those all
     * present ordinary publicly-signed certificates, so there was nothing to
     * gain and a machine-in-the-middle to lose, and the failure mode is
     * invisible: a substituted response looks exactly like a real one.
     *
     * @var array
     */
    public $options = array(
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
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
        // Same defaults as the property; the exemption for FOG's own nodes
        // is applied per URL in _getOptions().
        $this->options = array(
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
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
        // call_user_func, not $this->_callback(...): the latter is parsed as a
        // call to a METHOD named _callback, which does not exist, so it is a
        // fatal the moment a caller actually supplies one. The guard above kept
        // the property empty for years, so nothing ever reached this line and
        // the bug stayed latent until the kernel download began passing a
        // closure to read the HTTP status. 1.6 has always had it this way.
        if ($this->_callback && is_callable($this->_callback)) {
            call_user_func($this->_callback, $output, $info, $request);
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
                // Same method-vs-property call bug as in _singleCurl above.
                if ($this->_callback && is_callable($this->_callback)) {
                    $request = $this->_requests[$this->_requestMap[$key]];
                    call_user_func($this->_callback, $output, $info, $request);
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
        /**
         * Every host this install owns: its storage nodes, and itself.
         *
         * The FOG server is included because several of these requests are
         * this server calling its own status endpoints, and its certificate
         * is the installer's self-signed one.
         *
         * Note the 'ip' argument. This read used to be
         * getSubObjectIDs('StorageNode', array('isEnabled' => 1)) with no
         * field, and getSubObjectIDs() defaults $getField to 'id' -- so the
         * list it produced was storage node IDs, not addresses, and the
         * pattern below was matching '#1|2|5#' against the whole URL. The
         * only consequence was a proxy that was applied essentially never
         * (any URL containing one of those digits looked like a node), which
         * is why nobody noticed. It is not survivable now that the same
         * answer decides whether a certificate is verified.
         *
         * isEnabled is no longer filtered on either: a node an admin is
         * still creating is contacted by the storage node page, and a node
         * absent from this list gets its certificate verified, which for a
         * node addressed by bare IP means it is unreachable.
         */
        $hosts = self::getSubObjectIDs('StorageNode', array(), 'ip');
        $hosts[] = self::getSetting('FOG_WEB_HOST');
        $hosts = array_merge($hosts, array('127.0.0.1', '::1', 'localhost'));
        $hosts = array_values(
            array_unique(
                array_filter(
                    array_map(
                        function ($host) {
                            return strtolower(trim((string)$host));
                        },
                        $hosts
                    ),
                    'strlen'
                )
            )
        );
        $isFogHost = self::isFogHost($url, $hosts);
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            /*
             * The CSRF token goes to FOG's own hosts only. It is added so
             * that a status endpoint on a storage node accepts the POST; on
             * any other host it is a credential handed to a third party for
             * no purpose. Same reasoning as the cookie below.
             */
            $options[CURLOPT_HTTPHEADER] = $isFogHost
                ? (array)$headers
                : array_values(
                    array_filter(
                        (array)$headers,
                        function ($header) {
                            return 0 !== stripos(
                                (string)$header,
                                'X-CSRF-Token:'
                            );
                        }
                    )
                );
        }
        /*
         * The session cookie goes to FOG's own hosts only.
         *
         * This used to be sent on every request this class made, which meant
         * the signed-in administrator's PHP session id was handed to
         * api.github.com on every kernel listing and to fogproject.org on
         * every version check. It is here because a node's status endpoint
         * needs the caller's session to authorise the request -- that is a
         * reason to send it to a node, and not a reason to send it anywhere
         * else.
         */
        /*
         * Only when there is actually a session to forward. session_id() is ''
         * in any caller that never started one -- every CLI daemon, and any API
         * request authenticated by token rather than cookie -- and the header
         * was still being sent, as the bare "PHPSESSID=".
         *
         * An empty value is not nothing. isset($_COOKIE[session_name()]) is
         * TRUE for it, so the receiving end's "resume a session only if one was
         * presented" gate in commons/init.php opened, session_start() ran, and
         * session.use_strict_mode -- having no id to resume -- minted a brand
         * new empty session. Initiator::language() then wrote FOG_LANG into it
         * and nothing ever read it again: an 18-byte file per request, left for
         * gc.
         *
         * Sending nothing is also the honest signal. The request is
         * unauthenticated either way -- getfiles.php answers 401 to both -- but
         * an absent cookie says so without minting a session to prove it.
         */
        if ($isFogHost
            && !isset($options[CURLOPT_COOKIE])
            && session_id() !== ''
        ) {
            $options[CURLOPT_COOKIE] = session_name() . '=' . session_id();
        }
        /*
         * Prove to FOG's own hosts that this request came from FOG.
         *
         * The cookie above only works when a browser session exists to
         * forward. Every CLI daemon and every token-authenticated API call
         * has none, so StorageNode::_getData() went out unauthenticated,
         * getfiles.php answered 401, and the loader turned that into an
         * empty file list -- wrong data rather than an error. This is the
         * credential those callers can actually hold: a shared secret in
         * globalSettings, which master and node both read (GH-1312).
         *
         * Signed for every FOG host, not only the session-less case, so the
         * browser path exercises the same code. A signature that stops
         * verifying then shows up in the UI immediately instead of only in a
         * daemon nobody is watching.
         *
         * Bound to the method actually about to be sent, so a signature
         * issued for a GET cannot be presented as anything else.
         */
        if ($isFogHost) {
            if (!empty($options[CURLOPT_CUSTOMREQUEST])) {
                $method = (string)$options[CURLOPT_CUSTOMREQUEST];
            } elseif (!empty($options[CURLOPT_NOBODY])) {
                $method = 'HEAD';
            } elseif (!empty($options[CURLOPT_POST])) {
                $method = 'POST';
            } else {
                $method = 'GET';
            }
            $signature = self::nodeSignatureHeaders($url, $method);
            if (count($signature) > 0) {
                $options[CURLOPT_HTTPHEADER] = array_merge(
                    (array)(
                        isset($options[CURLOPT_HTTPHEADER])
                        ? $options[CURLOPT_HTTPHEADER]
                        : array()
                    ),
                    $signature
                );
            }
        }
        /*
         * The TLS exemption for FOG's own nodes. Applied here rather than in
         * the defaults so it is decided by the URL: a caller cannot acquire
         * it by not thinking about it, which is how every request this class
         * made ended up unverified in the first place.
         *
         * Skipped when the caller named CURLOPT_SSL_VERIFYPEER itself --
         * $request->options already wins over $this->options above, and an
         * explicit choice must not be overwritten by an implicit one.
         */
        if ($isFogHost
            && !isset($request->options[CURLOPT_SSL_VERIFYPEER])
        ) {
            $options = self::NODE_TLS_OPTIONS + $options;
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
        if (!$isFogHost) {
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
     * Whether a URL addresses a host this FOG install owns.
     *
     * The host is compared whole and exactly, because this answer decides
     * whether the connection's certificate is verified. Substring or pattern
     * matching would let an attacker-chosen URL that merely CONTAINS a node
     * address opt itself out of verification -- which is what the regex this
     * replaces did: unanchored, with the dots unescaped, so a node at
     * 10.0.0.5 also matched https://example.com/?ref=10.0.0.5.
     *
     * Public and static so it can be exercised directly: it needs no
     * database, and the cases that matter are the ones that must NOT match.
     *
     * @param string $url   the URL about to be requested
     * @param array  $hosts the hosts this install owns, already lowercased
     *
     * @return bool
     */
    public static function isFogHost($url, array $hosts)
    {
        $host = parse_url((string)$url, PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return false;
        }
        // parse_url keeps the brackets on an IPv6 literal; the stored value
        // has none.
        $host = strtolower(trim($host, '[]'));

        return in_array($host, $hosts, true);
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
        /**
         * With no port passed this probe falls back to the ftp port, so it
         * has to honour FOG_FTP_PORT the same way FOGFTP::connect() does.
         * FOGFTP's own value is only ever the hardcoded 21 here, which
         * reported every node offline on installs that moved ftp -- the same
         * defect as forums 18210 on 1.6, where the probe is ssh instead.
         * Only looked up when it can actually be needed.
         */
        $ftpPort = 0;
        if ($port == -1 || empty($port) || !$port) {
            $portOverride = self::getSetting('FOG_FTP_PORT');
            $ftpPort = (int)$portOverride ?: self::$FOGFTP->get('port');
        }
        foreach ((array) $urls as &$url) {
            $url = parse_url($url);
            if (!isset($url['host']) && isset($url['path'])) {
                $url['host'] = $url['path'];
            }
            /**
             * Resolved per url. $port is the caller's value and must stay
             * untouched, else the first url in the list dictates the port
             * used for every url after it.
             */
            $testPort = $port;
            if ($testPort == -1 || empty($testPort) || !$testPort) {
                if (isset($url['port'])) {
                    $testPort = $url['port'];
                } elseif (isset($url['scheme'])) {
                    switch ($url['scheme']) {
                        case "http":
                            $testPort = 80;
                            break;
                        case "https":
                            $testPort = 443;
                            break;
                        default:
                            $testPort = $ftpPort;
                    }
                } else {
                    $testPort = $ftpPort;
                }
            }
            $socket = @fsockopen(
                $url['host'],
                $testPort,
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
