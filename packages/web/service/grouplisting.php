<?php
require_once('../commons/base.inc.php');
try {
    $Groups = $FOGCore->getClass(GroupManager)->find();
    if (!$Groups) throw new Exception(_('There are no groups on this server.'));
    foreach ($Groups AS &$Group) printf('\tID# %d\t-\t%s\n',$Group->get(id),$Group->get(name));
    unset($Group);
} catch (Exception $e) {
    echo $e->getMessage();
}
