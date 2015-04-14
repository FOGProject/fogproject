<?php
class MACAddress extends FOGBase
{
	/** $MAC the MAC to test */
	private $MAC;
	/** $Host the Host if used */
	private $Host;
	/** __construct() initiates
	  * @param $MAC the mac either string or object
	  * @return void
	  */
	public function __construct($MAC)
	{
		parent::__construct();
		$this->tmpMAC = $MAC;
		$this->setMAC();
	}
	/** setMAC() sets the mac
	  * @return class back
	  */
	public function setMAC()
	{
		try
		{
			if (is_object($this->tmpMAC))
				$MAC = trim(($this->tmpMAC instanceof MACAddress) ? strtolower($this->tmpMAC) : (($this->tmpMAC instanceof MACAddressAssociation) ? strtolower($this->tmpMAC->get('mac')) : ''));
			else if (is_array($this->tmpMAC))
				$MAC = trim($MAC[0]);
			else if (strlen($this->tmpMAC) == 12)
			{
				for ($i = 0; $i < 12; $i = $i + 2)
					$newMAC[] = $this->tmpMAC{$i} . $this->tmpMAC{$i + 1};
				$MAC = implode(':', $newMAC);
			}
			else if (strlen($this->tmpMAC) == 17)
				$MAC = str_replace('-', ':', $this->tmpMAC);
			else
				$MAC = $this->tmpMAC;
			$this->MAC = $MAC;
		}
		catch (Exception $e)
		{
			if ($this->debug)
				$this->FOGCore->debug('Invalid MAC Address: MAC: %s', $MAC);
		}
		return $this;
	}
	/** getMACPrefix() get the MACs prefix
	  * @return the prefix
	  */
	public function getMACPrefix()
	{
		return substr(str_replace(':','-',strtolower($this->MAC)), 0, 8);
	}
	/** __toString() Magic method to return the string as defined
	  * @return the mac address with colons
	  */
	public function __toString()
	{
		return str_replace('-',':',strtolower($this->MAC));
	}
	/** isValid() returns if the mac is valid
	  * @return true or false
	  */
	public function isValid()
	{
		return preg_match('#^([0-9a-fA-F][0-9a-fA-F][:-]){5}([0-9a-fA-F][0-9a-fA-F])$#',$this->MAC);
	}
	public function isPending()
	{
		if ($this->tmpMAC instanceof MACAddressAssociation && $this->tmpMAC->isValid())
			return $this->tmpMAC->get('pending');
	}
	public function isClientIgnored()
	{
		if ($this->tmpMAC instanceof MACAddressAssociation && $this->tmpMAC->isValid())
			return $this->tmpMAC->get('clientIgnore');
	}
	public function isPrimary()
	{
		if ($this->tmpMAC instanceof MACAddressAssociation && $this->tmpMAC->isValid())
			return $this->tmpMAC->get('primary');
	}
	public function isImageIgnored()
	{
		if ($this->tmpMAC instanceof MACAddressAssociation && $this->tmpMAC->isValid())
			return $this->tmpMAC->get('imageIgnore');
	}
}
