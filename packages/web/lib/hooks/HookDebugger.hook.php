<?php
class HookDebugger extends Hook {
	public $name = 'HookDebugger';
	public $description = 'Prints all Hook data to the web page and/or file when a hook is encountered';
	public $author = 'Blackout';
	public $active = false;
	public $logLevel = 9;
	public $logToFile = false;		
	public $logToBrowser = true;
	public function run($arguments) {$this->log(print_r($arguments['event'],1));}
}
$HookDebugger = new HookDebugger();
if (!$HookManager->events)
	$HookManager->getEvents();
foreach($HookManager->events AS $event)
	$HookManager->register($event,array($HookDebugger,'run'));
