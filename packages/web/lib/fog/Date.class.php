<?php
/** Class Name: Date
	Construct for date in more
	human readable methods.
*/
class Date extends FOGBase
{
	private $time;
	// Overrides
	public function __construct( $longUnixTime ) 
	{
		// FOGBase Constructor
		parent::__construct();
		// Set time
		$this->time = $longUnixTime;
	}
	public function __toString()
	{
		return (string)$this->toFormatted();
	}
	// Custom
	public function toTimestamp()
	{
		return $this->time;
	}
	public function toFormatted()
	{
		return (string)$this->FOGCore->formatTime($this->time);
	}
	// LEGACY
	public function getLong()
	{
		return $this->time;
	}
}
