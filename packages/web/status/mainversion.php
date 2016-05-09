<?php
require_once('../commons/base.inc.php');
$URL = sprintf('https://fogproject.org/version/index.php?version=%s',FOG_VERSION);
$res = $FOGURLRequests->process($URL);
echo json_encode(array_shift($res));
exit;
