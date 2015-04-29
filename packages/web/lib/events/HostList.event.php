<?php
class HostList extends Event {
	/** @var $name the name of the event */
	public $name = 'HostListEvent';
	/** @var $description the description of what the event does */
	public $description = 'Triggers when the hosts are listed';
	/** @var $author the author of the event */
	public $author = 'Jbob';
	/** @var $active whether or not the event is to be running */
	public $active = false;
}
$EventManager->register('HOST_LIST_EVENT', new HostList());
