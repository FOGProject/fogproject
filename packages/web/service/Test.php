<?php
$Response = function() {
    echo "#!ok\n#Foo=bar\n#Empty=\n#-X=Special";
};
$ResponseArray = function() {
    echo "#!ok\n#obj0=Foo\n#obj1=bar\n#obj2=22!";
};
$BadResponse = function() {echo "#!er";};
$Download = function() {
    header('Content-Disposition: attachment; filename=test.txt');
    header('Content-Type: application/force-download');
    header('Content-Length: '.strlen('Foobar22!'));
    header('Connection: close');
    echo 'Foobar22!';
};
$AESDecryptionResponse1 = function($key,$iv,$data) {
    $data = "#!ok\n#data=$data";
    $cipher = bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,$key,$data,MCRYPT_MODE_CBC,$iv));
    $iv = bin2hex($iv);
    echo "#!en=$iv|$cipher";
};
$AESDecryptionResponse2 = function($key,$iv,$data) {
    $data = "#!ok\n#data=$data";
    $cipher = bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,$key,$data,MCRYPT_MODE_CBC,$iv));
    $iv = bin2hex($iv);
    echo "#!enkey=$iv|$cipher";
};
$AESDecryption = function($key,$iv,$data) {
    $cipher = bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,$key,$data,MCRYPT_MODE_CBC,$iv));
    $iv = bin2hex($iv);
    echo "$iv|$cipher";
};
$RawResponse = function() {
    echo 'Foobar22!';
};
$units = array('Response','ResponseArray','BadResponse','Download','AESDecryptionResponse1','AESDecryptionResponse2','AESDecryption','RawResponse');
if (!in_array(mb_convert_encoding($_REQUEST['unit'],'UTF-8'))) exit;
$unit = mb_convert_encoding($_REQUEST['unit'],'UTF-8');
if (strpos($unit,'AESDecryption') !== false) {
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128,MCRYPT_MODE_CBC);
    $iv = mcrypt_create_iv($iv_size,MCRYPT_DEV_URANDOM);
    $key = mb_convert_encoding($_REQUEST['key'],'UTF-8');
    $n = strlen($key);
    $i = 0;
    while ($i < $n) {
        $a = substr($key,$i,2);
        $c = pack('H*',$a);
        if ($i == 0) $key = $c;
        else $key .= $c;
        $i += 2;
    }
    $$unit($key,$iv,'Foobar22!');
} else {
    $$unit();
}
