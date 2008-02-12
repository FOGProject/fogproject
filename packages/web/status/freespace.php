<?php
require_once( "../commons/config.php" );


$free = ( disk_free_space( STORAGE_DATADIR ) );
$freegb = round( ( ( ($free / 1024) / 1024) /1024), 2);

$used = ( disk_total_space( STORAGE_DATADIR ) - disk_free_space( STORAGE_DATADIR ) );
$usedgb = round( ( ( ($used / 1024) / 1024) /1024), 2);

echo ( $freegb . "@" . $usedgb ); 
?>
