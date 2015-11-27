<?php
class ChangeTableHeader extends Hook {
	public $name = 'ChangeTableHeader';
	public $description = 'Remove & add table header columns';
	public $author = 'Blackout';
	public $active = false;
	/** @function HosttableHeader the header to change
	  * @param $arguments the Hook Events to enact upon
	  * @return void
	  */
    public function HostTableHeader($arguments) {
        $arguments['headerData'][3] = 'Chicken Sandwiches';
    }
}
// Example: Change Table Header and Data
$HookManager->register('HOST_HEADER_DATA', array(new ChangeTableHeader(), 'HostTableHeader'));
