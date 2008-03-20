<?php
// Example for use of JpGraph, 
// ljp, 01/03/01 20:32
include ("../jpgraph.php");
include ("../jpgraph_bar.php");

// We need some data
$datay=array(-0.13,0.25,-0.21,0.35,0.31,0.04);
$datax=array("Jan","Feb","Mar","Apr","May","June");

// Setup the graph. 
$graph = new Graph(400,200,"auto");	
$graph->img->SetMargin(60,20,30,50);
$graph->SetScale("textlin");
$graph->SetMarginColor("silver");
$graph->SetShadow();

// Set up the title for the graph
$graph->title->Set("Example negative bars");
$graph->title->SetFont(FF_VERDANA,FS_NORMAL,18);
$graph->title->SetColor("darkred");

// Setup font for axis
$graph->xaxis->SetFont(FF_VERDANA,FS_NORMAL,12);
$graph->xaxis->SetColor("black","red");
$graph->yaxis->SetFont(FF_VERDANA,FS_NORMAL,11);

// Show 0 label on Y-axis (default is not to show)
$graph->yscale->ticks->SupressZeroLabel(false);

// Setup X-axis labels
$graph->xaxis->SetTickLabels($datax);
$graph->xaxis->SetLabelAngle(50);

// Create the bar pot
$bplot = new BarPlot($datay);
$bplot->SetWidth(0.6);

// Setup color for gradient fill style 
$bplot->SetFillGradient("navy","steelblue",GRAD_MIDVER);

// Set color for the frame of each bar
$bplot->SetColor("navy");
$graph->Add($bplot);

// Finally send the graph to the browser
$graph->Stroke();
?>
