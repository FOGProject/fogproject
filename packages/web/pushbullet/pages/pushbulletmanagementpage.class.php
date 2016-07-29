<?php
class PushbulletManagementPage extends FOGPage {
    public $node = 'pushbullet';
    public function __construct($name = '') {
        $this->name = 'Pushbullet Management';
        parent::__construct($this->name);
        $this->menu = array(
            'list' => sprintf(self::$foglang['ListAll'],_('Pushbullet Accounts')),
            'add' => _('Link Pushbullet Account'),
        );
        if ($_REQUEST['id']) {
            unset($this->subMenu);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Name'),
            _('Email'),
            _('Delete'),
        );
        $this->templates = array(
            '<input type="checkbox" name="pushbullet[]" value="${id}" class="toggle-action"/>',
            '${name}',
            '${email}',
            sprintf('<a href="?node=%s&sub=delete&id=${id}" title="%s"><i class="fa fa-minus-circle fa-1x icon hand"></i></a>',$this->node,_('Delete')),
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'r'),
        );
        self::$returnData = function(&$PushBullet) {
            if (!$PushBullet->isValid()) return;
            $this->data[] = array(
                'name'    => $PushBullet->get('name'),
                'email'   => $PushBullet->get('email'),
                'id'      => $PushBullet->get('id'),
            );
            unset($PushBullet);
        };
    }
    public function index() {
        $this->title = _('Accounts');
        $this->data = array();
        array_map(self::$returnData,(array)self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('PUSHBULLET_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        $this->render();
    }
    public function search() {
        $this->index();
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
            '' => sprintf('<input name="add" class="smaller" type="submit" value="%s"/>',_('Add')),
        );
        foreach((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($fields);
        self::$HookManager->processEvent('PUSHBULLET_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $token = trim($_REQUEST['apiToken']);
            if (self::getClass('PushbulletManager')->exists(trim($_REQUEST['apiToken']))) throw new Exception(_('Account already linked'));
            if (!$token) throw new Exception(_('Please enter an access token'));
            $userInfo = self::getClass('PushbulletHandler',$token)->getUserInformation();
            $Bullet = self::getClass('Pushbullet')
                ->set('token',$token)
                ->set('name',$userInfo->name)
                ->set('email',$userInfo->email);
            if (!$Bullet->save()) throw new Exception(_('Failed to create'));
            self::getClass('PushbulletHandler',$token)->pushNote('', 'FOG', 'Account linked');
            $this->setMessage(_('Account Added!'));
            $this->redirect('?node=pushbullet&sub=list');
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
