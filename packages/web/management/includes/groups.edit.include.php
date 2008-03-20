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

if ( $_GET["groupid"] != null && is_numeric( $_GET["groupid"] ) )
{

	$groupid = mysql_real_escape_string( $_GET["groupid"] );

	if ( $_POST["update"] == "1" )
	{
		if ( ! groupExists( $conn, $_POST["name"], $groupid ) )
		{
			$name = mysql_real_escape_string( $_POST["name"] );
			$description = mysql_real_escape_string( $_POST["description"] );

			$sql = "update groups set groupName = '" . $name . "', groupDesc = '$description' where groupID = '" . mysql_real_escape_string( $_GET[groupid] ) . "'";
			mysql_query( $sql, $conn ) or die( mysql_error() );
			msgBox( "Group Updated!" );
			lg( "updated group $name" );
		} 
		else
		{
			msgBox( "Unable to update because the group name you picked already exists" );
		}
	}
	
	if ( $_POST["updatead"] == "1" )
	{
		$updated = 0;
		$members = getImageMembersByGroupID( $conn, $groupid );	
		if ( $members != null )
		{
			$useAD = "0";
			if ( $_POST["domain"] == "on" )
				$useAD = "1";
			
			$adDomain = mysql_real_escape_string( $_POST["domainname"] );
			$adOU = mysql_real_escape_string( $_POST["ou"] );
			$adUser = mysql_real_escape_string( $_POST["domainuser"] );
			$adPass = mysql_real_escape_string( $_POST["domainpassword"] );				
			
			for( $i =0; $i < count( $members ); $i++ )
			{
				if ( $members[$i] != null )
				{
					$sql = "update hosts set hostUseAD = '$useAD', hostADDomain = '$adDomain', hostADOU = '$adOU', hostADUser = '$adUser', hostADPass = '$adPass' where hostID = '" . $members[$i]->getID() . "'";
					if ( mysql_query( $sql, $conn ) )
					{
						$updated++;
					}
					else
					{
						die( mysql_error() );

					}						
				}			
			}
			msgBox( $updated . " hosts have been updated." );		
		}		
	}

	if ( $_POST["gsnapinadd"] == "1" )
	{
		$update = 0;
		$existing = 0;
		$failed = 0;
		$members = getImageMembersByGroupID( $conn, $groupid );	
		if ( $members != null )
		{
			$snapinid = mysql_real_escape_string($_POST["snap"]);
			if ( is_numeric( $snapinid ) )
			{
				for( $i =0; $i < count( $members ); $i++ )
				{
					if ( $members[$i] != null )
					{
						if ( ! isHostAssociatedWithSnapin( $conn, $members[$i]->getID(), $snapinid ) )
						{
							$returnVal = null;
							if ( addSnapinToHost( $conn, $members[$i]->getID(), $snapinid, $returnVal ) )
							{
								$update++;
							}
							else
								$failed++;
						}
						else
							$existing++;
					}			
				}
				msgBox( $update . " hosts have been updated.<br />" . $failed . " hosts have failed.<br />" . $existing . " were already linked." );
			}		
		}		
	}
	
	
	if ( $_POST["gsnapindel"] == "1" )
	{
		$removed = 0;
		$members = getImageMembersByGroupID( $conn, $groupid );	
		if ( $members != null )
		{
			$snapinid = mysql_real_escape_string($_POST["snap"]);
			if ( is_numeric( $snapinid ) )
			{
				for( $i =0; $i < count( $members ); $i++ )
				{
					if ( $members[$i] != null )
					{
						if ( isHostAssociatedWithSnapin( $conn, $members[$i]->getID(), $snapinid ) )
						{
							$returnVal = null;
							if ( deleteSnapinFromHost( $conn, $members[$i]->getID(), $snapinid, $returnVal )  )
							{
								$removed++;
							}
						}
					}			
				}
				msgBox( $removed . " hosts have been updated." );
			}		
		}		
	}	
	

	if ( $_POST["image"] != null && is_numeric( $_POST["image"] ) )
	{
		$updated = 0;
		$members = getImageMembersByGroupID( $conn, $groupid );
		if ( $members != null )
		{
			for( $i =0; $i < count( $members ); $i++ )
			{
				if ( $members[$i] != null )
				{
					if ( $members[$i]->getID() != null )
					{
						$sql = "update hosts set hostImage = '" . mysql_real_escape_string( $_POST["image"] ) . "' where hostID = '" . mysql_real_escape_string( $members[$i]->getID() ) . "'";
						if ( mysql_query( $sql, $conn ) )
						{
							$updated++;
							lg( "updated image for host " . $members[$i]->getID() );
						}
					}
				}
			}
		}
		msgBox( "$updated hosts updated" );
	}
	
	if ( $_POST["grpos"] !== null && is_numeric( $_POST["grpos"] ) )
	{
		$updated = 0;
		$members = getImageMembersByGroupID( $conn, $groupid );
		if ( $members != null )
		{
			for( $i =0; $i < count( $members ); $i++ )
			{
				if ( $members[$i] != null )
				{
					if ( $members[$i]->getID() != null )
					{
						$sql = "update hosts set hostOS = '" . mysql_real_escape_string( $_POST["grpos"] ) . "' where hostID = '" . mysql_real_escape_string( $members[$i]->getID() ) . "'";
						if ( mysql_query( $sql, $conn ) )
						{
							$updated++;
							lg( "updated os for host " . $members[$i]->getID() );
						}						
					}
				}			
			}
		}
		msgBox( "$updated hosts updated" );		
	}

	$sql = "select * from groups where groupID = '" . $groupid . "'";
	$res = mysql_query( $sql, $conn ) or die( mysql_error() );	


	if ( $ar = mysql_fetch_array( $res ) )
	{
		echo ( "<div class=\"scroll\">" );
			if ( $_GET["tab"] == "gen" || $_GET["tab"] == "" )
			{
				echo ( "<p class=\"title\">Modify Group " . $ar["groupName"] . "</p>" );
				echo ( "<form method=\"POST\" action=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&groupid=" . $_GET["groupid"] . "\">" );
					echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
					echo ( "<tr><td>Group Name:</td><td><input class=\"smaller\" type=\"text\" name=\"name\" value=\"$ar[groupName]\" /></td></tr>" );
					echo ( "<tr><td>Group Description:</td><td><textarea name=\"description\" rows=\"3\" cols=\"40\">$ar[groupDesc]</textarea></td></tr>" );
					echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"update\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></td></tr>" );				
					echo ( "</table></center>" );
				echo ( "</form>" );
			}
			
			if ( $_GET["tab"] == "image"  )
			{
				echo ( "<p class=\"title\">Image Association for $ar[groupName]</p>" );	
				echo ( "<div class=\"hostgroup\">" );	
					echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&groupid=$_GET[groupid]&tab=$_GET[tab]\">" );	
					$sql = "select * from images order by imageName";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					echo( "<select name=\"image\" size=\"1\">" );
					echo ( "<option value=\"\">Do Nothing</option>" );
					while( $ar1 = mysql_fetch_array( $res ) )
					{
						echo ( "<option value=\"" . $ar1["imageID"] . "\" >" . $ar1["imageName"] . "</option>" );
					}
					echo ( "</select>" );
					echo ( "<p><input class=\"smaller\" type=\"submit\" value=\"Update Images\" /></p>" );	
					echo ( "</form>" );	
				echo ( "</div>" );
			}
			
			if ( $_GET["tab"] == "os"  )
			{			
				echo ( "<p class=\"title\">Operating System Association for $ar[groupName]</p>" );
				echo ( "<div class=\"hostgroup\">" );	
					echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&groupid=$_GET[groupid]&tab=$_GET[tab]\">" );	
					echo ( getOSDropDown( $conn, $name="grpos" ) );
					echo ( "<p><input class=\"smaller\" type=\"submit\" value=\"Update Operating System\" /></p>" );		
					echo ( "</form>" );			
				echo ( "</div>" );	
			}
			
			if ( $_GET["tab"] == "tasks" )
			{	
				echo ( "<p class=\"title\">Basic Imaging Tasks</p>" );

				?>
				
				<table cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr><td class="leadingSpace bottomLightBorder" width="50"><a href="?node=tasks&type=group&direction=down&noconfirm=<?php echo $_GET["groupid"]; ?>"><img class="advancedIcon" src="./images/senddebug.png" /><p class="advancedTitle">Send</p></a></td><td class="leadingSpace bottomLightBorder"><p class=\"advancedDesc\">Send action will deploy an image saved on the FOG server to the client computer with all included snapins.</p></td></tr>
				</table>
				
				<?php
			}			
			
			if ( $_GET["tab"] == "snapadd"  )
			{				
				echo ( "<p class=\"title\">Add Snapin to all hosts in " . $ar["groupName"] . "</p>" );
				echo ( "<div class=\"hostgroup\">" );
					echo ( "<form method=\"POST\" action=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&groupid=" . $_GET["groupid"] . "&tab=$_GET[tab]\">" );
					echo ( getSnapinDropDown( $conn ) );
					echo( "<p><input type=\"hidden\" name=\"gsnapinadd\" value=\"1\" /><input type=\"submit\" value=\"Add Snapin\" /></p>" );
					echo ( "</form>" );
				echo ( "</div>" );
			}
			
			if ( $_GET["tab"] == "snapdel" )
			{
				echo ( "<p class=\"title\">Remove Snapin to all hosts in " . $ar["groupName"] . "</p>" );
				echo ( "<div class=\"hostgroup\">" );
					echo ( "<form method=\"POST\" action=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&groupid=" . $_GET["groupid"] . "&tab=$_GET[tab]\">" );
					echo ( getSnapinDropDown( $conn ) );
					echo( "<p><input type=\"hidden\" name=\"gsnapindel\" value=\"1\" /><input type=\"submit\" value=\"Remove Snapin\" /></p>" );
					echo ( "</form>" );
				echo ( "</div>" );								
			}
			
			if ( $_GET["tab"] == "ad" )
			{			
				echo ( "<p class=\"title\">Modify AD information for " . $ar["groupName"] . "</p>" );	
		
				echo ( "<form method=\"POST\" action=\"?node=" . $_GET["node"] . "&sub=" . $_GET["sub"] . "&groupid=" . $_GET["groupid"] . "&tab=$_GET[tab]\">" );
				echo ( "<table cellpadding=0 cellspacing=0 border=0 width=90%>" );
					echo ( "<tr><td>Join Domain after image task:</td><td><input class=\"smaller\" type=\"checkbox\" name=\"domain\" /></td></tr>" );
					echo ( "<tr><td>Domain name:</td><td><input class=\"smaller\" type=\"text\" name=\"domainname\" /></td></tr>" );				
					echo ( "<tr><td>Organizational Unit:</td><td><input class=\"smaller\" type=\"text\" name=\"ou\" /> <span class=\"lightColor\">(Blank for default)</span></td></tr>" );				
					echo ( "<tr><td>Domain Username:</td><td><input class=\"smaller\" type=\"text\" name=\"domainuser\" /></td></tr>" );						
					echo ( "<tr><td>Domain Password:</td><td><input class=\"smaller\" type=\"text\" name=\"domainpassword\" /> <span class=\"lightColor\">(Must be encrypted)</span></td></tr>" );												
					echo ( "<tr><td colspan=2><center><br /><input type=\"hidden\" name=\"updatead\" value=\"1\" /><input class=\"smaller\" type=\"submit\" value=\"Update\" /></center></td></tr>" );									
				echo ( "</table>" );
				echo ( "</form>" );
			}
			
			if ( $_GET["tab"] == "member" )
			{			
				echo ( "<p class=\"title\">Modify Membership for " . $ar["groupName"] . "</p>" );
				
				echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&groupid=$_GET[groupid]&tab=$_GET[tab]\">" );
				echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=100%>" );
				if ( $_GET["delhostid"] != null && is_numeric( $_GET["delhostid"] ) )
				{
					$sql = "delete from groupMembers where gmGroupID = '" . mysql_real_escape_string( $_GET["groupid"] ) . "' and gmHostID = '" . mysql_real_escape_string( $_GET["delhostid"] ) . "'";
					if ( !mysql_query( $sql, $conn ) )
						msgBox( "Failed to remove host from group!" );
				
				}		
		
		
				$members = getImageMembersByGroupID( $conn, $ar["groupID"] );
				if ( $members != null )
				{
					for( $i = 0; $i < count( $members ); $i++ )
					{
						if ( $members[$i] != null )
						{
							$bgcolor = "";
							if ( $i % 2 == 0 ) $bgcolor = "#E7E7E7";
							echo ( "<tr bgcolor=\"$bgcolor\"><td>&nbsp;" . $members[$i]->getHostName() . "</td><td>&nbsp;" . $members[$i]->getIPaddress() . "</td><td>&nbsp;" . $members[$i]->getMAC() . "</td><td><a href=\"?node=$_GET[node]&sub=$_GET[sub]&groupid=" . $_GET["groupid"] . "&tab=$_GET[tab]&delhostid=" . $members[$i]->getID() . "\"><img src=\"images/deleteSmall.png\" class=\"link\" /></a></td></tr>" );
						}	
					}
				}							
				echo ( "</table></center>" );
				echo ( "</form>" );
			}	
	
			if ( $_GET["tab"] == "del" )
			{		
				echo ( "<p class=\"title\">Delete Group</p>" );	
				echo ( "<p>Click on the icon below to delete this group from the FOG database.</p>" );					
				echo ( "<p><a href=\"?node=$_GET[node]&sub=$_GET[sub]&delgroupid=" . $ar["groupID"] . "\"><img class=\"link\" src=\"images/delete.png\"></a></p>" );
			}
			
		echo ( "</div>" );		
	}	
}
else if ( $_GET["delgroupid"] != null )
{
	if ( $_GET["delgroupid"] != null && is_numeric( $_GET["delgroupid"] ) )
	{
		$delid = mysql_real_escape_string( $_GET["delgroupid"] );
		if ( $_GET["confirm"] != 1 )
		{
			$sql = "select * from groups where groupID = '" . $delid . "'";
			$res = mysql_query( $sql, $conn ) or die( mysql_error() );
			if ( $ar = mysql_fetch_array( $res ) )
			{
				echo ( "<div id=\"pageContent\" class=\"scroll\">" );
				echo ( "<p class=\"title\">Confirm Group Removal</p>" );
				echo ( "<form method=\"POST\" action=\"?node=$_GET[node]&sub=$_GET[sub]&delgroupid=$_GET[delgroupid]&confirm=1\">" );
				echo ( "<center><table cellpadding=0 cellspacing=0 border=0 width=90%>" );
					echo ( "<tr><td>Group Name:</td><td>" . $ar["groupName"] . "</td></tr>" );
					echo ( "<tr><td colspan=2><center><br /><input class=\"smaller\" type=\"submit\" value=\"Yes, delete this group\" /></center></td></tr>" );				
				echo ( "</table></center>" );
				echo ( "</form>" );
				echo ( "</div>" );		
			}
		}
		else
		{

			$sql = "delete from groups where groupID = '" . $delid . "'";
			if ( mysql_query( $sql, $conn ) )
			{
				// now delete all the associations
				$sql = "delete from groupMembers where gmGroupID = '" . $delid . "'";
				if ( mysql_query( $sql, $conn ) )
				{
					lg( "Deleted group $_GET[delgroupid]" );
					echo ( "<div id=\"pageContent\" class=\"scroll\">" );
					echo ( "<p class=\"title\">Group Removal Complete</p>" );					
					echo ( "Group has been deleted." );
					echo ( "</div>" );					
				}
				else
					echo ( mysql_error() );
			}
			else
				echo ( mysql_error() );
			
		}		
	}
}
else
{
	echo "Invalid group information.";
}
	
		
?>
