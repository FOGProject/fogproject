<?php
session_cache_limiter("no-cache");
session_start();
 
require_once ("../lib/jpgraph/2.2/src/jpgraph.php");
require_once ("../lib/jpgraph/2.2/src/jpgraph_line.php");

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
$graph->title->Set("Bandwidth");
$graph->xaxis->title->Set("Time");
$graph->yaxis->title->Set("MB/s");

$graph->xaxis->Hide();

$graph->legend->Pos(0.1,0.1,"left","top"); 

$graph->SetBackgroundImage("../images/bandwidthbg.jpg",BGIMG_COPY);

$lineplot->SetColor("green");
$lineplot->SetFillColor("green@.90");
$lineplot->SetWeight(1);

$lineplot2->SetColor("blue");
$lineplot2->SetFillColor("blue@.90");
$lineplot2->SetWeight(2);

$lineplot->SetLegend("Rx");
$lineplot2->SetLegend("Tx");

$graph->Stroke();
?>
