<?php
echo json_encode(glob(sprintf('%s%s*',urldecode($_REQUEST['path']),DIRECTORY_SEPARATOR)));
