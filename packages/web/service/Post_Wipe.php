<?php
require_once('../commons/base.inc.php');
try {
    // Get the Host
    $Host = $FOGCore->getHostItem(false,false,true);
    if ($Host && $Host->isValid()) {
        // Task for Host
        $Task = $Host->get(task);
        if ($Task && $Task->isValid()) {
            if (!in_array($Task->get(typeID),array(12,13))) $Task->set(stateID,4);
            if ($Task->save()) {
                // Task Logging.
                $TaskLog = $FOGCore->getClass(TaskLog,$Task)
                    ->set(taskID,$Task->get(id))
                    ->set(taskStateID,$Task->get(stateID))
                    ->set(createdTime,$Task->get(createdTime))
                    ->set(createdBy,$Task->get(createdBy))
                    ->save();
            }
        }
    }
    echo '##';
} catch (Exception $e) {
    echo $e->getMessage();
}
