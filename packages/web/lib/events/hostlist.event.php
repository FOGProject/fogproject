<?php
class HostList extends Event {
	public $name = 'HostListEvent';
	public $description = 'Triggers when the hosts are listed';
	public $author = 'Jbob';
	public $active = false;
}
$EventManager->register('HOST_LIST_EVENT', new HostList());
