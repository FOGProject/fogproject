<?php

// Blackout - 4:27 PM 4/05/2012
class PrinterAssociationManager extends FOGManagerController
{
	function exists($name,$id = 0)
	{
		parent::exists($name,$id);
		$this->DB->query("SELECT COUNT(%s) AS total FROM `%s` WHERE `%s` = '%s' AND `%s` = '%s'",
		array(
			$this->databaseFields['hostID'],
			$this->databaseTable,
			$this->databaseFields['printerID'],
			$name,
			$this->databaseFields['hostID'],
			$id
		));
		return ($this->DB->fetch()->get('total') ? true : false);
	}
}
