<?php
@error_reporting( 0 ); 
define( "IMAGEDIR", "./imagepool" );
if ( is_dir(IMAGEDIR) ) 
{
	if ($dh = opendir(IMAGEDIR)) 
	{
		$arFiles = array();
		while (($file = readdir($dh)) !== false) 
		{
			if ( is_file( IMAGEDIR . "/" . $file ) )
				$arFiles[] = $file;
		} 
		if ( count( $arFiles ) > 0 )
		{
			$intRand = rand( 0, (count($arFiles) -1) );
			header("Content-type: image/jpeg");
			@readfile(IMAGEDIR . "/" . $arFiles[$intRand]);
		}
	}
} 
