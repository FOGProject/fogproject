<?php
@session_set_cookie_params(0);
@session_start();
print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
print "\n<html>";
print "\n\t<head>";
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="./css/static.css" />';
print "\n\t</head>";
print "\n<body>";
print "\n\t".'<div class="main">';
print "\n\t\t<h3>".$foglang['GenHelp'].'</h3>';
print "\n\t\t<h5>".$foglang['Desc'].'</h5>';
print "\n\t\t<p>";
print "\n\t\t\t".base64_decode($_REQUEST['data']);
print "\n\t\t</p>";
print "\n\t</div>";
print "\n</body>";
print "\n</html>";
