<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	if ( $_GET["rmhostid"] != null && is_numeric( $_GET["rmhostid"] ) )
	{
		$rmid = mysql_real_escape_string( $_GET["rmhostid"] );
		$sql = "delete from hosts where hostID = '" . $rmid . "'";
		if ( mysql_query( $sql, $conn ) )
		{
			msgBox( "Host removed!" );
			lg( "Removed host id #" . $rmid );
		}
		else
		{
			msgBox( "Failed to remove host!" );
			lg( "Failed to remove host. " . mysql_error() );
		}
	}


	if ( $_POST[frmSub] == "1" )
	{
		if ( $_POST[grp] != "-1" || $_POST[newgroup] != null )
		{
			$blGo = false;
			$grp = "";	
				
			if ( $_POST["newgroup"] != null )
			{
				if ( createGroup( $conn, $_POST["newgroup"] ) )
				{
					$blGo = true;
					$grp = $_POST["newgroup"];
				}
				else
				{
					echo ( "<center><b>Unable to create new group, does it exist already?</b></center>" );	
				}
			}
			else
			{
				$blGo = true;
				$grp = $_POST["grp"];
			}	
			
			$grpID = getGroupIDByName( $conn, $grp );		
			if ( $blGo && $grpID != -1 )
			{
				$checked = getCheckedItems( $_POST );
			
				if ( $checked != null )
				{
					if ( addMemebersToGroup( $conn, $grpID, $checked ) )
					{
						msgBox( "$grp was updated/created!" );
					}
					else
					{
						msgBox( "Error updating $grp $grpID" );
					}
				}
			}			
		}
		else
		{
			echo ( "<center><b>Please select or create a new group!</b></center>" );
		}		
	}
	
	echo ( "<center>" );
	echo ( "<table width=\"98%\" cellpadding=0 cellspacing=0 border=0>" );
	echo ( "<tr><td width=\"100\" valign=\"top\" >" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=home\" class=\"plainfont\">Home</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=newsearch\" class=\"plainfont\">New Search</a>" );
		echo ( "</div>" );
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=list\" class=\"plainfont\">List All Hosts</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=add\" class=\"plainfont\">Add New Host</a>" );
		echo ( "</div>" );	
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=upload\" class=\"plainfont\">Upload Hosts</a>" );
		echo ( "</div>" );		
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "add" )
		{
			require_once( "./includes/hosts.add.include.php" );
		}
		else if ( $_GET[sub] == "newsearch" )
		{
			require_once( "./includes/hosts.search.include.php" );
		}
		else if ( $_GET[sub] == "list" )
		{
			require_once( "./includes/hosts.list.include.php" );
		}		
		else if ( $_GET[sub] == "edit" )
		{
			require_once( "./includes/hosts.edit.include.php" );
		}	
		else if ( $_GET[sub] == "upload" )
		{
			require_once( "./includes/hosts.upload.include.php" );
		}			
		else
		{
			require_once( "./includes/hosts.search.include.php" );	
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
