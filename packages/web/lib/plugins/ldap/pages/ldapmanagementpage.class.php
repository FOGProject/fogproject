<?php
class LDAPManagementPage extends FOGPage {
    public $node = 'ldap';
    public function __construct($name = '') {
        $this->name = 'LDAP Management';
        parent::__construct($name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
                "$this->delformat" => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('LDAP Server Name') => $this->obj->get('name'),
                _('LDAP Server Address') => $this->obj->get('address'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('LDAP Server Name'),
            _('LDAP Server Description'),
            _('LDAP Server'),
            _('Port'),
        );
        $this->templates = array(
            '<input type="checkbox" name="ldap[]" value="${id}" class="toggle-action"/>',
            '<a href="?node=ldap&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${description}',
            '${address}',
            '${port}',
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
        );
        self::$returnData = function(&$LDAP) {
            if (!$LDAP->isValid()) return;
            $this->data[] = array(
                'id' => $LDAP->get('id'),
                'name' => $LDAP->get('name'),
                'description' => $LDAP->get('description'),
                'address' => $LDAP->get('address'),
                'DN' => $LDAP->get('DN'),
                'port' => $LDAP->get('port'),
            );
            unset($LDAP);
        };
    }
    public function index() {
        $this->title = _('All LDAPs');
        if ($this->getSetting('FOG_DATA_RETURNED') > 0 && self::getClass($this->childClass)->getManager()->count() > $this->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('LDAP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('LDAP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = _('New LDAP Server');
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
            _('LDAP Server Name') => '<input class="smaller" type="text" name="name"/>',
            _('LDAP Server Description') => '<input class="smaller" type="text" name="description"/>',
            _('LDAP Server Address') => '<input class="smaller" type="text" name="address"/>',
            _('DN') => '<input class="smaller" type="text" name="DN"/>',
            _('Server Port') => '<input class="smaller" type="text" name="port"/>',
            '' => sprintf('<input class="smaller" name="add" type="submit" value="%s"/>',_('Add')),
        );
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent('LDAP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            if (!isset($_REQUEST['add'])) throw new Exception(_('Not able to add'));
            $name = trim($_REQUEST['name']);
            $address = trim($_REQUEST['address']);
            if (empty($name)) throw new Exception(_('Please enter a name for this LDAP server.'));
            if (empty($address)) throw new Exception(_('Please enter a LDAP server address'));
            if (self::getClass('LDAPManager')->exists($name)) throw new Exception(_('LDAP server already Exists, please try again.'));
            $LDAP = self::getClass('LDAP')
                ->set('name',$name)
                ->set('description',$_REQUEST['description'])
                ->set('address',$address)
                ->set('DN',$_REQUEST['DN'])
                ->set('port',$_REQUEST['port']);
            if ($LDAP->save()) {
                $this->setMessage(_('LDAP Server Added, editing!'));
                $this->redirect(sprintf('?node=ldap&sub=edit&id=%s',$LDAP->get('id')));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
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
            _('LDAP Server Name') => sprintf('<input class="smaller" type="text" name="name" value="%s"/>',$this->obj->get('name')),
            _('LDAP Server Description') => sprintf('<input class="smaller" type="text" name="description" value="%s"/>',$this->obj->get('description')),
            _('LDAP Server Address') => sprintf('<input class="smaller" type="text" name="address" value="%s"/>',$this->obj->get('address')),
            _('DN') => sprintf('<input class="smaller" type="text" name="DN" value="%s"/>',$this->obj->get('DN')),
            _('Server Port') => sprintf('<input class="smaller" type="text" name="port" value="%s"/>',$this->obj->get('port')),
            '' => sprintf('<input name="update" type="submit" class="smaller" value="%s"/>',_('Update')),
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent('LDAP_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        self::$HookManager->processEvent('LDAP_EDIT_POST', array('LDAP'=> &$LDAP));
        try {
            if (!isset($_REQUEST['update'])) throw new Exception(_('Not able to update'));
            $name = trim($_REQUEST['name']);
            $address = trim($_REQUEST['address']);
            if (empty($name)) throw new Exception(_('Please enter a name for this LDAP server.'));
            if (empty($address)) throw new Exception(_('Please enter a LDAP server address'));
            if ($name != $this->obj->get('name') && $this->obj->getManager()->exists($name)) throw new Exception(_('An LDAP Server with that name already exists.'));
            $LDAP = $this->obj
                ->set('name',$name)
                ->set('description',$_REQUEST['description'])
                ->set('address',$address)
                ->set('DN',$_REQUEST['DN'])
                ->set('port',$_REQUEST['port']);
            if (!$LDAP->save()) throw new Exception(_('Database update failed'));
            self::$HookManager->processEvent('LDAP_EDIT_SUCCESS',array('LDAP'=>&$this->obj));
            $this->setMessage(_('LDAP information updated!'));
        } catch (Exception $e) {
            self::$HookManager->processEvent('LDAP_EDIT_FAIL',array('LDAP'=>&$this->obj));
            $this->setMessage($e->getMessage());
        }
        $this->redirect($this->formAction);
    }
}
