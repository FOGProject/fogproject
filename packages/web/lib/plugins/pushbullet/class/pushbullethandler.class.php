<?php
/**
 * Pushbullet handler
 *
 * PHP version 5
 *
 * @category PushbulletHandler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pushbullet handler
 *
 * @category PushbulletHandler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PushbulletHandler extends Pushbullet
{
    private $_apiKey;
    private $_curlCallback;
    const URL_PUSHES
        = 'https://api.pushbullet.com/v2/pushes';
    const URL_DEVICES
        = 'https://api.pushbullet.com/v2/devices';
    const URL_CONTACTS
        = 'https://api.pushbullet.com/v2/contacts';
    const URL_UPLOAD_REQUEST
        = 'https://api.pushbullet.com/v2/upload-request';
    const URL_USERS
        = 'https://api.pushbullet.com/v2/users';
    const URL_SUBSCRIPTIONS
        = 'https://api.pushbullet.com/v2/subscriptions';
    const URL_CHANNEL_INFO
        = 'https://api.pushbullet.com/v2/channel-info';
    const URL_EPHEMERALS
        = 'https://api.pushbullet.com/v2/ephemerals';
    /**
     * Pushbullet constructor.
     *
     * @param string $apiKey API key.
     *
     * @throws PushbulletException
     */
    public function __construct($apiKey)
    {
        $this->_apiKey = $apiKey;
        if (!function_exists('curl_init')) {
            throw new PushbulletException(
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
     * @throws PushbulletException
     */
    public function pushNote(
        $recipient,
        $title,
        $body = null
    ) {
        $data = array();
        PushbulletHandler::_parseRecipient(
            $recipient,
            $data
        );
        $data['type']  = 'note';
        $data['title'] = $title;
        $data['body']  = $body;
        return $this->_curlRequest(
            self::URL_PUSHES,
            'POST',
            $data
        );
    }
    /**
     * Push a link.
     *
     * @param string $recipient The recipient.
     * @param string $title     The link's title.
     * @param string $url       The URL to open.
     * @param string $body      A message associated with the link.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function pushLink(
        $recipient,
        $title,
        $url,
        $body = null
    ) {
        $data = array();
        PushbulletHandler::_parseRecipient(
            $recipient,
            $data
        );
        $data['type']  = 'link';
        $data['title'] = $title;
        $data['url']   = $url;
        $data['body']  = $body;
        return $this->_curlRequest(
            self::URL_PUSHES,
            'POST',
            $data
        );
    }
    /**
     * Push an address.
     *
     * @param string $recipient The recipient.
     * @param string $name      The place's name.
     * @param string $address   The place's address or a map search query.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function pushAddress(
        $recipient,
        $name,
        $address
    ) {
        $data = array();
        PushbulletHandler::_parseRecipient(
            $recipient,
            $data
        );
        $data['type']    = 'address';
        $data['name']    = $name;
        $data['address'] = $address;
        return $this->_curlRequest(
            self::URL_PUSHES,
            'POST',
            $data
        );
    }
    /**
     * Push a checklist.
     *
     * @param string $recipient The recipient.
     * @param string $title     The list's title.
     * @param array  $items     The list items.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function pushList(
        $recipient,
        $title,
        array $items
    ) {
        $data = array();
        PushbulletHandler::_parseRecipient(
            $recipient,
            $data
        );
        $data['type']  = 'list';
        $data['title'] = $title;
        $data['items'] = $items;
        return $this->_curlRequest(
            self::URL_PUSHES,
            'POST',
            $data
        );
    }
    /**
     * Push a file.
     *
     * @param string $recipient   The recipient.
     * @param string $filePath    The path of the file to push.
     * @param string $mimeType    The MIME type of the file.
     *      If null, we'll try to guess it.
     * @param string $title       The title of the push notification.
     * @param string $body        The body of the push notification.
     * @param string $altFileName Alternative file name to use instead
     *      of the original one.
     *      For example, you might want to push 'someFile.tmp' as 'image.jpg'.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function pushFile(
        $recipient,
        $filePath,
        $mimeType = null,
        $title = null,
        $body = null,
        $altFileName = null
    ) {
        $data = array();
        $fullFilePath = realpath($filePath);
        if (!is_readable($fullFilePath)) {
            throw new PushbulletException(
                'File: File does not exist or is unreadable.'
            );
        }
        if (self::getFilesize($fullFilePath) > 25 * 1024 * 1024) {
            throw new PushbulletException(
                'File: File size exceeds 25 MB.'
            );
        }
        $data['file_name'] = $altFileName === null ?
            basename($fullFilePath) :
            $altFileName;
        // Try to guess the MIME type if the argument is NULL
        $data['file_type'] = $mimeType === null ?
            mime_content_type($fullFilePath) :
            $mimeType;
        // Request authorization to upload the file
        $response = $this->_curlRequest(
            self::URL_UPLOAD_REQUEST,
            'GET',
            $data
        );
        $data['file_url'] = $response->file_url;
        if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
            $response->data->file = new CURLFile($fullFilePath);
        } else {
            $response->data->file = '@' . $fullFilePath;
        }
        // Upload the file
        $this->_curlRequest(
            $response->upload_url,
            'POST',
            $response->data,
            false,
            false
        );
        PushbulletHandler::_parseRecipient(
            $recipient,
            $data
        );
        $data['type']  = 'file';
        $data['title'] = $title;
        $data['body']  = $body;
        return $this->_curlRequest(
            self::URL_PUSHES,
            'POST',
            $data
        );
    }
    /**
     * Get push history.
     *
     * @param int    $modifiedAfter Request pushes modified after
     * this UNIX timestamp.
     * @param string $cursor        Request the next page via its
     * cursor from a previous response. See the API
     * documentation (https://docs.pushbullet.com/http/) for a
     * detailed description.
     * @param int    $limit         Maximum number of objects on each page.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function getPushHistory(
        $modifiedAfter = 0,
        $cursor = null,
        $limit = null
    ) {
        $data = array();
        $data['modified_after'] = $modifiedAfter;
        if ($cursor !== null) {
            $data['cursor'] = $cursor;
        }
        if ($limit !== null) {
            $data['limit'] = $limit;
        }
        return $this->_curlRequest(
            self::URL_PUSHES,
            'GET',
            $data
        );
    }
    /**
     * Dismiss a push.
     *
     * @param string $pushIden push_iden of the push notification.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function dismissPush($pushIden)
    {
        return $this->_curlRequest(
            self::URL_PUSHES . '/' . $pushIden,
            'POST',
            array('dismissed' => true)
        );
    }
    /**
     * Delete a push.
     *
     * @param string $pushIden push_iden of the push notification.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function deletePush($pushIden)
    {
        return $this->_curlRequest(
            self::URL_PUSHES . '/' . $pushIden,
            'DELETE'
        );
    }
    /**
     * Get a list of available devices.
     *
     * @param int    $modifiedAfter Request devices modified after
     * this UNIX timestamp.
     * @param string $cursor        Request the next page via its
     * cursor from a previous response. See the API
     * documentation (https://docs.pushbullet.com/http/)
     * for a detailed description.
     * @param int    $limit         Maximum number of objects on each page.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function getDevices(
        $modifiedAfter = 0,
        $cursor = null,
        $limit = null
    ) {
        $data = array();
        $data['modified_after'] = $modifiedAfter;
        if ($cursor !== null) {
            $data['cursor'] = $cursor;
        }
        if ($limit !== null) {
            $data['limit'] = $limit;
        }
        return $this->_curlRequest(
            self::URL_DEVICES,
            'GET',
            $data
        );
    }
    /**
     * Delete a device.
     *
     * @param string $deviceIden device_iden of the device.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function deleteDevice($deviceIden)
    {
        return $this->_curlRequest(
            self::URL_DEVICES . '/' . $deviceIden,
            'DELETE'
        );
    }
    /**
     * Create a new contact.
     *
     * @param string $name  Name.
     * @param string $email Email address.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function createContact($name, $email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new PushbulletException(
                'Create contact: Invalid email address.'
            );
        }
        $queryData = array(
            'name'  => $name,
            'email' => $email
        );
        return $this->_curlRequest(
            self::URL_CONTACTS,
            'POST',
            $queryData
        );
    }
    /**
     * Get a list of contacts.
     *
     * @param int    $modifiedAfter Request contacts modified after
     * this UNIX timestamp.
     * @param string $cursor        Request the next page via its
     * cursor from a previous response. See the API
     * documentation (https://docs.pushbullet.com/http/) for
     * a detailed description.
     * @param int    $limit         Maximum number of objects on each page.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function getContacts(
        $modifiedAfter = 0,
        $cursor = null,
        $limit = null
    ) {
        $data = array();
        $data['modified_after'] = $modifiedAfter;
        if ($cursor !== null) {
            $data['cursor'] = $cursor;
        }
        if ($limit !== null) {
            $data['limit'] = $limit;
        }
        return $this->_curlRequest(
            self::URL_CONTACTS,
            'GET',
            $data
        );
    }
    /**
     * Update a contact's name.
     *
     * @param string $contactIden contact_iden of the contact.
     * @param string $name        New name of the contact.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function updateContact($contactIden, $name)
    {
        return $this->_curlRequest(
            self::URL_CONTACTS . '/' . $contactIden,
            'POST',
            array('name' => $name)
        );
    }
    /**
     * Delete a contact.
     *
     * @param string $contactIden contact_iden of the contact.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function deleteContact($contactIden)
    {
        return $this->_curlRequest(
            self::URL_CONTACTS . '/' . $contactIden,
            'DELETE'
        );
    }
    /**
     * Get information about the current user.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function getUserInformation()
    {
        return $this->_curlRequest(self::URL_USERS . '/me', 'GET');
    }
    /**
     * Update preferences for the current user.
     *
     * @param array $preferences Preferences.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function updateUserPreferences($preferences)
    {
        return $this->_curlRequest(
            self::URL_USERS . '/me',
            'POST',
            array('preferences' => $preferences)
        );
    }
    /**
     * Subscribe to a channel.
     *
     * @param string $channelTag Channel tag.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function subscribeToChannel($channelTag)
    {
        return $this->_curlRequest(
            self::URL_SUBSCRIPTIONS,
            'POST',
            array('channel_tag' => $channelTag)
        );
    }
    /**
     * Get a list of the channels the current user is subscribed to.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function getSubscriptions()
    {
        return $this->_curlRequest(self::URL_SUBSCRIPTIONS, 'GET');
    }
    /**
     * Unsubscribe from a channel.
     *
     * @param string $channelIden channel_iden of the channel.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function unsubscribeFromChannel($channelIden)
    {
        return $this->_curlRequest(
            self::URL_SUBSCRIPTIONS . '/' . $channelIden,
            'DELETE'
        );
    }
    /**
     * Get information about a channel.
     *
     * @param string $channelTag Channel tag.
     *
     * @return object Response.
     * @throws PushbulletException
     */
    public function getChannelInformation($channelTag)
    {
        return $this->_curlRequest(
            self::URL_CHANNEL_INFO,
            'GET',
            array('tag' => $channelTag)
        );
    }
    /**
     * Send an SMS message.
     *
     * @param string $fromDeviceIden device_iden of the device
     * that should send the SMS message. Only devices which
     * have the 'has_sms' property set to true in their
     * descriptions can send SMS messages. Use {@link getDevices()}
     * to check if they're capable to do so.
     * @param mixed  $toNumber       Phone number of the recipient.
     * @param string $message        Text of the message.
     *
     * @throws PushBulletException
     * @return object
     */
    public function sendSms($fromDeviceIden, $toNumber, $message)
    {
        $data = array(
            'type' => 'push',
            'push' => array(
                'type'               => 'messaging_extension_reply',
                'package_name'       => 'com.pushbullet.android',
                'source_user_iden'   => $this->getUserInformation()->iden,
                'target_device_iden' => $fromDeviceIden,
                'conversation_iden'  => $toNumber,
                'message'            => $message
            ));

        return $this->_curlRequest(self::URL_EPHEMERALS, 'POST', $data, true, true);
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
     * @throws PushbulletException
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
