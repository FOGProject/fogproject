<?php
/** Class Name: Date
	Construct for date in more
	human readable methods.
*/
class Date extends FOGBase
{
	private $time;
	// Overrides
	function __construct( $longUnixTime ) 
	{
		// FOGBase Constructor
		parent::__construct();
		// Set time
		$this->time = $longUnixTime;
	}
	function __toString()
	{
		return (string)$this->toFormatted();
	}
	// Custom
	function toTimestamp()
	{
		return $this->time;
	}
	function toFormatted()
	{
		return (string)$this->FOGCore->formatTime($this->time);
	}
	// LEGACY
	public function getLong()
	{
		return $this->time;
	}
}
