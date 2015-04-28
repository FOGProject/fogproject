<?php
require_once('../commons/base.inc.php');
$bzImage = exec('strings '.BASEPATH.'/service/ipxe/bzImage|grep -A1 "Undefined video mode number:"|tail -1|awk \'{print $1}\'');
$bzImage32 = exec('strings '.BASEPATH.'/service/ipxe/bzImage32|grep -A1 "Undefined video mode number:"|tail -1|awk \'{print $1}\'');
print "bzImage Version: $bzImage\nbzImage32 Version: $bzImage32\n";
