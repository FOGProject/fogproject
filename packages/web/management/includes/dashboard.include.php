<?php
if ( IS_INCLUDED !== true ) die( "Unable to load system configuration information." );

if ( $currentUser != null && $currentUser->isLoggedIn() )
{
	
	echo ( "<div class=\"dashbaord\">" );
		echo ( "<p class=\"infoTitle\">System Overview</p>" );
		echo ( "<p class=\"noSpace\">User: " . $currentUser->getUserName() . "</p>" );
		echo ( "<p class=\"noSpace\">Web Server: " . WEB_HOST . "</p>" );		
		echo ( "<p class=\"noSpace\">TFTP Server: " . TFTP_HOST . "</p>" );				
		echo ( "<p class=\"noSpace\">Storage	 Server: " . STORAGE_HOST . "</p>" );						
		echo ( "<p class=\"noSpace\">Uptime:</p>" );	
		echo ( "<p class=\"taskPCT\">" . exec("uptime") . "</p>" );							
	echo ( "</div>" );
	
	echo ( "<div class=\"dashbaord\">" );
		echo ( "<p class=\"infoTitle\">System Activity</p>" );		
		echo ( "<p class=\"noSpace\">Active Tasks: " . getNumberOfTasks($conn, 1 ) . "</p>" );
		echo ( "<p class=\"noSpace\">Queued Tasks: " . getNumberOfTasks($conn, 0 ) . "</p>" );									
		echo ( "<p class=\"noSpace\">Open Slots: " . (QUEUESIZE - getNumberOfTasks($conn, 1 )). "</p>" );			
		$running = getNumberOfTasks($conn, 1 );
		$pct = 0;
		if ( $running != 0 )
			$pct = ( round(($running / QUEUESIZE) * 100,2)  );
		if ( $pct > 100 ) $pct = "100";
		echo ( "<p class=\"noSpace\"><div class=\"pb\"><img src=\"images/openslots.jpg\" height=25 width=\"$pct%\" /></div></p>");
		echo ( "<p class=\"taskPCT\">$pct% of slots full</p>" );

		
	echo ( "</div>" );	
	
	echo ( "<div class=\"dashbaord\">" );
		echo ( "<p class=\"infoTitle\">Disk Information</p>" );
		echo ( "<p class=\"noSpace\" id=\"remainingfreespace\">Checking free space...</p>" );
	echo ( "</div>" );	
	
	$_SESSION["30day"] = array();
	for( $i = 30; $i >= 0; $i-- )
	{
		$sql = "select count(*) as c, DATE(NOW() - INTERVAL $i DAY) as d from tasks where DATE(taskCreateTime) = DATE(NOW()) - INTERVAL $i DAY" ;
		$res = mysql_query( $sql, $conn ) or die( mysql_error() );
		if ( $ar = mysql_fetch_array( $res ) )
		{
			$_SESSION["30day"][( 30 - $i)] = $ar[c];	
		}		
		
	}

	echo ( "<br /><br /><img src=\"phpimages/30day.phpgraph.php\" />" );	
	
	if ( $_SESSION["rx"] === null )
	{
		$_SESSION["rx"] = array();
		$_SESSION["tx"] = array();
		
		for( $i = 0; $i < 30; $i++ )
		{
			$_SESSION["rx"][$i] = 0;
			$_SESSION["tx"][$i] = 0;
		}
	}	

	echo ( "<br /><div class=\"imgObj\" id=\"imgObj\"><img src=\"phpimages/bandwidth.phpgraph.php\" /></div>" );

	echo ( "<script type=\"text/javascript\">" );
	// bandwidth update
	echo ( "function work() { getContentBandwidth(); setTimeout (\"work()\", 5000); }" );
	echo ( "getContentBandwidth(); setTimeout (\"work()\", 5000);" );
	
	//hd space
	$hdUrl = "http://" . STORAGE_HOST .  WEB_ROOT ."status/freespace.php";
	echo ( "function hdUpdate() { getContentHD(\"$hdUrl\"); setTimeout (\"hdUpdate()\", 5000); }" );
	echo ( "getContentHD(\"$hdUrl\"); setTimeout(\"hdUpdate()\", 5000);" );	
	echo ( "</script>" );
}
?>
