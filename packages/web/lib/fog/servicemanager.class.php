<?php
class ServiceManager extends FOGManagerController {
    public function getSettingCats() {
        return $this->getSubObjectIDs('Service','','category','','','category','category');
    }
}
