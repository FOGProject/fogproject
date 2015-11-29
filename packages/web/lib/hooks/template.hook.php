<?php
class Template extends Hook {
    public $name = 'Hook Name';
    public $description = 'Hook Description';
    public $author = 'Hook Author';
    public $active = false;
    public function HostData($arguments) {
        $this->log(print_r($arguments, 1));
    }
}
$HookManager->register('HOST_DATA',array(new Template(),'HostData'));
