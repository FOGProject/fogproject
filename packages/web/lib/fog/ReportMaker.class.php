<?php
class ReportMaker extends FOGBase
{
	private $strHTML, $strCSV, $strLine, $filename;
	const FOG_REPORT_HTML = 0;
	const FOG_REPORT_CSV = 1;
	const FOG_REPORT_PDF = 2;
	const FOG_BACKUP_SQL = 3;
	const FOG_EXPORT_SQL = 4;
	const FOG_EXPORT_HOST = 5;
	public function appendHTML($html){$this->strHTML[] = $html;}
	public function addCSVCell($item){$this->strCSV[] = trim($item);}
	public function endCSVLine()
	{
		$this->strLine[] = implode($this->strCSV,',');
		unset($this->strCSV);
	}
	public function setFileName($filename){$this->filename = $filename;}
	public function outputReport($intType)
	{
		if ($intType === self::FOG_REPORT_HTML)
			print implode($this->strHTML,"\n");
		else if ( $intType === self::FOG_REPORT_CSV )
		{
			header('X-Content-Type-Options: nosniff');
			header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
			header('X-XSS-Protection: 1; mode=block');
			header('X-Frame-Options: deny');
			header('Cache-Control: no-cache');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$this->filename.'.csv"');
			print implode($this->strLine,"\n");
		}
		else if ( $intType === self::FOG_REPORT_PDF )
		{
			header('X-Content-Type-Options: nosniff');
			header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
			header('X-XSS-Protection: 1; mode=block');
			header('X-Frame-Options: deny');
			header('Cache-Control: no-cache');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$this->filename.'.pdf"');
			$proc = proc_open("htmldoc --links --header . --linkstyle plain --numbered --size letter --no-localfiles -t pdf14 --quiet --jpeg --webpage --size letter --left 0.25in --right 0.25in --top 0.25in --bottom 0.25in --header ... --footer ... -", array(0 => array("pipe", "r"), 1 => array("pipe", "w")), $pipes);
			fwrite($pipes[0], '<html><body>'.implode($this->strHTML,"\n")."</body></html>" );
			fclose($pipes[0]);
			fpassthru($pipes[1]);
			$status = proc_close($proc);
		}
		else if ($intType === self::FOG_BACKUP_SQL)
		{
			$filename="fog_backup.sql";
			$path=BASEPATH.'/management/other/';
			exec('mysqldump --opt -u'.DATABASE_USERNAME.' -p"'.DATABASE_PASSWORD.'" -h'.preg_replace('#p:#','',DATABASE_HOST).' '.DATABASE_NAME.' > '.$path.$filename);
			header('X-Content-Type-Options: nosniff');
			header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
			header('X-XSS-Protection: 1; mode=block');
			header('X-Frame-Options: deny');
			header('Cache-Control: no-cache');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=fog_backup.sql');
			readfile($path.$filename);
			exec('rm -rf '.$path.$filename);
		}
		else if ($intType === self::FOG_EXPORT_SQL )
		{
			$filename="host_export.sql";
			$path=BASEPATH.'/management/other/';
			$backup[]=exec('mysqldump -u'.DATABASE_USERNAME.' -p'.DATABASE_PASSWORD.' -h'.DATABASE_HOST.' '.DATABASE_NAME.' hosts > '.$path.$filename);
			header('X-Content-Type-Options: nosniff');
			header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
			header('X-XSS-Protection: 1; mode=block');
			header('X-Frame-Options: deny');
			header('Cache-Control: no-cache');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=host_export.sql');
			print_r($backup);
		}
		else if ( $intType === self::FOG_EXPORT_HOST )
		{
			header('X-Content-Type-Options: nosniff');
			header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
			header('X-XSS-Protection: 1; mode=block');
			header('X-Frame-Options: deny');
			header('Cache-Control: no-cache');
			header('Content-Type: application/octet-stream');
			header("Content-Disposition: attachment; filename=host_export.csv");	
			print implode($this->strLine,"\n");
		}
	}
}
