<?php
/**
 * Slack page edit/add.
 *
 * PHP Version 5
 *
 * @category SlackManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Slack page edit/add.
 *
 * @category SlackManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SlackManagementPage extends FOGPage
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
        $this->templates = [
            '',
            '',
            ''
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
        $apiToken = filter_input(
            INPUT_POST,
            'apiToken'
        );
        $user = filter_input(
            INPUT_POST,
            'user'
        );
        $labelClass = 'col-sm-2 control-label';
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
        echo self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually create the entry.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $token = trim(
                filter_input(
                    INPUT_POST,
                    'apiToken'
                )
            );
            $user = trim(
                filter_input(
                    INPUT_POST,
                    'user'
                )
            );
            $usertype = preg_match(
                '/^[@]/',
                $user
            );
            $channeltype = preg_match(
                '/^[#]/',
                $user
            );
            if (!$usertype && !$channeltype) {
                throw new Exception(
                    sprintf(
                        '%s @ %s # %s!',
                        _('Must use an'),
                        _('or'),
                        _('to signify if this is a user or channel to send to')
                    )
                );
            }
            if (!$token) {
                throw new Exception(
                    _('Please enter an access token')
                );
            }
            $Slack = self::getClass('Slack')
                ->set('token', $token)
                ->set('name', $user);
            if (!$Slack->verifyToken()) {
                throw new Exception(
                    _('Invalid token passed')
                );
            }
            $search = array_search(
                $user,
                self::fastmerge(
                    (array)$Slack->getChannels(),
                    (array)$Slack->getUsers()
                )
            );
            if ($search === false) {
                throw new Exception(
                    _('Invalid user and/or channel passed')
                );
            }
            $exists = self::getClass('SlackManager')
                ->exists(
                    $token,
                    '',
                    'token'
                );
            $exists2 = self::getClass('SlackManager')
                ->exists($usersend);
            if ($exists || $exists2) {
                throw new Exception(
                    _('Account already linked')
                );
            }
            if (!$Slack->save()) {
                throw new Exception(
                    _('Failed to create')
                );
            }
            $args = array(
                'channel' => $Slack->get('name'),
                'text' => sprintf(
                    '%s %s: %s',
                    $user,
                    _('Account linked to FOG GUI at'),
                    self::getSetting('FOG_WEB_HOST')
                )
            );
            $Slack->call(
                'chat.postMessage',
                $args
            );
            $msg = json_encode(
                array(
                    'msg' => _('Account successfully added!'),
                    'title' => _('Link Slack Account Success')
                )
            );
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Link Slack Account Fail')
                )
            );
        }
        unset($Slack);
        echo $msg;
        exit;
    }
}
