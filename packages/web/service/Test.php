<?php
/**
 * Tests the client stuff
 *
 * PHP version 5
 *
 * @category Test
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Tests the client stuff
 *
 * @category Test
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sends the response
 *
 * @return void
 */
$Response = function () {
    echo "#!ok\n#Foo=bar\n#Empty=\n#-X=Special";
};
/**
 * Sends the response of array of data
 *
 * @return void
 */
$ResponseArray = function () {
    echo "#!ok\n#obj0=Foo\n#obj1=bar\n#obj2=22!";
};
/**
 * Tests a bad response
 *
 * @return void
 */
$BadResponse = function () {
    echo "#!er";
};
/**
 * Tests the download functionality
 *
 * @return void
 */
$Download = function () {
    header('Content-Disposition: attachment; filename=test.txt');
    header('Content-Type: application/force-download');
    header('Content-Length: '.strlen('Foobar22!'));
    echo 'Foobar22!';
};
/**
 * Tests the decryption response
 *
 * @return void
 */
$AESDecryptionResponse1 = function (
    $key,
    $iv,
    $data
) {
    $data = "#!ok\n#data=$data";
    $cipher = bin2hex(
        mcrypt_encrypt(
            MCRYPT_RIJNDAEL_128,
            $key,
            $data,
            MCRYPT_MODE_CBC,
            $iv
        )
    );
    $iv = bin2hex($iv);
    echo "#!en=$iv|$cipher";
};
/**
 * Tests the decryption response 2nd time
 *
 * @return void
 */
$AESDecryptionResponse2 = function (
    $key,
    $iv,
    $data
) {
    $data = "#!ok\n#data=$data";
    $cipher = bin2hex(
        mcrypt_encrypt(
            MCRYPT_RIJNDAEL_128,
            $key,
            $data,
            MCRYPT_MODE_CBC,
            $iv
        )
    );
    $iv = bin2hex($iv);
    echo "#!enkey=$iv|$cipher";
};
/**
 * Send the data to decrypt
 *
 * @return void
 */
$AESDecryption = function (
    $key,
    $iv,
    $data
) {
    $cipher = bin2hex(
        mcrypt_encrypt(
            MCRYPT_RIJNDAEL_128,
            $key,
            $data,
            MCRYPT_MODE_CBC,
            $iv
        )
    );
    $iv = bin2hex($iv);
    echo "$iv|$cipher";
};
/**
 * Sends the raw response
 *
 * @return void
 */
$RawResponse = function () {
    echo 'Foobar22!';
};
$units = array(
    'Response',
    'ResponseArray',
    'BadResponse',
    'Download',
    'AESDecryptionResponse1',
    'AESDecryptionResponse2',
    'AESDecryption',
    'RawResponse'
);
if (!in_array(
    htmlspecialchars(
        $_REQUEST['unit'],
        ENT_QUOTES,
        'utf-8'
    ),
    $units
)
) {
    die(_('Invalid unit passed'));
}
$unit = htmlspecialchars(
    $_REQUEST['unit'],
    ENT_QUOTES,
    'utf-8'
);
if (strpos($unit, 'AESDecryption') !== false) {
    $iv_size = mcrypt_get_iv_size(
        MCRYPT_RIJNDAEL_128,
        MCRYPT_MODE_CBC
    );
    $iv = mcrypt_create_iv(
        $iv_size,
        MCRYPT_DEV_URANDOM
    );
    $key = $_REQUEST['key'];
    $n = strlen($key);
    $i = 0;
    while ($i < $n) {
        $a = substr(
            $key,
            $i,
            2
        );
        $c = pack(
            'H*',
            $a
        );
        if ($i == 0) {
            $key = $c;
        } else {
            $key .= $c;
        }
        $i += 2;
    }
    $$unit(
        $key,
        $iv,
        'Foobar22!'
    );
} else {
    $$unit();
}
exit;
