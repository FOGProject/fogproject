<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{

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
			echo ( "<a href=\"?node=$_GET[node]&sub=list\" class=\"plainfont\">List All Snapins</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=add\" class=\"plainfont\">New Snapin</a>" );
		echo ( "</div>" );
		
		if ( $_GET["snapinid"] !== null )
		{
			if ( is_numeric( $_GET["snapinid"] ) )
			{
				$m_snapinid = mysql_real_escape_string($_GET["snapinid"]);
				echo ( "<p class=\"hostTitle\">" );
						echo ( "Snapin Menu" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&snapinid=$m_snapinid&tab=gen\" class=\"plainfont\">General</a>" );
				echo ( "</div>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&snapinid=$m_snapinid&tab=delete\" class=\"plainfont\">Delete</a>" );
				echo ( "</div>" );
				
				echo ( "<p class=\"miscTitle\">" );
						echo ( "Quick Info" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"infoItem\">" );
					$hid = mysql_real_escape_string( $_GET["id"] );
					$sql = "select * from snapins where sID = '$m_snapinid'";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					if ( $ar = mysql_fetch_array( $res ) )
					{
						echo "<p class=\"hostInfoTitleFirst\">Host:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . trimString( stripslashes($ar["sName"]), 20 ) . "</p>" );
					}
				echo ( "</div>" );																
			}	
		}			
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "add" )
		{
			require_once( "./includes/snapin.add.include.php" );
		}
		else if ( $_GET[sub] == "list" )
		{
			require_once( "./includes/snapin.list.include.php" );
		}		
		else if ( $_GET[sub] == "edit" )
		{
			require_once( "./includes/snapin.edit.include.php" );
		}
		else if ( $_GET[sub] == "search" )
		{
			require_once( "./includes/snapin.search.include.php" );
		}						
		else
		{
			require_once( "./includes/snapin.search.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
