<?php
session_start();

if(!isset($_SESSION["locale"]))
        $_SESSION['locale'] = "en_US";
        
putenv("LC_ALL=".$_SESSION['locale']);
setlocale(LC_ALL, $_SESSION['locale']);
bindtextdomain("messages", "../languages");
textdomain("messages");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link rel="stylesheet" type="text/css" href="./css/static.css" />
</head>
<body>
	<div class="main">
	<h3><?php echo(_("FOG General Help")); ?></h3>
		
		<h5><?php echo(_("Description")); ?></h5>
		<p>
			<?php
				echo base64_decode( $_GET["data"] );
			?>
		</p>
		

	</div>
</body>
</html>
