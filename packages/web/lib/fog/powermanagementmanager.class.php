<?php
class PowerManagementManager extends FOGManagerController {
    public function getActionSelect($selected = '',$array = false) {
        $types = array(
            'shutdown'=>_('Shutdown'),
            'reboot'=>_('Reboot'),
            'wol'=>_('Wake On Lan'),
        );
        self::$HookManager->processEvent('PM_ACTION_TYPES',array('types'=>&$types));
        ob_start();
        array_walk($types,function(&$text,&$val) use ($selected,$template) {
            printf('<option value="%s"%s>%s</option>',trim($val),($template !== false && trim($template) === trim($val) ? ' selected' : (trim($selected) === trim($val) ? ' selected' : '')),$text);
        });
        return sprintf('<select name="action%s">%s%s</select>',$array !== false ? '[]' : '',$array === false ? sprintf('<option value="">- %s -</option>',self::$foglang['PleaseSelect']) : '',ob_get_clean());
    }
}
