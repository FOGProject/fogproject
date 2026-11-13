<?php
/**
 * FOGFTP::exists() finds a file whose name contains a space.
 *
 * exists() split each LIST line on whitespace with no limit and took field
 * 8 as the name. "7450 AIO-WIN11-A10-YHP57.CAB" became "7450", so the file
 * never existed on the storage node. rawsize() then reported 0, and the
 * image replicator deleted and copied the file again on every cycle
 * (forum 18313).
 *
 * This runs exists() from the shipped class against a LIST reply in the
 * shape vsftpd sends, with the FTP call replaced.
 *
 * Usage: php tests/ftp-exists-name-with-spaces.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require dirname(__DIR__) . '/packages/web/src/Net/FOGFTP.php';

// Replaces only the FTP call. exists() is the shipped method.
class FtpListingStub extends \FOG\Net\FOGFTP
{
    public $listing = [];
    public function rawlist($path)
    {
        return $this->listing;
    }
}

$ftp = new FtpListingStub();
$ftp->listing = [
    'drwxrwxrwx    2 fogproject administrator      4096 Nov 10 20:02 .',
    'drwxrwxrwx    3 fogproject administrator      4096 Nov 10 18:31 ..',
    '-rwxrwxrwx    1 fogproject administrator 718000218 Nov 10 20:04 7450 AIO-WIN11-A10-YHP57.CAB',
    '-rwxrwxrwx    1 fogproject administrator 434290695 Nov 30  2022 Video.zip',
    'lrwxrwxrwx    1 fogproject administrator         9 Nov 10 20:04 latest pack -> Video.zip',
];

$dir = '/images/Drivers/OptiPlex7450AIO/win11';
$cases = [
    "$dir/7450 AIO-WIN11-A10-YHP57.CAB" => true,
    "$dir/Video.zip" => true,
    "$dir/7450" => false,
    // A symlink is listed as "name -> target"; only the name exists.
    "$dir/latest pack" => true,
    "$dir/latest pack -> Video.zip" => false,
    "$dir/missing.zip" => false,
];

$fail = false;
foreach ($cases as $path => $want) {
    $got = $ftp->exists($path);
    if ($got !== $want) {
        fwrite(
            STDERR,
            sprintf(
                "FAIL: exists('%s') is %s, expected %s\n",
                $path,
                var_export($got, true),
                var_export($want, true)
            )
        );
        $fail = true;
    }
}

if ($fail) {
    exit(1);
}
echo "PASS: exists() keeps the spaces in a listed file name\n";
