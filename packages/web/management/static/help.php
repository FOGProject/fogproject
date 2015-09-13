<?php
@session_set_cookie_params(0);
@session_start();
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
echo "\n<html>";
echo "\n\t<head>";
echo "\n\t\t".'<link rel="stylesheet" type="text/css" href="./css/static.css" />';
echo "\n\t</head>";
echo "\n<body>";
echo "\n\t".'<div class="main">';
echo "\n\t\t<h3>".$foglang['GenHelp'].'</h3>';
echo "\n\t\t<h5>".$foglang['Desc'].'</h5>';
echo "\n\t\t<p>";
echo "\n\t\t\t".base64_decode($_REQUEST['data']);
echo "\n\t\t</p>";
echo "\n\t</div>";
echo "\n</body>";
echo "\n</html>";
