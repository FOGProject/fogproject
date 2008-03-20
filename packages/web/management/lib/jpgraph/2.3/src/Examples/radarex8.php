<?php
include ("../jpgraph.php");
include ("../jpgraph_radar.php");

// Create the basic radar graph
$graph = new RadarGraph(300,200,"auto");
//$graph->img->SetAntiAliasing();

// Set background color and shadow
$graph->SetColor("white");
$graph->SetShadow();

// Position the graph
$graph->SetCenter(0.4,0.55);

// Setup the axis formatting 	
$graph->axis->SetFont(FF_FONT1,FS_BOLD);

// Setup the grid lines
$graph->grid->SetLineStyle("solid");
$graph->grid->SetColor("navy");
$graph->grid->Show();
$graph->HideTickMarks();
		
// Setup graph titles
$graph->title->Set("Quality result");
$graph->title->SetFont(FF_FONT1,FS_BOLD);
$graph->SetTitles($gDateLocale->GetShortMonth());

// Create the first radar plot		
$plot = new RadarPlot(array(70,80,60,90,71,81,47));
$plot->SetLegend("Goal");
$plot->SetColor("red","lightred");
$plot->SetFill(false);
$plot->SetLineWeight(2);

// Create the second radar plot
$plot2 = new RadarPlot(array(70,40,30,80,31,51,14));
$plot2->SetLegend("Actual");
$plot2->SetLineWeight(2);
$plot2->SetColor("blue");
$plot2->SetFill(false);

// Add the plots to the graph
$graph->Add($plot2);
$graph->Add($plot);

// And output the graph
$graph->Stroke();

?>