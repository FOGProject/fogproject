<?php
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
require_once '../commons/base.inc.php';
return print $FOGCore->formatTime('now','D M d, Y G:i a');
