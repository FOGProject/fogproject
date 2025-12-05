<?php
/**
 * Ntfy handler
 *
 * PHP version 5
 *
 * @category NtfyHandler
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Ntfy handler
 *
 * @category NtfyHandler
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NtfyHandler extends Ntfy
{
    private $_curlCallback;
    /**
     * The associated link + endpoint to send curl request to
     * 
     * @var string
     */
    private $topicURL;
    /**
     * Ntfy constructor.
     *
     * @throws NtfyException
     */
    public function __construct($serverURL, $topicEndpoint)
    {
        $this->topicURL = $serverURL . '/' . $topicEndpoint;
        if (!function_exists('curl_init')) {
            throw new NftyException(
                'cURL library is not loaded.'
            );
        }
    }
    /**
     * Push a note.
     *
     * @param string $recipient The recipient.
     * @param string $title     The note's title.
     * @param string $body      The note's message.
     *
     * @return object Response.
     * @throws NtfyException
     */
    public function pushNote(
        $recipient,
        $title,
        $body = null
    ) {
        $data = array();
        NtfyHandler::_parseRecipient(
            $recipient,
            $data
        );
        $data['type']  = 'note';
        $data['title'] = $title;
        $data['body']  = $body;

        return $this->_curlRequest(
            $this->topicURL,
            'POST',
            $data
        );
    }
    /**
     * Add a callback function that will be invoked
     * right before executing each cURL request.
     *
     * @param callable $callback The callback function.
     *
     * @return void
     */
    public function addCurlCallback(callable $callback)
    {
        $this->_curlCallback = $callback;
    }
    /**
     * Parse recipient.
     *
     * @param string $recipient Recipient string.
     * @param array  $data      Data array to populate with
     * the correct recipient parameter.
     *
     * @return void
     */
    private static function _parseRecipient($recipient, array &$data)
    {
        if (!empty($recipient)) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
                $data[email] = $recipient;
            } else {
                if (substr($recipient, 0, 1) == "#") {
                    $data[channel_tag] = substr($recipient, 1);
                } else {
                    $data[device_iden] = $recipient;
                }
            }
        }
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
     * @throws NtfyException
     */
    private function _curlRequest(
        $url,
        $method,
        $data = null,
        $sendAsJSON = false,
        $auth = true
    ) {
        $data = self::$FOGURLRequests->process(
            $url,
            $method,
            $data,
            $sendAsJSON,
            (
                $auth ?
                $this->_apiKey :
                false
            ),
            $this->_curlCallback
        );
        return json_decode($data[0]);
    }
}