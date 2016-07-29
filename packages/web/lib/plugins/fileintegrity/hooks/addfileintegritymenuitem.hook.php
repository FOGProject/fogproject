<?php
class AddFileIntegrityMenuItem extends Hook {
    public $name = 'AddFileIntegrityMenuItem';
    public $description = 'Add menu item for File Integrity Information';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'fileintegrity';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('File Integrity Management'),'fa fa-list-ol fa-2x'));
    }
    public function addSearch($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['searchPages'],$this->node);
    }
    public function removeActionBox($arguments) {
        if (in_array($this->node,(array)$_SESSION['PluginsInstalled']) && $_REQUEST['node'] == $this->node) $arguments['actionbox'] = '';
    }
    public function addPageWithObject($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['PagesWithObjects'],$this->node);
    }
}
$AddFileIntegrityMenuItem = new AddFileIntegrityMenuItem();
$HookManager->register('MAIN_MENU_DATA',array($AddFileIntegrityMenuItem,'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddFileIntegrityMenuItem,'addSearch'));
$HookManager->register('ACTIONBOX',array($AddFileIntegrityItem,'removeActionBox'));
$HookManager->register('PAGES_WITH_OBJECTS', array($AddFileIntegrityMenuItem, 'addPageWithObject'));
