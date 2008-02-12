<?php
session_start();

function getD($aLabel)
{
	return date("M j", strtotime( "-" .(30 -$aLabel) ." day"));	
}

require_once ("../lib/jpgraph/2.2/src/jpgraph.php");
require_once ("../lib/jpgraph/2.2/src/jpgraph_line.php");

$ydata = $_SESSION["30day"];


$graph = new Graph(740,160,"auto");	
$graph->SetScale("textlin");
if ( function_exists( "imageantialias" ) )
	$graph->img->SetAntiAliasing();
$graph->SetColor("white");

$lineplot=new LinePlot($ydata);


$graph->Add($lineplot);

$graph->img->SetMargin(40,20,20,40);
$graph->title->Set("30 Day Imaging History");
$graph->xaxis->title->Set("");
$graph->yaxis->title->Set("# Computers Imaged");
$graph->SetBackgroundImage("../images/bandwidthbg.jpg",BGIMG_COPY);

$graph->xaxis->SetLabelFormatCallback("getD");
$graph->xaxis->SetTextLabelInterval(3);

$lineplot->SetColor("brown");
$lineplot->SetFillColor("red@.90");

$lineplot->SetWeight(1);

$graph->Stroke();
?>
