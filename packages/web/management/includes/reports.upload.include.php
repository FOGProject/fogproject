<?php
/*
 *  FOG - Free, Open-Source Ghost is a computer imaging solution.
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

//@ini_set( "max_execution_time", 120 );
 
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $_FILES["report"] != null  )
{
	if ( file_exists( $_FILES['report']['tmp_name'] ) )
	{
		$uploadfile = realpath(FOG_REPORT_DIR) . "/" . basename($_FILES['report']['name']); 
		if ( file_exists( FOG_REPORT_DIR ) )
		{
			if ( file_exists( $uploadfile ) )
			{	
				unlink( $uploadfile );		
			}
			
			if ( endsWith( $uploadfile, ".php" ) )
			{
				if ( move_uploaded_file($_FILES['report']['tmp_name'], $uploadfile) )					
				{				
					msgBox( "Your report has been added!" );
				}
				else
				{
					msgBox( "Unable to move uploaded file." . $uploadfile );
				}	
			}			
			else
				msgBox( "File does not look like a php source file" );
		}
		else
		{
			msgBox( "Unable to locate " .  FOG_REPORT_DIR );
		}
	}
}


echo ( "<div class=\"scroll\">" );
echo ( "<p class=\"title\">Upload FOG Reports</p>" );
	?>
	<div class="hostgroup">
		This section allows you to upload user defined reports that may not be part of the base FOG package.  The report files should end in .php.  
	</div>	
	
	<p class="titleBottomLeft">Upload a FOG Report</p>
	<form method="post" action="<?php echo( "?node=$_GET[node]&sub=$_GET[sub]");?>" enctype="multipart/form-data">
		<input type="file" name="report" value="" /> <span class="lightColor"> Max Size: <?php echo ini_get("post_max_size"); ?></span>
		<p><input type="submit" value="Upload File" /></p>
	</form>	
	<?php		
echo ( "</div>" );	
		
?>
