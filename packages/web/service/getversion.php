<?php
require_once('../commons/base.inc.php');
if (isset($_REQUEST['client'])) echo FOG_CLIENT_VERSION;
else if (isset($_REQUEST['clientver'])) echo '9.9.99';
else echo FOG_VERSION;
exit;
