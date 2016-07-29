<?php
class AddServiceConfiguration extends Hook {
    public $name = 'AddServiceConfiguration';
    public $description = 'Add Checkbox to service configuration page for snapins to enable/disable sending the location.';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'location';
    public function AddServiceCheckbox($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'service') return;
        printf('<h2>%s</h2>',_('Snapin Locations'));
        echo _('This area will allow the host checking in to tell where to download the snapin.  This is useful in the case of slow links between the main and the host.');
        echo '<br/><br/>';
        $fields = array(
            _('Enable location Sending?')=>sprintf('<input type="checkbox" name="snapinsend"%s/>',isset($_REQUEST['snapinsend']) ? ' checked' : (self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') ? ' checked' : '')),
            '&nbsp;'=>sprintf('<input type="submit" name="updateglobal" value="%s"/>',_('Update')),
        );
        unset($arguments['page']->headerData,$arguments['page']->data);
        $arguments['page']->attributes = array(
            array(),
            array('class'=>'r'),
        );
        $arguments['page']->templates = array(
            '${field}',
            '${input}',
        );
        foreach($fields AS $field => &$input) {
            $arguments['page']->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        printf('<form method="post" action="?node=service&sub=edit&tab=%s">','snapinclient');
        $arguments['page']->render();
        echo '</form>';
    }
    public function UpdateGlobalSetting($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'service') return;
        $Service = self::getClass('Service')->set('name','FOG_SNAPIN_LOCATION_SEND_ENABLED')->load('name');
        if (!$Service->isValid()) return;
        $Service->set('value',intval(isset($_REQUEST['snapinsend'])))->save();
        return true;
    }
    public function AddServiceNames($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'about') return;
        if ($_REQUEST['sub'] != 'settings') return;
        $arguments['ServiceNames'][] = 'FOG_SNAPIN_LOCATION_SEND_ENABLED';
    }
}
$AddServiceConfiguration = new AddServiceConfiguration();
$HookManager->register('SNAPIN_CLIENT_SERVICE',array($AddServiceConfiguration,'AddServiceCheckbox'));
$HookManager->register('SNAPIN_CLIENT_SERVICE_POST',array($AddServiceConfiguration,'UpdateGlobalSetting'));
$HookManager->register('SERVICE_NAMES',array($AddServiceConfiguration,'AddServiceNames'));
