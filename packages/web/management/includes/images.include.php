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
			echo ( "<a href=\"?node=$_GET[node]&sub=search\" class=\"plainfont\">New Search</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=list\" class=\"plainfont\">List All Images</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=add\" class=\"plainfont\">New Image</a>" );
		echo ( "</div>" );
		
		if ( $_GET["imageid"] !== null )
		{
			if ( is_numeric( $_GET["imageid"] ) )
			{
				$m_imageid = mysql_real_escape_string( $_GET["imageid"] );
				
				echo ( "<p class=\"hostTitle\">" );
						echo ( "Image Menu" );		
				echo ( "</p>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&imageid=$_GET[imageid]&tab=gen\" class=\"plainfont\">General</a>" );
				echo ( "</div>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&imageid=$_GET[imageid]&tab=delete\" class=\"plainfont\">Delete</a>" );
				echo ( "</div>" );								
				
				
				echo ( "<p class=\"miscTitle\">" );
						echo ( "Quick Info" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"infoItem\">" );
					$hid = mysql_real_escape_string( $_GET["id"] );
					$sql = "select * from images where imageID = '$m_imageid'";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					if ( $ar = mysql_fetch_array( $res ) )
					{
						echo "<p class=\"hostInfoTitleFirst\">Image Name:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . trimString( stripslashes($ar["imageName"]), 20 ) . "</p>" );
					}
				echo ( "</div>" );								
			}	
		}		
			
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "add" )
		{
			require_once( "./includes/images.add.include.php" );
		}
		else if ( $_GET[sub] == "list" )
		{
			require_once( "./includes/images.list.include.php" );
		}		
		else if ( $_GET[sub] == "edit" )
		{
			require_once( "./includes/images.edit.include.php" );
		}				
		else if ( $_GET[sub] == "search" )
		{
			require_once( "./includes/images.search.include.php" );
		}		
		else
		{
			require_once( "./includes/images.search.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
