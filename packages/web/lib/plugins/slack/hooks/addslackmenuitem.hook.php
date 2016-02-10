<?php
class AddSlackMenuItem extends Hook {
    public $name = 'AddSlackMenuItem';
    public $description = 'Add menu item for slack';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'slack';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Slack Management'),'fa fa-slack fa-2x'));
    }
    public function addPageWithObject($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['PagesWithObjects'],$this->node);
    }
    public function addSearch($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['searchPages'],$this->node);
    }
}
$AddSlackMenuItem = new AddSlackMenuItem();
$HookManager->register('MAIN_MENU_DATA',array($AddSlackMenuItem,'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddSlackMenuItem,'addSearch'));
$HookManager->register('PAGES_WITH_OBJECTS',array($AddSlackMenuItem,'addPageWithObject'));
