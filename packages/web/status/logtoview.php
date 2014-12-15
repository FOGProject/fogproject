<?php
$vals = shell_exec("tail -n {$_REQUEST['lines']} {$_REQUEST['file']}");
print json_encode($vals);
