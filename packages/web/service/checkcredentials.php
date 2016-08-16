<?php
require('../commons/base.inc.php');
try {
    $username = trim(base64_decode(trim($_REQUEST['username'])));
    $password = trim(base64_decode(trim($_REQUEST['password'])));
    if (!FOGCore::getClass('User')->password_validate($username, $password)) {
        throw new Exception('#!il');
    }
    echo '#!ok';
} catch (Exception $e) {
    echo $e->getMessage();
}
