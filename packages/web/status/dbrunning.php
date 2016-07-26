<?php
require_once '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
echo json_encode(array(
    'running'=>(bool)$DB->link(),
    'redirect'=>(bool)$DB->link() && FOGCore::getClass('Schema',1)->get('version') == FOG_SCHEMA,
));
