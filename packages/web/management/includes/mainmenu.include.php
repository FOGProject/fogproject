<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );
if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	echo ( "<div class=\"menuBar\">" );
		echo ( "<a href=\"?node=\"><img class=\"link\" src=\"images/menubar/gray/home.png\" onMouseover=\"colorImage(this, 'home.png');\" onMouseout=\"grayImage(this, 'home.png');\" /></a>" );
		echo ( "<a href=\"?node=users\"><img class=\"link\" src=\"images/menubar/gray/user.png\" onMouseover=\"colorImage(this, 'user.png');\" onMouseout=\"grayImage(this, 'user.png');\" /></a>" );
		echo ( "<a href=\"?node=host\"><img class=\"link\" src=\"images/menubar/gray/host.png\" onMouseover=\"colorImage(this, 'host.png');\" onMouseout=\"grayImage(this, 'host.png');\" /></a>" );
		echo ( "<a href=\"?node=group\"><img class=\"link\" src=\"images/menubar/gray/group.png\" onMouseover=\"colorImage(this, 'group.png');\" onMouseout=\"grayImage(this, 'group.png');\" /></a>" );		
		echo ( "<a href=\"?node=images\"><img class=\"link\" src=\"images/menubar/gray/image.png\" onMouseover=\"colorImage(this, 'image.png');\" onMouseout=\"grayImage(this, 'image.png');\" /></a>" );				
		echo ( "<a href=\"?node=snap\"><img class=\"link\" src=\"images/menubar/gray/snap.png\" onMouseover=\"colorImage(this, 'snap.png');\" onMouseout=\"grayImage(this, 'snap.png');\" /></a>" );				
		echo ( "<a href=\"?node=tasks\"><img class=\"link\" src=\"images/menubar/gray/star.png\" onMouseover=\"colorImage(this, 'star.png');\" onMouseout=\"grayImage(this, 'star.png');\" /></a>" );						
		echo ( "<a href=\"?node=about\"><img class=\"link\" src=\"images/menubar/gray/info.png\" onMouseover=\"colorImage(this, 'info.png');\" onMouseout=\"grayImage(this, 'info.png');\" /></a>" );								
		echo ( "<a href=\"?node=help\"><img class=\"link\" src=\"images/menubar/gray/help.png\" onMouseover=\"colorImage(this, 'help.png');\" onMouseout=\"grayImage(this, 'help.png');\" /></a>" );										
		echo ( "<a href=\"?node=logout\"><img class=\"link\" src=\"images/menubar/gray/logout.png\" onMouseover=\"colorImage(this, 'logout.png');\" onMouseout=\"grayImage(this, 'logout.png');\" /></a>" );												
	echo ( "</div>" );
}
?>
