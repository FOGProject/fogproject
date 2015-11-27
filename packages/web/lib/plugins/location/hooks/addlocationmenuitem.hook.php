<?php
class AddLocationMenuItem extends Hook {
    public function __construct() {
        parent::__construct();
        $this->name = 'AddLocationMenuItem';
        $this->description = 'Add menu item for location';
        $this->author = 'Tom Elliott';
        $this->active = true;
        $this->node = 'location';
    }
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('Location Management'),'fa fa-globe fa-2x'));
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
$AddLocationMenuItem = new AddLocationMenuItem();
$HookManager->register('MAIN_MENU_DATA', array($AddLocationMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddLocationMenuItem, 'addSearch'));
$HookManager->register('PAGES_WITH_OBJECTS', array($AddLocationMenuItem, 'addPageWithObject'));
