<?php
class NodeJS extends FOGController {
	// Table
	public $databaseTable = 'nodeJSconfig';
	// Name -> Database field name
	public $databaseFields = array(
			'id' => 'nodeID',
			'name' => 'nodeName',
			'ip' => 'nodeIP',
			'port' => 'nodePort',
			'aeskey' => 'nodeAES',
			);
}
