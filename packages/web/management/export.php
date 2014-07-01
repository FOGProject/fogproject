<?php
require_once('../commons/base.inc.php');
if ( IS_INCLUDED !== true ) die($foglang['NoLoad']);
if ( $_SESSION["foglastreport"] != null )
{
	$report = unserialize( $_SESSION["foglastreport"] );
	if ( $_GET["type"] == "csv" )
		$report->outputReport(ReportMaker::FOG_REPORT_CSV);
	else if ( $_GET["type"] == "pdf" )
		$report->outputReport(ReportMaker::FOG_REPORT_PDF);
	else if ($_GET["type"] == "host")
		$report->outputReport(ReportMaker::FOG_EXPORT_HOST);
	else if ($_GET["type"] == "sql")
		$report->outputReport(ReportMaker::FOG_BACKUP_SQL);
}
