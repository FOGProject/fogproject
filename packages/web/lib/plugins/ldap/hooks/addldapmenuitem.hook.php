<?php
class AddLDAPMenuItem extends Hook {
    public $name = 'AddLDAPMenuItem';
    public $description = 'Add menu item for LDAP';
    public $author = 'Fernando Gietz';
    public $active = true;
    public $node = 'ldap';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('LDAP Management'),'fa fa-key fa-2x'));
    }
    public function addSearch($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['searchPages'],$this->node);
    }
    public function addPageWithObject($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['PagesWithObjects'],$this->node);
    }
}
$AddLDAPMenuItem = new AddLDAPMenuItem();
$HookManager->register('MAIN_MENU_DATA',array($AddLDAPMenuItem,'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddLDAPMenuItem,'addSearch'));
$HookManager->register('PAGES_WITH_OBJECTS',array($AddLDAPMenuItem,'addPageWithObject'));
