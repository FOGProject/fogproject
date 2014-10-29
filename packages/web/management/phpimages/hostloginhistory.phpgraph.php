<?php
session_start();
require_once( "../../commons/base.inc.php");
require_once( BASEPATH . "/lib/fog/UserLoginEntry.class.php" );
require_once("../lib/jpgraph/" . $FOGCore->getSetting( "FOG_JPGRAPH_VERSION" ) . "/src/jpgraph.php");
require_once("../lib/jpgraph/" . $FOGCore->getSetting( "FOG_JPGRAPH_VERSION" ) . "/src/jpgraph_gantt.php");
$graph = new GanttGraph(575,-1);
$graph->ShowHeaders( GANTT_HDAY | GANTT_HHOUR );
$graph->scale->hour->SetStyle(HOURSTYLE_HAMPM);
$graph->scale->hour->SetIntervall(2);
if ( function_exists( "imageantialias" ) )
	$graph->img->SetAntiAliasing();
$graph->SetBackgroundImage("../images/bandwidthbg.jpg",BGIMG_FILLFRAME);
$graph->title->Set(_("Host Login History"));
$graph->scale->day->SetStyle( DAYSTYLE_LONGDAYDATE2 );
$graph->scale->actinfo->SetColTitles(array('',_('Username')));
$graph-> scale->actinfo->SetBackgroundColor('gray:0.7@0.5'); 
$cnt = 0;
$lastUser = "";
$blFirst = true;
for ( $i = 0; $i < count( $_SESSION["fog_logins"] ); $i++ )
{
	$entry = unserialize( $_SESSION["fog_logins"][$i]  );
	if ( $entry != null )
	{
		if ( $blFirst )
			$blFirst = false;
		else
		{
			if ( $lastUser != $entry->getUser() )
				$cnt++;
		}
		$icon = new IconImage( '../images/user.png');		
		$activity = new GanttBar($cnt,array($icon,$entry->getUser()),$entry->getLogInTime(),$entry->getLogOutTime() );
		$activity->SetPattern(GANTT_SOLID,"green");
 		if ( ! $entry->isClean() )
 		{
			$activity ->leftMark->Show();    
			$activity ->leftMark->SetFillColor( "red");
			$activity ->leftMark->title-> SetColor( "white"); 		
			$activity ->leftMark->SetWidth( 6 ) ;		
			$activity ->leftMark->SetType( MARK_FILLEDCIRCLE); 		
			$activity ->leftMark->title->Set(_("?")); 			
			$activity ->leftMark->SetColor( "red"); 
			$activity->SetPattern(GANTT_SOLID,"yellow");
 		}
		$graph->Add($activity);
		$lastUser = $entry->getUser();
	}
}
$graph->Stroke();
