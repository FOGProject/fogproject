<?php
class ReportMaker
{
	private $strHTML, $strCSV;
	private $blFirstCell;
	const FOG_REPORT_HTML = 0;
	const FOG_REPORT_CSV = 1;
	const FOG_REPORT_PDF = 2;
	const FOG_BACKUP_SQL = 3;
	const FOG_EXPORT_SQL = 4;
	const FOG_EXPORT_HOST = 5;
	function __construct()
	{
		$this->strHTML = "";
		$this->strCSV = "";
		$this->blFirstCell = true;
	}
	function appendHTML( $html ) { $this->strHTML .= $html . "\n"; }
	function addCSVCell( $item ) 
	{ 
		$append = "";
		if ( ! $this->blFirstCell )
			$append = ",";
		else 
			$this->blFirstCell = false;
		$this->strCSV .= $append . "\"".trim($item)."\""; 
	}
	function endCSVLine()
	{
		$this->strCSV .= "\n"; 
		$this->blFirstCell = true;
	}
	function outputReport($intType)
	{
		if ( $intType === self::FOG_REPORT_HTML )
			print $this->strHTML;
		else if ( $intType === self::FOG_REPORT_CSV )
		{
			header('Content-Type: application/octet-stream');
			header("Content-Disposition: attachment; filename=\"FOGReport.csv\"");	
			print $this->strCSV;	
		}
		else if ( $intType === self::FOG_REPORT_PDF )
		{
			header('Content-Type: application/octet-stream');
			header("Content-Disposition: attachment; filename=\"FOGReport.pdf\"");
			$proc = proc_open("htmldoc --links --header . --linkstyle plain --numbered --size letter --no-localfiles -t pdf14 --quiet --jpeg --webpage --size letter --left 0.25in --right 0.25in --top 0.25in --bottom 0.25in --header ... --footer ... -", array(0 => array("pipe", "r"), 1 => array("pipe", "w")), $pipes);
			fwrite($pipes[0], '<html><body>' . $this->strHTML . "</body></html>" );
			fclose($pipes[0]);
			fpassthru($pipes[1]);
			$status = proc_close($proc);
		}
		else if ($intType === self::FOG_BACKUP_SQL)
		{
			$filename="fog_backup.sql";
			$path=BASEPATH.'/management/other/';
			exec('mysqldump -u'.DATABASE_USERNAME.' -p\''.DATABASE_PASSWORD.'\' -h'.DATABASE_HOST.' '.DATABASE_NAME.' > '.$path.$filename);
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=fog_backup.sql');
			$backup = readfile($path.$filename);
			exec('rm -rf '.$path.$filename);
			print_r($backup);
		}
		else if ($intType === self::FOG_EXPORT_SQL )
		{
			$filename="host_export.sql";
			$path=BASEPATH.'/management/other/';
			$backup[]=exec('mysqldump -u'.DATABASE_USERNAME.' -p'.DATABASE_PASSWORD.' -h'.DATABASE_HOST.' '.DATABASE_NAME.' hosts > '.$path.$filename);
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=host_export.sql');
			print_r($backup);
		}
		else if ( $intType === self::FOG_EXPORT_HOST )
		{
			header('Content-Type: application/octet-stream');
			header("Content-Disposition: attachment; filename=host_export.csv");	
			print $this->strCSV;	
		}
	}
}
?>
