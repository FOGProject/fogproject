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
            'list' => sprintf(self::$foglang['ListAll'], _('Slack Accounts')),
            'add' => _('Link Slack Account'),
        );
        if ($_REQUEST['id']) {
            unset($this->subMenu);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Team'),
            _('Created By'),
            _('User/Channel Name'),
            _('Delete'),
        );
        $this->templates = array(
            '<input type="checkbox" name="slack[]" value='
            . '"${id}" class="toggle-action"/>',
            '${team}',
            '${createdBy}',
            '${name}',
            sprintf(
                '<a href="?node=%s&sub=delete&id=${id}" title="%s">'
                . '<i class="fa fa-minus-circle fa-1x icon hand"></i></a>',
                $this->node,
                _('Delete')
            ),
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l','width'=> 50),
            array('class' => 'l','width'=> 80),
            array('class' => 'l','width'=> 80),
            array('class' => 'r filter-false','width' => 16),
        );
        self::$returnData = function (&$Slack) {
            if (!$Slack->isValid()) {
                return;
            }
            $team_name = $Slack->call('auth.test');
            $this->data[] = array(
                'id' => $Slack->get('id'),
                'team' => $team_name['team'],
                'createdBy' => $team_name['user'],
                'name' => $Slack->get('name'),
            );
            unset($Slack);
        };
    }
    /**
     * Search redirect to list.
     *
     * @return void
     */
    public function search()
    {
        $this->index();
    }
    /**
     * Create new entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Link New Account');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Access Token') => sprintf(
                '<input class="smaller" type="text" name='
                . '"apiToken" value="%s"/>',
                $_REQUEST['apiToken']
            ),
            _('User/Channel to post to') => sprintf(
                '<input class="smaller" type="text" name="user" value="%s"/>',
                $_REQUEST['user']
            ),
            '&nbsp;' => sprintf(
                '<input name="add" class="smaller" type="submit" value="%s"/>',
                _('Add')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager
            ->processEvent(
                'SLACK_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
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
            $token = trim($_REQUEST['apiToken']);
            $usertype = preg_match('/^[@]/', trim($_REQUEST['user']));
            $channeltype = preg_match('/^[#]/', trim($_REQUEST['user']));
            $usersend = trim($_REQUEST['user']);
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
            $user = preg_replace('/^[#]|^[@]/', '', trim($_REQUEST['user']));
            if (!$token) {
                throw new Exception(_('Please enter an access token'));
            }
            $Slack = self::getClass('Slack')
                ->set('token', $token)
                ->set('name', $usersend);
            if (!$Slack->verifyToken()) {
                throw new Exception(_('Invalid token passed'));
            }
            $search = array_search(
                $user,
                self::fastmerge(
                    (array)$Slack->getChannels(),
                    (array)$Slack->getUsers()
                )
            );
            if ($search === false) {
                throw new Exception(_('Invalid user and/or channel passed'));
            }
            $exists = self::getClass('SlackManager')
                ->exists(
                    $token,
                    '',
                    'token'
                );
            $exists2 = self::getClass('SlackManager')
                ->exists($usersend);
            if ($exists && $exists2) {
                throw new Exception(_('Account already linked'));
            }
            if (!$Slack->save()) {
                throw new Exception(_('Failed to create'));
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
            $Slack->call('chat.postMessage', $args);
            self::setMessage(_('Account Added!'));
            self::redirect('?node=slack&sub=list');
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
}
