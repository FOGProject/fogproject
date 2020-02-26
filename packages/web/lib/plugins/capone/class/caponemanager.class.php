<?php
/**
 * Manager class for Capone
 *
 * PHP version 5
 *
 * @category CaponeManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for Capone
 *
 * @category CaponeManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class CaponeManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'capone';
    /**
     * Installs the capone database
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'cID',
                'cImageID',
                'cOSID',
                'cKey'
            ],
            [
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'VARCHAR(255)'
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                'cID',
                'cKey'
            ],
            'InnoDB',
            'utf8',
            'cID',
            'cID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        $category = sprintf('Plugin: Capone');
        $insert_fields = [
            'name',
            'description',
            'value',
            'category'
        ];
        $insert_values = [];
        $insert_values[] = [
            'FOG_PLUGIN_CAPONE_DMI',
            'This setting is used for the capone '
            . 'module to set the DMI field used.',
            '',
            $category
        ];
        $insert_values[] = [
            'FOG_PLUGIN_CAPONE_REGEX',
            'This setting is used for the capone '
            . 'module to set the reg ex used.',
            '',
            $category
        ];
        $insert_values[] = [
            'FOG_PLUGIN_CAPONE_SHUTDOWN',
            'This setting is used for the capone '
            . 'module to set the shutdown after imaging.',
            '',
            $category
        ];
        self::getClass('ServiceManager')
            ->insertBatch(
                $insert_fields,
                $insert_values
            );
        return true;
    }
    /**
     * Removes the database items when plugin is removed.
     *
     * @return bool
     */
    public function uninstall()
    {
        Route::deletemass(
            'service',
            ['name' => 'FOG_PLUGIN_CAPONE_%']
        );
        Route::deletemass(
            'pxemenuoptions',
            ['name' => 'fog.capone']
        );
        return parent::uninstall();
    }
}
