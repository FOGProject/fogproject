<?php
require '../commons/base.inc.php';
die(FOGCore::getClass('StorageNodeManager')->exists($_POST['ip'], 0, 'ip') ? 'exists' : '');
