<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	$_SESSION["allow_ajax_host"] = true;
	echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">Host Search</p>" );
	echo ( "<center><input type=\"text\" value=\"Search\" onFocus=\"this.value=''\" onkeyup=\"getContentHost( this.value );\" /></center>" );
	echo ( "<div class=\"searchResults\" id=\"hostSearchContent\"></div>" );
	echo ( "</div>" );

}
?>
