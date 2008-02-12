<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	echo ( "<center>" );
	echo ( "<table width=\"98%\" cellpadding=0 cellspacing=0 border=0>" );
	echo ( "<tr><td width=\"100\" valign=\"top\" >" );
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
