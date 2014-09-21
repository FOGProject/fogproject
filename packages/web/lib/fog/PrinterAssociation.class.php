<?php

// Blackout - 4:24 PM 4/05/2012
class PrinterAssociation extends FOGController
{
	// Table
	public $databaseTable = 'printerAssoc';
	
	// Name -> Database field name
	public $databaseFields = array(
		'id'		=> 'paID',
		'hostID'	=> 'paHostID',
		'printerID'	=> 'paPrinterID',
		'isDefault'	=> 'paIsDefault',
		'anon1'		=> 'paAnon1',
		'anon2'		=> 'paAnon2',
		'anon3'		=> 'paAnon3',
		'anon4'		=> 'paAnon4',
		'anon5'		=> 'paAnon5'
	);
	
	// Custom
	public function getHost()
	{
		return new Host($this->get('hostID'));
	}
	
	public function getPrinter()
	{
		return new Printer($this->get('printerID'));
	}
	public function isDefault()
	{
		return ($this->get('isDefault') === 1 ? true : false);
	}
}
