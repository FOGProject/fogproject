<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	if ( $_GET["rmhostid"] != null && is_numeric( $_GET["rmhostid"] ) )
	{
		$rmid = mysql_real_escape_string( $_GET["rmhostid"] );
		
		removeAllTasksForHostID( $conn, $rmid );
		
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
		echo ( "<p class=\"mainTitle\">" );
				echo ( "Main Menu" );		
		echo ( "</p>" );	
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
		
		if ( $_GET["id"] !== null )
		{
			if ( is_numeric( $_GET["id"] ) )
			{
				echo ( "<p class=\"hostTitle\">" );
						echo ( "Host Menu" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&id=$_GET[id]&tab=gen\" class=\"plainfont\">General</a>" );
				echo ( "</div>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&id=$_GET[id]&tab=tasks\" class=\"plainfont\">Basic Tasks</a>" );
				echo ( "</div>" );								
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&id=$_GET[id]&tab=ad\" class=\"plainfont\">Active Directory</a>" );
				echo ( "</div>" );				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=printers&id=$_GET[id]\" class=\"plainfont\">Printers</a>" );
				echo ( "</div>" );					
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&id=$_GET[id]&tab=snapins\" class=\"plainfont\">Snapins</a>" );
				echo ( "</div>" );		
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=inv&id=$_GET[id]\" class=\"plainfont\">Hardware</a>" );
				echo ( "</div>" );						
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&id=$_GET[id]&tab=virus\" class=\"plainfont\">Virus History</a>" );
				echo ( "</div>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=loginhist&id=$_GET[id]\" class=\"plainfont\">Login History</a>" );
				echo ( "</div>" );								
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&id=$_GET[id]&tab=delete\" class=\"plainfont\">Delete</a>" );
				echo ( "</div>" );
				
				echo ( "<p class=\"miscTitle\">" );
						echo ( "Quick Info" );		
				echo ( "</p>" );
				
				echo ( "<div class=\"infoItem\">" );
					$hid = mysql_real_escape_string( $_GET["id"] );
					$sql = "select * from hosts where hostID = '$hid'";
					$res = mysql_query( $sql, $conn ) or die( mysql_error() );
					if ( $ar = mysql_fetch_array( $res ) )
					{
						echo "<p class=\"hostInfoTitleFirst\">Host:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . trimString( stripslashes($ar["hostName"]), 20 ) . "</p>" );
						echo "<p class=\"hostInfoTitle\">MAC:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . stripslashes($ar["hostMAC"]) . "</p>" );						
						echo "<p class=\"hostInfoTitle\">Operat. System:</p>"; 
						echo ( "<p class=\"hostInfoItem\">" . stripslashes(getOSNameByID( $conn, $ar["hostOS"] )) . "</p>" );						
					}
				echo ( "</div>" );
														
			}
		}
		
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
		else if ( $_GET[sub] == "loginhist" )
		{
			require_once( "./includes/hosts.login.include.php" );
		}	
		else if ( $_GET[sub] == "printers" )
		{
			require_once( "./includes/hosts.printers.include.php" );
		}					
		else if ( $_GET[sub] == "inv" )
		{
			require_once( "./includes/hosts.inventory.include.php" );
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
