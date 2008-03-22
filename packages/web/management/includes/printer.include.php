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
			echo ( "<a href=\"?node=home\" class=\"plainfont\">&nbsp;Home</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=search\" class=\"plainfont\">New Search</a>" );
		echo ( "</div>" );				
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=list\" class=\"plainfont\">List All Printers</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=add\" class=\"plainfont\">Add New Printer</a>" );
		echo ( "</div>" );	
		
		if ( $_GET["id"] !== null )
		{
			if ( is_numeric( $_GET["id"] ) )
			{
				echo ( "<p class=\"hostTitle\">" );
						echo ( "Printer Menu" );		
				echo ( "</p>" );	
				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=$_GET[sub]&id=$_GET[id]\" class=\"plainfont\">General</a>" );
				echo ( "</div>" );	
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=delete&id=$_GET[id]\" class=\"plainfont\">Delete</a>" );
				echo ( "</div>" );											
				
				echo ( "<p class=\"miscTitle\">" );
						echo ( "Quick Info" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"infoItem\">" );
					$hid = mysql_real_escape_string( $_GET["id"] );
					$sql = "select * from printers where pID = '$hid'";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					if ( $ar = mysql_fetch_array( $res ) )
					{
						echo "<p class=\"hostInfoTitleFirst\">Model:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . stripslashes($ar["pModel"]) . "</p>" );
						echo "<p class=\"hostInfoTitle\">Alias:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . stripslashes($ar["pAlias"]) . "</p>" );						
					}
				echo ( "</div>" );								
			}			
		}	
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "add" )
		{
			require_once( "./includes/printer.add.include.php" );
		}
		else if ( $_GET[sub] == "list" )
		{
			require_once( "./includes/printer.list.include.php" );
		}	
		else if ( $_GET[sub] == "edit" )
		{
			require_once( "./includes/printer.edit.include.php" );
		}
		else if ( $_GET[sub] == "delete" )
		{
			require_once( "./includes/printer.delete.include.php" );
		}	
		else if ( $_GET[sub] == "search" )
		{
			require_once( "./includes/printer.search.include.php" );
		}												
		else
		{
			require_once( "./includes/printer.search.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
