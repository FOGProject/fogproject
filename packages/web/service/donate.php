<?php
require_once('../commons/base.inc.php');
try
{
	if ($FOGCore->getSetting('FOG_MINING_ENABLE') == "1" )
	{
		$abortHour = $FOGCore->getSetting('FOG_MINING_FULL_RESTART_HOUR');
		$ignoreWeekends = $FOGCore->getSetting('FOG_MINING_FULL_RUN_ON_WEEKEND' );
		$date = $FOGCore->nice_date();
		if  ($ignoreWeekends == "1" && $date->format("N") > 5)
			print "#!OK";
		else
		{
			// it is a weekday check the hour
			if ($abortHour == ($date->format('G')))
				throw new Exception("Restarting the client...");
			else
				print "#!OK";
        }
	}
	else
		throw new Exception("Donations are disabled!");
}
catch (Exception $e)
{
	print $e->getMessage();
}
