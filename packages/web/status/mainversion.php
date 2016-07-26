<?php
require_once '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
$URL = sprintf('https://fogproject.org/version/index.php?version=%s',FOG_VERSION);
$res = $FOGURLRequests->process($URL);
echo json_encode(array_shift($res));
exit;
