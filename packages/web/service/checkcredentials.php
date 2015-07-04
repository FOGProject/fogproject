<?php
require_once('../commons/base.inc.php');
try {
    $username = base64_decode(trim($_REQUEST[username]));
    $password = base64_decode(trim($_REQUEST[password]));
    if (!$FOGCore->attemptLogin($username, $password)) throw new Exception('#!il');
    print "#!ok";
} catch (Exception $e) {
    print $e->getMessage();
}
