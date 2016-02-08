<?php
class RemoveSlackItem extends Hook {
    public $name = 'RemoveSlackMenuItem';
    public $description = 'Remove slack item';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'slack';
    public function remove_multi($arguments) {
        foreach ((array)$this->getClass('SlackManager')->find(array('id'=>$arguments['removing'])) AS &$Token) {
            if (!$Token->isValid()) continue;
            $args = array(
                'channel' => $Token->get('name'),
                'text' => sprintf('%s %s: %s',preg_replace('/^[@]|^[#]/','',$Token->get('name')),_('Account removed from FOG GUI at'),$this->getSetting('FOG_WEB_HOST')),
            );
            $Token->call('chat.postMessage',$args);
            unset($Token);
        }
    }
    public function remove_single($arguments) {
        if (!$arguments['Slack']->isValid()) return;
        $args = array(
            'channel' => $arguments['Slack']->get('name'),
            'text' => sprintf('%s %s: %s',preg_replace('/^[@]|^[#]/','',$arguments['Slack']->get('name')),_('Account removed from FOG GUI at'),$this->getSetting('FOG_WEB_HOST')),
        );
        $arguments['Slack']->call('chat.postMessage',$args);
    }
}
$RemoveSlackItem = new RemoveSlackItem();
$HookManager->register('SLACK_DEL_POST',array($RemoveSlackItem,'remove_single'));
$HookManager->register('MULTI_REMOVE',array($RemoveSlackItem,'remove_multi'));
