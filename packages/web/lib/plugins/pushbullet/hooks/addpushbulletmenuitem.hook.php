<?php
class AddPushbulletMenuItem extends Hook {
    public $name = 'AddPushbulletMenuItem';
    public $description = 'Add menu item for pushbullet';
    public $author = 'Joe Schmitt';
    public $active = true;
    public $node = 'pushbullet';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Pushbullet Management'),'fa fa-bell fa-2x'));
    }
    public function addPageWithObject($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['PagesWithObjects'],$this->node);
    }
}
$AddPushbulletMenuItem = new AddPushbulletMenuItem();
$HookManager->register('MAIN_MENU_DATA',array($AddPushbulletMenuItem,'MenuData'));
$HookManager->register('PAGES_WITH_OBJECTS',array($AddPushbulletMenuItem,'addPageWithObject'));
