<?php

class ImageMember
{
	private $image;
	private $mac;
	private $id;
	private $building;
	private $blForce;
	private $hostname;
	private $ipaddress;
	private $imageID;
	private $osid;
	private $imgType;
	
	const IMAGETYPE_PARTITION = 0;
	const IMAGETYPE_DISKIMAGE = 1;

	function __construct( $host=null, $ip=null, $mac=null, $image=null, $hid=null, $building=null, $imageid=null, $force=false, $osid=null, $imgType=self::IMAGETYPE_PARTITION )
	{
		$this->setHostName( $host );
		$this->setIPAddress( $ip );
		$this->setMAC( $mac );
		$this->setImage( $image );
		$this->setID( $hid );
		$this->setBuilding( $building );
		$this->setIsForced( $force );
		$this->setImageID( $imageid );
		$this->setOSID( $osid );
		$this->setImageType( $imgType );
	}

	function setHostName( $host ) { $this->hostname = $host; }
	function setIPAddress( $ip ) { $this->ipaddress = $ip; }
	function setImage( $img ) { $this->image = $img; }
	function setMAC( $mac ) { $this->mac = $mac; }
	function setID( $id ) { $this->id = $id; }
	function setBuilding( $building ) { $this->building = $building; }
	function setIsForced( $blForce ) { $this->blForce = $blForce; }
	function setImageID( $imageid ) { $this->imageID = $imageid; }
	function setOSID( $id ) { $this->osid = $id; }
	function setImageType( $imgType ) { $this->imgType = $imgType; }

	function getImageID() { return $this->imageID; }
	function getHostName() { 	return $this->hostname; }
	function getIPAddress() { return $this->ipaddress; }
	function getImage() { 	return $this->image; }
	function getMAC() { 		return $this->mac; }
	function getOSID() { return $this->osid; }
	function getImageType() { return $this->imgType; }
	function getMACColon() 
	{ 
		return str_replace ( "-", ":", strtolower( $this->mac ) );
	}
	function getMACDash() 
	{ 
		return str_replace ( ":", "-", strtolower( $this->mac ) );
	}
	
	function getMACImageReady()
	{
		return "01-" . $this->getMACDash();
	}
	function getID() { 		return $this->id; }
	function getBuilding() { return $this->building; }
	function getIsForced() { return $this->blForce; }

}

?>
