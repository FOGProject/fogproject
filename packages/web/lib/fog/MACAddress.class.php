<?php

// Blackout - 10:15 AM 1/10/2011
class MACAddress extends FOGBase
{
	private $MAC;
	
	public function __construct($MAC)
	{
		$this->setMAC($MAC);
		return parent::__construct();
	}
	
	public function setMAC($MAC)
	{
		try
		{
			$MAC = trim($MAC);
			if ($MAC instanceof MACAddress)
				$MAC = $MAC->__toString();
			elseif (strlen($MAC) == 12)
			{
				for ($i = 0; $i < 12; $i = $i + 2)
					$newMAC[] = $MAC{$i} . $MAC{$i + 1};
				$MAC = implode(':', $newMAC);
			}
			elseif (strlen($MAC) == 17)
				$MAC = str_replace('-', ':', $MAC);
			else
				throw new Exception('');
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
		$PendingMACs = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $this->MAC, 'pending' => 1)));
		return ($PendingMACs && $PendingMACs instanceof MACAddressAssociation);
	}

	public function isClientIgnored()
	{
		$IgnoredMACs = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $this->MAC, 'clientIgnore' => 1)));
		return ($IgnoredMACs && $IgnoredMACs instanceof MACAddressAssociation);
	}

	public function isPrimary()
	{
		$PrimaryMACs = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $this->MAC, 'primary' => 1)));
		return ($PrimaryMACs && $PrimaryMACs instanceof MACAddressAssociation);
	}

	public function isImageIgnored()
	{
		$IgnoredMACs = current($this->getClass('MACAddressAssociationManager')->find(array('mac' => $this->MAC, 'imageIgnore' => 1)));
		return ($IgnoredMACs && $IgnoredMACs instanceof MACAddressAssociation);
	}
}
