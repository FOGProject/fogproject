<?php
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
die($FOGCore->formatTime('now', 'D M d, Y G:i a'));
