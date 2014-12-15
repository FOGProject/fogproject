<?php
$vals = shell_exec("cat {$_REQUEST['file']} | grep -v logtoview.php | tail -n {$_REQUEST['lines']}");
print json_encode($vals);
