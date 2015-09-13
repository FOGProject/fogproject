<?php
require_once('../commons/base.inc.php');
if (isset($_REQUEST[client])) echo FOG_CLIENT_VERSION;
else if (isset($_REQUEST[clientver])) exit;
else echo FOG_VERSION;
exit;
