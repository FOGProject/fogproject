<?php

// Blackout - 6:04 PM 28/09/2011
class Inventory extends FOGController
{
	// Table
	public $databaseTable = 'inventory';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'			=> 'iID',
		'hostID'		=> 'iHostID',
		'primaryUser'	=> 'iPrimaryUser',
		'other1'		=> 'iOtherTag',
		'other2'		=> 'iOtherTag1',
		'createdTime'	=> 'iCreateDate',
		'sysman'		=> 'iSysman',
		'sysproduct'	=> 'iSysproduct',
		'sysversion'	=> 'iSysversion',
		'sysserial'		=> 'iSysserial',
		'systype'		=> 'iSystype',
		'biosversion'	=> 'iBiosversion',
		'biosvendor'	=> 'iBiosvendor',
		'biosdate'		=> 'iBiosdate',
		'mbman'			=> 'iMbman',
		'mbproductname' => 'iMbproductname',
		'mbversion'		=> 'iMbversion',
		'mbserial'		=> 'iMbserial',
		'mbasset'		=> 'iMbasset',
		'cpuman'		=> 'iCpuman',
		'cpuversion'	=> 'iCpuversion',
		'cpucurrent'	=> 'iCpucurrent',
		'cpumax'		=> 'iCpumax',
		'mem'			=> 'iMem',
		'hdmodel'		=> 'iHdmodel',
		'hdserial'		=> 'iHdserial',
		'hdfirmware'	=> 'iHdfirmware',
		'caseman'		=> 'iCaseman',
		'casever'		=> 'iCasever',
		'caseserial'	=> 'iCaseserial',
		'caseasset'		=> 'iCaseasset',
	);

	public function getMem()
	{
		$memar = explode(' ',$this->get('mem'));
		if ($memar[1] < 1024)
			$mem = sprintf('%.2f %s',($memar[1]),'KB');
		else if ($memar[1] >= 1024 && $memar[1] < (1024*1024))
			$mem = sprintf('%.2f %s',($memar[1]/1024),'MB');
		else if ($memar[1] >= (1024*1024) && $memar[1] < (1024*1024*1024))
			$mem = sprintf('%.2f %s',($memar[1]/1024/1024),'GB');
		else
			$mem = sprintf('%.2f %s',($memar[1]/1024/1024/1024),'TB');
		return $mem;
	}
}
