<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	echo ( "<center>" );
	echo ( "<table width=\"98%\" cellpadding=0 cellspacing=0 border=0>" );
	echo ( "<tr><td width=\"100\" valign=\"top\" >" );
		echo ( "<p class=\"mainTitle\">" );
			echo ( "Main Menu" );		
		echo ( "</p>" );	
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=home\" class=\"plainfont\">Home</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=list\" class=\"plainfont\">List All Users</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=add\" class=\"plainfont\">New User</a>" );
		echo ( "</div>" );
		
		if ( $_GET["userid"] !== null )
		{
			if ( is_numeric( $_GET["userid"] ) )
			{	
				$m_userid = mysql_real_escape_string( $_GET["userid"] );
				echo ( "<p class=\"hostTitle\">" );
						echo ( "User Menu" );		
				echo ( "</p>" );	
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&userid=$_GET[userid]&tab=gen\" class=\"plainfont\">General</a>" );
				echo ( "</div>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&userid=$_GET[userid]&tab=delete\" class=\"plainfont\">Delete</a>" );
				echo ( "</div>" );
				
				echo ( "<p class=\"miscTitle\">" );
						echo ( "Quick Info" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"infoItem\">" );
					
					$sql = "select * from users where uID = '$m_userid'";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					if ( $ar = mysql_fetch_array( $res ) )
					{
						echo "<p class=\"hostInfoTitleFirst\">Username:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . trimString( stripslashes($ar["uName"]), 20 ) . "</p>" );
					}
				echo ( "</div>" );															
			}
		}
		
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "add" )
		{
			require_once( "./includes/users.add.include.php" );
		}
		else if ( $_GET[sub] == "list" )
		{
			require_once( "./includes/users.list.include.php" );
		}		
		else if ( $_GET[sub] == "edit" )
		{
			require_once( "./includes/users.edit.include.php" );
		}				
		else
		{
			require_once( "./includes/users.list.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
