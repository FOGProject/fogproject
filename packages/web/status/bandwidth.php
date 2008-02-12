<?
require_once( "../commons/config.php" );

function getData($interface, $type)
{
	$fp = fopen(PROCNETDEV, "r");
	$line = fgets($fp, 256);
	$line = fgets($fp, 256);
	while ($line = fgets($fp, 256))
	{
		$temp = split(":", trim($line));
		if ($temp[0] == $interface) 
		{
			$line = preg_split("/[\s]+/", trim($temp[1]));
			if ( $type == "tx" )
				return $line[8];
			else if ( $type = "rx" )
				return $line[0];
		}

	}
	fclose($fp);
	return null;
}

define("PROCNETDEV", "/proc/net/dev");
define("SLEEPSEC", 5);
define("KBPS", (SLEEPSEC * 1024) * 1024);

$intLastRx = getData(NFS_ETH_MONITOR, "rx");
$intLastTx = getData(NFS_ETH_MONITOR, "tx");
sleep(SLEEPSEC);
$intCurRx = getData(NFS_ETH_MONITOR, "rx");
$intCurTx = getData(NFS_ETH_MONITOR, "tx");

echo (round((($intCurRx - $intLastRx) / KBPS), 2)  );
echo ( "##" );
echo (round((($intCurTx - $intLastTx) / KBPS), 2)  );
?>
