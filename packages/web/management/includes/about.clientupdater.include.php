<?php
/*
 *  FOG is a computer imaging solution.
 *  Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
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
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );


echo ( "<div class=\"scroll\">" );
	echo ( "<p class=\"title\">FOG Client Service Updater</p>" );
	
	if ( $_GET["del"] != null && is_numeric($_GET["del"]))
	{
		$del = mysql_real_escape_string( $_GET["del"] );
		$sql = "delete from clientUpdates where cuID = '$del'";
		if (! mysql_query( $sql, $conn ) )
			msgBox( mysql_error() );
		else
			lg( "Client module update deleted: " . $del );
		
	}
	
	if ( $_FILES["module"] != null  )
	{
		if ( file_exists( $_FILES['module']['tmp_name'] ) )
		{
			$strContents = file_get_contents( $_FILES['module']['tmp_name'] );
			$md5 = md5( $strContents );	
			$strContents = base64_encode( $strContents );
			if ( $strContents != null )
			{
				$modname = mysql_real_escape_string( basename($_FILES['module']['name']) );
				$type = "bin";
				if ( endsWith( $modname, ".ini" ) )
					$type = "txt";
				
				
				
				$sql = "SELECT 
						count(*) as cnt 
					FROM 
						clientUpdates 
					WHERE 
						cuName = '$modname'";
				$res = mysql_query( $sql, $conn ) or die( mysql_error() );
				
				if ( $ar = mysql_fetch_array( $res ) )
				{
					if ( $ar["cnt"] == 0 )
					{
						$sql = "INSERT INTO
								clientUpdates (cuName, cuMD5, cuType, cuFile)
								values( '$modname', '$md5', '$type', '$strContents')";
					}
					else
					{
						$sql = "UPDATE
								clientUpdates 
							SET
								cuMD5 = '$md5',
								cuType = '$type',
								cuFile = '$strContents'
							WHERE
								cuName = '$modname'";
					}
					
					if ( ! mysql_query( $sql, $conn ) )
					{
						msgBox( mysql_error() );
					}
					else
						lg( "Client update module uploaded: " . $modname );
				}
			}
		}
	}
	?>
	<div class="hostgroup">
		This section allows you to update the modules and config files that run on the client computers.  The clients will checkin with the server from time to time to see if a new module is published.  If a new module is published the client will download the module and use it on the next time the service is started.  
	</div>
	
	<table width="100%" cellpadding="0" cellspacing="0">
	<tr bgcolor="#BDBDBD"><td>&nbsp;Module Name</td><td>&nbsp;Module MD5</td><td>&nbsp;Module Type</td><td>&nbsp;Delete</td></tr>
	<?php
		$sql = "SELECT * FROM clientUpdates order by cuName";
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( mysql_num_rows( $res ) > 0 )
		{
			while( $ar = mysql_fetch_array( $res ) )
			{		
				echo ( "<tr><td>&nbsp;" . $ar["cuName"] . "</td><td>&nbsp;" . $ar["cuMD5"] . "</td><td>&nbsp;" .  $ar["cuType"]  . "</td><td>&nbsp;<a href=\"?node=$_GET[node]&sub=$_GET[sub]&del=$ar[cuID]\"><img src=\"./images/deleteSmall.png\" class=\"noBorder\" /></a></td></tr>" );
			}
		}
		else
		{
			echo ( "<tr><td colspan='4'>&nbsp;<center>No modules found.</center></td></tr>" );
		}
	?>
	</table>
	
	<p class="titleBottomLeft">Upload a new client module / configuration file</p>
	<form method="post" action="<?php echo( "?node=$_GET[node]&sub=$_GET[sub]");?>" enctype="multipart/form-data">
		<input type="file" name="module" value="" /> <span class="lightColor"> Max Size: <?php echo ini_get("post_max_size"); ?></span>
		<p><input type="submit" value="Upload File" /></p>
	</form>
	<?php
echo ( "</div>" );		


	
		
?>
