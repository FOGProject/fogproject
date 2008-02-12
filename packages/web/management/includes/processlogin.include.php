<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $_POST["uname"] != null && $_POST["upass"] != null && $conn != null  )
{
	$username = mysql_real_escape_string(trim($_POST["uname"]));
	$password = mysql_real_escape_string(trim($_POST["upass"]));
	
	if (  ereg("^[[:alnum:]]*$", $password ) && ereg("^[[:alnum:]]*$", $username ) )
	{
		 $sql = "select * from users where uName = '$username' and uPass = '" . md5( $password ) . "'";
		 $res = mysql_query( $sql, $conn ) or die( mysql_error() );
		 if ( mysql_num_rows( $res ) == 1 )
		 {
		 	while( $ar = mysql_fetch_array( $res ) )
			{
				$currentUser = new User( $ar["uName"], $_SERVER["REMOTE_ADDR"], time() );
			}
		 }
		 else
		 {
		 	msgBox ( "Invalid Login." );
		 }
	}
	else
	{
		msgBox ( "Either the username or password contains invalid characters" );
	}
}
?>
