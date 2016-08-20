<?php
class AddLocationMenuItem extends Hook
{
    public $name = 'AddLocationMenuItem';
    public $description = 'Add menu item for location';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'location';
    public function MenuData($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $this->arrayInsertAfter('storage', $arguments['main'], $this->node, array(_('Location Management'), 'fa fa-globe fa-2x'));
        $Service = self::getClass('Service')->set('name', 'FOG_SNAPIN_LOCATION_SEND_ENABLED')->load('name');
        if (!$Service->isValid()) {
            $Service
                ->set('description', _('This setting defines sending the location url based on the host that checks in.  It tells the client to download snapins from the host defined location where available. Default is disabled.'))
                ->set('value', 0)
                ->set('category', 'FOG Client - Snapins')
                ->save();
        }
    }
    public function addSearch($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        array_push($arguments['searchPages'], $this->node);
    }
    public function addPageWithObject($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        array_push($arguments['PagesWithObjects'], $this->node);
    }
}
$AddLocationMenuItem = new AddLocationMenuItem();
$HookManager->register('MAIN_MENU_DATA', array($AddLocationMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddLocationMenuItem, 'addSearch'));
$HookManager->register('PAGES_WITH_OBJECTS', array($AddLocationMenuItem, 'addPageWithObject'));
