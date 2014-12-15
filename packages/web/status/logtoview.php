<?php
$vals = shell_exec("cat {$_REQUEST['file']} | grep -v logtoview.php | tail -".($_REQUEST['lines'] ? $_REQUEST['lines'] : '20'));
print json_encode($vals);
