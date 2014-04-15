<?php
/** \class FOGCron
	I don't know what it was going to be used for.
	I can image it was going to be used for CRON Job timing
	so the users new what time it was going to run next.
	May remove soon.
*/
class FOGCron extends FOGGetSet
{
	protected $data = array(
		'minute'	=> 0,		// Minute (0 - 59)
		'hour'		=> 23,		// Hour (0 - 23)
		'dayOfMonth'	=> '*',		// Day of Month (1 - 31)
		'month'		=> '*',		// Month (1 - 12)
		'dayOfWeek'	=> '*'		// Day of Week (0 - 6) - sun,mon,tue,wed,thu,fri,sat
	);
}
