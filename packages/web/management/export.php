<?php
/*
 *  FOG - Free, Open-Source Ghost is a computer imaging solution.
 *  Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */
session_start();
@error_reporting( 0 );

require_once( "../commons/config.php" );
require_once( "../commons/functions.include.php" );

require_once( "./lib/ReportMaker.class.php" );

$_SESSION["allow_ajax_host"] = false;

if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $_SESSION["foglastreport"] != null )
{
	$report = unserialize( $_SESSION["foglastreport"] );
	if ( $_GET["type"] == "csv" )
	{
		$report->outputReport(ReportMaker::FOG_REPORT_CSV);
	}
	else if ( $_GET["type"] == "pdf" )
	{
		$report->outputReport(ReportMaker::FOG_REPORT_PDF);
	}
}
