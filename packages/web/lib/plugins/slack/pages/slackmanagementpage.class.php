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
        $this->menu = array(
            'list' => sprintf(
                self::$foglang['ListAll'],
                _('Slack Accounts')
            ),
            'add' => _('Link Slack Account'),
        );
        global $id;
        if ($id) {
            unset($this->subMenu);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Team'),
            _('Created By'),
            _('User/Channel Name')
        );
        $this->templates = array(
            '<input type="checkbox" name="slack[]" '
            . 'value="${id}" class="toggle-action"/>',
            '${team}',
            '${createdBy}',
            '${name}'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Slack the object to use
         *
         * @return void
         */
        self::$returnData = function (&$Slack) {
            $team_name = self::getClass(
                'Slack',
                $Slack->id
            )->call('auth.test');
            $this->data[] = array(
                'id' => $Slack->id,
                'team' => $team_name['team'],
                'createdBy' => $team_name['user'],
                'name' => $Slack->name,
            );
            unset($Slack);
        };
    }
    /**
     * Presents for creating a new link
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->data,
            $this->form,
            $this->span,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('Link New Account');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $value = filter_input(
            INPUT_POST,
            'apiToken'
        );
        $user = filter_input(
            INPUT_POST,
            'user'
        );
        $fields = array(
            '<label for="apiToken">'
            . _('Access Token')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" '
            . 'name="apiToken" id="apiToken" value="'
            . $value
            . '" required/>'
            . '</div>',
            '<label for="user">'
            . _('User/Channel to post to')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" '
            . 'name="user" id="user" value="'
            . $user
            . '" required/>'
            . '</div>',
            '<label for="add">'
            . _('Add Slack Account')
            . '</label>' => '<button type="submit" name="add" class="'
            . 'btn btn-info btn-block" id="add">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'SLACK_ADD',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
