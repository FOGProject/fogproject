<?php
ob_start();
header('Content-Type: text/event-stream');
echo json_encode(glob(sprintf('%s%s*',preg_replace('#[\\/]#',DIRECTORY_SEPARATOR,urldecode($_REQUEST['path'])),DIRECTORY_SEPARATOR)));
header('Connection: close');
flush();
ob_flush();
ob_end_flush();
exit;
