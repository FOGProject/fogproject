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
			echo ( "<a href=\"?node=$_GET[node]&sub=ver\" class=\"plainfont\">Version Info</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=lic\" class=\"plainfont\">License</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=kernel\" class=\"plainfont\">Kernel Updates</a>" );
		echo ( "</div>" );				
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=clientup\" class=\"plainfont\">Client Updater</a>" );
		echo ( "</div>" );			
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"http://www.sf.net/projects/freeghost\" class=\"plainfont\" target=\"_blank\">Sourceforge Page</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"http://freeghost.sf.net/\" class=\"plainfont\" target=\"_blank\">Home Page</a>" );
		echo ( "</div>" );			
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "ver" )
		{
			require_once( "./includes/about.version.include.php" );
		}
		else if ( $_GET[sub] == "lic" )
		{
			require_once( "./includes/about.lic.include.php" );
		}
		else if ( $_GET[sub] == "kernel" )
		{
			require_once( "./includes/about.kernel.include.php" );
		}	
		else if ( $_GET[sub] == "virus" )
		{
			require_once( "./includes/about.virus.include.php" );
		}
		else if ( $_GET[sub] == "clientup" )
		{
			require_once( "./includes/about.clientupdater.include.php" );
		}									
		else
		{
			require_once( "./includes/about.version.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
