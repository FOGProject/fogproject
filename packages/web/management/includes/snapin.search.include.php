<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	$_SESSION["allow_ajax_snapin"] = true;
	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Snapin Search</p>" );
	echo ( "<center><input type=\"text\" value=\"Search\" onFocus=\"this.value=''\" onkeyup=\"getContentSnapin( this.value );\" /></center>" );
	echo ( "<div class=\"searchResults\" id=\"snapinSearchContent\"></div>" );
	echo ( "</div>" );

}
?>
