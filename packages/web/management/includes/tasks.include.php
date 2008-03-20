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
			echo ( "<a href=\"?node=$_GET[node]&sub=listgroups\" class=\"plainfont\">List All Groups</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=listhosts\" class=\"plainfont\">List All Hosts</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=active\" class=\"plainfont\">Active Tasks</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=activemc\" class=\"plainfont\">Active Multicast Tasks</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=activesnapins\" class=\"plainfont\">Active Snapins</a>" );
		echo ( "</div>" );		
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET["confirm"] != null || $_GET["noconfirm"] != null )
		{
			require_once( "./includes/tasks.confirm.include.php" );
		}
		else if ( $_GET["sub"] == "search" )
		{
			require_once( "./includes/tasks.search.include.php" );
		}	
		else if ( $_GET["sub"] == "listgroups" )
		{
			require_once( "./includes/tasks.listgroups.include.php" );
		}	
		else if ( $_GET["sub"] == "listhosts" )
		{
			require_once( "./includes/tasks.listhosts.include.php" );
		}
		else if ( $_GET["sub"] == "active" )
		{
			require_once( "./includes/tasks.active.include.php" );
		}						
		else if ( $_GET["sub"] == "activemc" )
		{
			require_once( "./includes/tasks.activemc.include.php" );
		}		
		else if ( $_GET["sub"] == "advanced" )
		{
			require_once( "./includes/tasks.advanced.include.php" );
		}
		else if ( $_GET["sub"] == "activesnapins" )
		{
			require_once( "./includes/tasks.activesnapins.include.php" );
		}					
		else
		{
			require_once( "./includes/tasks.search.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
