<?php
error_reporting( E_ALL );
session_cache_limiter("no-cache");
session_start();
require_once('../../commons/base.inc.php');
if(!isset($_SESSION["locale"]))
	$_SESSION['locale'] = "en_US";
putenv("LC_ALL=".$_SESSION['locale']);
setlocale(LC_ALL, $_SESSION['locale']);
bindtextdomain("messages", "../languages");
textdomain("messages");
$conn = mysql_connect( DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD);
if ( $conn )
	@mysql_select_db( DATABASE_NAME );
require_once ("../lib/jpgraph/" . $GLOBALS['FOGCore']->getSetting( "FOG_JPGRAPH_VERSION" ). "/src/jpgraph.php");
require_once ("../lib/jpgraph/" . $GLOBALS['FOGCore']->getSetting( "FOG_JPGRAPH_VERSION" ). "/src/jpgraph_line.php");
$data1 = $_SESSION["rx"];
$data2 = $_SESSION["tx"];
$ydata = $data1;
$ydata2 = $data2;
$graph = new Graph(740,160,"auto");	
$graph->SetScale("textlin");
if ( function_exists( "imageantialias" ) )
	$graph->img->SetAntiAliasing();
$graph->SetColor("white");
$lineplot=new LinePlot($ydata);
$lineplot2=new LinePlot($ydata2);
$graph->Add($lineplot);
$graph->Add($lineplot2);
$graph->img->SetMargin(40,20,20,40);
$graph->title->Set(_("Bandwidth"));
$graph->xaxis->title->Set(_("Time"));
$graph->yaxis->title->Set(_("MB/s"));
$graph->xaxis->Hide();
$graph->legend->Pos(0.1,0.1,"left","top"); 
$graph->SetBackgroundImage("../images/bandwidthbg.jpg",BGIMG_COPY);
$lineplot->SetColor("green");
$lineplot->SetFillColor("green@.90");
$lineplot->SetWeight(1);
$lineplot2->SetColor("blue");
$lineplot2->SetFillColor("blue@.90");
$lineplot2->SetWeight(2);
$lineplot->SetLegend(_("Rx"));
$lineplot2->SetLegend(_("Tx"));
$graph->Stroke();
