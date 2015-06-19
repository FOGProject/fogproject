<?php
class ServiceManager extends FOGManagerController {
    //Setting Categories
    public function getSettingCats() {return array_unique((array)$this->find('','','category','','','','','category'));}
}
