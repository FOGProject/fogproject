<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
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
			echo ( "<a href=\"?node=$_GET[node]&sub=search\" class=\"plainfont\">New Search</a>" );
		echo ( "</div>" );		
		echo ( "<div class=\"subMenu\">" );
			echo ( "<a href=\"?node=$_GET[node]&sub=list\" class=\"plainfont\">List Groups</a>" );
		echo ( "</div>" );
		
		if ( $_GET["groupid"] !== null )
		{	
			if ( is_numeric( $_GET["groupid"] ) )
			{
				echo ( "<p class=\"hostTitle\">" );
						echo ( "Group Menu" );		
				echo ( "</p>" );	
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=gen\" class=\"plainfont\">General</a>" );
				echo ( "</div>" );	
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=tasks\" class=\"plainfont\">Basic Tasks</a>" );
				echo ( "</div>" );				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=member\" class=\"plainfont\">Membership</a>" );
				echo ( "</div>" );										
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=image\" class=\"plainfont\">Image Assoc</a>" );
				echo ( "</div>" );				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=os\" class=\"plainfont\">OS Assoc</a>" );
				echo ( "</div>" );						
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=snapadd\" class=\"plainfont\">Add Snapins</a>" );
				echo ( "</div>" );				
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=snapdel\" class=\"plainfont\">Remove Snapins</a>" );
				echo ( "</div>" );
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=ad\" class=\"plainfont\">Active Directory</a>" );
				echo ( "</div>" );	
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=printers&groupid=$_GET[groupid]\" class=\"plainfont\">Printers</a>" );
				echo ( "</div>" );											
				echo ( "<div class=\"subMenu\">" );
					echo ( "<a href=\"?node=$_GET[node]&sub=edit&groupid=$_GET[groupid]&tab=del\" class=\"plainfont\">Delete</a>" );
				echo ( "</div>" );	
				
				echo ( "<p class=\"miscTitle\">" );
						echo ( "Quick Info" );		
				echo ( "</p>" );				
				echo ( "<div class=\"infoItem\">" );
					$gid = mysql_real_escape_string( $_GET["groupid"] );
					echo "<p class=\"hostInfoTitleFirst\">Group:</p>"; 
					echo ( "<p class=\"hostInfoItem\">" . getGroupNameByID( $conn, $gid ) . "</p>" );
					echo "<p class=\"hostInfoTitle\">Members:</p>"; 
					echo ( "<p class=\"hostInfoItem\">" . count(getImageMembersByGroupID( $conn, $gid )) . "</p>" );						
				echo ( "</div>" );								
			}		
		}	
	echo ( "</td>" );
	echo ( "<td>" );
		echo ( "<div class=\"sub\">" );
		if ( $_GET[sub] == "add" )
		{
			require_once( "./includes/images.add.include.php" );
		}
		else if ( $_GET[sub] == "list" )
		{
			require_once( "./includes/groups.list.include.php" );
		}
		else if ( $_GET[sub] == "edit" )
		{
			require_once( "./includes/groups.edit.include.php" );
		}				
		else if ( $_GET[sub] == "search" )
		{
			require_once( "./includes/groups.search.include.php" );
		}			
		else if ( $_GET[sub] == "printers" )
		{
			require_once( "./includes/groups.printers.include.php" );
		}		
		else
		{
			require_once( "./includes/groups.search.include.php" );
		}
		echo ( "</div>" );
	echo ( "</td></tr>" );
	echo ( "</table>" );
}
?>
