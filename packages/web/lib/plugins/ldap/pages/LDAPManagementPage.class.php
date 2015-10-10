<?php
class LDAPManagementPage extends FOGPage {
    public $node = 'ldap';
    public function __construct($name = '') {
        $this->name = 'LDAP Management';
        // Call parent constructor
        parent::__construct($name);
        if ($_REQUEST['id']) {
            $this->obj = $this->getClass(LDAP,$_REQUEST[id]);
            $this->subMenu = array(
                "$this->linkformat" => $this->foglang[General],
                "$this->delformat" => $this->foglang[Delete],
            );
            $this->notes = array(
                _('LDAP Server Name') => $this->obj->get(name),
                _('LDAP Server Address') => $this->obj->get(address),
            );
        }
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"  checked/>',
            'LDAP Server Name',
            'LDAP Server Description',
            'LDAP Server',
            'Port',
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="wolbroadcast[]" value="${id}" class="toggle-action" checked/>',
            '<a href="?node=ldap&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${description}',
            '${address}',
            '${port}',
        );
        $this->attributes = array(
            array('class' => 'c','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
        );
    }
    // Pages
    public function index() {
        // Set title
        $this->title = _('Search');
        if ($this->FOGCore->getSetting(FOG_DATA_RETURNED) > 0 && $this->getClass(LDAPManager)->count() > $this->FOGCore->getSetting(FOG_DATA_RETURNED) && $_REQUEST[sub] != 'list')
            $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        // Find data
        $LDAPs = $this->getClass(LDAPManager)->find();
        // Row data
        foreach ((array)$LDAPs AS $i => &$LDAP) {
            $this->data[] = array(
                id => $LDAP->get(id),
                name => $LDAP->get(name),
                description => $LDAP->get(description),
                address => $LDAP->get(address),
                DN => $LDAP->get(DN),
                port => $LDAP->get(port),

            );
        }
        unset($LDAP);
        // Hook
        $this->HookManager->event[] = 'LDAP_DATA';
        $this->HookManager->processEvent('LDAP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function search_post() {
        // Variables
        $keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $_REQUEST[crit]) . '%');
        // To assist with finding by storage group or location.
        $where = array(
            id => $keyword,
            name => $keyword,
            description => $keyword,
            address => $keyword,
            DN => $keyword,
        );
        // Find data -> Push data
        $LDAPs = $this->getClass(LDAPManager)->search();
        foreach ($LDAPs AS $i => &$LDAP) {
            $this->data[] = array(
                id => $LDAP->get(id),
                name => $LDAP->get(name),
                description => $LDAP->get(description),
                address => $LDAP->get(address),
                DN => $LDAP->get(DN),
                port => $LDAP->get(port),
            );
        }
        // Hook
        $this->HookManager->event[] = 'LDAP_DATA';
        $this->HookManager->processEvent('LDAP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function add() {
        $this->title = 'New LDAP Server';
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('LDAP Server Name') => '<input class="smaller" type="text" name="name" />',
            _('LDAP Server Description') => '<input class="smaller" type="text" name="description" />',
            _('LDAP Server Address') => '<input class="smaller" type="text" name="address" />',
            _('DN') => '<input class="smaller" type="text" name="DN" />',
            _('Server Port') => '<input class="smaller" type="text" name="port" />',
            '<input type="hidden" name="add" value="1" />' => '<input class="smaller" type="submit" value="'.('Add').'" />',
        );
        echo '<form method="post" action="'.$this->formAction.'">';
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($input);
        // Hook
        $this->HookManager->event[] = 'LDAP_ADD';
        $this->HookManager->processEvent('LDAP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        echo '</form>';
    }
    public function add_post() {
        try {
            $name = trim($_REQUEST[name]);
            $address = trim($_REQUEST[address]);
            if ($this->getClass(LDAPManager)->exists(trim($_REQUEST[name]))) throw new Exception('LDAP server already Exists, please try again.');
            if (!$name) throw new Exception('Please enter a name for this LDAP server.');
            if (empty($address)) throw new Exception('Please enter a LDAP server address');
            $LDAP = $this->getClass(LDAP)
                ->set(name,$name)
                ->set(description,$_REQUEST[description])
                ->set(address,$address)
                ->set(DN,$REQUEST[DN])
                ->set(port,$_REQUEST[port]);
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
        // Find
        $LDAP = $this->obj;
        // Title
        $this->title = sprintf('%s: %s', 'Edit', $LDAP->get('name'));
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('LDAP Server Name') => '<input class="smaller" type="text" name="name" value="${ldap_name}" />',
            _('LDAP Server Description') => '<input class="smaller" type="text" name="description" value="${ldap_description}" />',
            _('LDAP Server Address') => '<input class="smaller" type="text" name="address" value="${ldap_address}" />',
            _('DN') => '<input class="smaller" type="text" name="DN" value="${ldap_DN}" />',
            _('Server Port') => '<input class="smaller" type="text" name="port" value="${ldap_port}" />',
            '<input type="hidden" name="update" value="1" />' => '<input type="submit" class="smaller" value="'._('Update').'" />',
        );
        echo '<form method="post" action="'.$this->formAction.'&id='.$LDAP->get('id').'">';
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'ldap_name' => $LDAP->get(name),
                'ldap_description' => $LDAP->get(description),
                'ldap_address' => $LDAP->get(address),
                'ldap_DN' => $LDAP->get(DN),
                'ldap_port'	=> $LDAP->get(port),
            );
        }
        // Hook
        $this->HookManager->event[] = 'LDAP_EDIT';
        $this->HookManager->processEvent('LDAP_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        echo '</form>';
    }
    public function edit_post() {
        $LDAP = $this->obj;
        $LDAPMan = $LDAP->getManager();
        $this->HookManager->event[] = 'LDAP_EDIT_POST';
        $this->HookManager->processEvent('LDAP_EDIT_POST', array('LDAP'=> &$LDAP));
        try {
            if ($_REQUEST[name] != $LDAP->get(name) && $LDAPMan->exists($_REQUEST[name])) throw new Exception('A LDAP Server with that name already exists.');
            if (empty($_REQUEST[address])) throw new Exception('LDAP server address is empty!!');
            if ($_REQUEST[update]) {
                if ($_REQUEST[name] != $LDAP->get(name)) $LDAP->set(name,$_REQUEST[name]);

                if ($_REQUEST[description] != $LDAP->get(description)) $LDAP->set(description,$_REQUEST[description]);

                if ($_REQUEST[address] != $LDAP->get(address)) $LDAP->set(address,$_REQUEST[address]);
                if ($_REQUEST[DN] != $LDAP->get(DN)) $LDAP->set(DN,$_REQUEST[DN]);
                if ($_REQUEST[port] != $LDAP->get(port)) $LDAP->set(port,$_REQUEST[port]);
                if ($LDAP->save()) {
                    $this->setMessage('LDAP Server Updated');
                    $this->redirect('?node=ldap&sub=edit&id='.$LDAP->get('id'));
                }
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
