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

echo ( "<div class=\"scroll\">" );
echo ( "<p class=\"title\">FOG Kernel Updates</p>" );

if ( $_GET["file"] != null )
{
	$file = base64_decode( $_GET["file"] );
	flush();
	
	$basename = "/tmp/" . basename( $file );
	
	if ( file_exists( $basename ) )
		@unlink( $basename );
		
	$hdl = fopen($file, "rb");
		
	$contents = null;
	if ( $hdl )
	{
		while (! feof($hdl)) 
		{
			$contents .= fread($hdl, 8192);
		}
		fclose($hdl);	
		
		if ( count( $contents ) > 0 )
		{
			flush();
			$hdl = null;
			$hdl = fopen( $basename, "wb" );
			if ( $hdl !== null )
			{
				if (fwrite($hdl, $contents) === FALSE) 
					echo "Cannot write to file: $basename !!";
				fclose($hdl);		
				
				$ftp = ftp_connect(TFTP_HOST); 
				$ftp_loginres = ftp_login($ftp, TFTP_FTP_USERNAME, TFTP_FTP_PASSWORD); 			
				if ($ftp && $ftp_loginres ) 
				{				
					$backuppath = TFTP_PXE_KERNEL_DIR . "backup/";	
					$warning = "";	
					if ( ! ftp_mkdir( $ftp, $backuppath ) )
						$warning = "Warning: Unable to create backup directory, maybe it already exists?<br />";
					
					$bzImage = TFTP_PXE_KERNEL_DIR . "bzImage";
					$backupfile = $backuppath . "bzImage." . date("Ymd") . "_" . date("His");
					if ( ftp_rename( $ftp, $bzImage, $backupfile ) )
					{
						if ( ftp_put( $ftp, $bzImage, $basename, FTP_BINARY ) )
						{	
							@unlink($basename);				
							echo ( "<p>Kernel: " . basename( $file ) . " has been installed!</p>" );						
							echo ( "<p>If you have problems with the new kernel, the original can restored by coping  $backupfile to $bzImage</p>" );
							echo ( "<p><pre class=\"shellcommand\">cp $backupfile \\ \n$bzImage</pre></p>" );
							lg( "New Kernel installed!" );
						}
						else
						{
							if ( ftp_rename( $ftp, $backupfile, $bzImage ) )
							{
								echo ( "Failed to install new kernel, but the old kernel has been restored." );
							}
							else
							{
								echo ( "Failed to install new kernel and could not restore original kernel file!" );
							}
						}
					}
					else
					{
						echo $warning;
						echo "Failed to backup current kernel";
					}
				}
				else
					echo "Unable to connect to tftp server.<br />";
			}
			else
				echo "Failed to open file: $basename !!";
		}
		else
			echo "Failed to open file: $file !! (Error: 101)";
	}
	else
		echo "Failed to open file: $file !! (Error: 100)";
}
else
{
	echo ( "<div class=\"hostgroup\">" );	
		echo ( "This section allows you to update the Linux kernel which is used to boot the client computers.  In FOG, this kernel holds all the drivers for the client computer, so if you are unable to boot a client you may wish to update to a newer kernel which may have more drivers built in.  This installation process may take a few minutes, as FOG will attempt to go out to the internet to get the requested Kernel, so if it seems like the process is hanging please be patient." );
	echo ( "</div>" );
	
	echo ( "<div>" );
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT, '10');
		curl_setopt($ch, CURLOPT_URL, "http://freeghost.sourceforge.net/kernelupdates/index.php?version=" . FOG_VERSION );
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$ret = curl_exec($ch);
	echo ( "</div>" );		
}
echo ( "</div>" );	
		
?>
