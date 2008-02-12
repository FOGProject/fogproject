<?php

/*
 *  FOG is a computer imaging solution.
 *  Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */

function out( $string, $device, $blLog=false, $blNewLine=true ) 
{
	if ( $blNewLine ) $strOut = $string . "\n";
		
	if (!$hdl = fopen( $device, 'w')) 
	{
		return;
	}

	if (fwrite($hdl, $strOut) === FALSE) 
	{
		return;
	}
	fclose($hdl);		
}

function getDateTime()
{
	return date("m-d-y g:i:s a");
}

function wlog( $string, $path )
{
	if ( filesize( $path ) > LOGMAXSIZE )
		unlink( $path );
		
	if (!$hdl = fopen( $path, 'a')) 
	{
		out( " " );
		out( " * Error: Unable to open file: $path" );
		out( "" );
		return;
	}

	if (fwrite($hdl, "[" . getDateTime() . "] " . $string . "\n") === FALSE) 
	{
		out( " " );
		out( " * Error: Unable to write to file: $path" );
		out( "" );
		return;
	}
	fclose($hdl);	
}

function getBanner()
{
	
	$str = "        ___           ___           ___      \n";
	$str .= "       /\  \         /\  \         /\  \     \n";
	$str .= "      /::\  \       /::\  \       /::\  \    \n";
	$str .= "     /:/\:\  \     /:/\:\  \     /:/\:\  \   \n";
	$str .= "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  \n";
	$str .= "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ \n";
	$str .= "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ \n";
	$str .= "        \:\__\    \:\  /:/  /   \:\ \:\__\   \n";
	$str .= "         \/__/     \:\/:/  /     \:\/:/  /   \n";
	$str .= "                    \::/  /       \::/  /    \n";
	$str .= "                     \/__/         \/__/     \n";
	$str .= "\n";
	$str .= "  ###########################################\n";
	$str .= "  #     Free Computer Imaging Solution      #\n";
	$str .= "  #                                         #\n";
	$str .= "  #     Created by:                         #\n";
	$str .= "  #         Chuck Syperski                  #\n";	
	$str .= "  #         Jian Zhang                      #\n";
	$str .= "  #                                         #\n";		
	$str .= "  #     GNU GPL Version 3                   #\n";		
	$str .= "  ###########################################\n";
	$str .= "\n";
	return $str;
	
}

function isMCTaskNew( $arKnown, $id )
{
	for( $i = 0; $i < count( $arKnown ); $i++ )
	{
		if ( $arKnown[$i] != null )
		{
			if ( $arKnown[$i]->getID() == $id ) return false;
		}
	}
	return true;
}

function getMCExistingTask( $arKnown, $id )
{
	for( $i = 0; $i < count( $arKnown ); $i++ )
	{
		if ( $arKnown[$i] != null )
		{
			if ( $arKnown[$i]->getID() == $id ) return $arKnown[$i];
		}
	}
	return null;
}

function removeFromKnownList( $arKnown, $id )
{
	$arNew = array();
	for( $i = 0; $i < count( $arKnown ); $i++ )
	{
		if ( $arKnown[$i] != null )
		{
			if ( $arKnown[$i]->getID() != $id ) $arNew[] = $arKnown[$i];
		}
	}
	return $arNew;
}

function getMCTasksNotInDB( $arKnown, $arAll )
{
	// arKnown are known tasks to the service
	// arAll are all the tasks known to the database
	
	// returns an array of tasks that should be purged from the known list
	$arRet = array();
	for( $i = 0; $i < count( $arKnown ); $i++ )
	{
		if ( $arKnown[$i] != null )
		{
			if ( $arKnown[$i]->getID() !== null )
			{
				$kID = $arKnown[$i]->getID();
				$blFound = false;
				for( $z = 0; $z < count( $arAll ); $z++ )
				{
					if ( $arAll[$z] != null )
					{
						if ( $arAll[$z]->getID() !== null )
						{
							if ( $kID == $arAll[$z]->getID() )
							{	
								$blFound = true;
								break;
							}
						}
					}
				}
				
				if ( ! $blFound )
					$arRet[] = $arKnown[$i];
			}
		}
	}
	return $arRet;
}

?>
