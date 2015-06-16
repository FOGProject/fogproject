<?php
class ChangeTableHeader extends Hook {
	/** @var $name the name of the hook */
		public $name = 'ChangeTableHeader';
		/** @var $description the description of what the hook does */
		public $description = 'Remove & add table header columns';
		/** @var $author the author of the hook */
		public $author = 'Blackout';
		/** @var $active whether or not the hook is to be running */
		public $active = false;
		/** @function HosttableHeader the header to change
		 * @param $arguments the Hook Events to enact upon
		 * @return void
		 */
		public function HostTableHeader($arguments) {$arguments['headerData'][3] = 'Chicken Sandwiches';}
}
// Example: Change Table Header and Data
$HookManager->register('HOST_HEADER_DATA', array(new ChangeTableHeader(), 'HostTableHeader'));
