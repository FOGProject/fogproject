<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	$_SESSION["allow_ajax_task"] = true;
	echo ( "<div class=\"scroll\">" );
		echo ( "<p class=\"title\">Task Search</p>" );
		echo ( "<input type=\"text\" value=\"Search\" onFocus=\"this.value=''\" onkeyup=\"getContentTask( this.value );\" />" );
		echo ( "<div class=\"searchResultsTasks\" id=\"taskSearchContent\"></div>" );
	echo ( "</div>" );
}
?>
