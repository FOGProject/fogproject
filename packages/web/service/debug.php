<?php
try
{
	throw new Exception(_("Hello FOG Client"));
}
catch(Exception $e)
{
	print $e->getMessage();
}
