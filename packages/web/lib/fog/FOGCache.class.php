<?php
class FOGCache
{
	public $filepath;
	public function __construct()
	{
		$this->filepath = rtrim(BASEPATH,'/').'/management/other/cache/';
	}
	public function read($filename)
	{
		$filename = $this->filepath.$filename;
		if (time() - $_SESSION[$filename] >= (5 * 60 * 60))
			$this->delete($filename);
		if (file_exists($filename))
		{
			$handle = fopen($filename,'rb');
			$variable = fread($handle,filesize($filename));
			fclose($handle);
			return unserialize($variable);
		}
		else
			return false;
	}
	public function write($filename,$variable)
	{
		$filename = $this->filepath.$filename;
		$handle = fopen($filename,'a');
		fwrite($handle,serialize($variable));
		fclose($handle);
		$_SESSION[$filename] = time();
	}
	public function delete($filename)
	{
		@unlink($filename);
	}
}
