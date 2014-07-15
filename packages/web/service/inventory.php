<?php
require('../commons/base.inc.php');
try
{
	//Get MAC to get Host from mac address.
	$ifconfig = explode('HWaddr',base64_decode(trim($_REQUEST['mac'])));
	$mac = strtolower(trim($ifconfig[1]));
	$MACAddress = new MACAddress($mac);
	if (!$MACAddress->isValid())
<<<<<<< HEAD
		throw new Exception(_('Invalid MAC Address'));
=======
		throw new Exception($foglang['InvalidMAC']);
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
	// Set the Host variable to find host record for update.
	// If it doesn't exist, it creates new inventory record.
	$Host = $MACAddress->getHost();
	if ($Host->isValid())
		$Inventory = current($FOGCore->getClass('InventoryManager')->find(array('hostID' => $Host->get('id'))));
	$sysman=trim(base64_decode($_REQUEST['sysman']));
	$sysproduct=trim(base64_decode($_REQUEST["sysproduct"]));
	$sysversion=trim(base64_decode($_REQUEST["sysversion"]));
	$sysserial=trim(base64_decode($_REQUEST["sysserial"]));
	$systype=trim(base64_decode($_REQUEST["systype"]));
	$biosversion=trim(base64_decode($_REQUEST["biosversion"]));
	$biosvendor=trim(base64_decode($_REQUEST["biosvendor"]));
	$biosdate=trim(base64_decode($_REQUEST["biosdate"]));
	$mbman=trim(base64_decode($_REQUEST["mbman"]));
	$mbproductname=trim(base64_decode($_REQUEST["mbproductname"]));
	$mbversion=trim(base64_decode($_REQUEST["mbversion"]));
	$mbserial=trim(base64_decode($_REQUEST["mbserial"]));
	$mbasset=trim(base64_decode($_REQUEST["mbasset"]));
	$cpuman=trim(base64_decode($_REQUEST["cpuman"]));
	$cpuversion=trim(base64_decode($_REQUEST["cpuversion"]));
	$cpucurrent=trim(base64_decode($_REQUEST["cpucurrent"]));
	$cpumax=trim(base64_decode($_REQUEST["cpumax"]));
	$mem=trim(base64_decode($_REQUEST["mem"]));
	$hdinfo=trim(base64_decode($_REQUEST["hdinfo"]));
	if ($hdinfo != null)
	{
		$arHd = explode(",",$hdinfo);
		$hdmodel = trim(str_replace("Model=","",trim( $arHd[0])));
		$hdfirmware = trim(str_replace("FwRev=","",trim( $arHd[1])));
		$hdserial = trim(str_replace("SerialNo=","",trim( $arHd[2])));
	}
	else
	{
		$hdmodel = '';
		$hdfirmware = '';
		$hdserial = '';
	}
	$caseman=trim(base64_decode($_REQUEST["caseman"]));
	$casever=trim(base64_decode($_REQUEST["casever"]));
	$caseserial=trim(base64_decode($_REQUEST["caseserial"]));
	$casesasset=trim(base64_decode($_REQUEST["casesasset"]));						
	if (!$Inventory)
	{
			$Inventory = new Inventory(array(
						'hostID' => $Host->get('id'),
						'sysman' => $sysman,
						'sysproduct' => $sysproduct,
						'sysversion' => $sysversion,
						'sysserial' => $sysserial,
						'systype' => $systype,
						'biosversion' => $biosversion,
						'mbman' => $mbman,
						'mbproductname' => $mbproductname,
						'mbversion' => $mbversion,
						'mbserial' => $mbserial,
						'mbasset' => $mbasset,
						'cpuman' => $cpuman,
						'cpuversion' => $cpuversion,
						'cpucurrent' => $cpucurrent,
						'cpumax' => $cpumax,
						'mem' => $mem,
						'hdmodel' => $hdmodel,
						'hdfirmware' => $hdfirmware,
						'hdserial' => $hdserial,
						'caseman' => $caseman,
						'casever' => $casever,
						'caseserial' => $caseserial,
						'caseasset' => $casesasset
			));
			if ($Inventory->save())
				print _('Done');
			else
				throw new Exception(_('Failed to create inventory for this host!'));
	}
	else
	{
		$Inventory->set('sysman',$sysman)
				  ->set('sysproduct',$sysproduct)
				  ->set('sysversion',$sysversion)
				  ->set('sysserial',$sysserial)
				  ->set('systype',$systype)
				  ->set('biosversion',$biosversion)
				  ->set('biosvendor',$biosvendor)
				  ->set('biosdate',$biosdate)
				  ->set('mbman',$mbman)
				  ->set('mbproductname',$mbproductname)
				  ->set('mbversion',$mbversion)
				  ->set('mbserial',$mbserial)
				  ->set('mbasset',$mbasset)
				  ->set('cpuman',$cpuman)
				  ->set('cpuversion',$cpuversion)
				  ->set('cpucurrent',$cpucurrent)
				  ->set('cpumax',$cpumax)
				  ->set('mem',$mem)
				  ->set('hdmodel',$hdmodel)
				  ->set('hdfirmware',$hdfirmware)
				  ->set('hdserial',$hdserial)
				  ->set('caseman',$caseman)
				  ->set('casever',$casever)
				  ->set('caseserial',$caseserial)
				  ->set('caseasset',$casesasset);
		if ($Inventory->save())
			print _('Done');
		else
			throw new Exception(_('Failed to update inventory for this host!'));
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
