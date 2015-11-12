<?php
class PushbulletManagementPage extends FOGPage {
    public $node = 'pushbullet';
    public function __construct($name = '') {
        $this->name = 'Pushbullet Management';
        parent::__construct($this->name);
        $this->menu = array(
            'list' => sprintf($this->foglang['ListAll'],_('Pushbullet Accounts')),
            'add' => _('Link Pushbullet Account'),
        );
        if ($_REQUEST['id']) {
            unset($this->subMenu);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            _('Name'),
            _('Email'),
            _('Delete'),
        );
        $this->templates = array(
            '<input type="checkbox" name="pushbullet[]" value="${id}" class="toggle-action" checked/>',
            '${name}',
            '${email}',
            sprintf('<a href="?node=%s&sub=delete&id=${id}" title="%s"><i class="fa fa-minus-circle fa-1x icon hand"></i></a>',$this->node,_('Delete')),
        );
        $this->attributes = array(
            array('class' => 'c','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'r'),
        );
    }
    public function index() {
        $this->title = _('Accounts');
        $users = $this->getClass('PushbulletManager')->find();
        foreach ((array)$this->getClass('PushbulletManager')->find() AS $Token) {
            $this->data[] = array(
                'name'    => $Token->get('name'),
                'email'   => $Token->get('email'),
                'id'      => $Token->get('id'),
            );
        }
        $this->HookManager->processEvent('PUSHBULLET_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = 'Link New Account';
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
            '&nbsp;' => printf('<input name="add" class="smaller" type="submit" value="%s"/>',_('Add')),
        );
        printf('<form method="post" action="%s">',$this->formAction);
        foreach((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        $this->HookManager->event[] = 'PUSHBULLET_ADD';
        $this->HookManager->processEvent('PUSHBULLET_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $token = trim($_REQUEST['apiToken']);
            if ($this->getClass('PushbulletManager')->exists(trim($_REQUEST['apiToken']))) throw new Exception('Account already linked');
            if (!$token) throw new Exception('Please enter an access token');
            $userInfo = $this->getClass('PushbulletHandler',$token)->getUserInformation();
            $Bullet = new Pushbullet(array(
                'token' => $token,
                'name'  => $userInfo->name,
                'email' => $userInfo->email,
            ));
            if ($Bullet->save()) {
                $this->getClass('PushbulletHandler',$token)->pushNote('', 'FOG', 'Account linked');
                $this->setMessage('Account Added!');
                $this->redirect('?node=pushbullet&sub=list');
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
