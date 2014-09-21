<?php
// microleaks - Tue 14 Feb 2012 09:38:18 AM CST 
class NodeFailure extends FOGController
{
	// Table
	public $databaseTable = 'nfsFailures';
	protected $loadQueryTemplateSingle = "SELECT * FROM `%s` WHERE `%s`='%s' AND TIMESTAMP(nfDateTime) BETWEEN TIMESTAMP(DATE_ADD(NOW(), INTERVAL -5 MINUTE)) and TIMESTAMP(NOW())";
	protected $loadQueryTemplateMultiple = "SELECT * FROM `%s` WHERE (%s) AND TIMESTAMP(nfDateTime) BETWEEN TIMESTAMP(DATE_ADD(NOW(), INTERVAL -5 MINUTE)) and TIMESTAMP(NOW())";
	// Name -> Database field name
	public $databaseFields = array(
		'id'			=> 'nfID',
		'storageNodeID'		=> 'nfNodeID',
		'taskID'		=> 'nfTaskID',
		'hostID'		=> 'nfHostID',
		'groupID'		=> 'nfGroupID',
		'failureTime'		=> 'nfDateTime'
	);
	// Required database fields
	public $databaseFieldsRequired = array(
		'id',
		'storageNodeID',
		'taskID',
		'hostID',
		'groupID', 
		'failureTime'
	);
}
