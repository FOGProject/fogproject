<?php
class LDAPManagementPage extends FOGPage {
    public $node = 'ldap';
    public function __construct($name = '') {
        $this->name = 'LDAP Management';
        parent::__construct($name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat" => $this->foglang['General'],
                "$this->delformat" => $this->foglang['Delete'],
            );
            $this->notes = array(
                _('LDAP Server Name') => $this->obj->get('name'),
                _('LDAP Server Address') => $this->obj->get('address'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            'LDAP Server Name',
            'LDAP Server Description',
            'LDAP Server',
            'Port',
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
    }
    public function index() {
        $this->title = _('Search');
        if ($this->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('LDAPManager')->count() > $this->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        foreach ((array)$this->getClass('LDAPManager')->find() AS $i => &$LDAP) {
            if (!$LDAP->isValid()) continue;
            $this->data[] = array(
                'id' => $LDAP->get('id'),
                'name' => $LDAP->get('name'),
                'description' => $LDAP->get('description'),
                'address' => $LDAP->get('address'),
                'DN' => $LDAP->get('DN'),
                'port' => $LDAP->get('port'),
            );
            unset($LDAP);
        }
        $this->HookManager->processEvent('LDAP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $ids = $this->getClass('LDAPManager')->search();
        foreach ($this->getClass('LDAPManager')->search('',true) AS $i => &$LDAP) {
            if (!$LDAP->isValid()) continue;
            $this->data[] = array(
                'id' => $LDAP->get('id'),
                'name' => $LDAP->get('name'),
                'description' => $LDAP->get('description'),
                'address' => $LDAP->get('address'),
                'DN' => $LDAP->get('DN'),
                'port' => $LDAP->get('port'),
            );
            unset($LDAP);
        }
        $this->HookManager->processEvent('LDAP_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add() {
        $this->title = 'New LDAP Server';
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
            '&nbsp;' => sprintf('<input class="smaller" name="add" type="submit" value="%s"/>',_('Add')),
        );
        printf('<form method="post" action="%s">',$this->formAction);
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($input);
        $this->HookManager->processEvent('LDAP_ADD',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            if (!isset($_REQUEST['add'])) throw new Exception(_('Not able to add'));
            $name = trim($_REQUEST['name']);
            $address = trim($_REQUEST['address']);
            if (empty($name)) throw new Exception('Please enter a name for this LDAP server.');
            if (empty($address)) throw new Exception('Please enter a LDAP server address');
            if ($this->getClass('LDAPManager')->exists($name)) throw new Exception('LDAP server already Exists, please try again.');
            $LDAP = $this->getClass('LDAP')
                ->set('name',$name)
                ->set('description',$_REQUEST['description'])
                ->set('address',$address)
                ->set('DN',$REQUEST['DN'])
                ->set('port',$_REQUEST['port']);
            if ($LDAP->save()) {
                $this->setMessage('LDAP Server Added, editing!');
                $this->redirect('?node=ldap&sub=edit&id='.$LDAP->get('id'));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit() {
        $this->title = sprintf('%s: %s', 'Edit', $this->obj->get('name'));
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
            '&nbsp;' => sprintf('<input name="update" type="submit" class="smaller" value="%s"/>',_('Update')),
        );
        printf('<form method="post" action="%s">',$this->formAction);
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        $this->HookManager->processEvent('LDAP_EDIT',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        $this->HookManager->processEvent('LDAP_EDIT_POST', array('LDAP'=> &$LDAP));
        try {
            if (!isset($_REQUEST['update'])) throw new Exception(_('Not able to update'));
            $name = trim($_REQUEST['name']);
            $address = trim($_REQUEST['address']);
            if (empty($name)) throw new Exception('Please enter a name for this LDAP server.');
            if (empty($address)) throw new Exception('Please enter a LDAP server address');
            if ($name != $this->obj->get('name') && $this->obj->getManager()->exists($name)) throw new Exception(_('An LDAP Server with that name already exists.'));
            $LDAP = $this->obj
                ->set('name',$name)
                ->set('description',$_REQUEST['description'])
                ->set('address',$address)
                ->set('DN',$_REQUEST['DN'])
                ->set('port',$_REQUEST['port']);
            if (!$LDAP->save()) throw new Exception(_('Database update failed'));
            $this->HookManager->processEvent('LDAP_EDIT_SUCCESS',array('LDAP'=>&$this->obj));
            $this->setMessage(_('LDAP information updated!'));
        } catch (Exception $e) {
            $this->HookManager->processEvent('LDAP_EDIT_FAIL',array('LDAP'=>&$this->obj));
            $this->setMessage($e->getMessage());
        }
        $this->redirect($this->formAction);
    }
}
