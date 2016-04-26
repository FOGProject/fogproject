<?php
class CaponeManager extends FOGManagerController {
    public function install($name) {
        $this->uninstall();
        $sql = "CREATE TABLE `capone`
            (`cID` INTEGER NOT NULL AUTO_INCREMENT,
            `cImageID` INTEGER NOT NULL,
            `cOSID` INTEGER NOT NULL,
            `cKey` VARCHAR(250) NOT NULL,
            PRIMARY KEY(`cID`),
        INDEX new_index (`cImageID`),
        INDEX new_index2 (`cKey`))
        ENGINE = MyISAM";
        if (self::$DB->query($sql)->fetch()->get()) {
            $category = sprintf('Plugin: %s',$name);
            self::getClass('Service')
                ->set('name','FOG_PLUGIN_CAPONE_DMI')
                ->set('description','This setting is used for the capone module to set the DMI field used.')
                ->set('value','')
                ->set('category',$category)
                ->save();
            self::getClass('Service')
                ->set('name','FOG_PLUGIN_CAPONE_REGEX')
                ->set('description','This setting is used for the capone module to set the reg ex used.')
                ->set('value','')
                ->set('category',$category)
                ->save();
            self::getClass('Service')
                ->set('name','FOG_PLUGIN_CAPONE_SHUTDOWN')
                ->set('description','This setting is used for the capone module to set the shutdown after imaging.')
                ->set('value','')
                ->set('category',$category)
                ->save();
            return true;
        }
        return false;
    }
    public function uninstall() {
        $res = true;
        if (!self::$DB->query("DROP TABLE IF EXISTS `capone`")->fetch()->get()) $res = false;
        if (!self::getClass('ServiceManager')->destroy(array('name'=> 'FOG_PLUGIN_CAPONE_%'))) $res = false;
        if (!self::getClass('PXEMenuOptionsManager')->destroy(array('name' => 'fog.capone'))) $res = false;
        return $res;
    }
}
