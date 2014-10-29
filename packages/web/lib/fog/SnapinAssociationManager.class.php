<?php

// Blackout - 3:55 PM 4/05/2012
class SnapinAssociationManager extends FOGManagerController
{
	public function exists($name,$id = 0)
	{
		parent::exists($name,$id);
		$this->DB->query("SELECT COUNT(%s) AS total FROM `%s` WHERE `%s` = '%s' AND `%s` = '%s'",
		array(
			$this->databaseFields['hostID'],
			$this->databaseTable,
			$this->databaseFields['snapinID'],
			$name,
			$this->databaseFields['hostID'],
			$id
			)
		);
		return ($this->DB->fetch()->get('total') > 0 ? true : false);
	}
}
