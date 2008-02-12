<?php
require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

$mac = $_GET["wakeonlan"];
if ( isValidMACAddress( $mac ) )
{
	$output;
	$ret = "";
	exec ( "sudo /sbin/ether-wake -i " . WOL_INTERFACE . " " . $mac, $output, $ret );
}
?>
