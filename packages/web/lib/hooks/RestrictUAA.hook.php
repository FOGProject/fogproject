<?php
class RestrictUAA extends Hook
{
    var $name = 'RestrictUAA';
    var $description = 'Removes All users except the current user';
    var $author = 'Rowlett';
    var $active = false;
 
    function UserData($arguments)
    {
        $currentUser = (!empty($_SESSION['FOG_USER']) ? unserialize($_SESSION['FOG_USER']) : null);
        foreach ($arguments['data'] AS $i => $data)
        if($arguments['data'][$i]['name'] != $currentUser)
        unset($arguments['data'][$i]);   
    }
}
$RestrictUAA = new RestrictUAA();
// Register hooks
$HookManager->register('USER_DATA', array($RestrictUAA, 'UserData'));
