<?php
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
$url = 'https://fogproject.org/version/index.php';
$data = array(
    'version' => FOG_VERSION,
);
$res = $FOGURLRequests->process($url, 'POST', $data);
die(json_encode(array_shift($res)));
