<?php
session_cache_limiter("no-cache");
session_start();

require_once( "../../commons/config.php" );
require_once( "../../commons/functions.include.php" );


$ch = curl_init();
curl_setopt($ch, CURLOPT_TIMEOUT, '10');
curl_setopt($ch, CURLOPT_URL, "http://" . STORAGE_HOST . STORAGE_BANDWIDTHPATH  );
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
$ret = curl_exec($ch);
$ar = explode( "##", $ret );
if ( true || count( $ar ) == 2 && $ar[0] >= 0 && $ar[1] >= 0 )
{
	while( count( $_SESSION["rx"] ) > 29 )
	{
		array_shift($_SESSION["tx"]);
		array_shift($_SESSION["rx"]);
	}

	$_SESSION["rx"][] = $ar[0];
	$_SESSION["tx"][] = $ar[1];
}
curl_close($ch);	



?>
