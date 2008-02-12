<?php
require_once( "../commons/config.php" );

$bytes = ( disk_total_space( STORAGE_DATADIR ) - disk_free_space( STORAGE_DATADIR ) );
$gb = round( ( ( ($bytes / 1024) / 1024) /1024), 2);
echo ( $gb . " GB"); 
?>
