<?php
require_once('../commons/base.inc.php');
try {
    // Just for checking if the host exists
    // Sends back if it does or doesn't.
    $hostname = trim(base64_decode(trim($_REQUEST[host])));
    foreach ($FOGCore->getClass(HostManager)->find(array('name' => $hostname)) AS &$Host) {
        if ($Host->isValid()) throw new Exception("\tA hostname with that name already exists.\nThe MAC Address associated with this is:".$Host->get(mac));
    }
    unset($Host);
    throw new Exception('#!ok');
} catch (Exception $e) {
    print $e->getMessage();
}
