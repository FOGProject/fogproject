<?php
/**
 * Slack class.
 *
 * PHP Version 5
 *
 * @category Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  https://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Slack class.
 *
 * @category Slack
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  https://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Slack extends FOGController
{
    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'slack';
    /**
     * The fields within the table.
     *
     * @var array
     */
    protected $databaseFields = [
        'id'     => 'sID',
        'token'  => 'sToken',
        'name' => 'sUsername'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'token',
        'name'
    ];
    /**
     * Get the conversations of the slack you're logging in with.
     *
     * @throws SlackException
     *
     * @return array
     */
    public function getConversations()
    {
        $channels = [];
        $channelnames = $this->call('conversations.list');
        if (!$channelnames['ok']) {
            throw new SlackException(_('Channel call is invalid'));
        }
        foreach ((array)$channelnames['channels'] as $channelname) {
            $channels[] = $channelname['name'];
        }
        natcasesort($channels);
        $channels = array_values($channels ?? []);
        return $channels;
    }
    /**
     * Get the users of the slack you're logging in with.
     *
     * @throws SlackException
     *
     * @return array
     */
    public function getUsers()
    {
        $users = [];
        $usernames = $this->call('users.list');
        if (!$usernames['ok']) {
            throw new SlackException(_('User call is invalid'));
        }
        foreach ((array)$usernames['members'] as $names) {
            if ($names['name'] == 'slackbot') {
                continue;
            }
            $users[] = $names['name'];
        }
        unset($usernames);
        @natcasesort($users);
        $users = array_values((array)$users);
        return (array)$users;
    }
    /**
     * Validates the token.
     *
     * @return bool
     */
    public function verifyToken()
    {
        $testAuth = self::getClass(
            'SlackHandler',
            $this->get('token')
        )->call('auth.test');
        return (bool)$testAuth['ok'];
    }
    /**
     * Call the chat elements.
     *
     * @param string $method How is the message passing.
     * @param array  $args   Any extra arguments sent in.
     *
     * @return mixed
     */
    public function call($method, $args = [])
    {
        if ($method === 'chat.postMessage') {
            $tmpName = preg_replace('/^[#]|^[@]/', '', $this->get('name'));
            $username = $this->call('auth.test');
            if ($tmpName != $username['user']
                || in_array($tmpName, (array)$this->getChannels())
            ) {
                $args['username'] = $username['user'];
                $args['as_user'] = true;
            }
        }
        return self::getClass(
            'SlackHandler',
            $this->get('token')
        )->call($method, $args);
    }
}
