<?php
class AddWOLMenuItem extends Hook {
    public function __construct() {
        parent::__construct();
        $this->name = 'AddWOLMenuItem';
        $this->description = 'Add menu item for WOL Broadcast';
        $this->author = 'Tom Elliott';
        $this->active = true;
        $this->node = 'wolbroadcast';
    }
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('WOL Broadcast Management'),'fa fa-plug fa-2x'));
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
$AddWOLMenuItem = new AddWOLMenuItem();
$HookManager->register('MAIN_MENU_DATA', array($AddWOLMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddWOLMenuItem, 'addSearch'));
$HookManager->register('PAGES_WITH_OBJECTS', array($AddMenuNotesItems, 'addPageWithObject'));
