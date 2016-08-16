<?php
class Slack extends FOGController
{
    protected $databaseTable = 'slack';
    protected $databaseFields = array(
        'id'     => 'sID',
        'token'  => 'sToken',
        'name' => 'sUsername',
    );
    protected $databaseFieldsRequired = array(
        'token',
        'name',
    );
    public function getChannels()
    {
        $channels = array();
        $channelnames = $this->call('channels.list');
        if (!$channelnames['ok']) {
            throw new SlackException(_('Channel call is invalid'));
        }
        foreach ((array)$channelnames['channels'] as &$channelname) {
            $channels[] = $channelname['name'];
            unset($channelname);
        }
        unset($channelnames);
        natcasesort($channels);
        $channels = array_values((array)$channels);
        return (array)$channels;
    }
    public function getUsers()
    {
        $users = array();
        $usernames = $this->call('users.list');
        if (!$usernames['ok']) {
            throw new SlackException(_('User call is invalid'));
        }
        foreach ((array)$usernames['members'] as &$names) {
            if ($names['name'] == 'slackbot') {
                continue;
            }
            $users[] = $names['name'];
            unset($names);
        }
        unset($usernames);
        natcasesort($users);
        $users = array_values((array)$users);
        return (array)$users;
    }
    public function verifyToken()
    {
        $testAuth = self::getClass('SlackHandler', $this->get('token'))->call('auth.test');
        return (bool)$testAuth['ok'];
    }
    public function call($method, $args = array())
    {
        if ($method === 'chat.postMessage') {
            $tmpName = preg_replace('/^[#]|^[@]/', '', $this->get('name'));
            $username = $this->call('auth.test');
            if ($tmpName != $username['user'] || in_array($tmpName, (array)$this->getChannels())) {
                $args['username'] = $username['user'];
                $args['as_user'] = true;
            }
        }
        return self::getClass('SlackHandler', $this->get('token'))->call($method, $args);
    }
}
