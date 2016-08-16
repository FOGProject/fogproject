<?php
require('../commons/base.inc.php');
$clientUpdate = (bool)$FOGCore->getSetting('FOG_CLIENT_AUTOUPDATE');
if (isset($_REQUEST['client'])) {
    $ver = $clientUpdate ? '9.9.99' : '0.0.0';
} elseif (isset($_REQUEST['clientver'])) {
    $ver = $clientUpdate ? FOG_CLIENT_VERSION : '0.0.0';
} elseif (isset($_REQUEST['url'])) {
    $res = $FOGURLRequests->process($_REQUEST['url']);
    $ver = array_shift($res);
} else {
    $ver = FOG_VERSION;
}
die($ver);
