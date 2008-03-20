<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $_POST["add"] != null )
{
		if ( ! userExists( $conn, $_POST["name"] ) )
		{
			$name = mysql_real_escape_string( $_POST["name"] );
			$password1 = mysql_real_escape_string( $_POST["p1"] );
			$password2 = mysql_real_escape_string( $_POST["p2"] );
			$user = mysql_real_escape_string( $currentUser->getUserName() );
			
			if ( isValidPassword( $password1, $password2 ) )
			{
				$sql = "insert into users( uName, uPass, uCreateDate, uCreateBy ) values( '$name', MD5('$password1'), NOW(), '$user')";
				if ( mysql_query( $sql, $conn ) )
				{
					msgBox( "User created.<br />You may now add another." );
					lg( "User Added :: $name" );
				}
				else
				{
					msgBox( "Failed to add user." );
					lg( "Failed to add user :: $name " . mysql_error()  );
				}				
			}
			else
			{
				msgBox( "Invalid Password!" );
			}
		}
		else
		{
			msgBox( "$_POST[name] already exists" );
		}
}
echo ( "<div id=\"pageContent\" class=\"scroll\">" );
echo ( "<p class=\"title\">Add new user account</p>" );
echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]\">" );
echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
	echo ( "<tr><td><font>User Name:</font></td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"\" /></td></tr>" );
	echo ( "<tr><td><font>User Password:</font></td><td><input type=\"password\" name=\"p1\" value=\"\" /></td></tr>" );
	echo ( "<tr><td><font>User Password (confirm):</font></td><td><input type=\"password\" name=\"p2\" value=\"\" /><td></td></tr>" );
	echo ( "<tr><td colspan=2><font><center><br /><input type=\"hidden\" name=\"add\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Create User\" /></center></font></td></tr>" );				
echo ( "</table></center>" );
echo ( "</form>" );
echo ( "</div>" );		

?>
