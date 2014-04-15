<?php
session_start();
require_once("../../commons/base.inc.php");
if(!isset($_SESSION["locale"]))
	$_SESSION['locale'] = "en_US";
putenv("LC_ALL=".$_SESSION['locale']);
setlocale(LC_ALL, $_SESSION['locale']);
bindtextdomain("messages", "../languages");
textdomain("messages");
function getD($aLabel)
{
	return date("M j", strtotime( "-" .(30 -$aLabel) ." day"));	
}
$conn = mysql_connect( DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD);
if ( $conn )
{
	@mysql_select_db( DATABASE_NAME );
}
require_once ("../lib/jpgraph/" . $FOGCore->getSetting( "FOG_JPGRAPH_VERSION" ). "/src/jpgraph.php");
require_once ("../lib/jpgraph/" . $FOGCore->getSetting( "FOG_JPGRAPH_VERSION" ) . "/src/jpgraph_line.php");
$ydata = $_SESSION["30day"];
$graph = new Graph(740,160,"auto");	
$graph->SetScale("textlin");
if ( function_exists( "imageantialias" ) )
	$graph->img->SetAntiAliasing();
$graph->SetColor("white");
$lineplot=new LinePlot($ydata);
$graph->Add($lineplot);
$graph->img->SetMargin(40,20,20,40);
$graph->title->Set(_("30 Day Imaging History"));
$graph->xaxis->title->Set("");
$graph->yaxis->title->Set(_("# Computers Imaged"));
$graph->SetBackgroundImage("../images/bandwidthbg.jpg",BGIMG_COPY);
$graph->xaxis->SetLabelFormatCallback("getD");
$graph->xaxis->SetTextLabelInterval(3);
$lineplot->SetColor("brown");
$lineplot->SetFillColor("red@.90");
$lineplot->SetWeight(1);
$graph->Stroke();
