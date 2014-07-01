<?php
// Require FOG Base
require('../commons/base.inc.php');
$storagedir = $FOGCore->getSetting('FOG_NFS_DATADIR');
$bytes = ( @disk_total_space( $storagedir ) - @disk_free_space( $storagedir ) );
$gb = round( ( ( ($bytes / 1024) / 1024) /1024), 2);
echo ( $gb . " GB");
