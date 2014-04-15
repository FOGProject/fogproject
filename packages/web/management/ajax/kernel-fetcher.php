<?php
require((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
// Allow AJAX check
if (!$_SESSION['AllowAJAXTasks'])
	die('FOG Session Invalid');
if ( $_SESSION["allow_ajax_kdl"] && $_SESSION["dest-kernel-file"] != null && $_SESSION["tmp-kernel-file"] != null && $_SESSION["dl-kernel-file"] != null )
{
	if ( $_POST["msg"] == "dl" )
	{
		// download kernel from sf
		$blUseProxy = false;
		$proxy = "";
		if ( trim( $FOGCore->getSetting( "FOG_PROXY_IP" ) ) != null )
		{
			$blUseProxy = true;
			$proxy = $FOGCore->getSetting( "FOG_PROXY_IP" ).":".$FOGCore->getSetting( "FOG_PROXY_PORT" );
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT, '700');
		if ( $blUseProxy )
			curl_setopt($ch, CURLOPT_PROXY, $proxy);
		curl_setopt($ch, CURLOPT_URL, $_SESSION["dl-kernel-file"] );
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$fp = fopen($_SESSION["tmp-kernel-file"], 'wb');
		if ( $fp )
		{
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_exec ($ch);
			curl_close ($ch);
			fclose($fp);	
			if ( file_exists( $_SESSION["tmp-kernel-file"] ) )
			{
				if (filesize( $_SESSION["tmp-kernel-file"]) > 1048576 )
					echo "##OK##";
				else
					echo "Error: Download failed: filesize = " . filesize( $_SESSION["tmp-kernel-file"]);
			}
			else
				echo "Error: Failed to download kernel!";
		}
		else
			echo "Error: Failed to open temp file.";
	}
	else if ( $_POST["msg"] == "tftp" )
	{
		$ftp = $GLOBALS['FOGFTP'];
		$ftp->set('host',$FOGCore->getSetting('FOG_TFTP_HOST'))
			->set('username', $FOGCore->getSetting('FOG_TFTP_FTP_USERNAME'))
			->set('password', $FOGCore->getSetting('FOG_TFTP_FTP_PASSWORD'));
		if ($ftp->connect()) 
		{				
			$backuppath = $FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_DIR')."backup/";	
			$orig = $FOGCore->getSetting('FOG_TFTP_PXE_KERNEL_DIR').$_SESSION['dest-kernel-file'];
			$backupfile = $backuppath.$_SESSION["dest-kernel-file"].date("Ymd")."_".date("His");
			$ftp->mkdir($backuppath);
			$ftp->rename($backupfile,$orig);
			if ($ftp->put($orig,$_SESSION['tmp-kernel-file'],FTP_BINARY))
			{	
				@unlink($_SESSION['tmp-kernel-file']);
				print '##OK##';
			}
			else
				print _('Error: Failed to install new kernel!');
			$ftp->close();
		}
		else
			print _('Error: Unable to connect to tftp server.');
	}
}
else
	echo "<b><center>"._("This page can only be viewed via the FOG Management portal")."</center></b>";
