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
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Link Slack Account');
        $apiToken = filter_input(INPUT_POST, 'apiToken');
        $user = filter_input(INPUT_POST, 'user');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
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

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'SLACK_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Slack' => self::getClass('Slack')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'slack-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="slack-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-borader">';
        echo '<h4 class="box-title">';
        echo _('Link Slack Account');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function addModal()
    {
        $apiToken = filter_input(INPUT_POST, 'apiToken');
        $user = filter_input(INPUT_POST, 'user');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
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

        self::$HookManager->processEvent(
            'SLACK_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Slack' => self::getClass('Slack')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=slack&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually create the entry.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('SLACK_ADD_POST');
        $token = trim(
            filter_input(INPUT_POST, 'apiToken')
        );
        $user = trim(
            filter_input(INPUT_POST, 'user')
        );
        $usertype = preg_match('/^[@]/', $user);
        $channeltype = preg_match('/^[#]/', $user);

        $serverFault = false;
        try {
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
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'SLACK_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Account successfully added!'),
                    'title' => _('Link Slack Account Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SLACK_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Link Slack Account Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Slack' => &$Slack,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Slack);
        echo $msg;
        exit;
    }
}
