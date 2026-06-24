<?php
/**
 * Slack page edit/add.
 *
 * PHP Version 5
 *
 * @category SlackManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Slack page edit/add.
 *
 * @category SlackManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SlackManagement extends FOGPage
{
    /**
     * Node to work with.
     *
     * @var string
     */
    public $node = 'slack';
    /**
     * Constructor for the page.
     *
     * @param string $name The name to set.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Slack Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Team'),
            _('Created By'),
            _('User/Channel Name')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $apiToken = filter_input(INPUT_POST, 'apiToken');
        $user = filter_input(INPUT_POST, 'user');

        $labelClass = 'col-sm-3 control-label';

        return [
            self::makeLabel(
                $labelClass,
                'apiToken',
                _('Access Token')
            ) => self::makeInput(
                'form-control slacktoken-input',
                'apiToken',
                _('Slack Token'),
                'text',
                'apiToken',
                $apiToken,
                true
            ),
            self::makeLabel(
                $labelClass,
                'user',
                _('User/Channel')
            ) => self::makeInput(
                'form-control slackuser-input',
                'user',
                _('Slack User/Slack Channel'),
                'text',
                'user',
                $user,
                true
            )
        ];
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'slack',
            _('Link Slack Account'),
            'SLACK_ADD_FIELDS',
            'Slack'
        );
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'slack',
            'SLACK_ADD_FIELDS',
            'Slack'
        );
    }
    /**
     * Actually create the entry.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Slack',
            'SLACK_ADD',
            _('Account successfully added!'),
            _('Link Slack Account Success'),
            _('Link Slack Account Fail'),
            function (&$serverFault) {
                $token = trim(
                    filter_input(INPUT_POST, 'apiToken')
                );
                $user = trim(
                    filter_input(INPUT_POST, 'user')
                );
                $usertype = preg_match('/^[@]/', $user);
                $channeltype = preg_match('/^[#]/', $user);
                if (!$usertype && !$channeltype) {
                    throw new Exception(
                        _('Please start user/channel with @/# respectively')
                    );
                }
                $Slack = self::getClass('Slack')
                    ->set('token', $token)
                    ->set('name', $user);
                if (!$Slack->verifyToken()) {
                    throw new Exception(_('Invalid token passed'));
                }
                $user = preg_replace('/^[#@]/', '', $user);
                if ($usertype) {
                    array_search(
                        $user,
                        $Slack->getUsers()
                    );
                    if ($search === false) {
                        throw new Exception(_('User not found'));
                    }
                }
                if ($channeltype) {
                    array_search(
                        $user,
                        $Slack->getChannels()
                    );
                    if ($search === false) {
                        throw new Exception(_('Channel not found'));
                    }
                }
                $exists = self::getClass('SlackManager')
                    ->exists($token, '', 'token');
                $exists2 = self::getClass('SlackManager')
                    ->exists($usersend);
                if ($exists || $exists2) {
                    throw new Exception(
                        _('Account already linked')
                    );
                }
                if (!$Slack->save()) {
                    $serverFault = true;
                    throw new Exception(
                        _('Add slack account failed!')
                    );
                }
                $args = [
                    'channel' => $Slack->get('name'),
                    'text' => sprintf(
                        '%s %s: %s',
                        $user,
                        _('Account linked to FOG GUI at'),
                        self::getSetting('FOG_WEB_HOST')
                    )
                ];
                $Slack->call(
                    'chat.postMessage',
                    $args
                );
                return $Slack;
            }
        );
    }
}
