<?php
class AddLDAPSubMenuItems extends Hook
{
        var $name = 'AddLDAPSubMenuItems';
        var $description = 'Add sub menu items for LDAP server Management';
        var $author = 'Fernando Gietz';
        var $active = true;
        var $node = 'ldap';
        public function SubMenuData($arguments)
        {
                $plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1, 'state' => 1)));
                if ($plugin && $plugin->isValid())
                {
                        $arguments['submenu'][$this->node]['search'] = $this->foglang['NewSearch'];
                        $arguments['submenu'][$this->node]['list'] = sprintf($this->foglang['ListAll'],_('LDAP Server'));
                        $arguments['submenu'][$this->node]['add'] = sprintf($this->foglang['CreateNew'],_('LDAP Server'));
                        if ($_REQUEST['id'])
                        {
                                $LDAP = new LDAP($_REQUEST['id']);
                                $arguments['id'] = 'id';
                                $arguments['submenu'][$this->node]['id'][$_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=delete&id='.$_REQUEST['id']] = $this->foglang['Delete'];
                        }
                }
        }
        public function SubMenuNotes($arguments)
        {
                $plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1, 'state' => 1)));
                if ($plugin && $plugin->isValid())
                {
                        if ($_REQUEST['node'] == $this->node && $_REQUEST['id'])
                        {
                                $arguments['name'] = sprintf($this->foglang['SelMenu'],$this->foglang['LDAP']);
                                $arguments['object'] = new LDAP($_REQUEST['id']);
                                $arguments['title'] = array(
                                        _('LDAP Server Name') => $arguments['object']->get('name'),
                                        _('LDAP Server Address') => $arguments['object']->get('address'),
                                );
                        }
                }
        }
}
$AddLDAPSubMenuItems = new AddLDAPSubMenuItems();
// Register Hooks
$HookManager->register('SUB_MENULINK_NOTES', array($AddLDAPSubMenuItems,'SubMenuNotes'));
$HookManager->register('SUB_MENULINK_DATA', array($AddLDAPSubMenuItems,'SubMenuData'));
