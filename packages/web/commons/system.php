<?php
/****************************************************
 * FOG Dashboard JS
 *	Author:		$Author$
 *	Created:	5:44 PM 4/12/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
// These variables are for the FOG system and do NOT need to be modified by the end user
if (!ini_get('date.timezone'))
	date_default_timezone_set(date_default_timezone_get());
define('IS_INCLUDED', true);
define('FOG_VERSION', '1.0.1');
define('FOG_SCHEMA', 101);
define('FOG_SVN_REVISION', '$Revision$');
define('FOG_SVN_LAST_UPDATE', '$LastChangedDate$');
define('PHP_VERSION_REQUIRED', '5.2.1');
define('PHP_COMPATIBLE', version_compare(PHP_VERSION, PHP_VERSION_REQUIRED, '>='));
define('BASEPATH', DetermineBasePath());
define('SPACE_DEFAULT_STORAGE', '/images');
// PHP: Version check
if (PHP_COMPATIBLE === false)
{
	die(sprintf(_('Your systems PHP version is not sufficient. You have version %s, version %s is required.'), PHP_VERSION, PHP_VERSION_REQUIRED));
	exit;
}
function DetermineBasePath()
{
	// Find the name of the first directory in the files path
	if($_SERVER['DOCUMENT_ROOT'] == null)
	{
		if(file_exists('/var/www/html/fog'))
			$_SERVER['DOCUMENT_ROOT'] = '/var/www/html/fog';
		if(file_exists('/var/www/fog'))
			$_SERVER['DOCUMENT_ROOT'] = '/var/www/fog';
		define('WEB_ROOT','/'.basename($_SERVER['DOCUMENT_ROOT']).'/');
		return $_SERVER['DOCUMENT_ROOT'];
	}
	if($_SERVER['DOCUMENT_ROOT'] != null)
	{
		if(preg_match('#/fog/#i',$_SERVER['PHP_SELF']))
		{
			define('WEB_ROOT', '/fog/');
			return $_SERVER['DOCUMENT_ROOT'].WEB_ROOT;
		}
		else
		{
			define('WEB_ROOT','/');
			return $_SERVER['DOCUMENT_ROOT'];
		}
	}
}
