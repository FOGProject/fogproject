<?php
//=======================================================================
// File:	JPGRAPH_LED.PHP
// Description:	Module to generate Dotted LED-like digits
// Created: 	2006-11-26
// Ver:		$Id: jpgraph_led.php 804 2006-11-26 20:17:26Z ljp $
//
// Copyright 2006 (c) Aditus Consulting. All rights reserved.
//========================================================================

// Constants for color schema
DEFINE('LEDC_RED',0);
DEFINE('LEDC_GREEN',1);
DEFINE('LEDC_BLUE',2);
DEFINE('LEDC_YELLOW',3);
DEFINE('LEDC_GRAY',4);

//========================================================================
// CLASS DigitalLED74
// Description: 
// Construct a number as an image that looks like LED numbers in a
// 7x4 digital matrix
//========================================================================
class DigitalLED74 {
    private $iLED_X = 4, $iLED_Y=7,
    
	$iLEDSpec = array( 0 => array(6,9,9,9,9,9,6),
			   1 => array(2,6,10,2,2,2,2),
			   2 => array(6,9,1,2,4,8,15),
			   3 => array(6,9,1,6,1,9,6),
			   4 => array(1,3,5,9,15,1,1),
			   5 => array(15,8,8,14,1,9,6),
			   6 => array(6,8,8,14,9,9,6),
			   7 => array(15,1,1,2,4,4,4),
			   8 => array(6,9,9,6,9,9,6),
			   9 => array(6,9,9,7,1,1,6), 
			   '.' => array(0,0,0,0,0,3,3),
			   ' ' => array(0,0,0,0,0,0,0),
			   '#' => array(0,9,15,9,15,9,0),
			   'A' => array(6,9,9,15,9,9,9),
			   'B' => array(14,9,9,14,9,9,14),
			   'C' => array(6,9,8,8,8,9,6),
			   'D' => array(14,9,9,9,9,9,14),
			   'E' => array(15,8,8,14,8,8,15),
			   'F' => array(15,8,8,14,8,8,8),
			   'G' => array(6,9,8,8,11,9,6),
			   'H' => array(9,9,9,15,9,9,9),
			   'I' => array(14,4,4,4,4,4,14),
			   'J' => array(15,1,1,1,1,9,6),
			   'K' => array(8,9,10,12,12,10,9),
			   'L' => array(8,8,8,8,8,8,15)	),

	$iColorSchema = array(0 => array('red','darkred:0.9','red:0.3'),
			      1 => array('green','darkgreen','green:0.3'),
			      2 => array('lightblue:0.9','darkblue:0.85','darkblue:0.7'), 
			      3 => array('yellow','yellow:0.4','yellow:0.3'), 
			      4 => array('gray:1.4','darkgray:0.85','darkgray:0.7')),

	$iSuperSampling = 3, $iMarg = 1, $iRad = 4 ;
    
    function DigitalLED74($aRadius=2,$aMargin=0.6) {
	$this->iRad = $aRadius;
	$this->iMarg = $aMargin;
    }

    function SetSupersampling($aSuperSampling=2) {
	$this->iSuperSampling = $aSuperSampling;
    }

    function _GetLED($aLedIdx,$aColor=0) {

	if( $aColor < 0 || $aColor > 4 ) 
	    $aColor = 0 ;

	$width=  $this->iLED_X*$this->iRad*2 +  ($this->iLED_X+1)*$this->iMarg + $this->iRad ;
	$height= $this->iLED_Y*$this->iRad*2 +  ($this->iLED_Y+1)*$this->iMarg + $this->iRad * 2;

	// Adjust radious for supersampling
	$rad = $this->iRad * $this->iSuperSampling;

	// Margin in between "Led" dots
	$marg = $this->iMarg * $this->iSuperSampling;
	
	$swidth = $width*$this->iSuperSampling;
	$sheight = $height*$this->iSuperSampling;

	$simg = new RotImage($swidth,$sheight,0,DEFAULT_GFORMAT,false);
	$simg->SetColor($this->iColorSchema[$aColor][2]);
	$simg->FilledRectangle(0,0,$swidth-1,$sheight-1);


	$d = $this->iLEDSpec[$aLedIdx];

	for( $r = 0 ; $r < 7; ++$r ) {

	    $dr = $d[$r];

	    for($c=0; $c < 4; ++$c ) {

		if( ($dr & pow(2,3-$c)) !== 0 ) {
		    $color = $this->iColorSchema[$aColor][0];
		}
		else {
		    $color = $this->iColorSchema[$aColor][1];
		}

		$x = 2*$rad*$c+$rad + ($c+1)*$marg + $rad ;
		$y = 2*$rad*$r+$rad + ($r+1)*$marg + $rad ;

		$simg->SetColor($color);
		$simg->FilledCircle($x,$y,$rad);

	    }
	}
	
	$img =  new Image($width,$height,DEFAULT_GFORMAT,false);
	$img->Copy($simg->img,0,0,0,0,$width,$height,$swidth,$sheight);
	$simg->Destroy();
	unset($simg);
	return $img;
    }

    function StrokeNumber($aValStr,$aColor=0) {
	$n=strlen($aValStr);
	for( $i=0 ; $i < $n; ++$i ) {
	    $d = substr($aValStr,$i,1);
	    if( ctype_digit($d) )
		$d = (int)$d;
	    else {
		$d = strtoupper($d);
		if( $d != '#' && $d != '.' && ($d < 'A' || $d > 'L') )
		    $d = ' ';
	    }
	    $digit_img[$i] = $this->_GetLED($d,$aColor);
	}
	
	$w = imagesx($digit_img[0]->img);
	$h = imagesy($digit_img[0]->img);

	$number_img = new Image($w*$n,$h,DEFAULT_GFORMAT,false);

	for($i=0; $i < $n; ++$i ) {
	    $number_img->Copy($digit_img[$i]->img,$i*$w,0,0,0,$w,$h,$w,$h);
	}
	
	$number_img->Headers();
	$number_img->Stream();
	
    }
}


?>
