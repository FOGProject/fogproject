<?php

require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

$conn = mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
if ( $conn )
{
	if ( ! mysql_select_db( MYSQL_DATABASE, $conn ) ) die( "Unable to select database" );
}
else
{
	die( "Unable to connect to Database" );
}

$mac = $_GET["mac"];

if ( ! isValidMACAddress( $mac ) )
{
	die( "Invalid MAC address format!" );
}

if ( $mac != null  )
{
	
	$hostid = getHostID( $conn, $mac );	
	$jobid = getTaskIDByMac( $conn, $mac);

	$ftp = ftp_connect(TFTP_HOST); 
	$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 				
	if ((!$ftp) || (!$ftp_loginres )) 
	{
  		echo "FTP connection has failed!";
 		exit;
 	}			

 	$mac = str_replace( ":", "-", $mac );
	if ( ftp_delete ( $ftp, TFTP_PXE_CONFIG_DIR . "01-". $mac ) )
	{
		if ( $jobid !== null )
		{			
			if ( checkOut( $conn, $jobid ) )
			{			
				echo "##";
			}
			else
			{
				echo "Error: Checkout Failed.";
			}	
		}
		else
		{
			echo "Unable to locate job in database, please ensure that mac address is correct.";
		}							
	}
	else
	{
		echo "Error: Unable to remove TFTP file";
	}
	ftp_close($ftp); 
}
else
	echo "Invalid MAC or FTP Address";
?>
