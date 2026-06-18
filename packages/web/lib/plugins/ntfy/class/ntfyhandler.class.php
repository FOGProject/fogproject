<?php
/**
 * Ntfy handler
 *
 * PHP version 5
 *
 * @category NtfyHandler
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Ntfy handler
 *
 * Speaks ntfy's publish protocol: the message text is the POST body and the
 * title is sent via the "Title" header. Optional credentials authenticate
 * against protected topics -- "user:pass" uses HTTP basic auth, anything else
 * is treated as an access token and sent as "Authorization: Bearer <token>".
 *
 * @category NtfyHandler
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NtfyHandler extends Ntfy
{
    /**
     * The full topic URL to publish to (server + endpoint).
     *
     * @var string
     */
    private $_topicURL;
    /**
     * The credentials used to authenticate (token or "user:pass").
     *
     * @var string
     */
    private $_credentials;
    /**
     * Ntfy constructor.
     *
     * @param string $serverURL     The ntfy server base URL.
     * @param string $topicEndpoint The topic to publish to.
     * @param string $credentials   Optional token or "user:pass".
     *
     * @throws NtfyException
     */
    public function __construct($serverURL, $topicEndpoint, $credentials = '')
    {
        $this->_topicURL = rtrim($serverURL, '/') . '/' . ltrim($topicEndpoint, '/');
        $this->_credentials = (string)$credentials;
        if (!function_exists('curl_init')) {
            throw new NtfyException(
                'cURL library is not loaded.'
            );
        }
    }
    /**
     * Publish a notification to the topic.
     *
     * @param string $title The notification title.
     * @param string $body  The notification message body.
     *
     * @return object Response.
     * @throws NtfyException
     */
    public function pushNote($title, $body = '')
    {
        $headers = [];
        if ($title !== '' && $title !== null) {
            $headers[] = 'Title: ' . str_replace(
                ["\r", "\n"],
                ' ',
                $title
            );
        }
        $auth = false;
        if ($this->_credentials !== '') {
            if (strpos($this->_credentials, ':') !== false) {
                // HTTP basic auth (user:pass) via CURLOPT_USERPWD.
                $auth = $this->_credentials;
            } else {
                // ntfy access token.
                $headers[] = 'Authorization: Bearer ' . $this->_credentials;
            }
        }
        return $this->_curlRequest(
            $this->_topicURL,
            'POST',
            (string)$body,
            $auth,
            $headers
        );
    }
    /**
     * Send a request to the ntfy server using cURL.
     *
     * @param string $url     URL to send the request to.
     * @param string $method  HTTP method.
     * @param string $body    Raw request body (the message text).
     * @param mixed  $auth    "user:pass" for basic auth, or false.
     * @param array  $headers Extra HTTP headers.
     *
     * @return object Response.
     * @throws NtfyException
     */
    private function _curlRequest(
        $url,
        $method,
        $body = '',
        $auth = false,
        array $headers = []
    ) {
        $data = self::$FOGURLRequests->process(
            $url,
            $method,
            $body,
            false,
            $auth,
            false,
            false,
            false,
            $headers
        );
        return json_decode($data[0]);
    }
}
