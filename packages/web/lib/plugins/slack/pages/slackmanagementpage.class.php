<?php
class SlackManagementPage extends FOGPage {
    public $node = 'slack';
    public function __construct($name = '') {
        $this->name = 'Slack Management';
        parent::__construct($this->name);
        $this->menu = array(
            'list' => sprintf($this->foglang['ListAll'],_('Slack Accounts')),
            'add' => _('Link Slack Account'),
        );
        if ($_REQUEST['id']) unset($this->subMenu);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            _('User Name'),
            _('Delete'),
        );
        $this->templates = array(
            '<input type="checkbox" name="slack[]" value="${id}" class="toggle-action" checked/>',
            '${name}',
            sprintf('<a href="?node=%s&sub=delete&id=${id}" title="%s"><i class="fa fa-minus-circle fa-1x icon hand"></i></a>',$this->node,_('Delete')),
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'r'),
        );
    }
    public function index() {
        $this->title = _('Accounts');
        foreach ((array)$this->getClass('SlackManager')->find() AS &$Token) {
            $this->data[] = array(
                'id'      => $Token->get('id'),
                'name'    => $Token->get('name'),
            );
            unset($Token);
        }
        $this->HookManager->processEvent('SLACK_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
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
            _('Access Token') => '<input class="smaller" type="text" name="apiToken" />',
            _('User to post to') => '<input class="smaller" type="text" name="user" />',
            '' => sprintf('<input name="add" class="smaller" type="submit" value="%s"/>',_('Add')),
        );
        foreach((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($fields);
        $this->HookManager->processEvent('SLACK_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $token = trim($_REQUEST['apiToken']);
            $user = trim($_REQUEST['user']);
            if (!$token) throw new Exception(_('Please enter an access token'));
            if ($this->getClass('SlackManager')->exists($user)) throw new Exception(_('Account already linked'));
            $Slack = $this->getClass('Slack')
                ->set('token',$token)
                ->set('name',$user);
            if (!$Slack->save()) throw new Exception(_('Failed to create'));
            $this->getClass('SlackHandler',$Slack->get('token'))->call('chat.postMessage',array('channel'=>"@{$Slack->get(name)}",'text'=>'Account linked for fog.'));
            $this->setMessage(_('Account Added!'));
            $this->redirect('?node=slack&sub=list');
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
