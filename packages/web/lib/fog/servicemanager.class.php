<?php
class ServiceManager extends FOGManagerController {
    public function getSettingCats() {
        return self::getSubObjectIDs('Service','','category','','','category','category');
    }
}
