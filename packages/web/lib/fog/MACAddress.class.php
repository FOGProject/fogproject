<?php

// Blackout - 10:15 AM 1/10/2011
class MACAddress extends FOGBase
{
	private $MAC,$Host;
	public function __construct($MAC,$primary = false,$pending = false, $isClientIgnored = false, $isImageIgnored = false)
	{
		parent::__construct();
		$this->tmpMAC = $MAC;
		$this->setMAC($MAC);
	}
	public function setMAC($MAC)
	{
		try
		{
			if ($this->tmpMAC instanceof MACAddress)
				$MAC = trim($MAC->__toString());
			else if (is_object($this->tmpMAC) && $this->tmpMAC instanceof MACAddressAssociation)
				$MAC = trim($MAC->get('mac'));
			else
				$this->tmpMAC = $this->getClass('MACAddressAssociation',array('id' => 0));
			if (strlen($MAC) == 12)
			{
				for ($i = 0; $i < 12; $i = $i + 2)
					$newMAC[] = $MAC{$i} . $MAC{$i + 1};
				$MAC = implode(':', $newMAC);
			}
			elseif (strlen($MAC) == 17)
				$MAC = str_replace('-', ':', $MAC);
			$this->MAC = $MAC;
		}
		catch (Exception $e)
		{
			if ($this->debug)
				$this->FOGCore->debug('Invalid MAC Address: MAC: %s', $MAC);
		}
		return $this;
	}
	public function getMACPrefix()
	{
		return substr(str_replace(':','-',strtolower($this->MAC)), 0, 8);
	}
	public function __toString()
	{
		return str_replace('-',':',strtolower($this->MAC));
	}
	public function isValid()
	{
		return ($this->__toString() != '' ? preg_match('#^([0-9a-fA-F][0-9a-fA-F][:-]){5}([0-9a-fA-F][0-9a-fA-F])$#', $this->__toString()) : false);
	}
	public function isPending()
	{
		return $this->tmpMAC->get('pending');
	}
	public function isClientIgnored()
	{
		return $this->tmpMAC->get('clientIgnore');
	}
	public function isPrimary()
	{
		return $this->tmpMAC->get('primary');
	}
	public function isImageIgnored()
	{
		return $this->tmpMAC->get('imageIgnore');
	}
}
