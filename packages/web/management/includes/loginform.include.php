<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

echo ( "<center><div class=\"login\">\n" );
	echo "<p class=\"loginTitle\">FOG Management Login</p>\n";
	echo ( "<form method=\"post\" action=\"?node=login\">\n" );
		echo ( "<div class=\"loginElement\">Username:</div><div class=\"loginElement\"><input type=\"text\" class=\"login\" name=\"uname\" /></div>" );
		echo ( "<br />" );
		echo ( "<div class=\"loginElement\">Password: </div><div class=\"loginElement\"><input type=\"password\" class=\"login\" name=\"upass\" /></div>" );
		echo ( "<p><input type=\"submit\" value=\"Login\" /></p>" );
	echo ( "</form>" );
echo ( "</div>" );

echo ( "<div class=\"fogInfo\">" );
	echo " Estimated FOG users: <b>";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_TIMEOUT, '2');
	curl_setopt($ch, CURLOPT_URL, "http://freeghost.no-ip.org/globalusers/" );
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$ret = curl_exec($ch);
	echo ( "</b><br /> ");

	echo ( "Latest Version: " );
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_TIMEOUT, '2');
	curl_setopt($ch, CURLOPT_URL, "http://freeghost.sourceforge.net/version/version.php" );
	curl_setopt($ch, CURLOPT_HEADER, 0);
	$ret = curl_exec($ch);	

echo( "</div></center>\n" );
?>
