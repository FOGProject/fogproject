<?php
class NodeFailure extends FOGController {
    protected $loadQueryTemplateSingle = "SELECT * FROM `%s` WHERE `%s`='%s' AND TIMESTAMP(nfDateTime) BETWEEN TIMESTAMP(DATE_ADD(NOW(), INTERVAL -5 MINUTE)) and TIMESTAMP(NOW())";
    protected $loadQueryTemplateMultiple = "SELECT * FROM `%s` WHERE (%s) AND TIMESTAMP(nfDateTime) BETWEEN TIMESTAMP(DATE_ADD(NOW(), INTERVAL -5 MINUTE)) and TIMESTAMP(NOW())";
    protected $databaseTable = 'nfsFailures';
    protected $databaseFields = array(
        'id' => 'nfID',
        'storageNodeID' => 'nfNodeID',
        'taskID' => 'nfTaskID',
        'hostID' => 'nfHostID',
        'groupID' => 'nfGroupID',
        'failureTime' => 'nfDateTime'
    );
    protected $databaseFieldsRequired = array(
        'storageNodeID',
        'taskID',
        'hostID',
        'groupID',
        'failureTime'
    );
}
