<?php
$decodedPath = urldecode($_REQUEST['path']);
$replaced_dir_sep = preg_replace('#[\\/]#', DIRECTORY_SEPARATOR, $decodedPath);
$glob_str = sprintf('%s%s*', $replaced_dir_sep, DIRECTORY_SEPARATOR);
$files = glob($glob_str);
die(json_encode($files));
