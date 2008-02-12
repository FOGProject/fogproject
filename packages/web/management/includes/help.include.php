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
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
			echo ( "<div id=\"pageContent\" class=\"scroll\">" );
			echo ( "<p class=\"title\">FOG Help Resources</p>" );		
				echo ( "<a href=\"http://freeghost.no-ip.org/wiki/index.php/FOGUserGuide\" target=\"_blank\">FOG Wiki Documentation</a>" );
			echo ( "</div>" );
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
