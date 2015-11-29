<?php
class AddMenuNotesItems extends Hook {
    public $name = 'AddMenuNotesItems';
    public $description = 'Add menu items to the management page';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'accesscontrol';
    public function AddMenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $arguments['main'] = $this->array_insert_after('user',$arguments['main'],$this->node,array(_('Access Control'),'fa fa-user-secret fa-2x'));
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
$AddMenuNotesItems = new AddMenuNotesItems();
$HookManager->register('MAIN_MENU_DATA',array($AddMenuNotesItems,'AddMenuData'));
$HookManager->register('SEARCH_PAGES',array($AddMenuNotesItems,'AddSearch'));
$HookManager->register('PAGES_WITH_OBJECTS',array($AddMenuNotesItems,'addPageWithObject'));
