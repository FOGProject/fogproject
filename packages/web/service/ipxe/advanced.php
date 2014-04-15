<?php
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
print "#!ipxe\n";
print $FOGCore->getSetting('FOG_PXE_ADVANCED');
?>
