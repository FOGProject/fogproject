<?php
/*=======================================================================
// File: 	JPGRAPH_UTILS.INC
// Description: Collection of non-essential "nice to have" utilities 
// Created: 	2005-11-20
// Ver:		$Id: jpgraph_utils.inc.php 781 2006-10-08 08:07:47Z ljp $
//
// Copyright (c) Aditus Consulting. All rights reserved.
//========================================================================
*/

//===================================================
// CLASS FuncGenerator
// Description: Utility class to help generate data for function plots. 
// The class supports both parametric and regular functions.
//===================================================
class FuncGenerator {
    private $iFunc='',$iXFunc='',$iMin,$iMax,$iStepSize;
	
    function FuncGenerator($aFunc,$aXFunc='') {
	$this->iFunc = $aFunc;
	$this->iXFunc = $aXFunc;
    }
	
    function E($aXMin,$aXMax,$aSteps=50) {
	$this->iMin = $aXMin;
	$this->iMax = $aXMax;
	$this->iStepSize = ($aXMax-$aXMin)/$aSteps;

	if( $this->iXFunc != '' )
	    $t = 'for($i='.$aXMin.'; $i<='.$aXMax.'; $i += '.$this->iStepSize.') {$ya[]='.$this->iFunc.';$xa[]='.$this->iXFunc.';}';
	elseif( $this->iFunc != '' )
	    $t = 'for($x='.$aXMin.'; $x<='.$aXMax.'; $x += '.$this->iStepSize.') {$ya[]='.$this->iFunc.';$xa[]=$x;} $x='.$aXMax.';$ya[]='.$this->iFunc.';$xa[]=$x;';
	else
	    JpGraphError::RaiseL(24001);//('FuncGenerator : No function specified. ');
			
	@eval($t);
		
	// If there is an error in the function specifcation this is the only
	// way we can discover that.
	if( empty($xa) || empty($ya) )
	    JpGraphError::RaiseL(24002);//('FuncGenerator : Syntax error in function specification ');
				
	return array($xa,$ya);
    }
}

//=============================================================================
// CLASS SymChar
// Description: Code values for some commonly used characters that 
//              normally isn't available directly on the keyboard, for example
//              mathematical and greek symbols.
//=============================================================================
class  SymChar {
    static function Get($aSymb,$aCapital=FALSE) {
        $iSymbols = array(
    /* Greek */
	array('alpha','03B1','0391'),
	array('beta','03B2','0392'),
	array('gamma','03B3','0393'),
	array('delta','03B4','0394'),
	array('epsilon','03B5','0395'),
	array('zeta','03B6','0396'),
	array('ny','03B7','0397'),
	array('eta','03B8','0398'),
	array('theta','03B8','0398'),
	array('iota','03B9','0399'),
	array('kappa','03BA','039A'),
	array('lambda','03BB','039B'),
	array('mu','03BC','039C'),
	array('nu','03BD','039D'),
	array('xi','03BE','039E'),
	array('omicron','03BF','039F'),
	array('pi','03C0','03A0'),
	array('rho','03C1','03A1'),
	array('sigma','03C3','03A3'),
	array('tau','03C4','03A4'),
	array('upsilon','03C5','03A5'),
	array('phi','03C6','03A6'),
	array('chi','03C7','03A7'),
	array('psi','03C8','03A8'),
	array('omega','03C9','03A9'),
    /* Money */
	array('euro','20AC'),
	array('yen','00A5'),
	array('pound','20A4'),
    /* Math */
	array('approx','2248'),
	array('neq','2260'),
	array('not','2310'),
	array('def','2261'),
	array('inf','221E'),
	array('sqrt','221A'),
	array('int','222B'),
    /* Misc */
	array('copy','00A9'),
	array('para','00A7'));

	$n = count($iSymbols);
	$i=0;
	$found = false;
	$aSymb = strtolower($aSymb);
	while( $i < $n && !$found ) {
	    $found = $aSymb === $iSymbols[$i++][0];
	}
	if( $found ) {
	    $ca = $iSymbols[--$i];
	    if( $aCapital && count($ca)==3 ) 
		$s = $ca[2];
	    else
		$s = $ca[1];
	    return sprintf('&#%04d;',hexdec($s));
	}
	else
	    return '';
    }
}


//=============================================================================
// CLASS DateScaleUtils
// Description: Help to create a manual date scale
//=============================================================================
DEFINE('DSUTILS_MONTH',1); // Major and minor ticks on a monthly basis
DEFINE('DSUTILS_MONTH1',1); // Major and minor ticks on a monthly basis
DEFINE('DSUTILS_MONTH2',2); // Major ticks on a bi-monthly basis
DEFINE('DSUTILS_MONTH3',3); // Major icks on a tri-monthly basis
DEFINE('DSUTILS_MONTH6',4); // Major on a six-monthly basis
DEFINE('DSUTILS_WEEK1',5); // Major ticks on a weekly basis
DEFINE('DSUTILS_WEEK2',6); // Major ticks on a bi-weekly basis
DEFINE('DSUTILS_WEEK4',7); // Major ticks on a quod-weekly basis
DEFINE('DSUTILS_DAY1',8); // Major ticks on a daily basis
DEFINE('DSUTILS_DAY2',9); // Major ticks on a bi-daily basis
DEFINE('DSUTILS_DAY4',10); // Major ticks on a qoud-daily basis
DEFINE('DSUTILS_YEAR1',11); // Major ticks on a yearly basis
DEFINE('DSUTILS_YEAR2',12); // Major ticks on a bi-yearly basis
DEFINE('DSUTILS_YEAR5',13); // Major ticks on a five-yearly basis


class DateScaleUtils {
    private $starthour,$startmonth, $startday, $startyear;
    private $endmonth, $endyear, $endday;
    private $tickPositions=array(),$minTickPositions=array();
    private $iUseWeeks = true;

    function UseWeekFormat($aFlg) {
	$this->iUseWeeks = $aFlg;
    }

    function doYearly($aType,$aMinor=false) {
	$i=0; $j=0;
	$m = $this->startmonth;
	$y = $this->startyear;

	if( $this->startday == 1 ) {
	    $this->tickPositions[$i++] = mktime(0,0,0,$m,1,$y);
	}
	++$m;


	switch( $aType ) {
	    case DSUTILS_YEAR1:
		for($y=$this->startyear; $y <= $this->endyear; ++$y ) {
		    if( $aMinor ) {
			while( $m <= 12 ) {
			    if( !($y == $this->endyear && $m > $this->endmonth) ) {
				$this->minTickPositions[$j++] = mktime(0,0,0,$m,1,$y);
			    }
			    ++$m;
			}
			$m=1;
		    }
		    $this->tickPositions[$i++] = mktime(0,0,0,1,1,$y);    
		}
		break;
	    case DSUTILS_YEAR2:
		$y=$this->startyear; 
		while( $y <= $this->endyear ) {
		    $this->tickPositions[$i++] = mktime(0,0,0,1,1,$y);    
		    for($k=0; $k < 1; ++$k ) { 
			++$y;
			if( $aMinor ) {
			    $this->minTickPositions[$j++] = mktime(0,0,0,1,1,$y);
			}
		    }
		    ++$y;
		}
		break;
	    case DSUTILS_YEAR5:
		$y=$this->startyear; 
		while( $y <= $this->endyear ) {
		    $this->tickPositions[$i++] = mktime(0,0,0,1,1,$y);    
		    for($k=0; $k < 4; ++$k ) { 
			++$y;
			if( $aMinor ) {
			    $this->minTickPositions[$j++] = mktime(0,0,0,1,1,$y);
			}
		    }
		    ++$y;
		}
		break;
	}
    }

    function doDaily($aType,$aMinor=false) {
	$m = $this->startmonth;
	$y = $this->startyear;
	$d = $this->startday;
	$h = $this->starthour;
	$i=0;$j=0;

	if( $h == 0 ) {
	    $this->tickPositions[$i++] = mktime(0,0,0,$m,$d,$y);
	    $t = mktime(0,0,0,$m,$d,$y);	    
	}
	else {
	    $t = mktime(0,0,0,$m,$d,$y);	    
	}
	switch($aType) {
	    case DSUTILS_DAY1:
		while( $t <= $this->iMax ) {
		    $t += 3600*24;
		    $this->tickPositions[$i++] = $t;
		}
		break;
	    case DSUTILS_DAY2:
		while( $t <= $this->iMax ) {
		    $t += 3600*24;
		    if( $aMinor ) {
			$this->minTickPositions[$j++] = $t;
		    }
		    $t += 3600*24;
		    $this->tickPositions[$i++] = $t;
		}
		break;
	    case DSUTILS_DAY4:
		while( $t <= $this->iMax ) {
		    for($k=0; $k < 3; ++$k ) {
			$t += 3600*24;
			if( $aMinor ) {
			    $this->minTickPositions[$j++] = $t;
			}
		    }
		    $t += 3600*24;
		    $this->tickPositions[$i++] = $t;
		}
		break;
	}
    }

    function doWeekly($aType,$aMinor=false) {
	$hpd = 3600*24;
	$hpw = 3600*24*7;
	// Find out week number of min date
	$thursday = $this->iMin + $hpd * (3 - (date('w', $this->iMin) + 6) % 7);
	$week = 1 + (date('z', $thursday) - (11 - date('w', mktime(0, 0, 0, 1, 1, date('Y', $thursday)))) % 7) / 7;
	$daynumber = date('w',$this->iMin);
	if( $daynumber == 0 ) $daynumber = 7;
	$m = $this->startmonth;
	$y = $this->startyear;
	$d = $this->startday;
	$i=0;$j=0;
	// The assumption is that the weeks start on Monday. If the first day
	// is later in the week then the first week tick has to be on the following
	// week.
	if( $daynumber == 1 ) {
	    $this->tickPositions[$i++] = mktime(0,0,0,$m,$d,$y);
	    $t = mktime(0,0,0,$m,$d,$y) + $hpw;
	}
	else {
	    $t = mktime(0,0,0,$m,$d,$y) + $hpd*(8-$daynumber);
	}

	switch($aType) {
	    case DSUTILS_WEEK1:
		$cnt=0;
		break;
	    case DSUTILS_WEEK2:
		$cnt=1;
		break;
	    case DSUTILS_WEEK4:
		$cnt=3;
		break;
	}
	while( $t <= $this->iMax ) {
	    $this->tickPositions[$i++] = $t;
	    for($k=0; $k < $cnt; ++$k ) {
		$t += $hpw;
		if( $aMinor ) {
		    $this->minTickPositions[$j++] = $t;
		}
	    }
	    $t += $hpw;
	}
    }

    function doMonthly($aType,$aMinor=false) {
	$monthcount=0;
	$m = $this->startmonth;
	$y = $this->startyear;
	$i=0; $j=0;

	// Skip the first month label if it is before the startdate
	if( $this->startday == 1 ) {
	    $this->tickPositions[$i++] = mktime(0,0,0,$m,1,$y);
	    $monthcount=1;
	}
	if( $aType == 1 ) {
	    if( $this->startday < 15 ) {
		$this->minTickPositions[$j++] = mktime(0,0,0,$m,15,$y);
	    }
	}
	++$m;

	// Loop through all the years included in the scale
	for($y=$this->startyear; $y <= $this->endyear; ++$y ) {
	    // Loop through all the months. There are three cases to consider:
	    // 1. We are in the first year and must start with the startmonth
	    // 2. We are in the end year and we must stop at last month of the scale
	    // 3. A year in between where we run through all the 12 months
	    $stopmonth = $y == $this->endyear ? $this->endmonth : 12;
	    while( $m <= $stopmonth ) {
		switch( $aType ) {
		    case DSUTILS_MONTH1: 
			// Set minor tick at the middle of the month
			if( $aMinor ) {
			    if( $m <= $stopmonth ) {
				if( !($y==$this->endyear && $m==$stopmonth && $this->endday < 15) ) 
				$this->minTickPositions[$j++] = mktime(0,0,0,$m,15,$y);
			    }
			}
			// Major at month 
			// Get timestamp of first hour of first day in each month
			$this->tickPositions[$i++] = mktime(0,0,0,$m,1,$y);

			break;
		    case DSUTILS_MONTH2: 
			if( $aMinor ) {
			    // Set minor tick at start of each month
			    $this->minTickPositions[$j++] = mktime(0,0,0,$m,1,$y);
			}

			// Major at every second month 
			// Get timestamp of first hour of first day in each month
			if( $monthcount % 2 == 0 ) {
			    $this->tickPositions[$i++] = mktime(0,0,0,$m,1,$y);
			}
			break;
		    case DSUTILS_MONTH3: 
			if( $aMinor ) {
			    // Set minor tick at start of each month
			    $this->minTickPositions[$j++] = mktime(0,0,0,$m,1,$y);
			}
			    // Major at every third month 
			// Get timestamp of first hour of first day in each month
			if( $monthcount % 3 == 0 ) {
			    $this->tickPositions[$i++] = mktime(0,0,0,$m,1,$y);
			}
			break;
		    case DSUTILS_MONTH6: 
			if( $aMinor ) {
			    // Set minor tick at start of each month
			    $this->minTickPositions[$j++] = mktime(0,0,0,$m,1,$y);
			}
			// Major at every third month 
			// Get timestamp of first hour of first day in each month
			if( $monthcount % 6 == 0 ) {
			    $this->tickPositions[$i++] = mktime(0,0,0,$m,1,$y);
			}
			break;
		}
		++$m;
		++$monthcount;
	    }
	    $m=1;
	}

	// For the case where all dates are within the same month
	// we want to make sure we have at least two ticks on the scale
	// since the scale want work properly otherwise
	if($this->startmonth == $this->endmonth && $this->startyear == $this->endyear && $aType==1 ) {
	    $this->tickPositions[$i++] = mktime(0 ,0 ,0, $this->startmonth + 1, 1, $this->startyear);
	} 

	return array($this->tickPositions,$this->minTickPositions);
    }

    function GetTicks($aData,$aType=1,$aMinor=false,$aEndPoints=false) {
	$n = count($aData);
	return $this->GetTicksFromMinMax($aData[0],$aData[$n-1],$aType,$aMinor,$aEndPoints);
    }

    function GetAutoTicks($aMin,$aMax,$aMaxTicks=10,$aMinor=false) {
	$diff = $aMax - $aMin;
	$spd = 3600*24;
	$spw = $spd*7;
	$spm = $spd*30;
	$spy = $spd*352;

	if( $this->iUseWeeks )
	    $w = 'W';
	else
	    $w = 'd M';

	// Decision table for suitable scales
	// First value: Main decision point
	// Second value: Array of formatting depending on divisor for wanted max number of ticks. <divisor><formatting><format-string>,..
	$tt = array( 
	    array($spw, array(1,DSUTILS_DAY1,'d M',2,DSUTILS_DAY2,'d M',-1,DSUTILS_DAY4,'d M')),
	    array($spm, array(1,DSUTILS_DAY1,'d M',2,DSUTILS_DAY2,'d M',4,DSUTILS_DAY4,'d M',
			      7,DSUTILS_WEEK1,$w,-1,DSUTILS_WEEK2,$w)),
	    array($spy, array(1,DSUTILS_DAY1,'d M',2,DSUTILS_DAY2,'d M',4,DSUTILS_DAY4,'d M',
			      7,DSUTILS_WEEK1,$w,14,DSUTILS_WEEK2,$w,
			      30,DSUTILS_MONTH1,'M',60,DSUTILS_MONTH2,'M',-1,DSUTILS_MONTH3,'M')),
	    array(-1, array(30,DSUTILS_MONTH1,'M-Y',60,DSUTILS_MONTH2,'M-Y',90,DSUTILS_MONTH3,'M-Y',
			    180,DSUTILS_MONTH6,'M-Y',352,DSUTILS_YEAR1,'Y',704,DSUTILS_YEAR2,'Y',-1,DSUTILS_YEAR5,'Y')));

	$ntt = count($tt);
	$nd = floor($diff/$spd);
	for($i=0; $i < $ntt; ++$i ) {
	    if( $diff <= $tt[$i][0] || $i==$ntt-1) {
		$t = $tt[$i][1];
		$n = count($t)/3;
		for( $j=0; $j < $n; ++$j ) {
		    if( $nd/$t[3*$j] <= $aMaxTicks || $j==$n-1) {
			$type = $t[3*$j+1];
			$fs = $t[3*$j+2];
			list($tickPositions,$minTickPositions) = $this->GetTicksFromMinMax($aMin,$aMax,$type,$aMinor);
			return array($fs,$tickPositions,$minTickPositions,$type);
		    }
		}
	    }
	}
    }

    function GetTicksFromMinMax($aMin,$aMax,$aType,$aMinor=false,$aEndPoints=false) {
	$this->starthour = date('G',$aMin);
	$this->startmonth = date('n',$aMin);
	$this->startday = date('j',$aMin);
	$this->startyear = date('Y',$aMin);
	$this->endmonth = date('n',$aMax);
	$this->endyear = date('Y',$aMax);
	$this->endday = date('j',$aMax);
	$this->iMin = $aMin;
	$this->iMax = $aMax;

	if( $aType <= DSUTILS_MONTH6 ) {
	    $this->doMonthly($aType,$aMinor);
	}
	elseif( $aType <= DSUTILS_WEEK4 ) {
	    $this->doWeekly($aType,$aMinor);
	}
	elseif( $aType <= DSUTILS_DAY4 ) {
	    $this->doDaily($aType,$aMinor);
	}
	elseif( $aType <= DSUTILS_YEAR5 ) {
	    $this->doYearly($aType,$aMinor);
	}
	else {
	    JpGraphError::RaiseL(24003);
	}
	// put a label at the very left data pos
	if( $aEndPoints ) {
	    $tickPositions[$i++] = $aData[0];
	}

	// put a label at the very right data pos
	if( $aEndPoints ) {
	    $tickPositions[$i] = $aData[$n-1];
	}

	return array($this->tickPositions,$this->minTickPositions);
    }

}

//=============================================================================
// Class ReadFileData
//=============================================================================
Class ReadFileData {

    //----------------------------------------------------------------------------
    // Desciption:
    // Read numeric data from a file. 
    // Each value should be separated by either a new line or by a specified 
    // separator character (default is ',').
    // Before returning the data each value is converted to a proper float 
    // value. The routine is robust in the sense that non numeric data in the 
    // file will be discarded.
    //
    // Returns: 
    // The number of data values read on success, FALSE on failure
    //----------------------------------------------------------------------------
    static function FromCSV($aFile,&$aData,$aSepChar=',',$aMaxLineLength=1024) {
	$rh = fopen($aFile,'r');
	if( $rh === false )
	    return false;
	$tmp = array();
	$lineofdata = fgetcsv($rh, 1000, ',');
	while ( $lineofdata !== FALSE) {
	    $tmp = array_merge($tmp,$lineofdata);
	    $lineofdata = fgetcsv($rh, $aMaxLineLength, $aSepChar);
	}
	fclose($rh);

	// Now make sure that all data is numeric. By default
	// all data is read as strings
	$n = count($tmp);
	$aData = array();
	$cnt=0;
	for($i=0; $i < $n; ++$i) {
	    if( $tmp[$i] !== "" ) {
		$aData[$cnt++] = floatval($tmp[$i]);
	    }
	}
	return $cnt;
    }
}

?>