<?php
/**
 * Handles the api calling of Slack messages.
 *
 * PHP Version 5
 *
 * @category SlackHandler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  https://opensource.org/license/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles the api calling of Slack messages.
 *
 * @category SlackHandler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  https://opensource.org/license/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SlackHandler extends Slack
{
    /**
     * The token
     *
     * @var string
     */
    private $_apiToken;
    /**
     * The api endpoint.
     *
     * @var string
     */
    private static $_apiEndpoint = 'https://slack.com/api/<method>';
    /**
     * Callback for curl to use.
     *
     * @var callable
     */
    private $_curlCallback;
    /**
     * The methods available for slack.
     *
     * @var array
     */
    private static $_methods = [
        // api
        'api.test',
        // apps.permissions
        'apps.permissions.info',
        'apps.permissions.request',
        // apps.permissions.resources
        'apps.permissions.resources.list',
        // apps.permissions.scopes
        'apps.permissions.scopes.list',
        // apps.permissions.users
        'apps.permissions.users.list',
        'apps.permissions.users.request',
        // apps
        'apps.uninstall',
        // auth
        'auth.revoke',
        'auth.test',
        // bots
        'bots.info',
        // channels
        'conversations.list',
        'channels.archive',
        'channels.create',
        'channels.history',
        'channels.info',
        'channels.invite',
        'channels.join',
        'channels.kick',
        'channels.leave',
        'channels.list',
        'channels.mark',
        'channels.rename',
        'channels.replies',
        'channels.setPurpose',
        'channels.setTopic',
        'channels.unarchive',
        // chat
        'chat.delete',
        'chat.getPermalink',
        'chat.meMessage',
        'chat.postEphemeral',
        'chat.postMessage',
        'chat.unfurl',
        'chat.update',
        // conversations
        'conversations.archive',
        'conversations.close',
        'conversations.create',
        'conversations.history',
        'conversations.info',
        'conversations.invite',
        'conversations.join',
        'conversations.kick',
        'conversations.leave',
        'conversations.list',
        'conversations.members',
        'conversations.open',
        'conversations.rename',
        'conversations.replies',
        'conversations.setPurpose',
        'conversations.setTopic',
        'conversations.unarchive',
        // dialog
        'dialog.open',
        // dnd
        'dnd.endDnd',
        'dnd.endSnooze',
        'dnd.info',
        'dnd.setSnooze',
        'dnd.teamInfo',
        // emoji
        'emoji.list',
        // files.comments
        'files.comments.add',
        'files.comments.delete',
        'files.comments.edit',
        // files
        'files.delete',
        'files.info',
        'files.list',
        'files.revokePublicURL',
        'files.sharedPublicURL',
        'files.upload',
        // groups
        'groups.archive',
        'groups.close',
        'groups.create',
        'groups.createChild',
        'groups.history',
        'groups.info',
        'groups.invite',
        'groups.kick',
        'groups.leave',
        'groups.list',
        'groups.mark',
        'groups.open',
        'groups.rename',
        'groups.replies',
        'groups.setPurpose',
        'groups.setTopic',
        'groups.unarchive',
        // im
        'im.close',
        'im.history',
        'im.list',
        'im.mark',
        'im.open',
        'im.replies',
        // migration
        'migration.exchange',
        // mpim
        'mpim.close',
        'mpim.history',
        'mpim.list',
        'mpim.mark',
        'mpim.open',
        'mpim.replies',
        // oauth
        'oauth.access',
        'oauth.token',
        // pins
        'pins.add',
        'pins.list',
        'pins.remove',
        // reactions
        'reactions.add',
        'reactions.get',
        'reactions.list',
        'reactions.remove',
        // reminders
        'reminders.add',
        'reminders.complete',
        'reminders.delete',
        'reminders.info',
        'reminders.list',
        // rtm
        'rtm.connect',
        'rtm.start',
        // search
        'search.all',
        'search.files',
        'search.messages',
        // stars
        'stars.add',
        'stars.list',
        'stars.remove',
        // team
        'team.accessLogs',
        'team.billableInfo',
        'team.info',
        'team.integrationLogs',
        // team.profile
        'team.profile.get',
        // usergroups
        'usergroups.create',
        'usergroups.disable',
        'usergroups.enable',
        'usergroups.list',
        'usergroups.update',
        // usergroups.users
        'usergroups.users.list',
        'usergroups.users.update',
        // users
        'users.conversations',
        'users.deletePhoto',
        'users.getPresence',
        'users.identity',
        'users.info',
        'users.list',
        'users.lookupByEmail',
        'users.setActive',
        'users.setPhoto',
        'users.setPresence',
        // users.profile
        'users.profile.get',
        'users.profile.set'
    ];
    /**
     * Initializes the handler object.
     *
     * @param string $apiToken The token to use.
     *
     * @throws SlackException
     *
     * @return void
     */
    public function __construct($apiToken)
    {
        $this->_apiToken = $apiToken;
        if (!function_exists('curl_init')) {
            throw new SlackException('cURL library is not loaded.');
        }
    }
    /**
     * Performs the call.
     *
     * @param string $method How are wew posting the call.
     * @param array  $args   The arguments to pass into the call.
     *
     * @return array
     */
    public function call($method, $args = [])
    {
        if (array_search($method, self::$_methods, true) === false) {
            throw new SlackException(_('Invalid method called'));
        }
        $args['token'] = $this->_apiToken;
        return json_decode(
            json_encode(
                $this->_curlRequest(
                    str_replace(
                        '<method>',
                        $method,
                        self::$_apiEndpoint
                    ),
                    'POST',
                    $args
                )
            ),
            true
        );
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
                $this->_apiToken :
                false
            ),
            $this->_curlCallback
        );
        return json_decode($data[0]);
    }
}
