<?php
require_once('../commons/base.inc.php');
if (isset($_REQUEST['client'])) echo '9.9.99';
else if (isset($_REQUEST['clientver'])) echo FOG_CLIENT_VERSION;
else echo FOG_VERSION;
exit;
