<?php
class ServiceManager extends FOGManagerController
{
    //Setting Categories
    public function getSettingCats()
    {   
		foreach($this->find('','','category') AS $Service)
			$Cats[] = $Service->get('category');
		$Cat = array_unique($Cats);
        return $Cat;
    } 
}
