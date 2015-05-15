<?php
$Response = function() {
	print "#!ok\n#Foo=bar\n#Empty=\n#-X=Special";
};
$ResponseArray = function() {
	print "#!ok\n#obj0=Foo\n#obj1=bar\n#obj2=22!";
};
$BadResponse = function() {
	print "#!er";
};
$Download = function() {
	header('Content-Disposition: attachment; filename=test.txt');
	header('Content-Type: application/force-download');
	header('Content-Length: '.strlen('Foobar22!'));
	header('Connection: close');
	print 'Foobar22!';
};
$AESDecryptionResponse1 = function($key,$iv,$data) {
	$data = "#!ok\n#data=$data";
	$cipher = bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,$key,$data,MCRYPT_MODE_CBC,$iv));
	$iv = bin2hex($iv);
	print "#!en=$iv|$cipher";
};
$AESDecryptionResponse2 = function($key,$iv,$data) {
	$data = "#!ok\n#data=$data";
	$cipher = bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,$key,$data,MCRYPT_MODE_CBC,$iv));
	$iv = bin2hex($iv);
	print "#!enkey=$iv|$cipher";
};
$AESDecryption = function($key,$iv,$data) {
	$cipher = bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,$key,$data,MCRYPT_MODE_CBC,$iv));
	$iv = bin2hex($iv);
	print "$iv|$cipher";
};
$RawResponse = function() {
	print 'Foobar22!';
};
$units = array_keys(
	array(
		'Response',
		'ResponseArray',
		'BadResponse',
		'Download',
		'AESDecryptionResponse1',
		'AESDecryptionResponse2',
		'AESDecryption',
		'RawResponse',
	)
);
if (in_array($_REQUEST['unit'],$units)) {
	if (strpos($_REQUEST['unit'],'AESDecryption') !== false) {
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128,MCRYPT_MODE_CBC);
		$iv = mcrypt_create_iv($iv_size,MCRYPT_DEV_URANDOM);
		$n = strlen($_REQUEST['key']);
		$i = 0;
		while ($i < $n) {
			$a = substr($_REQUEST['key'],$i,2);
			$c = pack("H*",$a);
			if ($i == 0) $key = $c;
			else $key .= $c;
			$i += 2;
		}
		$$_REQUEST['unit']($key,$iv,'Foobar22!');
	} else {
		$$_REQUEST['unit']();
	}
}
