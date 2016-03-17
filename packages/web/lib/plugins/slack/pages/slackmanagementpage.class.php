<?php
class SlackManagementPage extends FOGPage {
    public $node = 'slack';
    public function __construct($name = '') {
        $this->name = 'Slack Management';
        parent::__construct($this->name);
        $this->menu = array(
            'list' => sprintf(self::$foglang['ListAll'],_('Slack Accounts')),
            'add' => _('Link Slack Account'),
        );
        if ($_REQUEST['id']) unset($this->subMenu);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Team'),
            _('Created By'),
            _('User/Channel Name'),
            _('Delete'),
        );
        $this->templates = array(
            '<input type="checkbox" name="slack[]" value="${id}" class="toggle-action"/>',
            '${team}',
            '${createdBy}',
            '${name}',
            sprintf('<a href="?node=%s&sub=delete&id=${id}" title="%s"><i class="fa fa-minus-circle fa-1x icon hand"></i></a>',$this->node,_('Delete')),
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l','width'=> 50),
            array('class' => 'l','width'=> 80),
            array('class' => 'l','width'=> 80),
            array('class' => 'r filter-false','width' => 16),
        );
    }
    public function search() {
        $this->index();
    }
    public function index() {
        $this->title = _('Accounts');
        foreach ((array)self::getClass('SlackManager')->find() AS &$Token) {
            if (!$Token->isValid()) continue;
            $team_name = $Token->call('auth.test');
            $this->data[] = array(
                'id'      => $Token->get('id'),
                'team' => $team_name['team'],
                'createdBy' => $team_name['user'],
                'name'    => $Token->get('name'),
            );
            unset($Token);
        }
        self::$HookManager->processEvent('SLACK_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        $this->render();
    }
    public function add() {
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
            _('Access Token') => sprintf('<input class="smaller" type="text" name="apiToken" value="%s"/>',$_REQUEST['apiToken']),
            _('User/Channel to post to') => sprintf('<input class="smaller" type="text" name="user" value="%s"/>',$_REQUEST['user']),
            '&nbsp;' => sprintf('<input name="add" class="smaller" type="submit" value="%s"/>',_('Add')),
        );
        foreach((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($fields);
        self::$HookManager->processEvent('SLACK_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $token = trim($_REQUEST['apiToken']);
            $usertype = preg_match('/^[@]/',trim($_REQUEST['user']));
            $channeltype = preg_match('/^[#]/',trim($_REQUEST['user']));
            $usersend = trim($_REQUEST['user']);
            if (!$usertype && !$channeltype) throw new Exception(_('Must use an @ or # to signify if this is a user or channel to send message to!'));
            $user = preg_replace('/^[#]|^[@]/','',trim($_REQUEST['user']));
            if (!$token) throw new Exception(_('Please enter an access token'));
            $Slack = self::getClass('Slack')
                ->set('token',$token)
                ->set('name',$usersend);
            if (!$Slack->verifyToken()) throw new Exception(_('Invalid token passed'));
            if (array_search($user,array_merge((array)$Slack->getChannels(),(array)$Slack->getUsers())) === false) throw new Exception(_('Invalid user and/or channel passed'));
            if (self::getClass('SlackManager')->exists($token,'','token') && self::getClass('SlackManager')->exists($usersend)) throw new Exception(_('Account already linked'));
            if (!$Slack->save()) throw new Exception(_('Failed to create'));
            $args = array(
                'channel' => $Slack->get('name'),
                'text' => sprintf('%s %s: %s',$user,_('Account linked to FOG GUI at'),$this->getSetting('FOG_WEB_HOST')),
            );
            $Slack->call('chat.postMessage',$args);
            $this->setMessage(_('Account Added!'));
            $this->redirect('?node=slack&sub=list');
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
