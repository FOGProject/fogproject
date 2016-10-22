<?php
/**
 * Manager class for Capone
 *
 * PHP version 5
 *
 * @category CaponeManager
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
/**
 * Manager class for Capone
 *
 * @category CaponeManager
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
class CaponeManager extends FOGManagerController
{
    /**
     * Installs the capone database
     *
     * @param string $name the name of the plugin
     *
     * @return bool
     */
    public function install($name)
    {
        $this->uninstall();
        $sql = 'CREATE TABLE `capone`'
            . '(`cID` INTEGER NOT NULL AUTO_INCREMENT,'
            . '`cImageID` INTEGER NOT NULL,'
            . '`cOSID` INTEGER NOT NULL,'
            . '`cKey` VARCHAR(250) NOT NULL,'
            . 'PRIMARY KEY(`cID`),'
            . 'INDEX new_index (`cImageID`),'
            . 'INDEX new_index2 (`cKey`))'
            . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC';
        if (self::$DB->query($sql)) {
            $category = sprintf('Plugin: %s', $name);
            $insert_fields = array(
                'name',
                'description',
                'value',
                'category'
            );
            $insert_values = array();
            $insert_values[] = array(
                'FOG_PLUGIN_CAPONE_DMI',
                'This setting is used for the capone '
                . 'module to set the DMI field used.',
                '',
                $category
            );
            $insert_values[] = array(
                'FOG_PLUGIN_CAPONE_REGEX',
                'This setting is used for the capone '
                . 'module to set the reg ex used.',
                '',
                $category
            );
            $insert_values[] = array(
                'FOG_PLUGIN_CAPONE_SHUTDOWN',
                'This setting is used for the capone '
                . 'module to set the shutdown after imaging.',
                '',
                $category
            );
            self::getClass('ServiceManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
            return true;
        }
        return false;
    }
    /**
     * Removes the database items when plugin is removed.
     *
     * @return bool
     */
    public function uninstall()
    {
        $dropQuery = 'DROP TABLE IF EXISTS `capone`';
        self::$DB->query($dropQuery);
        self::getClass('ServiceManager')
            ->destroy(
                array(
                    'name' => 'FOG_PLUGIN_CAPONE_%'
                )
            );
        self::getClass('PXEMenuOptionsManager')
            ->destroy(
                array(
                    'name' => 'fog.capone'
                )
            );
        return true;
    }
}
