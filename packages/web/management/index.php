<?php
/*
 *  FOG - Free, Open-Source Ghost is a computer imaging solution.
 *  Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */
session_start();
@error_reporting( 0 );

require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

require_once( "./lib/User.class.php" );
require_once( "./lib/ImageMember.class.php" );

$_SESSION["allow_ajax_host"] = false;

if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

$conn = mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
if ( $conn )
{
	$blOk = false;
	
	$curVer=getCurrentDBVersion( $conn );
	if ( $curVer == FOG_SCHEMA )
		$blOk = true;

	if ( ! $blOk )
	{
		header('Location: ../commons/schemaupdater/index.php');
		exit;
	}	
}
else
{
	die( "Unable to connect to Database" );
}

$currentUser = null;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link rel="stylesheet" type="text/css" href="css/<?php echo FOG_THEME;?>" />
<script type="text/javascript" src="js/main.js"></script>
<title>
FOG :: A Free, Open Source Computer Cloning Solution :: Version <?php echo FOG_VERSION; ?>
</title>
</head>
<body>
	<div class="mainContainer">
		<div class="header">
			<?php echo ("<div class=\"version\">Version: " . FOG_VERSION . "</div>\n"); ?>
		</div>
		<div class="mainContent">
		<?php
			if ( $_SESSION["fog_user"] != null )
				$currentUser = unserialize($_SESSION["fog_user"]);
		
			require_once( "./includes/processlogin.include.php" );
			if ( $_GET["node"] == "logout" || $currentUser == null || ! $currentUser->isLoggedIn() )
			{
				if ( $_GET["node"] == "logout" )
				{
					$_SESSION["fog_user"] = null;
					$currentUser  = null;
					session_destroy();
				}
				
				require_once( "./includes/loginform.include.php" );
			}
			else
			{
				require_once( "./includes/mainmenu.include.php" );				
			
				if( $_GET[node] == "images" )
				{
					require_once( "./includes/images.include.php" );
				}
				else if ( $_GET[node] == "host" )
					require_once( "./includes/hosts.include.php" );
				else if ( $_GET[node] == "group" )
					require_once( "./includes/groups.include.php" );						
				else if ( $_GET[node] == "tasks" )
					require_once( "./includes/tasks.include.php" );	
				else if ( $_GET[node] == "users" )
					require_once( "./includes/users.include.php" );		
				else if ( $_GET[node] == "about" )
					require_once( "./includes/about.include.php" );																	
				else if ( $_GET[node] == "help" )
					require_once( "./includes/help.include.php" );							
				else if ( $_GET[node] == "snap" )
					require_once( "./includes/snapins.include.php" );	
				else if ( $_GET[node] == "report" )
					require_once( "./includes/reports.include.php" );
				else if ( $_GET[node] == "print" )
					require_once( "./includes/printer.include.php" );					
				else
					require_once( "./includes/dashboard.include.php" );
			}
			
			if ( $currentUser != null )
				$_SESSION["fog_user"] = serialize( $currentUser );
		?>
		</div>
		<!-- Footer -->
		
	</div>

	<div class="footer"><h1>Created By Chuck Syperski &amp; Jian Zhang | GNU GPL v3</h1></div>
</body>
</html>
