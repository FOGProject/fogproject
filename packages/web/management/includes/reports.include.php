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
		
		$dh = opendir( FOG_REPORT_DIR );
		if ( $dh != null )
		{
			while ( ! (($f = readdir( $dh )) === FALSE) )
			{
				if ( is_file( FOG_REPORT_DIR . $f ) )
				{	
					if ( endswith( $f, ".php" ) )
					{
						echo ( "<div class=\"subMenu\">" );
							echo ( "<a href=\"?node=$_GET[node]&sub=file&f=" . base64_encode($f) . "\" class=\"plainfont\">" . substr( $f, 0, strlen( $f ) -4 ) . "</a>" );
						echo ( "</div>" );						
					}								
				}
			}
		}
		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=upload\" class=\"plainfont\">Upload a Report</a>" );
		echo ( "</div>" );
					
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET["sub"] == "file" )
		{			
			if ( $_GET["f"] != null )
			{
				$file = base64_decode($_GET["f"]);
				if ( endswith( $file, ".php" ) )
				{
					require_once( FOG_REPORT_DIR . $file );				
				}
			}
		}
		else if ( $_GET[sub] == "upload" )
		{
			require_once( "./includes/reports.upload.include.php" );
		}					
		else
		{
			require_once( "./includes/reports.about.include.php" );	
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
