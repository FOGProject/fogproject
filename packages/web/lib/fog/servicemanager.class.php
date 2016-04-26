<?php
class ServiceManager extends FOGManagerController {
    public function getSettingCats() {
        return static::getSubObjectIDs('Service','','category','','','category','category');
    }
}
